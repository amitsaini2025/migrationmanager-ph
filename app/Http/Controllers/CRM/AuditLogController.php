<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffLoginLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
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
     * Audit login logs, optionally filtered by staff.
     *
     * @return \Illuminate\Http\Response
     */
	public function index(Request $request)
	{
		$query = StaffLoginLog::query();

		if ($request->filled('staff_id')) {
			$query->where('user_id', $request->integer('staff_id'));
		}

		$totalData = $query->count();
		$lists = $query->sortable(['id' => 'desc'])->paginate(20);
		$staffList = Staff::where('status', 1)->orderBy('first_name')->get();

		return view('crm.auditlogs.index', compact(['lists', 'totalData', 'staffList']));
	}
}
