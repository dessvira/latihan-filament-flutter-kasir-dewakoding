<?php

namespace App\Models;

use App\Models\OrderProduct;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    //
    use HasFactory;
    public function payment_method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orderProduct(): HasMany
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }
}
