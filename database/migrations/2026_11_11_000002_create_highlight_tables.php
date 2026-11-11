<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Highlight — Slide Show และ Popup (webemcc.sql หัวข้อ 3–4)
 * FK slides.news_id -> news อยู่ใน migration ข่าว (สร้างหลังตาราง news)
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if (! Schema::hasTable('slides')) {
            Schema::create('slides', function (Blueprint $table) {
                $table->comment('สไลด์โชว์หน้าแรก');
                $table->id();
                $table->string('title', 200)->comment('ชื่อเรียกในหลังบ้าน (ไม่แสดงหน้าเว็บ)');
                $table->string('image_th', 255)->comment('path รูปภาษาไทย (บังคับ)');
                $table->string('image_en', 255)->nullable()->comment('path รูปภาษาอังกฤษ (ว่าง = ใช้รูปไทย)');
                $table->string('image_mobile', 255)->nullable()->comment('รูปสำหรับจอมือถือ (ไม่บังคับ)');
                $table->string('alt_th', 255)->comment('คำอธิบายรูป สำหรับผู้พิการทางสายตา');
                $table->string('alt_en', 255)->nullable();
                $table->enum('link_type', ['none', 'news', 'url'])->default('none');
                $table->unsignedBigInteger('news_id')->nullable()->comment('ใช้เมื่อ link_type = news');
                $table->string('link_url', 500)->nullable()->comment('ใช้เมื่อ link_type = url (http/https เท่านั้น)');
                $table->boolean('open_new_tab')->default(true);
                $table->date('start_date')->nullable()->comment('ว่าง = แสดงทันที');
                $table->date('end_date')->nullable()->comment('ว่าง = ไม่มีวันหมดอายุ');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->datetimes();
                $table->softDeletesDatetime();

                $table->index(['is_active', 'start_date', 'end_date', 'sort_order'], 'idx_slides_show');
                $table->index('news_id', 'idx_slides_news');
                $table->foreign('created_by', 'fk_slides_created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'fk_slides_updated_by')->references('id')->on('users')->nullOnDelete();
            });

            if ($mysql) {
                DB::statement('ALTER TABLE `slides` ADD CONSTRAINT `chk_slides_dates` CHECK (`end_date` IS NULL OR `start_date` IS NULL OR `end_date` >= `start_date`)');
                // link_type = news ต้องมี news_id — ตรวจในแอป (MySQL ห้าม CHECK บนคอลัมน์ที่มี FK แบบ SET NULL)
                DB::statement("ALTER TABLE `slides` ADD CONSTRAINT `chk_slides_link` CHECK (`link_type` <> 'url' OR `link_url` IS NOT NULL)");
            }
        }

        if (! Schema::hasTable('popups')) {
            Schema::create('popups', function (Blueprint $table) {
                $table->comment('ป๊อปอัปหน้าเว็บ');
                $table->id();
                $table->string('title', 200)->comment('ชื่อเรียกในหลังบ้าน');
                $table->string('image_th', 255);
                $table->string('image_en', 255)->nullable();
                $table->string('alt_th', 255);
                $table->string('alt_en', 255)->nullable();
                $table->string('link_url', 500)->nullable()->comment('ไม่บังคับ (http/https เท่านั้น)');
                $table->boolean('open_new_tab')->default(true);
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->datetimes();
                $table->softDeletesDatetime();

                $table->index(['is_active', 'start_date', 'end_date', 'sort_order'], 'idx_popups_show');
                $table->foreign('created_by', 'fk_popups_created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'fk_popups_updated_by')->references('id')->on('users')->nullOnDelete();
            });

            if ($mysql) {
                DB::statement('ALTER TABLE `popups` ADD CONSTRAINT `chk_popups_dates` CHECK (`end_date` >= `start_date`)');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
        Schema::dropIfExists('slides');
    }
};
