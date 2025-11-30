<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat atau update admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@systempakar.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin123'), // Password default: admin123
                'whatsapp_number' => '6281234567890', // Nomor default, bisa diubah
                'role' => 'admin',
                'is_verified' => true,
            ]
        );
            
        if ($admin->wasRecentlyCreated) {
            $this->command->info('Admin user created successfully!');
        } else {
            $this->command->info('Admin user updated successfully!');
        }
        
        $this->command->info('Email: admin@systempakar.com');
        $this->command->info('Password: admin123');
        $this->command->warn('⚠️  Please change the password after first login!');
    }
}
