<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\User;
use App\Models\UserPagePermission;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ---------- ล็อกอิน / ล็อกบัญชี ----------

    public function test_five_wrong_passwords_lock_account_for_15_minutes(): void
    {
        $user = User::factory()->create(['username' => 'staff', 'password' => 'secret123']);

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Login::class)
                ->set('data.username', 'staff')
                ->set('data.password', 'wrong')
                ->call('authenticate')
                ->assertHasErrors(['data.username']);
        }

        $user->refresh();
        $this->assertTrue($user->isLocked());
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($user->locked_until), 1);

        // ใส่รหัสถูกระหว่างล็อกก็เข้าไม่ได้
        Livewire::test(Login::class)
            ->set('data.username', 'staff')
            ->set('data.password', 'secret123')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);
        $this->assertGuest();

        // ผ่านไป 15 นาทีเข้าได้
        $this->travel(16)->minutes();
        Livewire::test(Login::class)
            ->set('data.username', 'staff')
            ->set('data.password', 'secret123')
            ->call('authenticate')
            ->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->refresh()->failed_login_attempts);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_login_events_are_logged_without_password(): void
    {
        User::factory()->create(['username' => 'staff', 'password' => 'secret123']);

        Livewire::test(Login::class)->set('data.username', 'staff')->set('data.password', 'wrongpass')->call('authenticate');
        Livewire::test(Login::class)->set('data.username', 'nobody')->set('data.password', 'x')->call('authenticate');
        Livewire::test(Login::class)->set('data.username', 'staff')->set('data.password', 'secret123')->call('authenticate');

        $this->assertSame(['login_failed', 'login_failed', 'login'], ActivityLog::whereIn('action', ['login', 'login_failed'])->orderBy('id')->pluck('action')->all());
        $this->assertNull(ActivityLog::where('description', 'like', '%no***%')->value('user_id'));
        $this->assertFalse(ActivityLog::where('description', 'like', '%nobody%')->exists());

        $all = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString('secret123', $all);
        $this->assertStringNotContainsString('wrongpass', $all);
    }

    // ---------- บังคับเปลี่ยนรหัส ----------

    public function test_user_must_change_password_before_using_other_pages(): void
    {
        $developer = User::where('username', 'developer')->first();
        $this->actingAs($developer);

        $this->get('/backend')->assertRedirect('/backend/profile');
        $this->get('/backend/users')->assertRedirect('/backend/profile');
        $this->get('/backend/profile')->assertOk()->assertSee('กรุณาตั้งรหัสผ่านใหม่ก่อนเริ่มใช้งาน');

        // สั้นเกิน / ซ้ำรหัสเริ่มต้น -> ไม่ผ่าน
        Livewire::test(EditProfile::class)
            ->set('data.password', '12345')
            ->set('data.passwordConfirmation', '12345')
            ->set('data.currentPassword', 'passwordemcc')
            ->call('save')
            ->assertHasErrors(['data.password']);

        Livewire::test(EditProfile::class)
            ->set('data.password', 'passwordemcc')
            ->set('data.passwordConfirmation', 'passwordemcc')
            ->set('data.currentPassword', 'passwordemcc')
            ->call('save')
            ->assertHasErrors(['data.password']);

        Livewire::test(EditProfile::class)
            ->set('data.password', 'newpass1')
            ->set('data.passwordConfirmation', 'newpass1')
            ->set('data.currentPassword', 'passwordemcc')
            ->call('save')
            ->assertHasNoErrors();

        $developer->refresh();
        $this->assertFalse($developer->must_change_password);
        $this->assertTrue(Hash::check('newpass1', $developer->password));
        $this->assertNotNull($developer->password_changed_at);
        $this->assertTrue(ActivityLog::where('action', 'password_change')->exists());

        // (เบราว์เซอร์จริงได้ session ใหม่จากหน้าโปรไฟล์ — ในเทสต์ล้าง session เอง)
        $this->flushSession()->actingAs($developer)->get('/backend')->assertOk();
    }

    public function test_profile_updates_own_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('data.first_name', '  สมชาย ')
            ->set('data.phone', '081-234-5678')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('สมชาย', $user->first_name);
        $this->assertSame('0812345678', $user->phone);
    }

    // ---------- จัดการผู้ใช้งาน ----------

    public function test_only_admins_can_access_user_management_and_logs(): void
    {
        $user = User::factory()->create();
        UserPagePermission::where('user_id', $user->id)->update(['can_view' => true]);

        $this->actingAs($user);
        $this->get('/backend/users')->assertForbidden();
        $this->get('/backend/activity-logs')->assertForbidden();

        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/backend/users')->assertOk();
        $this->get('/backend/activity-logs')->assertOk();
    }

    public function test_admin_creates_user_with_default_password_and_permissions(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $news = Page::where('code', 'news')->first();
        $slides = Page::where('code', 'slides')->first();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'username' => ' New.User ',
                'email' => 'new@example.com',
                'first_name' => 'ใหม่',
                'last_name' => 'ผู้ใช้',
                'phone' => '081 234 5678',
                'role' => User::ROLE_USER,
                'is_active' => true,
                'page_permissions' => [$news->id, $slides->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('username', 'new.user')->firstOrFail();
        $this->assertTrue(Hash::check('passwordemcc', $created->password));
        $this->assertTrue($created->must_change_password);
        $this->assertSame('0812345678', $created->phone);
        $this->assertTrue($created->canViewPage('news'));
        $this->assertTrue($created->canViewPage('slides'));
        $this->assertTrue($created->canViewPage('highlight'), 'เมนูแม่เห็นเมื่อมีเมนูย่อยที่เห็น');
        $this->assertFalse($created->canViewPage('downloads'));
        $this->assertTrue(ActivityLog::where('action', 'create')->where('subject_id', $created->id)->exists());
    }

    public function test_username_rules(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test(CreateUser::class)
            ->fillForm(['username' => 'สมชาย', 'email' => 'a@example.com', 'first_name' => 'a', 'last_name' => 'b', 'role' => 'user'])
            ->call('create')
            ->assertHasFormErrors(['username' => 'regex']);

        $trashed = User::factory()->create(['username' => 'oldone']);
        $trashed->delete();

        Livewire::test(CreateUser::class)
            ->fillForm(['username' => 'oldone', 'email' => 'b@example.com', 'first_name' => 'a', 'last_name' => 'b', 'role' => 'user'])
            ->call('create')
            ->assertHasFormErrors(['username' => 'unique']);
    }

    public function test_administrator_cannot_manage_developer_or_assign_developer_role(): void
    {
        $admin = User::factory()->administrator()->create();
        $developer = User::where('username', 'developer')->first();
        $this->actingAs($admin);

        $this->get("/backend/users/{$developer->id}/edit")->assertForbidden();

        Livewire::test(CreateUser::class)
            ->fillForm(['username' => 'x1', 'email' => 'x1@example.com', 'first_name' => 'a', 'last_name' => 'b', 'role' => User::ROLE_DEVELOPER])
            ->call('create')
            ->assertHasFormErrors(['role']);

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('resetPassword')->table($developer))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($developer));
    }

    public function test_cannot_delete_or_deactivate_self(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->assertActionHidden(DeleteAction::class)
            ->fillForm(['is_active' => false, 'role' => User::ROLE_USER])
            ->call('save');

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertSame(User::ROLE_ADMINISTRATOR, $admin->role);
        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_soft_delete_and_restore_user(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->id])->callAction(DeleteAction::class);
        $this->assertNotNull(User::withTrashed()->find($target->id)->deleted_at);

        User::withTrashed()->find($target->id)->restore();
        $this->assertNull(User::find($target->id)->deleted_at);
        $this->assertSame(['delete', 'restore'], ActivityLog::where('subject_id', $target->id)
            ->whereIn('action', ['delete', 'restore'])->orderBy('id')->pluck('action')->all());
    }

    public function test_reset_password_action(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $target = User::factory()->create(['password' => 'mypassword', 'locked_until' => now()->addMinutes(10)]);

        Livewire::test(EditUser::class, ['record' => $target->id])->callAction('resetPassword');

        $target->refresh();
        $this->assertTrue(Hash::check('passwordemcc', $target->password));
        $this->assertTrue($target->must_change_password);
        $this->assertFalse($target->isLocked());
        $this->assertTrue(ActivityLog::where('action', 'password_reset')->exists());
        $this->assertStringNotContainsString('passwordemcc', ActivityLog::all()->toJson());
    }

    public function test_activity_log_page_is_read_only(): void
    {
        $this->actingAs(User::factory()->developer()->create());

        Livewire::test(ListActivityLogs::class)->assertOk();
        $this->assertFalse(auth()->user()->can('delete', ActivityLog::first() ?? new ActivityLog));
        $this->assertFalse(auth()->user()->can('create', ActivityLog::class));
    }
}
