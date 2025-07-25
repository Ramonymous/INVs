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

/**
 * @property int $id
 * @property string $receipt_number
 * @property int $received_by
 * @property \Illuminate\Support\Carbon $received_at
 *
 * @property-read User $user
 * @property-read Collection<int,InvReceiptItem> $items
 * @property-read int|float $totalReceivedQty
 * @property-read int $uniquePartsCount
 */
class InvReceipt extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['receipt_number', 'received_by', 'received_at'];

    /** @var array<string,string> */
    protected $casts = ['received_at' => 'datetime'];

    /* ---------------- Relations ---------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvReceiptItem::class);
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
}