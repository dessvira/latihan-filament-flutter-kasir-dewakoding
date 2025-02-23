<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\PaymentMethod;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use function Laravel\Prompts\select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OrderResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderResource\RelationManagers;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-cart';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()
                ->schema([
                    Section::make('Info Utama')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                            Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Laki-laki',
                                'female' => 'Perempuan',
                            ])
                            ->required(),
                        ]),
                    ]),
                    Group::make()
                    ->schema([
                        Section::make('Info Tambahan')
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255)
                                ->default(null),
                                Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->maxLength(255)
                                ->default(null),
                                Forms\Components\DatePicker::make('birthday'),
                            ]),
                        ]),
                    Section::make('Produk Dipesan')
                    ->schema([
                        self::getItemsRepeater(),
                    ]),

                    Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                    Forms\Components\TextInput::make('total_price')
                        ->required()
                        ->readOnly()
                        ->numeric(),
                     Forms\Components\Textarea::make('note')
                        ->columnSpanFull(),
                        ])
                    ]),
                    Group::make()
                    ->schema([
                        Section::make('Pembayaran')
                            ->schema([
                                Forms\Components\Select::make('payment_method_id')
                                ->relationship('payment_method', 'name')
                                ->reactive()
                                ->afterStateUpdated(function ($state,Set $set, Get $get) {
                                    $paymentMethod = PaymentMethod::find($state);
                                    $set('is_cash',$paymentMethod->is_cash ?? false );
                                    
                                    if (!$paymentMethod->is_cash) {
                                        $set('change_amount', 0);
                                        $set('paid_amount', $get('total_price'));
                                    }
                                
                                })
                                ->afterStateHydrated(function (Set $set, Get $get, $state) {
                                    $paymentMethod = PaymentMethod::find($state);
                                    if (!$paymentMethod?->is_cash) {
                                        $set('paid_amount', $get('total_price'));
                                        $set('change_amount', 0);
                                    }
                                    
                                    $set('is_cash', $paymentMethod->is_cash ?? false);
                                }),
                            Hidden::make('is_cash')
                                ->dehydrated(),
                            Forms\Components\TextInput::make('paid_amount')
                                ->numeric()
                                ->reactive()
                                ->label('Nominal Bayar')
                                ->readOnly(fn (Get $get) => $get('is_cash') == false)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    //function untuk menghitung uang kembalian

                                    self::updateExcangePaid($get, $set);
                                }),
                            Forms\Components\TextInput::make('change_amount')
                                ->numeric()
                                ->label('Kembalian')
                                ->readOnly(),
                        
                            ])
                     ]),
                
              
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                //Tables\Columns\TextColumn::make('email')
                    //->searchable(),
                Tables\Columns\TextColumn::make('gender'),
                //Tables\Columns\TextColumn::make('phone')
                  //  ->searchable(),
                //Tables\Columns\TextColumn::make('birthday')
                    //->date()
                    //->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getItemsRepeater(): Repeater
    {
        return Repeater::make('orderProducts')
            ->relationship()
            ->live()
            ->columns([
                'md' => 10,
            ])
            ->afterStateUpdated(function(Get $get, Set $set){
                self::updateTotalPrice($get, $set);
            })
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->required()
                    ->live()
                    ->options(Product::query()->where('stock', '>', 1)->pluck('name', 'id'))
                    ->columnSpan([
                        'md' => 5,
                    ])
                    ->afterStateHydrated(function (Set $set, Get $get, $state){ //afterStateHydrated ini digunakan unk mengset petamakali kalo kita klik edit order
                        $product = Product::find($state);
                        $set('unit_price', $product->price ?? 0);
                        $set('stock', $product->stock ?? 0);
                    })
                    ->afterStateUpdated(function($state, Set $set, Get $get){
                        $product = Product::find($state);
                        $set('unit_price', $product->price ?? 0);
                        $set('stock', $product->stock ?? 0);
                        $quantity = $get('quantity') ?? 1;
                        $stock =$get('stock');
                        self::updateTotalPrice($get, $set);
                    })
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->columnSpan([
                        'md' => 1
                    ])
                    ->afterStateUpdated(function($state, Set $set, Get $get){
                        $stock = $get('stock');
                        if ($state > $stock){
                            $set('quantity', $stock);
                 Notification::make()
                    ->title('Stock tidak mencukupi')
                    ->warning()
                    ->send();
                        }

                    self::updateTotalPrice($get, $set);
                    }),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->columnSpan([
                        'md' => 1
                    ]),
                TextInput::make('unit_price')
                    ->label('Harga saat ini')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->columnSpan([
                        'md' => 3
                    ]),
            ]);
    }

    protected static function updateTotalPrice(Get $get, Set $set): void
    {
        $selectedProducts = collect($get('orderProducts'))->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

        $prices = Product::find($selectedProducts->pluck('product_id'))->pluck('price', 'id');
        $total = $selectedProducts->reduce(function ($total, $product) use ($prices) {
            return $total + ($prices[$product['product_id']] * $product['quantity']);
        }, 0);

        $set('total_price', $total);
    }
    
    protected static function updateExcangePaid(Get $get, Set $set): void
    {
    $paidAmount = (int) $get('paid_amount') ?? 0 ;
    $totalPrice = (int) $get('total_price') ?? 0 ;
    $exchangePaid = $paidAmount - $totalPrice;
    $set('change_amount', $exchangePaid);
    }
}