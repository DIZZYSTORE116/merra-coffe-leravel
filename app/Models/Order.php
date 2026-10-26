<?php

namespace App\Models;

use App\Support\Iso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    public const STATUSES = ['Baru', 'Diproses', 'Siap Diambil', 'Selesai'];

    public const PAYMENT_METHODS = ['QRIS', 'Bayar Tunai di Kasir', 'Transfer Virtual Account'];

    protected $fillable = [
        'order_number', 'customer_name', 'table_note', 'items',
        'total_amount', 'payment_method', 'status',
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'integer',
    ];

    /** Pesanan sejak 00:00 hari ini (zona waktu aplikasi, default Asia/Jakarta). */
    public function scopeToday(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfDay());
    }

    /**
     * Nomor pesanan berikutnya untuk hari ini (1, 2, 3, ...). Baris penghitung dikunci lewat
     * UPDATE di dalam transaksi, sehingga dua pembeli bersamaan tidak mendapat nomor yang sama.
     * Panggil di dalam DB::transaction() yang sama dengan pembuatan pesanan.
     */
    public static function nextNumber(): string
    {
        $day = now()->toDateString();

        DB::table('order_counters')->insertOrIgnore(['day' => $day, 'last_number' => 0]);
        DB::table('order_counters')->where('day', $day)->increment('last_number');

        return (string) DB::table('order_counters')->where('day', $day)->value('last_number');
    }

    /**
     * Hapus pesanan hari-hari sebelumnya dan penghitung nomor lamanya. Idempoten.
     * Tanpa $force, hanya dijalankan sekali per hari (dijaga lewat cache).
     */
    public static function purgeStale(bool $force = false): ?int
    {
        if (! $force && ! Cache::add('orders.purged.'.now()->toDateString(), true, now()->endOfDay())) {
            return null;
        }

        $deleted = static::where('created_at', '<', now()->startOfDay())->delete();
        DB::table('order_counters')->where('day', '<', now()->toDateString())->delete();

        return $deleted;
    }

    /** Bentuk JSON sama persis dengan API versi Vercel (camelCase). */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'customerName' => $this->customer_name,
            'tableNote' => $this->table_note,
            'items' => $this->items,
            'totalAmount' => $this->total_amount,
            'paymentMethod' => $this->payment_method,
            'status' => $this->status,
            'createdAt' => Iso::format($this->created_at),
            'updatedAt' => Iso::format($this->updated_at),
        ];
    }
}
