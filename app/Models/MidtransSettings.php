<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MidtransSettings extends Model
{
    protected $table = 'midtrans_settings';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'server_key',
        'client_key',
        'is_production',
    ];

    public function isProduction(): bool
    {
        return (bool) $this->attributes['is_production'];
    }
}
