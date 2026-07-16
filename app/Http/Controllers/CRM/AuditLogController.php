<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Middleware\TrackStaffCrmActivity;
use App\Models\Staff;
use App\Models\StaffLoginLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

	/**
     * Audit logs from staff_login_logs: login-page events + existing-session activity.
     */
	public function index(Request $request)
	{
		$query = StaffLoginLog::query();

		if ($request->filled('staff_id')) {
			$query->where('user_id', $request->integer('staff_id'));
		}

		$totalData = $query->count();
		$lists = $query->sortable(['id' => 'desc'])
			->paginate(20)
			->appends($request->query());

		$staffIds = $lists->getCollection()->pluck('user_id')->filter()->unique()->values();
		$staffById = $staffIds->isEmpty()
			? collect()
			: Staff::whereIn('id', $staffIds)->get()->keyBy('id');

		$activityMessage = TrackStaffCrmActivity::ACTIVITY_MESSAGE;
		$staffList = Staff::where('status', 1)->orderBy('first_name')->get();

		return view('crm.auditlogs.index', compact([
			'lists',
			'totalData',
			'staffList',
			'staffById',
			'activityMessage',
		]));
	}
}
