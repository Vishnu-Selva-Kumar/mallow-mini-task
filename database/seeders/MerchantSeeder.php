<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Merchant::firstOrCreate(
            ['id' => 1],
            ['name' => 'Acme Corp']
        );

        Merchant::firstOrCreate(
            ['id' => 2],
            ['name' => 'Starlight Tech']
        );
    }
}
