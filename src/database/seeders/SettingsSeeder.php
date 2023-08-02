<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Settings\app\Models\Settings;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      Settings::create([
        'key' => 'payment',
        'template' => 'payment'
      ]);


      Settings::create([
        'key' => 'currency',
        'template' => 'currency',
      ]);

      Settings::create([
        'key' => 'delivery',
        'template' => 'delivery'
      ]);

      Settings::create([
        'key' => 'contacts',
        'template' => 'contacts'
      ]);

      Settings::create([
        'key' => 'noty',
        'template' => 'noty'
      ]);

      Settings::create([
        'key' => 'product',
        'template' => 'product'
      ]);
    }
}
