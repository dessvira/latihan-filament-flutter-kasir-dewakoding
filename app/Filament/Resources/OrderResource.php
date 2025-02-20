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
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use function Laravel\Prompts\select;
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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                            Forms\Components\TextInput::make('gender')
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
                Forms\Components\TextInput::make('total_price')
                    ->required()
                    ->readOnly()
                    ->numeric(),
                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),
                Forms\Components\Select::make('payment_method_id')
                    ->relationship('payment_method', 'name')
                    ->default(null),
                Forms\Components\TextInput::make('paid_amount')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('changed_amount')
                    ->numeric()
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('birthday')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_amount')
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
                    ->options(Product::query()->where('stock', '=', 1)->pluck('name', 'id'))
                    ->columnSpan([
                        'md' => 5,
                    ])
                    ->afterStateUpdated(function($state, Set $set, Get $get){
                        $product = Product::find($state);
                        $set('unit_price', $product->price ?? 0);
                        $set('stock', $product->price ?? 0);
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
                    ->columnSpan([
                        'md' => 1
                    ]),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->columnSpan([
                        'md' => 1
                    ]),
            ]);
    }

    protected static function updateTotalPrice(Get $get, Set $set): void
    {
        $selectedProducts = collect($get('orderProducts'))->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

        $price = Product::find($selectedProducts->pluck('product_id'))->pluck('price', 'id');
        $total = $selectedProducts->reduce(function ($total, $product) use ($price) {
            return $total + ($price[$product['product_id']] * $product['quantity']);
        }, 0);

        $set('total_price', $total);
    }
}
