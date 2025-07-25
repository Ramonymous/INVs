<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $requested_by
 * @property \Illuminate\Support\Carbon $requested_at
 * @property string $destination
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int,InvRequestItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int,InvIssuance> $issuances
 * @property-read int|float $totalRequestedQty
 * @property-read int|float $totalIssuedQty
 * @property-read string $issuanceStatus
 */
class InvRequest extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['requested_by', 'requested_at', 'destination'];

    /** @var array<string,string> */
    protected $casts = ['requested_at' => 'datetime'];

    /* ---------------- Relations ---------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvRequestItem::class, 'request_id');
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(InvIssuance::class);
    }

    /* ---------------- Accessors ---------------- */

    public function getTotalRequestedQtyAttribute(): int|float
    {
        return $this->total_requested_qty ?? $this->items->sum('quantity');
    }

    public function getTotalIssuedQtyAttribute(): int|float
    {
        return $this->total_issued_qty ?? $this->issuances()->sum('issued_quantity');
    }

    public function getIssuanceStatusAttribute(): string
    {
        $requested = $this->total_requested_qty;
        $issued = $this->total_issued_qty;

        return match (true) {
            $issued <= 0 => 'pending',
            $issued >= $requested => 'fully issued',
            default => 'partially issued',
        };
    }

    /* ---------------- Scopes ---------------- */

    public function scopeWithStatus(Builder $query): Builder
    {
        return $query->withSum('items as total_requested_qty', 'quantity')
                     ->withSum('issuances as total_issued_qty', 'issued_quantity');
    }

    public function scopeWhereStatus(Builder $query, string $status): Builder
    {
        return $query->withStatus()->where(function ($q) use ($status) {
            switch ($status) {
                case 'pending':
                    $q->where(function ($subQuery) {
                        $subQuery->whereDoesntHave('issuances')
                                 ->orWhereRaw('(select sum(issued_quantity) from inv_issuances where inv_issuances.request_id = inv_requests.id and inv_issuances.deleted_at is null) <= 0');
                    });
                    break;
                case 'fully issued':
                    $q->whereHas('issuances')
                      ->whereRaw('(select sum(issued_quantity) from inv_issuances where inv_issuances.request_id = inv_requests.id and inv_issuances.deleted_at is null) >= (select sum(quantity) from inv_request_items where inv_request_items.request_id = inv_requests.id and inv_request_items.deleted_at is null)');
                    break;
                case 'partially issued':
                    $q->whereHas('issuances')
                      ->whereRaw('(select sum(issued_quantity) from inv_issuances where inv_issuances.request_id = inv_requests.id and inv_issuances.deleted_at is null) > 0')
                      ->whereRaw('(select sum(issued_quantity) from inv_issuances where inv_issuances.request_id = inv_requests.id and inv_issuances.deleted_at is null) < (select sum(quantity) from inv_request_items where inv_request_items.request_id = inv_requests.id and inv_request_items.deleted_at is null)');
                    break;
            }
        });
    }
}