<?php

namespace App\Controllers\Backend;

use App\Libraries\ActivityLogger;
use App\Models\ActivityLogModel;
use App\Models\UserModel;

class ActivityLogs extends BackendController
{
    public function index()
    {
        $q      = $this->searchTerm();
        $action = (string) $this->request->getGet('action');
        $userId = (int) $this->request->getGet('user') ?: null;
        $from   = $this->getDate('from');
        $until  = $this->getDate('until');
        $model  = (new ActivityLogModel())->select('activity_logs.*, u.username')->join('users u', 'u.id = activity_logs.user_id', 'left');

        if ($q !== '') {
            $model->groupStart()->like('activity_logs.description', $q)->orLike('u.username', $q)->groupEnd();
        }

        if (isset(ActivityLogger::ACTIONS[$action])) {
            $model->where('activity_logs.action', $action);
        }

        if ($userId !== null) {
            $model->where('activity_logs.user_id', $userId);
        }

        if ($from !== null) {
            $model->where('activity_logs.created_at >=', $from . ' 00:00:00');
        }

        if ($until !== null) {
            $model->where('activity_logs.created_at <=', $until . ' 23:59:59');
        }

        $dir = $this->request->getGet('dir') === 'asc' ? 'asc' : 'desc';
        $model->orderBy('activity_logs.id', $dir);

        return view('backend/activity_logs/index', [
            'title'       => 'บันทึกการใช้งาน',
            'breadcrumbs' => ['บันทึกการใช้งาน' => site_url('backend/activity-logs'), 'รายการ' => null],
            'q'           => $q,
            'action'      => $action,
            'userId'      => $userId,
            'from'        => $from,
            'until'       => $until,
            'dateError'   => $from !== null && $until !== null && $until < $from ? 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม' : null,
            'users'       => array_column((new UserModel())->withDeleted()->select('id, username')->orderBy('username')->findAll(), 'username', 'id'),
            'pager'       => $this->paginate($model),
            'query'       => $this->query(['q', 'per_page', 'action', 'user', 'from', 'until', 'dir']),
            'dir'         => $dir,
        ]);
    }

    public function show(int $id)
    {
        $log = (new ActivityLogModel())->select('activity_logs.*, u.username')->join('users u', 'u.id = activity_logs.user_id', 'left')->find($id);

        if ($log === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('backend/activity_logs/show', [
            'title'       => 'รายละเอียดบันทึก #' . $id,
            'breadcrumbs' => ['บันทึกการใช้งาน' => site_url('backend/activity-logs'), 'รายละเอียด' => null],
            'log'         => $log,
            'changes'     => ActivityLogModel::decodeChanges($log['changes']),
        ]);
    }

    private function getDate(string $key): ?string
    {
        $value = (string) $this->request->getGet($key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}
