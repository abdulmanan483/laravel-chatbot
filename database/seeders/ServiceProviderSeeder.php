<?php

namespace Database\Seeders;

use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;

class ServiceProviderSeeder extends Seeder
{
    public function run(): void
    {
        // ServiceProvider::truncate();
        $users    = User::serviceProvider()->active()->pluck('id')->all();
        $services = Service::pluck('id')->all();

        // Create 50 random service providers
        foreach (range(1, 200) as $i) {
            ServiceProvider::create([
                'user_id' => fake()->randomElement($users),
                'service_id' => fake()->randomElement($services),
            ]);
        }


        // Insert fixed service providers for PAK and NPL
        $pakistan = Country::where('iso3', 'PAK')->first();
        $nepal    = Country::where('iso3', 'NPL')->first();

        $fsd = City::where('name', 'Faisalabad')->first();
        $lhr = City::where('name', 'Lahore')->first();
        $ktm = City::where('name', 'Kathmandu')->first();

        // Create fixed services if needed (optional)
        $acRepair     = Service::firstOrCreate(['name' => 'AC Repair', 'city_id' => $fsd->id, 'country_id' => $pakistan->id]);
        $hvacInstall  = Service::firstOrCreate(['name' => 'Car with Driver on Rent', 'city_id' => $lhr->id, 'country_id' => $pakistan->id]);
        $bedRepair    = Service::firstOrCreate(['name' => 'Furniture Repair', 'city_id' => $ktm->id, 'country_id' => $nepal->id]);

        // Attach fixed users to fixed services (replace with real user IDs or random if needed)
        ServiceProvider::create([
            'user_id'    => $users[array_rand($users)],
            'service_id' => $acRepair->id,
        ]);

        ServiceProvider::create([
            'user_id'    => $users[array_rand($users)],
            'service_id' => $hvacInstall->id,
        ]);

        ServiceProvider::create([
            'user_id'    => $users[array_rand($users)],
            'service_id' => $bedRepair->id,
        ]);
    }
}
