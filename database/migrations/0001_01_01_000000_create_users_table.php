<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตาราง users ให้ตรงกับ database/webemcc.sql (หัวข้อ 1) + ตาราง sessions ของ Laravel
 * ถ้า import webemcc.sql มาแล้ว ตาราง users จะมีอยู่แล้ว -> ข้ามการสร้าง
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->comment('ผู้ใช้งานหลังบ้าน');
                $table->id();
                $table->string('username', 50)->comment('a-z 0-9 . _ - เท่านั้น (ตรวจในแอป)');
                $table->string('password', 255)->comment('bcrypt hash เท่านั้น ห้ามเก็บรหัสจริง');
                $table->enum('role', ['developer', 'administrator', 'user'])->default('user')
                    ->comment('developer/administrator เข้าได้ทุกหน้า + จัดการผู้ใช้; user ตามสิทธิ์รายหน้า');
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 191);
                $table->string('phone', 20)->nullable()->comment('เก็บเฉพาะตัวเลข เช่น 0812345678');
                $table->string('position', 150)->nullable()->comment('ตำแหน่ง');
                $table->boolean('is_active')->default(true)->comment('0 = ปิดการใช้งาน ล็อกอินไม่ได้');
                $table->boolean('must_change_password')->default(true)->comment('1 = ต้องเปลี่ยนรหัสก่อนใช้งาน');
                $table->dateTime('password_changed_at')->nullable();
                $table->unsignedTinyInteger('failed_login_attempts')->default(0);
                $table->dateTime('locked_until')->nullable()->comment('ล็อกชั่วคราวเมื่อใส่รหัสผิดเกินกำหนด');
                $table->dateTime('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable()->comment('รองรับ IPv6');
                $table->string('remember_token', 100)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->datetimes();
                $table->softDeletesDatetime();

                $table->unique('username', 'uq_users_username');
                $table->unique('email', 'uq_users_email');
                $table->index(['role', 'is_active'], 'idx_users_role_active');
                $table->foreign('created_by', 'fk_users_created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'fk_users_updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
