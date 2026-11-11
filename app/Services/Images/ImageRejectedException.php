<?php

namespace App\Services\Images;

use RuntimeException;

/** รูปไม่ผ่านการตรวจ — message เป็นภาษาไทย แสดงให้ผู้ใช้ได้เลย */
class ImageRejectedException extends RuntimeException {}
