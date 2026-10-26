<?php

namespace Database\Seeders;

use App\Models\Deploy;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Data awal riwayat rilis (ditandai source = 'seed'). Aman dijalankan berulang. */
    public function run(): void
    {
        $seed = [
            ['v1.0.0', 3, 'Rilis awal: katalog menu pembeli, keranjang, dan form pemesanan', 41],
            ['v1.1.0', 2, 'Dashboard admin: antrean pesanan real-time dan alur status barista', 38],
            ['v1.2.0', 1, 'Riwayat transaksi, laporan penjualan, dan ekspor CSV', 44],
            ['v1.3.0', 0, 'Admin tanpa login dan menu Deploys', 36],
        ];

        foreach ($seed as [$version, $daysAgo, $message, $duration]) {
            Deploy::firstOrCreate(
                ['deployment_id' => "seed-{$version}"],
                [
                    'version' => $version,
                    'environment' => 'Production',
                    'status' => 'Berhasil',
                    'branch' => 'main',
                    'commit_message' => $message,
                    'deployed_by' => 'Tim Meera Coffee',
                    'duration_seconds' => $duration,
                    'source' => 'seed',
                    'created_at' => now()->subDays($daysAgo),
                ]
            );
        }
    }
}
