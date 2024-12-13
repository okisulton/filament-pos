<?php

namespace App\Livewire;

use App\Models\PaymentMethod;
use App\Models\Product;
use Livewire\Component;

class Pos extends Component
{
    public $search;

    public function render()
    {
        return view('livewire.pos', [
            'products' => Product::where('is_active', true)
                            ->where('stock', '>', 0)
                            ->search($this->search)
                            ->paginate(3),
            'paymentMethods' => PaymentMethod::all(),
        ]);
    }
}
