<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@amegatravel.com'],
            [
                'name' => 'AMEGA Administrator',
                'role' => 'admin',
                'password' => bcrypt('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'agent@amegatravel.com'],
            [
                'name' => 'AMEGA Travel Agent',
                'role' => 'agent',
                'phone' => '+63 918 888 9999',
                'password' => bcrypt('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'client@amegatravel.com'],
            [
                'name' => 'John Traveler',
                'role' => 'client',
                'phone' => '+63 917 123 4567',
                'password' => bcrypt('password'),
            ]
        );

        $this->call([
            DestinationSeeder::class,
            TravelPackageSeeder::class,
            ServiceSeeder::class,
            TestimonialSeeder::class,
            GalleryItemSeeder::class,
        ]);
    }
}
