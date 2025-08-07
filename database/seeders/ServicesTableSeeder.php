<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;

class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = City::all();

        if ($cities->isEmpty()) {
            $this->command->error('Countries or Cities table is empty. Seed them first.');
            return;
        }

        $services = [
            // Repairment Services
            'AC Repair',
            'Washing Machine Repair',
            'Refrigerator Repair',
            'Electrical Maintenance',
            'Plumbing Services',
            'Mobile Phone Repair',
            'Computer / Laptop Repair',
            'Furniture Repair',
            'Generator Repair',
            'Solar Panel Installation & Repair',
            'Appliance Installation',

            // Product Supply
            'Grocery Supply',
            'Office Supplies',
            'Water Delivery',
            'Food Catering',
            'Construction Material Supply',
            'Electronics Supply',
            'Cleaning Material Supply',
            'Stationery Supply',
            'Medical Equipment Supply',
            'Home Essentials Supply',

            // Car Drivers / Chauffeur Services
            'Personal Driver (Hourly)',
            'Office Pickup & Drop',
            'Event Driver',
            'Airport Pickup Driver',
            'Truck Driver',
            'Delivery Rider (Bike)',
            'Car with Driver on Rent',
            'School Van Driver',
            'Ambulance Driver',
            'Private Chauffeur (Luxury Car)',
        ];

        foreach ($services as $name) {
            $randomCity = $cities->random();
            Service::create([
                'name' => $name,
                'country_id' => $randomCity->country_id,
                'city_id' => $randomCity->id,
            ]);
        }

        $this->command->info('Services seeded with random country and city IDs.');
    }
}
