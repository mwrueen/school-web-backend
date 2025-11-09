<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@school.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        // Create teacher user
        User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@school.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER,
            'email_verified_at' => now(),
        ]);

        // Create additional test users
        User::factory(5)->create(['role' => User::ROLE_TEACHER]);
        User::factory(2)->create(['role' => User::ROLE_ADMIN]);
    }
}
