<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Ramonymous',
            'email' => 'me@r-dev.asia',
            'password' => Hash::make('IPkmqb1V'),
            'email_verified_at' => now(),
        ]);
    }
}
