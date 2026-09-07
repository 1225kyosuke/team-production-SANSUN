<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function login(string $email, string $password): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password])->assertOk()->json('token');
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_staff_can_view_user_detail_with_assigned_subjects(): void
    {
        $token = $this->login('staff@sansun.test', 'Staff123');
        $teacher = User::where('email', 'teacher@sansun.test')->firstOrFail();
        $subject = Subject::where('teacher_id', $teacher->id)->firstOrFail();

        $response = $this->withHeaders($this->auth($token))->getJson("/api/masters/users/{$teacher->id}");

        $response->assertOk()
            ->assertJsonPath('user.id', $teacher->id)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('subjects.0.id', $subject->id);
        $this->assertArrayNotHasKey('password_setup_token_hash', $response->json('user'));
    }

    public function test_teacher_cannot_view_user_detail(): void
    {
        $token = $this->login('teacher@sansun.test', 'Teacher123');
        $teacher = User::where('email', 'teacher2@sansun.test')->firstOrFail();

        $this->withHeaders($this->auth($token))
            ->getJson("/api/masters/users/{$teacher->id}")
            ->assertForbidden();
    }

    public function test_staff_can_approve_both_user_role_directions(): void
    {
        $token = $this->login('staff@sansun.test', 'Staff123');
        $teacher = User::where('email', 'teacher@sansun.test')->firstOrFail();

        $response = $this->withHeaders($this->auth($token))
            ->postJson("/api/masters/users/{$teacher->id}/role-approval", ['role' => 'staff']);

        $response->assertOk()
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonPath('message', '専任職員として承認しました。');
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'role' => 'staff']);

        $response = $this->withHeaders($this->auth($token))
            ->postJson("/api/masters/users/{$teacher->id}/role-approval", ['role' => 'teacher']);

        $response->assertOk()
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('message', '講師として承認しました。');
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'role' => 'teacher']);
    }

    public function test_teacher_cannot_approve_a_user_role(): void
    {
        $token = $this->login('teacher@sansun.test', 'Teacher123');
        $teacher = User::where('email', 'teacher2@sansun.test')->firstOrFail();

        $this->withHeaders($this->auth($token))
            ->postJson("/api/masters/users/{$teacher->id}/role-approval", ['role' => 'staff'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'role' => 'teacher']);
    }

    public function test_staff_cannot_approve_their_own_role(): void
    {
        $token = $this->login('staff@sansun.test', 'Staff123');
        $staff = User::where('email', 'staff@sansun.test')->firstOrFail();

        $this->withHeaders($this->auth($token))
            ->postJson("/api/masters/users/{$staff->id}/role-approval", ['role' => 'teacher'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ログイン中の自分自身の役割は変更できません。');

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'staff']);
    }

    public function test_invalid_user_role_is_rejected(): void
    {
        $token = $this->login('staff@sansun.test', 'Staff123');
        $teacher = User::where('email', 'teacher@sansun.test')->firstOrFail();

        $this->withHeaders($this->auth($token))
            ->postJson("/api/masters/users/{$teacher->id}/role-approval", ['role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_staff_can_send_a_user_password_reset_email(): void
    {
        $token = $this->login('staff@sansun.test', 'Staff123');
        $teacher = User::where('email', 'teacher@sansun.test')->firstOrFail();
        $transport = Mail::mailer()->getSymfonyTransport();
        $transport->flush();

        $response = $this->withHeaders($this->auth($token))
            ->postJson("/api/masters/users/{$teacher->id}/password-reset");

        $response->assertOk()->assertJsonPath('message', 'パスワード再設定用リンクをメールで送信しました。');
        $updatedTeacher = $teacher->fresh();
        $this->assertTrue($updatedTeacher->must_set_password);
        $this->assertNotNull($updatedTeacher->password_setup_token_hash);
        $this->assertNotNull($updatedTeacher->password_setup_expires_at);
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->first()->getOriginalMessage();
        $this->assertSame('teacher@sansun.test', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('パスワード再設定のご案内', $message->getSubject());
        $body = quoted_printable_decode($message->getBody()->toString());
        $this->assertStringContainsString('/password/setup?token=', $body);
    }
}
