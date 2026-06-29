<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageType extends Model
{
    protected $table = 'storages_type';
    protected $fillable = ['type', 'name', 'allowed_types', 'metadata'];
    protected $casts = ['allowed_types' => 'array', 'metadata' => 'array'];
}
