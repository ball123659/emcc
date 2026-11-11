<?php

namespace Tests\Feature;

use Illuminate\Foundation\Console\ServeCommand;
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

    public function test_artisan_serve_passes_temp_directory_to_server_process(): void
    {
        $this->assertContains('TEMP', ServeCommand::$passthroughVariables);
        $this->assertContains('TMP', ServeCommand::$passthroughVariables);
    }
}
