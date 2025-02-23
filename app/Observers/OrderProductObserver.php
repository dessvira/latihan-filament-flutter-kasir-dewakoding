<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\OrderProduct;

class OrderProductObserver
{
    /**
     * Handle the OrderProduct "created" event.
     */
    public function created(OrderProduct $orderProduct): void
    {
        $product = Product::find($orderProduct->product_id);
        $product->decrement('stock', $orderProduct->quantity); //mengurangi stok diorder prodak sejumlah kuantitas yg diorder
    }

    /**
     * Handle the OrderProduct "updated" event.
     */
    public function updated(OrderProduct $orderProduct): void
    {
        $product = Product::find($orderProduct->product_id);
        $originalQuantity = $orderProduct->getOriginal('quantity');
        $newQuantity = $orderProduct->quantity;
        
        if ($originalQuantity != $newQuantity) {
            $product->increment('stock', $originalQuantity);
            $product->decrement('stock', $newQuantity);
        }
    }

    /**
     * Handle the OrderProduct "deleted" event.
     */
    public function deleted(OrderProduct $orderProduct): void //enghapus barang dari orderan nanti stoknya naik
    {
        $product = Product::find($orderProduct->product_id);
        $product->increment('stock', $orderProduct->quantity);
    }

}