<?php

namespace App\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';
    //
    use HasFactory;

    public function order(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    protected $fillable = ['name', 'image', 'is_cash'];
}
