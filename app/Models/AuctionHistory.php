<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionHistory extends Model
{
    protected $table = 'auction_history';

    protected $fillable = ['lot_uuid', 'seller_uuid', 'buyer_uuid', 'template_slug', 'quantity', 'price', 'commission', 'seller_received', 'action', 'occurred_at'];
    protected $casts = ['quantity' => 'integer', 'price' => 'integer', 'commission' => 'integer', 'seller_received' => 'integer', 'occurred_at' => 'datetime'];

    public function lot(): BelongsTo { return $this->belongsTo(AuctionLot::class, 'lot_uuid', 'uuid'); }
    public function seller(): BelongsTo { return $this->belongsTo(Character::class, 'seller_uuid', 'uuid'); }
    public function buyer(): BelongsTo { return $this->belongsTo(Character::class, 'buyer_uuid', 'uuid'); }
    public function template(): BelongsTo { return $this->belongsTo(ItemTemplate::class, 'template_slug', 'slug'); }
}
