<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หน้า/เมนูหลังบ้าน และสิทธิ์รายหน้า (webemcc.sql หัวข้อ 2)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->comment('รายการหน้า/เมนูหลังบ้าน');
                $table->increments('id');
                $table->string('code', 50)->comment('รหัสที่โค้ดใช้อ้างอิง เช่น news, popups');
                $table->unsignedInteger('parent_id')->nullable()->comment('เมนูแม่ (เช่น Highlight)');
                $table->string('name_th', 100);
                $table->string('name_en', 100)->nullable();
                $table->string('icon', 50)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('admin_only')->default(false)
                    ->comment('1 = เฉพาะ developer/administrator (เช่น จัดการผู้ใช้) ให้สิทธิ์ user ไม่ได้');
                $table->datetimes();

                $table->unique('code', 'uq_pages_code');
                $table->index('parent_id', 'idx_pages_parent');
                $table->foreign('parent_id', 'fk_pages_parent')->references('id')->on('pages')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_page_permissions')) {
            Schema::create('user_page_permissions', function (Blueprint $table) {
                $table->comment('สิทธิ์ผู้ใช้ x หน้า');
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('page_id');
                $table->boolean('can_view')->default(false)->comment('0 = ไม่เห็น (ค่าเริ่มต้น), 1 = เห็นและใช้งานได้');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->primary(['user_id', 'page_id']);
                $table->index('page_id', 'idx_upp_page');
                $table->foreign('user_id', 'fk_upp_user')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('page_id', 'fk_upp_page')->references('id')->on('pages')->cascadeOnDelete();
                $table->foreign('updated_by', 'fk_upp_updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_page_permissions');
        Schema::dropIfExists('pages');
    }
};
