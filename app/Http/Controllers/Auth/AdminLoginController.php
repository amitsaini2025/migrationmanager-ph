<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\IpUtils;

class AdminLoginController extends Controller
{
    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest:admin')->except('logout');
    }

    // ── Trait-replacement helpers ─────────────────────────────────────────

    public function username(): string
    {
        return 'email';
    }

    public function redirectPath(): string
    {
        return $this->redirectTo ?? '/home';
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        $key = 'login|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                $this->username() => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ])->onlyInput($this->username());
        }

        if ($this->attemptLogin($request)) {
            $request->session()->regenerate();
            RateLimiter::clear($key);
            return $this->authenticated($request, $this->guard()->user())
                ?: redirect()->intended($this->redirectPath());
        }

        RateLimiter::hit($key, 60);
        return $this->sendFailedLoginResponse($request);
    }

    protected function attemptLogin(Request $request): bool
    {
        if (! $this->guard()->attempt(
            $request->only($this->username(), 'password'),
            $request->boolean('remember')
        )) {
            return false;
        }

        // A4: block explicitly inactive staff; allow status=1 and legacy NULL
        $user = $this->guard()->user();
        if ($user && $user->status !== null && (int) $user->status === 0) {
            $this->guard()->logout();

            return false;
        }

        return true;
    }

    // ── Overridden / custom methods ───────────────────────────────────────

    public function showLoginForm()
    {
        // Purge any legacy insecure password cookie from older "Remember Me" behaviour
        \Cookie::queue(\Cookie::forget('password'));

        return view('auth.admin-login');
    }

    protected function guard()
    {
        return Auth::guard('admin');
    }

    protected function validateLogin(Request $request)
    {
        $rules = [
            'email'    => 'required|string',
            'password' => 'required|string',
        ];

        if (config('services.recaptcha.key') && config('services.recaptcha.secret')) {
            $rules['g-recaptcha-response'] = 'required';
        }

        $request->validate($rules);
    }

    public function authenticated(Request $request, $user)
    {
        if (config('services.recaptcha.key') && config('services.recaptcha.secret')) {
            $recaptchaResponse = $request->input('g-recaptcha-response');

            if (is_null($recaptchaResponse)) {
                return redirect()->back()->withErrors([
                    'g-recaptcha-response' => 'Please Complete the Recaptcha to proceed',
                ]);
            }

            $response = Http::get('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => config('services.recaptcha.secret'),
                'response' => $recaptchaResponse,
                'remoteip' => IpUtils::anonymize($request->ip()),
            ]);

            $result = json_decode($response);

            if (! $response->successful() || $result->success !== true) {
                return redirect()->back()->withErrors([
                    'g-recaptcha-response' => 'Please Complete the Recaptcha Again to proceed',
                ]);
            }
        }

        // Remember Me uses Laravel's remember_token (via attempt above).
        // Only optionally persist email for form convenience — never the password.
        \Cookie::queue(\Cookie::forget('password'));
        if (! empty($request->remember)) {
            \Cookie::queue(\Cookie::make('email', $request->email, 3600));
        } else {
            \Cookie::queue(\Cookie::forget('email'));
        }

        $log = new \App\Models\StaffLoginLog;
        $log->level      = 'info';
        $log->user_id    = $user->id;
        $log->ip_address = $request->getClientIp();
        $log->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $log->message    = 'Logged in successfully';
        $log->save();

        return redirect()->intended($this->redirectPath());
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $errors = [$this->username() => trans('auth.failed')];

        $staff = \App\Models\Staff::where($this->username(), $request->{$this->username()})->first();

        if ($staff && !\Hash::check($request->password, $staff->password)) {
            $errors = ['password' => 'Wrong password'];
        }

        if ($request->expectsJson()) {
            return response()->json($errors, 422);
        }

        $log = new \App\Models\StaffLoginLog;
        $log->level      = 'critical';
        $log->user_id    = $staff ? $staff->id : null;
        $log->ip_address = $request->getClientIp();
        $log->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $log->message    = 'Invalid Email or Password !';
        $log->save();

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors($errors);
    }

    public function logout(Request $request)
    {
        $log = new \App\Models\StaffLoginLog;
        $log->level      = 'info';
        $log->user_id    = $request->id;
        $log->ip_address = $request->getClientIp();
        $log->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $log->message    = 'Logged out successfully';
        $log->save();

        Auth::guard('admin')->logout();
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->route('crm.login');
    }
}
