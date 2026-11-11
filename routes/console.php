<?php

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Schedule;

/*
| งานตั้งเวลา — Linux: cron "* * * * * php artisan schedule:run"
|              Windows: Task Scheduler รัน "php artisan schedule:run" ทุก 1 นาที
*/

// ลบบันทึกการใช้งานที่เก่ากว่าระยะเวลาที่กำหนด (config/emcc.php: activity_log_retention_days)
Schedule::command('model:prune', ['--model' => [ActivityLog::class]])->dailyAt('02:15');
