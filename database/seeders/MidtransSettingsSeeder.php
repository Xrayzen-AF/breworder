<?php

namespace Database\Seeders;

use App\Models\MidtransSettings;
use Illuminate\Database\Seeder;

class MidtransSettingsSeeder extends Seeder
{
    public function run(): void
    {
        MidtransSettings::updateOrCreate(
            ['is_production' => false],
            [
                'server_key' => env('MIDTRANS_SERVER_KEY', ''),
                'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
                'is_production' => filter_var(env('MIDTRANS_IS_PRODUCTION', 'false'), FILTER_VALIDATE_BOOLEAN),
            ]
        );
    }
}
