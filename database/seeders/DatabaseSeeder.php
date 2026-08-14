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
        User::updateOrCreate(
            ['email' => 'admin@orchajourney.com'],
            [
                'name' => 'Admin Orcha',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            TestimonialSeeder::class,
            PartnerSeeder::class,
            TravelPackageSeeder::class,
            DestinationPopulerSeeder::class,
            CarSeeder::class,
        ]);
    }
}
