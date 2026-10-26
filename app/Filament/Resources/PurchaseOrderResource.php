<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Currency;
use App\Models\Part;
use App\Models\PartPriceHistory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\VendorItem;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Purchase Order (PO)';

    protected static ?int $navigationSort = 3;

    public static function updatePoTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price_idr'] ?? 0);
            $subtotal += round($qty * $unitPrice, 2);
        }

        $taxRate = (float) ($get('tax_percentage') ?? 11.0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $grandTotal = round($subtotal + $taxAmount, 2);

        $set('subtotal_idr', round($subtotal, 2));
        $set('tax_amount_idr', $taxAmount);
        $set('total_amount_idr', $grandTotal);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Dokumen Purchase Order')
                            ->description('Detail nomor PO, supplier, tanggal order, dan termin pembayaran')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\TextInput::make('po_number')
                                    ->label('Nomor PO')
                                    ->placeholder('[Dibuat otomatis oleh sistem]')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('user_id')
                                    ->default(fn () => Auth::id()),

                                Forms\Components\Select::make('supplier_id')
                                    ->label('Vendor / Supplier')
                                    ->relationship('supplier', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->columnSpan(2),

                                Forms\Components\DatePicker::make('order_date')
                                    ->label('Tanggal Order')
                                    ->required()
                                    ->default(now())
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('delivery_date')
                                    ->label('Estimasi Pengiriman (Delivery Date)')
                                    ->default(now()->addDays(14))
                                    ->columnSpan(1),

                                Forms\Components\Select::make('payment_terms')
                                    ->label('Termin Pembayaran')
                                    ->options([
                                        'Net 30 Days' => 'Net 30 Hari',
                                        'Net 60 Days' => 'Net 60 Hari',
                                        'Cash on Delivery' => 'Cash on Delivery (COD)',
                                        'Advance 50%' => 'DP 50% / Pelunasan COD',
                                    ])
                                    ->default('Net 30 Days')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('currency_id')
                                    ->label('Mata Uang (Currency)')
                                    ->relationship('currency', 'code')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $curr = Currency::find($state);
                                        if ($curr) {
                                            $set('exchange_rate', $curr->exchange_rate_to_idr);
                                        }
                                    })
                                    ->default(fn () => Currency::where('code', 'IDR')->first()?->id)
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('Kurs ke IDR')
                                    ->required()
                                    ->numeric()
                                    ->default(1.0000)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('quotation_id')
                                    ->label('Ref. Dokumen Quotation (Opsional)')
                                    ->relationship('quotation', 'document_number')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->columnSpan(1),
                            ])->columns(3),

                        Forms\Components\Section::make('Daftar Item Barang (Line Items)')
                            ->description('Pilih item dari Master Part / masukkan item vendor')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship('items')
                                    ->schema([
                                        Forms\Components\Select::make('part_id')
                                            ->label('Pilih dari Master Part')
                                            ->relationship('part', 'part_number')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if (! $state) {
                                                    return;
                                                }
                                                $part = Part::find($state);
                                                if ($part) {
                                                    $set('item_code', $part->item_code ?? $part->part_number);
                                                    $set('description', $part->description ?? $part->part_name);

                                                    // Cek jika supplier memiliki mapping VendorItem
                                                    $supplierId = $get('../../supplier_id');
                                                    if ($supplierId) {
                                                        $vi = VendorItem::where('supplier_id', $supplierId)
                                                            ->where('part_id', $part->id)
                                                            ->first();
                                                        if ($vi) {
                                                            $set('vendor_item_code', $vi->vendor_item_code);
                                                            $set('moq', $vi->moq);
                                                            $set('lead_time_days', $vi->lead_time_days);

                                                            if ($vi->unit_price > 0) {
                                                                $set('unit_price_idr', $vi->unit_price);
                                                            }

                                                            // Default quantity ke batas MOQ jika tersedia
                                                            if ($vi->moq > 0) {
                                                                $set('quantity', $vi->moq);
                                                                $price = (float) ($vi->unit_price ?: ($get('unit_price_idr') ?? 0));
                                                                $set('total_price_idr', round($vi->moq * $price, 2));
                                                            }

                                                            // Hitung saran tanggal delivery jika lead time tersedia
                                                            if ($vi->lead_time_days > 0) {
                                                                $orderDate = $get('../../order_date') ? Carbon::parse($get('../../order_date')) : now();
                                                                $set('../../delivery_date', $orderDate->addDays($vi->lead_time_days)->format('Y-m-d'));
                                                            }
                                                        }
                                                    }
                                                }
                                            })
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('item_code')
                                            ->label('Kode Item Internal')
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('vendor_item_code')
                                            ->label('Kode Item Vendor / Supplier')
                                            ->placeholder('Contoh: MBS-BKT-889')
                                            ->columnSpan(3),

                                        Forms\Components\TextInput::make('description')
                                            ->label('Nama & Deskripsi Barang')
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\Hidden::make('moq'),
                                        Forms\Components\Hidden::make('lead_time_days'),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Qty Order')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->helperText(function (Get $get) {
                                                $moq = (int) ($get('moq') ?? 0);
                                                $leadTime = (int) ($get('lead_time_days') ?? 0);
                                                $hints = [];
                                                if ($moq > 0) {
                                                    $hints[] = 'MOQ: '.number_format($moq, 0, ',', '.').' pcs';
                                                }
                                                if ($leadTime > 0) {
                                                    $hints[] = "Lead Time: {$leadTime} Hari";
                                                }

                                                return ! empty($hints) ? implode(' • ', $hints) : null;
                                            })
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                $qty = (float) $state;
                                                $price = (float) ($get('unit_price_idr') ?? 0);
                                                $moq = (int) ($get('moq') ?? 0);

                                                if ($moq > 0 && $qty < $moq) {
                                                    Notification::make()
                                                        ->title('Peringatan: Di Bawah MOQ Supplier!')
                                                        ->body("Pesanan ({$qty} pcs) lebih kecil dari batas minimum ({$moq} pcs). Supplier berpotensi mengenakan surcharge atau menolak PO.")
                                                        ->warning()
                                                        ->persistent()
                                                        ->send();
                                                }

                                                $set('total_price_idr', round($qty * $price, 2));
                                                static::updatePoTotals($get, $set);
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('unit')
                                            ->label('Satuan')
                                            ->default('pcs')
                                            ->required()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('unit_price_idr')
                                            ->label('Harga Satuan (IDR)')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                $price = (float) $state;
                                                $qty = (float) ($get('quantity') ?? 1);
                                                $set('total_price_idr', round($qty * $price, 2));
                                                static::updatePoTotals($get, $set);
                                            })
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('total_price_idr')
                                            ->label('Total Harga (IDR)')
                                            ->prefix('Rp')
                                            ->readOnly()
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12)
                                    ->defaultItems(1)
                                    ->addActionLabel('+ Tambah Item')
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePoTotals($get, $set)),
                            ]),
                    ])->columnSpan(2),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Ringkasan Biaya & Pajak')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('subtotal_idr')
                                    ->label('Subtotal (IDR)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->default(0.00),

                                Forms\Components\TextInput::make('tax_percentage')
                                    ->label('Tarif PPN (%)')
                                    ->suffix('%')
                                    ->numeric()
                                    ->default(11.00)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updatePoTotals($get, $set)),

                                Forms\Components\TextInput::make('tax_amount_idr')
                                    ->label('Nominal PPN (11%)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->default(0.00),

                                Forms\Components\TextInput::make('total_amount_idr')
                                    ->label('GRAND TOTAL (Inc. PPN)')
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->extraInputAttributes([
                                        'class' => 'font-extrabold text-lg text-emerald-600 bg-emerald-50 dark:bg-emerald-950 p-2 rounded',
                                    ]),

                                Forms\Components\Select::make('status')
                                    ->label('Status Purchase Order')
                                    ->options([
                                        'Draft' => 'Draft',
                                        'Approved' => 'Approved (Disetujui)',
                                        'Sent to Vendor' => 'Sent to Vendor (Dikirim ke Supplier)',
                                        'Completed' => 'Completed (Selesai Diterima)',
                                        'Cancelled' => 'Cancelled (Dibatalkan)',
                                    ])
                                    ->default('Draft')
                                    ->required(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Instruksi Pengiriman / Catatan Khusus')
                                    ->placeholder('Contoh: Kirim ke Gudang 2 Pintu Barat, sertakan Mill Certificate.')
                                    ->rows(4),
                            ]),
                    ])->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->weight('bold')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier / Vendor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Tanggal Order')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Pengiriman')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency.code')
                    ->label('Valuta')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subtotal_idr')
                    ->label('Subtotal')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_amount_idr')
                    ->label('PPN 11%')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_amount_idr')
                    ->label('Total PO')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Approved' => 'info',
                        'Sent to Vendor' => 'warning',
                        'Completed' => 'success',
                        'Cancelled' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_status')
                    ->label('Status Kirim')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Fully Received' => 'success',
                        'Partially Received' => 'warning',
                        'Over Received' => 'purple',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_progress')
                    ->label('Progres Kedatangan')
                    ->state(fn (PurchaseOrder $record) => number_format($record->total_received_quantity, 0, ',', '.').' / '.number_format($record->total_ordered_quantity, 0, ',', '.').' pcs ('.$record->delivery_percentage.'%)')
                    ->badge()
                    ->color(fn (PurchaseOrder $record) => match (true) {
                        $record->delivery_percentage >= 100 => 'success',
                        $record->delivery_percentage > 0 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status PO')
                    ->options([
                        'Draft' => 'Draft',
                        'Approved' => 'Approved',
                        'Sent to Vendor' => 'Sent to Vendor',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('delivery_status')
                    ->label('Status Pengiriman')
                    ->options([
                        'Unfulfilled' => 'Belum Kirim (Unfulfilled)',
                        'Partially Received' => 'Parsial (Partially Received)',
                        'Fully Received' => 'Lengkap (Fully Received)',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('receive_goods')
                    ->label('Terima Barang')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === 'Approved' && $record->delivery_status !== 'Fully Received')
                    ->url(fn (PurchaseOrder $record): string => GoodsReceiptResource::getUrl('create', ['po_id' => $record->id])),

                Tables\Actions\Action::make('print_pdf')
                    ->label('Cetak PO')
                    ->icon('heroicon-o-printer')
                    ->color('danger')
                    ->url(fn (PurchaseOrder $record): string => route('purchase-orders.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === 'Draft' && (Auth::user()?->isAdmin() || Auth::user()?->isApprover()))
                    ->requiresConfirmation()
                    ->modalHeading('Persetujuan Purchase Order')
                    ->modalDescription('Apakah Anda yakin ingin menyetujui Purchase Order ini? Harga part akan dicatat ke Histori Harga.')
                    ->action(function (PurchaseOrder $record): void {
                        $record->update([
                            'status' => 'Approved',
                            'approved_by' => Auth::id(),
                        ]);

                        // Catat ke PartPriceHistory
                        foreach ($record->items as $item) {
                            if ($item->part_id) {
                                PartPriceHistory::logPrice(
                                    partId: $item->part_id,
                                    supplierId: $record->supplier_id,
                                    unitPriceIdr: (float) $item->unit_price_idr,
                                    sourceType: 'Purchase Order',
                                    referenceNo: $record->po_number,
                                    notes: "Diterbitkan dari PO {$record->po_number} - Supplier: {$record->supplier?->name}",
                                    date: $record->order_date?->format('Y-m-d'),
                                    currencyCode: $record->currency?->code ?? 'IDR',
                                    userId: Auth::id()
                                );
                            }
                        }

                        Notification::make()
                            ->title("PO {$record->po_number} Berhasil Disetujui")
                            ->body('Status berubah menjadi Approved dan harga item dicatat ke riwayat tren harga.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'currency', 'quotation']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
