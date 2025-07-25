<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $part_number
 * @property string $part_name
 * @property string $model
 * @property string $variant
 * @property string $homeline
 * @property string $address
 *
 * @property-read int|float $totalIssuedQuantity
 */
class MasterChildpart extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'part_number',
        'part_name',
        'model',
        'variant',
        'homeline',
        'address',
    ];

    /* ---------------- Relations ---------------- */

    public function receiptItems(): HasMany
    {
        return $this->hasMany(InvReceiptItem::class, 'child_part_id');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(InvRequestItem::class, 'child_part_id');
    }

    public function issuances(): HasManyThrough
    {
        return $this->hasManyThrough(
            InvIssuance::class,
            InvReceiptItem::class,
            'child_part_id',
            'receipt_item_id',
            'id',
            'id'
        );
    }

    /* ---------------- Accessors ---------------- */

    public function getTotalIssuedQuantityAttribute(): int|float
    {
        return $this->issuances()->sum('issued_quantity');
    }
}