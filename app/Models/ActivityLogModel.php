<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table         = 'activity_logs';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'ip_address', 'user_agent', 'created_at'];

    public static function decodeChanges(?string $json): array
    {
        $data = json_decode((string) $json, true);

        return is_array($data) ? $data : [];
    }
}
