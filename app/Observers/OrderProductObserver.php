<?php

namespace App\Observers;

use App\Models\OrderProduct;
use App\Models\Product;

class OrderProductObserver
{
    public function created(OrderProduct $orderProduct): void
    {
        $product = Product::find($orderProduct->product_id);
        $product->decrement('stock', $orderProduct->quantity);
    }

    
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

    public function deleted(OrderProduct $orderProduct): void
    {
        $product = Product::find($orderProduct->product_id);
        $product->increment('stock', $orderProduct->quantity);
    }

    /**
     * Handle the OrderProduct "restored" event.
     */
    public function restored(OrderProduct $orderProduct): void
    {
        //
    }

    /**
     * Handle the OrderProduct "force deleted" event.
     */
    public function forceDeleted(OrderProduct $orderProduct): void
    {
        //
    }
}
