<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function storages(): HasMany
    {
        return $this->hasMany(Storage::class, 'owner_id')
            ->where('owner_type', 'player');
    }

    public function inventory(): HasMany
    {
        return $this->storages()->where('type', 'inventory');
    }

    public function equipment(): HasMany
    {
        return $this->storages()->where('type', 'equipment');
    }

    public function banks(): HasMany
    {
        return $this->storages()->where('type', 'bank');
    }

    public function resourceBalances(): HasMany
    {
        return $this->hasMany(ResourceBalance::class);
    }

    public function getResourceBalance(string $templateSlug): int
    {
        $template = ItemTemplate::where('slug', $templateSlug)->first();
        if (!$template) {
            return 0;
        }

        $balance = $this->resourceBalances()
            ->where('template_id', $template->id)
            ->first();

        return $balance ? $balance->quantity : 0;
    }

    public function getGold(): int
    {
        return $this->getResourceBalance('gold');
    }

    public function getMainInventory(): ?Storage
    {
        return $this->storages()
            ->where('type', 'inventory')
            ->first();
    }

    public function getEquipmentStorage(): ?Storage
    {
        return $this->storages()
            ->where('type', 'equipment')
            ->first();
    }
}
