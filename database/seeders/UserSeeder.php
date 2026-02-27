<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['nombre' => 'Alice Garcia',  'email' => 'alice@example.com'],
            ['nombre' => 'Bob Martinez',  'email' => 'bob@example.com'],
            ['nombre' => 'Carlos Lopez',  'email' => 'carlos@example.com'],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                ['nombre' => $data['nombre'], 'password' => Hash::make('password123')]
            );
        }

        $this->command->info('Users seeded.');
    }
}
