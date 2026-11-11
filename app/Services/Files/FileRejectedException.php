<?php

namespace App\Services\Files;

use RuntimeException;

/** ไฟล์ไม่ผ่านการตรวจ — message เป็นภาษาไทย แสดงให้ผู้ใช้ได้เลย */
class FileRejectedException extends RuntimeException {}
