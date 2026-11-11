<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ข่าวประชาสัมพันธ์ (webemcc.sql หัวข้อ 5) + FK slides.news_id
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if (! Schema::hasTable('news_categories')) {
            Schema::create('news_categories', function (Blueprint $table) {
                $table->comment('หมวดหมู่ข่าว');
                $table->increments('id');
                $table->string('slug', 100);
                $table->string('name_th', 100);
                $table->string('name_en', 100)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->datetimes();

                $table->unique('slug', 'uq_news_categories_slug');
            });
        }

        if (! Schema::hasTable('news')) {
            Schema::create('news', function (Blueprint $table) use ($mysql) {
                $table->comment('ข่าวประชาสัมพันธ์');
                $table->id();
                $table->unsignedInteger('category_id');
                $table->string('slug', 200)->comment('ส่วนของ URL เช่น 2026-11-pm25-report');
                $table->string('title_th', 255);
                $table->string('title_en', 255)->nullable();
                $table->string('excerpt_th', 500)->nullable()->comment('คำโปรยบนการ์ดข่าว');
                $table->string('excerpt_en', 500)->nullable();
                $table->mediumText('content_th')->comment('HTML ที่ผ่านการกรองแล้ว (กัน XSS)');
                $table->mediumText('content_en')->nullable();
                $table->string('cover_image', 255)->comment('path รูปปก (ย่อแล้ว)');
                $table->string('cover_thumb', 255)->nullable()->comment('path รูปปกขนาดเล็กสำหรับหน้ารายการ');
                $table->string('cover_alt_th', 255)->nullable();
                $table->string('cover_alt_en', 255)->nullable();
                $table->enum('status', ['draft', 'published', 'hidden'])->default('draft');
                $table->dateTime('published_at')->nullable()->comment('วันเวลาเผยแพร่ (ตั้งล่วงหน้าได้)');
                $table->boolean('is_pinned')->default(false)->comment('ปักหมุดไว้บนสุด');
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->datetimes();
                $table->softDeletesDatetime();

                $table->unique('slug', 'uq_news_slug');
                $table->index(['status', 'published_at'], 'idx_news_list');
                $table->index('category_id', 'idx_news_category');
                if ($mysql) {
                    $table->fullText(['title_th', 'title_en'], 'ft_news_title');
                }
                $table->foreign('category_id', 'fk_news_category')->references('id')->on('news_categories')->restrictOnDelete();
                $table->foreign('created_by', 'fk_news_created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'fk_news_updated_by')->references('id')->on('users')->nullOnDelete();
            });

            if ($mysql) {
                DB::statement("ALTER TABLE `news` ADD CONSTRAINT `chk_news_published` CHECK (`status` <> 'published' OR `published_at` IS NOT NULL)");
            }
        }

        if (! Schema::hasTable('news_images')) {
            Schema::create('news_images', function (Blueprint $table) {
                $table->comment('แกลเลอรีรูปของข่าว');
                $table->id();
                $table->unsignedBigInteger('news_id');
                $table->string('image_path', 255)->comment('ย่อแล้ว (ด้านยาวไม่เกิน 1920px, WebP)');
                $table->string('thumb_path', 255);
                $table->string('caption_th', 255)->nullable();
                $table->string('caption_en', 255)->nullable();
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->unsignedInteger('file_size')->nullable()->comment('ไบต์');
                $table->unsignedInteger('sort_order')->default(0);
                $table->datetimes();

                $table->index(['news_id', 'sort_order'], 'idx_news_images_news');
                $table->foreign('news_id', 'fk_news_images_news')->references('id')->on('news')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('news_attachments')) {
            Schema::create('news_attachments', function (Blueprint $table) {
                $table->comment('ไฟล์แนบของข่าว');
                $table->id();
                $table->unsignedBigInteger('news_id');
                $table->string('title_th', 255);
                $table->string('title_en', 255)->nullable();
                $table->string('file_path', 255)->comment('ชื่อสุ่ม เก็บนอก public');
                $table->string('original_name', 255);
                $table->string('mime_type', 100);
                $table->unsignedInteger('file_size');
                $table->enum('scan_status', ['pending', 'clean', 'infected', 'failed'])->default('pending');
                $table->unsignedInteger('sort_order')->default(0);
                $table->datetimes();

                $table->index(['news_id', 'sort_order'], 'idx_news_att_news');
                $table->foreign('news_id', 'fk_news_att_news')->references('id')->on('news')->cascadeOnDelete();
            });
        }

        // FK ของ slides -> news (SQLite เพิ่ม FK หลังสร้างตารางไม่ได้ จึงทำเฉพาะ MySQL/MariaDB)
        if ($mysql && ! $this->foreignKeyExists('slides', 'fk_slides_news')) {
            Schema::table('slides', function (Blueprint $table) {
                $table->foreign('news_id', 'fk_slides_news')->references('id')->on('news')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && $this->foreignKeyExists('slides', 'fk_slides_news')) {
            Schema::table('slides', fn (Blueprint $table) => $table->dropForeign('fk_slides_news'));
        }

        Schema::dropIfExists('news_attachments');
        Schema::dropIfExists('news_images');
        Schema::dropIfExists('news');
        Schema::dropIfExists('news_categories');
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $fk) => $fk['name'] === $name);
    }
};
