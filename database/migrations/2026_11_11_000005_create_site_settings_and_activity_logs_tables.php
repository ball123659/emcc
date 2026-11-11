<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตั้งค่าเว็บไซต์ และ Activity Log (webemcc.sql หัวข้อ 7–8)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->comment('ตั้งค่าเว็บไซต์');
                $table->increments('id');
                $table->string('group', 50)->default('general');
                $table->string('key', 100);
                $table->string('label_th', 150)->comment('ชื่อช่องที่แสดงในหลังบ้าน');
                $table->text('value_th')->nullable();
                $table->text('value_en')->nullable();
                $table->enum('input_type', ['text', 'textarea', 'url', 'email', 'phone', 'image'])->default('text');
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->unique('key', 'uq_site_settings_key');
                $table->foreign('updated_by', 'fk_settings_updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->comment('บันทึกการใช้งาน');
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->comment('NULL = ไม่ทราบผู้ใช้ เช่น ล็อกอินผิด username');
                $table->string('action', 50)->comment('login, login_failed, logout, create, update, delete, restore, password_reset, ...');
                $table->string('subject_type', 50)->nullable()->comment('ชื่อตาราง เช่น news, users');
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('description', 500)->nullable();
                $table->json('changes')->nullable()->comment('ค่าเดิม/ค่าใหม่ (ไม่เก็บรหัสผ่าน)');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index(['user_id', 'created_at'], 'idx_logs_user');
                $table->index(['subject_type', 'subject_id'], 'idx_logs_subject');
                $table->index(['action', 'created_at'], 'idx_logs_action');
                $table->foreign('user_id', 'fk_logs_user')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('site_settings');
    }
};
