<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Hanya seed admin user saja.
     * Semua data lain (Plants, Symptoms, Diseases, CF Levels) 
     * dikelola melalui sistem admin panel.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class, // Buat admin user saja
        ]);
        
        $this->command->info('');
        $this->command->info('✅ Database seeder completed!');
        $this->command->info('');
        $this->command->warn('📝 Catatan:');
        $this->command->warn('   - Data Plants, Symptoms, Diseases, dan CF Levels');
        $this->command->warn('     dapat dikelola melalui Admin Panel di sistem.');
        $this->command->warn('   - Login sebagai admin untuk mengakses panel admin.');
        $this->command->info('');
    }
}
