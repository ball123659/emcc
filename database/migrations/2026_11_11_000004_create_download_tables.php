<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ไฟล์ดาวน์โหลด (webemcc.sql หัวข้อ 6)
 *   type = file -> อัปโหลดเข้าระบบ ต้อง scan_status = clean จึงจะแสดง
 *   type = link -> ลิงก์ภายนอก ไม่เก็บไฟล์บนเซิร์ฟเวอร์
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('download_categories')) {
            Schema::create('download_categories', function (Blueprint $table) {
                $table->comment('หมวดหมู่ไฟล์ดาวน์โหลด');
                $table->increments('id');
                $table->string('slug', 100);
                $table->string('name_th', 100);
                $table->string('name_en', 100)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->datetimes();

                $table->unique('slug', 'uq_download_categories_slug');
            });
        }

        if (! Schema::hasTable('downloads')) {
            Schema::create('downloads', function (Blueprint $table) {
                $table->comment('ไฟล์ดาวน์โหลด');
                $table->id();
                $table->unsignedInteger('category_id');
                $table->string('title_th', 255);
                $table->string('title_en', 255)->nullable();
                $table->text('description_th')->nullable();
                $table->text('description_en')->nullable();
                $table->enum('type', ['file', 'link'])->default('file');
                $table->string('file_path', 255)->nullable()->comment('ชื่อสุ่ม เก็บนอก public');
                $table->string('original_name', 255)->nullable()->comment('ชื่อไฟล์เดิม ใช้ตอนดาวน์โหลด');
                $table->string('file_extension', 10)->nullable();
                $table->string('mime_type', 100)->nullable()->comment('ตรวจจากเนื้อไฟล์จริง');
                $table->unsignedBigInteger('file_size')->nullable()->comment('ไบต์');
                $table->char('file_hash', 64)->nullable()->comment('SHA-256 ใช้ตรวจไฟล์ซ้ำ/ถูกแก้ไข');
                $table->enum('scan_status', ['pending', 'clean', 'infected', 'failed'])->nullable();
                $table->dateTime('scanned_at')->nullable();
                $table->string('external_url', 500)->nullable()->comment('ใช้เมื่อ type = link (https เท่านั้น)');
                $table->unsignedInteger('download_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->datetimes();
                $table->softDeletesDatetime();

                $table->index(['category_id', 'is_active', 'sort_order'], 'idx_downloads_list');
                $table->index('file_hash', 'idx_downloads_hash');
                $table->foreign('category_id', 'fk_downloads_category')->references('id')->on('download_categories')->restrictOnDelete();
                $table->foreign('created_by', 'fk_downloads_created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'fk_downloads_updated_by')->references('id')->on('users')->nullOnDelete();
            });

            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `downloads` ADD CONSTRAINT `chk_downloads_type` CHECK (
                       (`type` = 'file' AND `file_path` IS NOT NULL AND `external_url` IS NULL)
                    OR (`type` = 'link' AND `external_url` IS NOT NULL AND `file_path` IS NULL)
                )");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('download_categories');
    }
};
