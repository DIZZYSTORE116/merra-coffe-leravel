<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // order_number = urutan pesanan pada hari itu, jadi TIDAK unik secara global.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->string('customer_name', 100);
            $table->string('table_note', 100)->default('-');
            $table->json('items');
            $table->unsignedInteger('total_amount');
            $table->string('payment_method', 100);
            $table->string('status', 20)->default('Baru');
            $table->timestamps();
            $table->index('created_at');
        });

        // Penghitung nomor pesanan per hari; baris hari lalu dihapus saat reset.
        Schema::create('order_counters', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_counters');
        Schema::dropIfExists('orders');
    }
};
