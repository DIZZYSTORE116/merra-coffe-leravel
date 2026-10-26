<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Iso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderController extends Controller
{
    /** GET /api/orders — daftar pesanan hari ini + ringkasan untuk dashboard admin. */
    public function index(): JsonResponse
    {
        try {
            Order::purgeStale();
            $orders = Order::today()->orderByDesc('created_at')->orderByDesc('id')->get();
        } catch (Throwable $e) {
            report($e);

            return $this->error('Gagal memuat daftar pesanan dari database.', 500);
        }

        $count = fn (string $status) => $orders->where('status', $status)->count();
        $diproses = $count('Diproses');
        $siap = $count('Siap Diambil');

        return response()->json([
            'orders' => $orders,
            'summary' => [
                'totalOrders' => $orders->count(),
                'baruCount' => $count('Baru'),
                'diprosesCount' => $diproses,
                'siapDiambilCount' => $siap,
                'activeProcessingCount' => $diproses + $siap,
                'selesaiCount' => $count('Selesai'),
                'totalRevenue' => (int) $orders->sum('total_amount'),
                'completedRevenue' => (int) $orders->where('status', 'Selesai')->sum('total_amount'),
            ],
            'serverTime' => Iso::format(now()),
        ]);
    }

    /** POST /api/orders — pembeli membuat pesanan; harga divalidasi ulang di server. */
    public function store(Request $request): JsonResponse
    {
        $customerName = $this->clean($request->input('customerName'));
        if ($customerName === '') {
            return $this->error('Nama pembeli wajib diisi.');
        }
        if (mb_strlen($customerName) > 100) {
            return $this->error('Nama pembeli terlalu panjang (maksimal 100 karakter).');
        }

        $tableNote = $this->clean($request->input('tableNote', '-'), 100) ?: '-';
        $paymentMethod = $this->normalizePaymentMethod($this->clean($request->input('paymentMethod', 'QRIS')));

        $rawItems = $request->input('items');
        if (! is_array($rawItems) || count($rawItems) === 0) {
            return $this->error('Daftar produk pesanan tidak boleh kosong.');
        }

        // Gabungkan item duplikat dan validasi terhadap katalog resmi.
        $catalog = config('menu.items');
        $qtyByName = [];
        foreach ($rawItems as $raw) {
            $name = $this->clean(data_get($raw, 'name'));
            if ($name === '' || ! isset($catalog[$name])) {
                return $this->error('Produk "'.($name ?: 'Tidak dikenal').'" tidak terdaftar di katalog Meera Coffee.');
            }

            $qty = filter_var(data_get($raw, 'qty'), FILTER_VALIDATE_INT);
            if ($qty === false || $qty < 1 || $qty > 99) {
                return $this->error('Jumlah untuk "'.$name.'" tidak valid.');
            }

            $qtyByName[$name] = ($qtyByName[$name] ?? 0) + $qty;
        }

        $items = [];
        $total = 0;
        foreach ($qtyByName as $name => $qty) {
            $price = $catalog[$name]['price'];
            $items[] = ['name' => $name, 'price' => $price, 'qty' => $qty, 'subtotal' => $price * $qty];
            $total += $price * $qty;
        }

        try {
            Order::purgeStale();
            $order = DB::transaction(fn () => Order::create([
                'order_number' => Order::nextNumber(),
                'customer_name' => $customerName,
                'table_note' => $tableNote,
                'items' => $items,
                'total_amount' => $total,
                'payment_method' => $paymentMethod,
                'status' => 'Baru',
            ]));
        } catch (Throwable $e) {
            report($e);

            return $this->error('Gagal menyimpan pesanan ke database. Silakan coba lagi.', 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil disimpan.',
            'order' => $order,
        ], 201);
    }

    /** PATCH|PUT /api/orders/{id} (atau /api/orders dengan id / orderNumber di body) — ubah status. */
    public function update(Request $request, ?string $id = null): JsonResponse
    {
        $status = $this->clean($request->input('status'));
        if (! in_array($status, Order::STATUSES, true)) {
            return $this->error('Status tidak valid. Pilih salah satu dari: '.implode(', ', Order::STATUSES).'.');
        }

        $paramId = $id ?? $request->query('id') ?? $request->input('id');
        $orderNumber = $request->input('orderNumber');

        if (blank($paramId) && blank($orderNumber)) {
            return $this->error('ID pesanan atau nomor pesanan wajib disertakan.');
        }
        if (filled($paramId) && filter_var($paramId, FILTER_VALIDATE_INT) === false) {
            return $this->error('ID pesanan tidak valid.');
        }

        try {
            // Nomor pesanan berulang tiap hari, jadi pencarian lewat nomor dibatasi pada hari ini.
            $order = filled($paramId)
                ? Order::find((int) $paramId)
                : Order::today()->where('order_number', (string) $orderNumber)->first();

            if (! $order) {
                return $this->error('Pesanan tidak ditemukan.', 404);
            }

            $order->update(['status' => $status]);
        } catch (Throwable $e) {
            report($e);

            return $this->error('Gagal memperbarui status pesanan.', 500);
        }

        $label = $status === 'Baru' ? 'Pesanan Baru' : $status;

        return response()->json([
            'success' => true,
            'message' => "Status pesanan {$order->order_number} diperbarui menjadi \"{$label}\".",
            'order' => $order,
        ]);
    }

    /** GET|POST /api/reset — reset harian manual (cadangan bila scheduler tidak berjalan). */
    public function reset(): JsonResponse
    {
        try {
            $deleted = Order::purgeStale(true);
        } catch (Throwable $e) {
            report($e);

            return $this->error('Gagal mereset data pesanan.', 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan hari-hari sebelumnya telah direset.',
            'deletedOrders' => $deleted ?? 0,
            'serverTime' => Iso::format(now()),
        ]);
    }

    private function normalizePaymentMethod(string $raw): string
    {
        $cleaned = trim(preg_replace('/^[^\p{L}\p{N}_]+/u', '', $raw) ?? $raw);

        foreach (Order::PAYMENT_METHODS as $method) {
            if (stripos($cleaned, $method) !== false) {
                return $method;
            }
        }

        return mb_substr($cleaned, 0, 100) ?: 'QRIS';
    }
}
