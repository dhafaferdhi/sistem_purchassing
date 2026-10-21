<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
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

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Penerimaan Barang (GRN)';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Dokumen Penerimaan (Good Receipt Note)')
                            ->description('Catat kedatangan barang berdasarkan Purchase Order dan Surat Jalan vendor')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Forms\Components\TextInput::make('grn_number')
                                    ->label('Nomor GRN')
                                    ->placeholder('[Dibuat otomatis oleh sistem]')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('purchase_order_id')
                                    ->label('Pilih Purchase Order (PO)')
                                    ->options(function () {
                                        return PurchaseOrder::where('status', 'Approved')
                                            ->where('delivery_status', '!=', 'Fully Received')
                                            ->orWhere('id', request()->get('po_id'))
                                            ->get()
                                            ->mapWithKeys(function ($po) {
                                                return [$po->id => "{$po->po_number} — {$po->supplier?->name} ({$po->delivery_status})"];
                                            });
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $po = PurchaseOrder::with('items')->find($state);
                                        if ($po) {
                                            $set('supplier_id', $po->supplier_id);

                                            // Auto-populate line items dari PO
                                            $itemsData = [];
                                            foreach ($po->items as $item) {
                                                $rem = max(0, (float) ($item->quantity - $item->quantity_received));
                                                $itemsData[] = [
                                                    'purchase_order_item_id' => $item->id,
                                                    'part_id' => $item->part_id,
                                                    'item_code' => $item->item_code,
                                                    'description' => $item->description,
                                                    'quantity_ordered' => (float) $item->quantity,
                                                    'quantity_previously_received' => (float) $item->quantity_received,
                                                    'quantity_remaining' => $rem,
                                                    'quantity_received' => $rem, // Default datang sejumlah sisa
                                                    'quantity_rejected' => 0,
                                                    'unit' => $item->unit,
                                                    'notes' => null,
                                                ];
                                            }
                                            $set('items', $itemsData);
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\Select::make('supplier_id')
                                    ->label('Supplier / Vendor')
                                    ->relationship('supplier', 'name')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('delivery_order_number')
                                    ->label('No. Surat Jalan Vendor')
                                    ->placeholder('Contoh: SJ-2026-8891 / DO-MBS-092')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('received_date')
                                    ->label('Tanggal Terima Fisik')
                                    ->required()
                                    ->default(now())
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('received_by')
                                    ->default(fn () => Auth::id()),

                                Forms\Components\TextInput::make('driver_name')
                                    ->label('Nama Supir / Jasa Ekspedisi')
                                    ->placeholder('Contoh: Pak Joko (Lalamove / Truk Vendor)')
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('vehicle_number')
                                    ->label('No. Polisi Kendaraan')
                                    ->placeholder('Contoh: B 9281 KXY')
                                    ->columnSpan(1),

                                Forms\Components\Select::make('status')
                                    ->label('Status Pemeriksaan')
                                    ->options([
                                        'Verified' => 'Verified (Sesuai & Diterima Gudang)',
                                        'Draft' => 'Draft (Masih Proses Hitung Fisik)',
                                        'Returned' => 'Returned (Ditolak / Masalah Kualitas)',
                                    ])
                                    ->default('Verified')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Kondisi Kemasan / Catatan Eksternal')
                                    ->placeholder('Kemasan utuh, segel packing kayu tidak rusak, palet rapi.')
                                    ->columnSpanFull()
                                    ->rows(2),
                            ])->columns(3),

                        Forms\Components\Section::make('Rincian Item yang Diterima (Line Items Receiving)')
                            ->description('Input kuantitas aktual barang yang datang dan kuantitas rusak/reject QC')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship('items')
                                    ->schema([
                                        Forms\Components\Hidden::make('purchase_order_item_id'),
                                        Forms\Components\Hidden::make('part_id'),

                                        Forms\Components\TextInput::make('item_code')
                                            ->label('Kode Item')
                                            ->readOnly()
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('description')
                                            ->label('Nama Barang')
                                            ->readOnly()
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('quantity_ordered')
                                            ->label('Qty PO')
                                            ->numeric()
                                            ->readOnly()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('quantity_previously_received')
                                            ->label('Telah Terima')
                                            ->numeric()
                                            ->readOnly()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('quantity_remaining')
                                            ->label('Sisa PO')
                                            ->numeric()
                                            ->readOnly()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('quantity_received')
                                            ->label('Datang Kali Ini')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                $rem = (float) ($get('quantity_remaining') ?? 0);
                                                $recv = (float) $state;
                                                if ($recv > $rem) {
                                                    Notification::make()
                                                        ->title('Peringatan Over-Delivery')
                                                        ->body("Barang yang datang ({$recv}) melebihi sisa PO ({$rem}).")
                                                        ->warning()
                                                        ->send();
                                                }
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('quantity_rejected')
                                            ->label('Qty Reject QC')
                                            ->numeric()
                                            ->default(0)
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('unit')
                                            ->label('Satuan')
                                            ->readOnly()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('notes')
                                            ->label('Catatan QC Receiving')
                                            ->placeholder('Kondisi fisik barang oke.')
                                            ->columnSpan(11),
                                    ])
                                    ->columns(12)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->defaultItems(0),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('grn_number')
                    ->label('Nomor GRN')
                    ->weight('bold')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('Ref. PO')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier / Vendor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_order_number')
                    ->label('No. Surat Jalan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_date')
                    ->label('Tanggal Terima')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label('Penerima Gudang')
                    ->placeholder('Staff'),

                Tables\Columns\TextColumn::make('vehicle_number')
                    ->label('No. Polisi')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Verified' => 'success',
                        'Draft' => 'gray',
                        'Returned' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Verified' => 'Verified',
                        'Draft' => 'Draft',
                        'Returned' => 'Returned',
                    ]),
            ])
            ->actions([
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
            ->with(['purchaseOrder', 'supplier', 'receivedBy']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoodsReceipts::route('/'),
            'create' => Pages\CreateGoodsReceipt::route('/create'),
            'edit' => Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
