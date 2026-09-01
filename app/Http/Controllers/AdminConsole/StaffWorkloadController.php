<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Services\StaffWorkloadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffWorkloadController extends Controller
{
    public function __construct(
        protected StaffWorkloadService $staffWorkloadService,
    ) {
        $this->middleware('auth:admin');
        $this->middleware('adminconsole');
    }

    public function index(Request $request): View
    {
        $dateInput = $request->query('date');
        $day = null;
        if (is_string($dateInput) && $dateInput !== '') {
            try {
                $day = Carbon::parse($dateInput, (string) config('app.timezone'))->startOfDay();
            } catch (\Throwable) {
                $day = null;
            }
        }

        [$start] = $this->staffWorkloadService->dayBounds($day);
        $rows = $this->staffWorkloadService->getAdminWorkloadRows($start);

        return view('AdminConsole.staff.workload', [
            'rows' => $rows,
            'selectedDate' => $start->toDateString(),
            'dateLabel' => $start->format('l, j M Y'),
        ]);
    }
}
