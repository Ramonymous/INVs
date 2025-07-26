<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB; // Pastikan DB di-import

class InvReceipt extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['receipt_number', 'received_by', 'received_at'];

    /** @var array<string,string> */
    protected $casts = ['received_at' => 'datetime'];

    /* ---------------- Booting ---------------- */

    // ❌ METODE BOOTED YANG SALAH SUDAH DIHAPUS DARI SINI

    /* ---------------- Relations ---------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvReceiptItem::class, 'receipt_id'); // Lebih eksplisit dengan foreign key
    }

    /* ---------------- Accessors ---------------- */

    public function getTotalReceivedQtyAttribute(): int|float
    {
        return $this->items->sum('quantity');
    }

    public function getUniquePartsCountAttribute(): int
    {
        return $this->items->pluck('child_part_id')->unique()->count();
    }

    /* ---------------- Scopes ---------------- */

    public function scopeWithTotals(Builder $query): Builder
    {
        return $query->withSum('items as total_received_qty', 'quantity')
                     ->withCount(['items as unique_parts_count' => fn ($q) => $q->distinct('child_part_id')]);
    }

    /* ---------------- Static Function ---------------- */
    public static function createWithItems(array $receiptData, array $itemsData): self
    {
        return DB::transaction(function () use ($receiptData, $itemsData) {
            // 1. Buat record utama
            $receipt = self::create($receiptData);

            // 2. Buat nomor batch yang aman
            $date = now()->format('Ymd');
            $receipt->receipt_number = 'BATCH-' . $date . '-' . str_pad((string)$receipt->id, 4, '0', STR_PAD_LEFT);
            $receipt->save();

            // 3. Siapkan dan insert item
            $itemsToInsert = collect($itemsData)->map(function ($item, $i) use ($receipt, $date) {
                return [
                    'receipt_id' => $receipt->id,
                    'child_part_id' => $item['child_part_id'],
                    'quantity' => $item['quantity'],
                    'available' => $item['quantity'],
                    'code' => 'RCPT-' . $date . '-' . str_pad((string)(($receipt->id * 100) + ($i + 1)), 4, '0', STR_PAD_LEFT),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            InvReceiptItem::insert($itemsToInsert);
            
            // 4. ✅ TAMBAHKAN KEMBALI LOGIKA UPDATE STOK
            foreach ($itemsData as $item) {
                MasterChildpart::where('id', $item['child_part_id'])->increment('stock', $item['quantity']);
            }
            
            // 5. Muat relasi items agar bisa diakses setelah pembuatan
            $receipt->load('items');

            return $receipt;
        });
    }
}