<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $receipt_id
 * @property int $child_part_id
 * @property int|float $quantity
 * @property string $code
 *
 * @property-read InvReceipt $receipt
 * @property-read MasterChildpart $part
 * @property-read \Illuminate\Database\Eloquent\Collection<int,InvIssuance> $issuances
 */
class InvReceiptItem extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['receipt_id', 'child_part_id', 'quantity', 'code'];

    /* ---------------- Relations ---------------- */

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InvReceipt::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(MasterChildpart::class, 'child_part_id');
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(InvIssuance::class);
    }
}