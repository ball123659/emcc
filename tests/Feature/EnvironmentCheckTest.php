<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentCheckTest extends TestCase
{
    public function test_upload_error_message_is_thai(): void
    {
        $this->assertStringContainsString('อัปโหลดไม่สำเร็จ', __('validation.uploaded', ['attribute' => 'รูป']));
    }

    public function test_check_command_runs(): void
    {
        $this->artisan('emcc:check')->expectsOutputToContain('โฟลเดอร์ไฟล์อัปโหลดชั่วคราว');
    }
}
