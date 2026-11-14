<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark emcc:from-laravel — ใช้ฐานข้อมูลที่เคยใช้กับเวอร์ชัน Laravel ต่อกับเวอร์ชัน CodeIgniter
 * ตาราง migrations ของ Laravel มีคอลัมน์ต่างกัน (migrate ของ CodeIgniter อ่านไม่ได้)
 * -> เปลี่ยนชื่อเป็น laravel_migrations (เก็บไว้ ไม่ลบ) แล้วรัน migrate ตามปกติ
 */
class FromLaravel extends BaseCommand
{
    protected $group       = 'EMCC';
    protected $name        = 'emcc:from-laravel';
    protected $description = 'เตรียมฐานข้อมูลจากเวอร์ชัน Laravel ให้ใช้กับเวอร์ชันนี้ (เก็บตาราง migrations เดิมเป็น laravel_migrations)';

    public function run(array $params)
    {
        $db = db_connect();

        if (! self::hasLaravelMigrations()) {
            CLI::write('ฐานข้อมูลนี้ไม่มีตาราง migrations ของ Laravel — ไม่ต้องทำอะไร', 'green');

            return EXIT_SUCCESS;
        }

        if ($db->tableExists('laravel_migrations')) {
            CLI::error('มีตาราง laravel_migrations อยู่แล้ว กรุณาตรวจสอบด้วยตนเองก่อน');

            return EXIT_ERROR;
        }

        $db->query('RENAME TABLE `migrations` TO `laravel_migrations`');
        CLI::write('เปลี่ยนชื่อตาราง migrations ของ Laravel เป็น laravel_migrations แล้ว', 'green');
        CLI::write('ขั้นต่อไป: php spark migrate --all  แล้ว  php spark db:seed DatabaseSeeder');

        return EXIT_SUCCESS;
    }

    /** ตาราง migrations ที่มีอยู่เป็นรูปแบบของ Laravel (คอลัมน์ migration) */
    public static function hasLaravelMigrations(): bool
    {
        $db = db_connect();

        return $db->tableExists('migrations', false) && in_array('migration', $db->getFieldNames('migrations'), true)
            && ! in_array('version', $db->getFieldNames('migrations'), true);
    }
}
