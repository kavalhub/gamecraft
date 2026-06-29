<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    protected $fillable = ['uuid', 'event_type', 'aggregate_type', 'aggregate_uuid', 'actor_uuid', 'occurred_at', 'payload', 'metadata', 'correlation_uuid', 'causation_uuid', 'version'];
    protected $casts = ['payload' => 'array', 'metadata' => 'array', 'occurred_at' => 'datetime', 'version' => 'integer'];
}
