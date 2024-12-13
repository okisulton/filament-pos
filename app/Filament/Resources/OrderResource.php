<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-c-shopping-cart';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Main Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                    Forms\Components\Select::make('gender')
                                        ->options([
                                            'male' => 'Laki-laki',
                                            'female' => 'Perempuan'
                                        ])
                                        ->required(),
                            ]),
                ]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Secondary Information')
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->maxLength(255),
                            Forms\Components\DatePicker::make('date_of_birth'),
                        ]),
                ]),
                
                Forms\Components\Section::make('Poduct Dipesan')->schema([
                    self::getItemsRepeater(),                
                ]),
                
                Group::make()->schema([
                    Section::make()->schema([
                        Forms\Components\TextInput::make('total_price')
                            ->required()
                            ->readOnly()
                            ->numeric(),
                        Forms\Components\Textarea::make('note')
                            ->columnSpanFull(),
                    ])
                ]),

                Group::make()->schema([
                    Section::make("Pembayaran")->schema([
                        
                        Forms\Components\Select::make('payment_method_id')
                            ->relationship('paymentMethod', 'name')
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $paymentMethod = PaymentMethod::find($state);
                                $set('is_cash', $paymentMethod?->is_cash ?? false);

                                if (!$paymentMethod->is_cash) {
                                    $set('change_amount', 0);
                                    $set('paid_amount', $get('total_price'));
                                }
                            })
                            ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get, $state) {
                                $paymentMethod = PaymentMethod::find($state);

                                if (!$paymentMethod?->is_cash) {
                                    $set('paid_amount', $get('total_price'));
                                    $set('change_amount', 0);
                                }

                                $set('is_cash', $paymentMethod?->is_cash ?? false);
                            }),
                        Forms\Components\Hidden::make('is_cash')
                            ->dehydrated(),
                        Forms\Components\TextInput::make('paid_amount')
                            ->numeric()
                            ->reactive()
                            ->required()
                            ->label('Nominal Bayar')
                            ->readOnly(fn (Forms\Get $get) => $get('is_cash') == false)
                            ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // function untuk menghitung uang kembalian

                                self::updateExcangePaid($get, $set);
                            })
                            ->live(debounce: 1000),
                        Forms\Components\TextInput::make('change_amount')
                            ->numeric()
                            ->required()
                            ->readOnly()
                            ->label('Uang Kembalian'),
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
                Tables\Columns\TextColumn::make('gender'),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_amount')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                self::updateTotalPrice($get, $set, "step 1");
            })
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Produk')
                    ->required()
                    ->options(Product::query()->where('stock', '>', 0)->pluck('name', 'id'))
                    ->columnSpan([
                        'md' => 5
                    ])
                    ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get, $state) {
                        $product = Product::find($state);
                        $set('unit_price', $product->price ?? 0);
                        $set('stock', $product->stock ?? 0);
                    })
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $product = Product::find($state);
                        $set('unit_price', $product->price ?? 0);
                        $set('stock', $product->stock ?? 0);
                        $quantity = $get('quantity') ?? 1;
                        $stock = $get('stock');
                        self::updateTotalPrice($get, $set, "step 3");
                    })
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->columnSpan([
                        'md' => 1
                    ])
                    ->live(debounce:500)
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        $stock = $get('stock');
                        
                        // Jika quantity melebihi stok, set quantity ke stok dan tampilkan notifikasi
                        if ($state > $stock) {
                            $set('quantity', $stock);
                            Notification::make()
                                ->title('Stok tidak mencukupi')
                                ->warning()
                                ->send();
                        }
                
                        // Mengambil kembali nilai quantity setelah pembaruan (untuk memastikan valid)
                        $updatedQuantity = $get('quantity');
                
                        // Panggil updateTotalPrice hanya jika quantity valid (tidak melebihi stok)
                        if ($updatedQuantity <= $stock) {
                            self::updateTotalPrice($get, $set, "step 2");
                        }
                }),                
                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->columnSpan([
                        'md' => 1
                    ]),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Harga saat ini')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->columnSpan([
                        'md' => 3
                    ]),
                
            ]);
    }

    protected static function updateTotalPrice(Get $get, Set $set, $state): void
    {
        $selectedProducts = collect($get('orderProducts'))->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));
        Log::info($state);
        $prices = Product::find($selectedProducts->pluck('product_id'))->pluck('price', 'id');
        $total = $selectedProducts->reduce(function ($total, $product) use ($prices) {
            return $total + ($prices[$product['product_id']] * $product['quantity']);
        }, 0);
        Log::info("total: ".$total);
        $set('total_price', $total);
    }

    protected static function updateExcangePaid(Forms\Get $get, Forms\Set $set): void
    {
        $paidAmount = (int) $get('paid_amount') ?? 0;
        $totalPrice = (int) $get('total_price') ?? 0;
        $exchangePaid = $paidAmount - $totalPrice;
        $set('change_amount', $exchangePaid);
    }
}
