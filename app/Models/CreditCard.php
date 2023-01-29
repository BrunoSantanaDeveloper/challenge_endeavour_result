<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'type',
        'number',
        'name',
        'expirationDate'
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
