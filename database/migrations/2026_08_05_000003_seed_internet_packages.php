<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $packages = [
            [
                'name' => 'Semesta10',
                'speed' => '10Mbps',
                'price' => 100000,
                'description' => 'Kecepatan Upto 10 Mbps, (Rekomendasi untuk maks 2 perangkat)',
            ],
            [
                'name' => 'Semesta20',
                'speed' => '20Mbps',
                'price' => 135000,
                'description' => 'Kecepatan Upto 20 Mbps. (Rekomendasi 2-3 perangkat)',
            ],
            [
                'name' => 'Semesta40',
                'speed' => '40Mbps',
                'price' => 150000,
                'description' => 'Kecepatan up to 40 Mbps. (Rekomendasi 3-4 perangkat)',
            ],
            [
                'name' => 'Semesta60',
                'speed' => '60Mbps',
                'price' => 175000,
                'description' => 'Kecepatan upto 60 Mbps. Rekomendasi 5-6 perangkat.',
            ],
            [
                'name' => 'Semesta100',
                'speed' => '100Mbps',
                'price' => 200000,
                'description' => 'Kecepatan upto 100 Mbps. Rekomendasi untuk 6+ perangkat.',
            ],
        ];

        foreach ($packages as $package) {
            $package['is_active'] = true;
            $package['created_at'] = $now;
            $package['updated_at'] = $now;

            DB::table('internet_packages')->updateOrInsert(
                ['name' => $package['name']],
                $package,
            );
        }
    }

    public function down(): void
    {
        DB::table('internet_packages')
            ->whereIn('name', ['Semesta10', 'Semesta20', 'Semesta40', 'Semesta60', 'Semesta100'])
            ->delete();
    }
};