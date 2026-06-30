<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\EmailLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class EmailLabelController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Display a listing of email labels.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = EmailLabel::with(['user']);
        
        // Filter by type if provided
        if ($request->has('type') && $request->type != '') {
            $query->where('type', $request->type);
        }
        
        // Filter active/inactive
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $totalData = $query->count();
        $lists = $query->orderBy('type', 'desc') // System labels first
                      ->orderBy('name', 'asc')
                      ->paginate(config('constants.limit', 15));
        
        return view('AdminConsole.features.emaillabels.index', compact(['lists', 'totalData']));
    }

    /**
     * Show the form for creating a new email label.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return view('AdminConsole.features.emaillabels.create');
    }

    /**
     * Store a newly created email label in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->isMethod('post')) {
            $userId = Auth::id();
            
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) use ($userId) {
                        // Check if label name already exists for this user
                        $exists = EmailLabel::where('user_id', $userId)
                            ->where('name', $value)
                            ->where('is_active', true)
                            ->exists();
                        
                        if ($exists) {
                            $fail('A label with this name already exists.');
                        }
                    }
                ],
                'color' => [
                    'required',
                    'string',
                    'regex:/^#[0-9A-Fa-f]{6}$/'
                ],
                'icon' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:500',
                'type' => 'nullable|in:system,custom',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $requestData = $request->all();
            
            $obj = new EmailLabel;
            $obj->user_id = $userId;
            $obj->name = $requestData['name'];
            $obj->color = $requestData['color'];
            $obj->icon = $requestData['icon'] ?? 'tag';
            $obj->type = $requestData['type'] ?? 'custom';
            $obj->description = $requestData['description'] ?? null;
            $obj->is_active = true;
            
            $saved = $obj->save();

            if (!$saved) {
                return redirect()->back()->with('error', config('constants.server_error'));
            } else {
                return redirect()->route('adminconsole.features.emaillabels.index')->with('success', 'Email Label Created Successfully');
            }
        }

        return view('AdminConsole.features.emaillabels.create');
    }

    /**
     * Show the form for editing the specified email label.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (isset($id) && !empty($id)) {
            $id = $this->decodeString($id);
            if (EmailLabel::where('id', '=', $id)->exists()) {
                $fetchedData = EmailLabel::find($id);
                return view('AdminConsole.features.emaillabels.edit', compact(['fetchedData']));
            } else {
                return redirect()->route('adminconsole.features.emaillabels.index')->with('error', 'Record Not Exist');
            }
        } else {
            return redirect()->route('adminconsole.features.emaillabels.index')->with('error', config('constants.unauthorized'));
        }
    }

    /**
     * Update the specified email label in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $requestData = $request->all();
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($userId, $id) {
                    // Check if label name already exists for this user (excluding current label)
                    $exists = EmailLabel::where('user_id', $userId)
                        ->where('name', $value)
                        ->where('id', '!=', $id)
                        ->where('is_active', true)
                        ->exists();
                    
                    if ($exists) {
                        $fail('A label with this name already exists.');
                    }
                }
            ],
            'color' => [
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/'
            ],
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:system,custom',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $obj = EmailLabel::find($id);
        if (!$obj) {
            return redirect()->route('adminconsole.features.emaillabels.index')->with('error', 'Record Not Found');
        }

        // Prevent editing system labels
        if ($obj->type === 'system') {
            return redirect()->back()->with('error', 'System labels cannot be edited');
        }

        $obj->name = $requestData['name'];
        $obj->color = $requestData['color'];
        $obj->icon = $requestData['icon'] ?? 'tag';
        $obj->description = $requestData['description'] ?? null;
        if (isset($requestData['is_active'])) {
            $obj->is_active = $requestData['is_active'];
        }
        
        $saved = $obj->save();

        if (!$saved) {
            return redirect()->back()->with('error', config('constants.server_error'));
        } else {
            return redirect()->route('adminconsole.features.emaillabels.index')->with('success', 'Email Label Updated Successfully');
        }
    }

    /**
     * Decode string ID (following pattern from TagController)
     */
    public function decodeString($string = null)
    {
        return convert_uudecode(base64_decode($string));
    }
}

