<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $partners = [
            [
                'partner_name' => 'Rumah Makan Bu Sholeh',
            ],
            [
                'partner_name' => 'Jaya Travel Mandiri',
            ],
            [
                'partner_name' => 'Bakso Urat Pak Shomad',
            ],
            [
                'partner_name' => 'Cahaya Travel',
            ],
        ];

        foreach ($partners as $partner) {
            Partner::create($partner);
        }
    }
}
