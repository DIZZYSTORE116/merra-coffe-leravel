<?php

use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:reset', function () {
    $deleted = Order::purgeStale(true);
    $this->info("Pesanan hari-hari sebelumnya telah direset ({$deleted} dihapus).");
})->purpose('Hapus pesanan sebelum hari ini dan mulai nomor pesanan dari #1');

// Reset harian pukul 00:00 mengikuti APP_TIMEZONE (default Asia/Jakarta = WIB).
Schedule::command('orders:reset')->dailyAt('00:00');
