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

    protected $fillable = [ //ini diambil dari database
        'name',
        'email',
        'gender',
        'phone',
        'birthday',
        'total_price',
        'note',
        'payment_method_id',
        'paid_amount',
        'change_amount',
    ];
    public function payment_method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }
}