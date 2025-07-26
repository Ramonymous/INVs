<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $request_id
 * @property int $child_part_id
 * @property int|float $quantity
 * @property bool $fulfilled
 *
 * @property-read InvRequest $request
 * @property-read MasterChildpart $part
 * @property-read \Illuminate\Database\Eloquent\Collection<int,InvIssuance> $issuances
 */
class InvRequestItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['request_id', 'child_part_id', 'quantity', 'fulfilled'];

    /** @var array<string,string> */
    protected $casts = ['fulfilled' => 'boolean', 'created_at' => 'timestamp', 'updated_at' => 'timestamp'];

    /* ---------------- Relations ---------------- */

    public function request(): BelongsTo
    {
        return $this->belongsTo(InvRequest::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(MasterChildpart::class, 'child_part_id');
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(InvIssuance::class, 'request_item_id');
    }
}