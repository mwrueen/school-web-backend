<?php

namespace Tests\Unit;

use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_admin_role(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isTeacher());
        $this->assertTrue($user->hasRole(User::ROLE_ADMIN));
    }

    public function test_user_has_teacher_role(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TEACHER]);
        
        $this->assertTrue($user->isTeacher());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->hasRole(User::ROLE_TEACHER));
    }

    public function test_user_role_constants(): void
    {
        $this->assertEquals('admin', User::ROLE_ADMIN);
        $this->assertEquals('teacher', User::ROLE_TEACHER);
    }

    public function test_user_fillable_attributes(): void
    {
        $user = new User();
        $fillable = $user->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('role', $fillable);
    }

    public function test_user_hidden_attributes(): void
    {
        $user = new User();
        $hidden = $user->getHidden();
        
        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    public function test_teacher_has_many_classes(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $class1 = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $class2 = Classes::factory()->create(['teacher_id' => $teacher->id]);

        $this->assertCount(2, $teacher->classes);
        $this->assertTrue($teacher->classes->contains($class1));
        $this->assertTrue($teacher->classes->contains($class2));
    }
}
