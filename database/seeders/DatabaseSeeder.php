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
            ['email' => 'ticketing@amegatravel.com'],
            [
                'name' => 'AMEGA Ticketing Officer',
                'role' => 'ticketing',
                'phone' => '+63 918 777 6666',
                'password' => bcrypt('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'visa@amegatravel.com'],
            [
                'name' => 'AMEGA Visa Assistance Officer',
                'role' => 'visa_assistance',
                'phone' => '+63 918 555 4444',
                'password' => bcrypt('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'srrv@amegatravel.com'],
            [
                'name' => 'AMEGA SRRV Officer',
                'role' => 'srrv',
                'phone' => '+63 918 333 2222',
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
            ImmigrationPricingSeeder::class,
            VisaPricingSeeder::class,
            SrrvPricingSeeder::class,
            TestimonialSeeder::class,
            GalleryItemSeeder::class,
        ]);
    }
}
