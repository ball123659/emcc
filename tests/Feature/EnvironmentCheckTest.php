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

    public function test_check_command_requires_20m_upload_and_30m_post(): void
    {
        $this->artisan('emcc:check')
            ->expectsOutputToContain('upload_max_filesize ≥ 20M')
            ->expectsOutputToContain('post_max_size ≥ 30M');
    }

    public function test_user_ini_matches_required_limits(): void
    {
        $ini = parse_ini_file(public_path('.user.ini'));

        $this->assertSame(config('emcc.php_upload_max_mb').'M', $ini['upload_max_filesize']);
        $this->assertSame(config('emcc.php_post_max_mb').'M', $ini['post_max_size']);
    }
}
