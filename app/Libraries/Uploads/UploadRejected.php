<?php

namespace App\Libraries\Uploads;

use RuntimeException;

/** ไฟล์อัปโหลดไม่ผ่านการตรวจ — ข้อความเป็นภาษาไทยที่แสดงให้ผู้ใช้ได้ทันที */
class UploadRejected extends RuntimeException
{
}
