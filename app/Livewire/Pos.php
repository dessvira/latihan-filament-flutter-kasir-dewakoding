<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\PaymentMethod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;

use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;

class Pos extends Component implements HasForms
{
    use InteractsWithForms;
    public $search ='';
    public $name_customer = '';
    public $payment_methods;
    public $order_items =[]; // order itemssebuah variable yang akan menampung barang yang diorder
    public $total_price;
    
    public function render()
    {
        return view('livewire.pos', [
            'products' => Product::where('stock', '>', 0)
                          ->search($this->search)
                          ->paginate(12)
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Form Checkout')
                   ->schema( [
                TextInput::make('name_custemer')
                    ->required()
                    ->maxLength(255)
                    ->default(fn () => $this->name_customer),
                Select::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Famele',
                    ]),
                TextInput::make('total_price')
                    ->readOnly()
                    ->numeric()
                    ->default(fn () => $this->total_price),
                Select::make('payment_method_id')
                   ->required()
                   ->label('Payment Method')
                   ->options($this ->payment_methods->pluck('name', 'id'))
                   ])
            ]);
    }

    public function mount()
    {
        if (session()->has('orderItems')) {
            $this->order_items = session('orderItems');
        }
        $this->payment_methods = PaymentMethod::all();
        $this->form->fill(['payment_methods', $this->payment_methods]);
    }
    
    public function addToOrder($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            if ($product->stock <= 0) {
                Notification::make()
                ->title ('Stok habis')
                ->danger()
                ->send();
                return;
        }
        
        $existingItemKey = null;
        foreach ($this->order_items as $key => $item) { //ketika user mngklik menu 2x akan menambahkan di kuantitas
            if ($item['product_id'] == $productId) {    
                $existingItemKey = $key;
                break;
            }
    }

    if ($existingItemKey!== null) {
        $this->order_items[$existingItemKey]['quantity']++;
    } else {
        $this->order_items[] = [
            'product_id' => $productId,
            'name' => $product->name,
            'price' => $product->price,
            'image_url' => $product->image_url,
            'quantity' => 1,
        ];
    }
    
    session()->put('orderItems', $this->order_items);
    Notification::make()
        ->title ('Produk ditambahkan ke keranjang')
        ->success()
        ->send();
}
}

    public function loadOrderItems($orderItems)
    {
        $this->order_items = $orderItems;
        session()->put('orderItems', $orderItems);
    }

    public function increaseQuantity($product_id)
    {
        $product = Product::find($product_id);

        if (!$product) {
            Notification::make()
            ->title ('Produk tidak ditemukan')
            ->danger()
            ->send();
            return;    
    }

    foreach ($this->order_items as $key => $item) {
        if ($item['product_id'] == $product_id) {
            if ($item['quantity'] + 1 <= $product->stock) {
            $this->order_items[$key]['quantity']++;
         } else {
            Notification::make()
                ->title ('Stok barang tidak mencukupi')
                ->danger()
                ->send();
         }
         break;
         
        }

    }

    session()->put('orderItems', $this->order_items);
    
}

    public function decreaseQuantity($product_id)
    {
        foreach ($this->order_items as $key => $item) {
            if ($item['product_id'] == $product_id) {
                if ($this->order_items[$key]['quantity'] > 1) {
                    $this->order_items[$key]['quantity']--;
                } else {
                    unset($this->order_items[$key]);
                    $this->order_items = array_values($this->order_items);
                }
                break;
            }
    
    }

    session()->put('orderItems', $this->order_items);

}

    public function calculateTotal()
    {
        $total = 0;
        foreach ($this->order_items as $item) {
            $total += $item['quantity'] * $item['price'];
        }
        $this->total_price = $total;
        return $total;
    }
    
}