<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $request_id
 * @property int $receipt_item_id
 * @property int|float $issued_quantity
 * @property int $issued_by
 * @property \Illuminate\Support\Carbon $issued_at
 * @property bool $is_forced
 *
 * @property-read InvRequest $request
 * @property-read InvReceiptItem $receiptItem
 * @property-read User $issuer
 * @property-read MasterChildpart|null $childPart
 */
class InvIssuance extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'request_id',
        'request_item_id',
        'receipt_item_id',
        'issued_quantity',
        'issued_by',
        'issued_at',
        'is_forced',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'issued_at'      => 'datetime',
        'issued_quantity'=> 'integer',
        'is_forced'      => 'boolean',
    ];

    /* ---------------- Relations ---------------- */

    public function request(): BelongsTo
    {
        return $this->belongsTo(InvRequest::class);
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(InvReceiptItem::class, 'receipt_item_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeForPart(Builder $query, int $partId): Builder
    {
        return $query->whereHas('receiptItem', fn ($q) => $q->where('child_part_id', $partId));
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(InvRequestItem::class, 'request_item_id');
    }

}