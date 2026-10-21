<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PudMonitoringResource\Pages;
use App\Models\PudRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PudMonitoringResource extends Resource
{
    protected static ?string $model = PudRequest::class;

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'PUD Monitoring';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'PUD Monitoring';

    protected static ?string $pluralModelLabel = 'PUD Monitoring';

    protected static ?string $slug = 'pud-monitoring';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Permintaan Pemohon')
                    ->description('Detail barang dan spesifikasi yang diminta oleh pemohon.')
                    ->schema([
                        Forms\Components\TextInput::make('request_number')
                            ->label('Request ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('user_name')
                            ->label('Nama Pemohon')
                            ->disabled(),

                        Forms\Components\TextInput::make('plant')
                            ->label('Plant')
                            ->disabled(),

                        Forms\Components\TextInput::make('division')
                            ->label('Division')
                            ->disabled(),

                        Forms\Components\TextInput::make('part_name')
                            ->label('Nama Barang')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('brand')
                            ->label('Brand')
                            ->disabled(),

                        Forms\Components\TextInput::make('type')
                            ->label('Type')
                            ->disabled(),

                        Forms\Components\TextInput::make('qty_display')
                            ->label('Quantity')
                            ->formatStateUsing(fn ($state, ?PudRequest $record) => $record ? number_format((float) $record->qty, 0, ',', '.').' '.$record->unit : '')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('purpose')
                            ->label('Use / Purpose')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('spec')
                            ->label('Specification')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\FileUpload::make('photo')
                            ->label('Photo Barang')
                            ->disk('public')
                            ->visibility('public')
                            ->image()
                            ->directory('pud-requests')
                            ->imagePreviewHeight('200')
                            ->disabled()
                            ->dehydrated(false)
                            ->openable()
                            ->downloadable()
                            ->columnSpan(4)
                            ->visible(fn (?PudRequest $record) => ! empty($record?->photo)),
                    ])
                    ->columns(4)
                    ->collapsible(),

                Forms\Components\Section::make('Supplier & Quotation — Internal PUD')
                    ->description('Bandingkan penawaran dari hingga 3 vendor/supplier dan upload file quotation.')
                    ->schema([
                        // SUPPLIER 1
                        Forms\Components\Fieldset::make('Supplier 1')
                            ->schema([
                                Forms\Components\TextInput::make('supplier_1_name')
                                    ->label('Nama Supplier 1')
                                    ->placeholder('Nama vendor/supplier'),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('supplier_1_currency_1')
                                        ->label('Mata Uang')
                                        ->options(array_combine(PudRequest::CURRENCIES, PudRequest::CURRENCIES))
                                        ->default('Rp'),
                                    Forms\Components\TextInput::make('supplier_1_price_1')
                                        ->label('Price 01')
                                        ->numeric()
                                        ->prefix('Valas'),
                                ]),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('supplier_1_currency_2')
                                        ->label('Mata Uang')
                                        ->options(array_combine(PudRequest::CURRENCIES, PudRequest::CURRENCIES))
                                        ->default('Rp'),
                                    Forms\Components\TextInput::make('supplier_1_price_2')
                                        ->label('Price 02 (Final)')
                                        ->numeric()
                                        ->prefix('Valas'),
                                ]),

                                Forms\Components\FileUpload::make('supplier_1_quotation_file')
                                    ->label('Quotation File')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('quotations')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->openable()
                                    ->downloadable(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('select_sup_1')
                                        ->label('✓ Pilih Supplier 1')
                                        ->color('success')
                                        ->action(function (Set $set, Forms\Get $get) {
                                            $name = $get('supplier_1_name');
                                            if ($name) {
                                                $set('selected_supplier', $name);
                                                Notification::make()->title("Supplier 1 dipilih: {$name}")->success()->send();
                                            } else {
                                                Notification::make()->title('Nama Supplier 1 belum diisi')->warning()->send();
                                            }
                                        }),
                                ]),
                            ])
                            ->columnSpan(1),

                        // SUPPLIER 2
                        Forms\Components\Fieldset::make('Supplier 2')
                            ->schema([
                                Forms\Components\TextInput::make('supplier_2_name')
                                    ->label('Nama Supplier 2')
                                    ->placeholder('Nama vendor/supplier'),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('supplier_2_currency_1')
                                        ->label('Mata Uang')
                                        ->options(array_combine(PudRequest::CURRENCIES, PudRequest::CURRENCIES))
                                        ->default('Rp'),
                                    Forms\Components\TextInput::make('supplier_2_price_1')
                                        ->label('Price 01')
                                        ->numeric()
                                        ->prefix('Valas'),
                                ]),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('supplier_2_currency_2')
                                        ->label('Mata Uang')
                                        ->options(array_combine(PudRequest::CURRENCIES, PudRequest::CURRENCIES))
                                        ->default('Rp'),
                                    Forms\Components\TextInput::make('supplier_2_price_2')
                                        ->label('Price 02 (Final)')
                                        ->numeric()
                                        ->prefix('Valas'),
                                ]),

                                Forms\Components\FileUpload::make('supplier_2_quotation_file')
                                    ->label('Quotation File')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('quotations')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->openable()
                                    ->downloadable(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('select_sup_2')
                                        ->label('✓ Pilih Supplier 2')
                                        ->color('success')
                                        ->action(function (Set $set, Forms\Get $get) {
                                            $name = $get('supplier_2_name');
                                            if ($name) {
                                                $set('selected_supplier', $name);
                                                Notification::make()->title("Supplier 2 dipilih: {$name}")->success()->send();
                                            } else {
                                                Notification::make()->title('Nama Supplier 2 belum diisi')->warning()->send();
                                            }
                                        }),
                                ]),
                            ])
                            ->columnSpan(1),

                        // SUPPLIER 3
                        Forms\Components\Fieldset::make('Supplier 3')
                            ->schema([
                                Forms\Components\TextInput::make('supplier_3_name')
                                    ->label('Nama Supplier 3')
                                    ->placeholder('Nama vendor/supplier'),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('supplier_3_currency_1')
                                        ->label('Mata Uang')
                                        ->options(array_combine(PudRequest::CURRENCIES, PudRequest::CURRENCIES))
                                        ->default('Rp'),
                                    Forms\Components\TextInput::make('supplier_3_price_1')
                                        ->label('Price 01')
                                        ->numeric()
                                        ->prefix('Valas'),
                                ]),

                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('supplier_3_currency_2')
                                        ->label('Mata Uang')
                                        ->options(array_combine(PudRequest::CURRENCIES, PudRequest::CURRENCIES))
                                        ->default('Rp'),
                                    Forms\Components\TextInput::make('supplier_3_price_2')
                                        ->label('Price 02 (Final)')
                                        ->numeric()
                                        ->prefix('Valas'),
                                ]),

                                Forms\Components\FileUpload::make('supplier_3_quotation_file')
                                    ->label('Quotation File')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory('quotations')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->openable()
                                    ->downloadable(),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('select_sup_3')
                                        ->label('✓ Pilih Supplier 3')
                                        ->color('success')
                                        ->action(function (Set $set, Forms\Get $get) {
                                            $name = $get('supplier_3_name');
                                            if ($name) {
                                                $set('selected_supplier', $name);
                                                Notification::make()->title("Supplier 3 dipilih: {$name}")->success()->send();
                                            } else {
                                                Notification::make()->title('Nama Supplier 3 belum diisi')->warning()->send();
                                            }
                                        }),
                                ]),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('PUD Processing & Keputusan')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Request')
                            ->options([
                                PudRequest::STATUS_DELIVERED => 'Delivered to PUD',
                                PudRequest::STATUS_REQUEST_QUOTATION => 'Request Quotation',
                                PudRequest::STATUS_REVIEW_NEGOTIATION => 'Review & Negotiation',
                                PudRequest::STATUS_ITEM_CODE_CREATED => 'Item Code Created',
                                PudRequest::STATUS_CLOSED => 'Closed',
                            ])
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('selected_supplier')
                            ->label('Selected Supplier')
                            ->placeholder('Supplier yang disepakati')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('item_code')
                            ->label('Item Code')
                            ->placeholder('Contoh: PRT-CYL-SMC-04025')
                            ->required(fn (Forms\Get $get) => $get('status') === PudRequest::STATUS_CLOSED)
                            ->helperText('Wajib diisi sebelum status diubah menjadi Closed.')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('feedback')
                            ->label('Feedback untuk Pemohon')
                            ->placeholder('Catatan atau informasi hasil negosiasi/pengadaan untuk user')
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal PUD Notes')
                            ->placeholder('Catatan rahasia/internal tim PUD (tidak tampil ke user biasa)')
                            ->columnSpan(3),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('is_deleted', false)
                    ->orderByRaw("
                        CASE 
                            WHEN status = 'Closed' THEN 5
                            WHEN status = 'Cancelled by User' THEN 6
                            ELSE 1
                        END ASC
                    ")
                    ->latest('id');
            })
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('ID')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_date')
                    ->label('Date Request')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_name')
                    ->label('User Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('division')
                    ->label('Division')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('plant')
                    ->label('Plant')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SUNTER' => 'info',
                        'KIIC' => 'primary',
                        'SC1' => 'warning',
                        'SC2' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\ImageColumn::make('photo')
                    ->label('Photo')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->defaultImageUrl('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="%23cbd5e1" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>'),

                Tables\Columns\TextColumn::make('part_name')
                    ->label('Part')
                    ->searchable()
                    ->description(fn (PudRequest $record): string => trim(($record->brand ? "({$record->brand}) " : '').($record->type ?: ''))),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state, PudRequest $record) => number_format((float) $state, 0, ',', '.').' '.$record->unit)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (PudRequest $record): string => $record->status_badge_color),

                Tables\Columns\TextColumn::make('sla_progress')
                    ->label('Progress')
                    ->badge()
                    ->color(fn (PudRequest $record): string => $record->sla_badge_color),

                Tables\Columns\TextColumn::make('selected_supplier')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('item_code')
                    ->label('Item Code')
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        PudRequest::STATUS_DELIVERED => 'Delivered to PUD',
                        PudRequest::STATUS_REQUEST_QUOTATION => 'Request Quotation',
                        PudRequest::STATUS_REVIEW_NEGOTIATION => 'Review & Negotiation',
                        PudRequest::STATUS_ITEM_CODE_CREATED => 'Item Code Created',
                        PudRequest::STATUS_CLOSED => 'Closed',
                    ]),

                Tables\Filters\SelectFilter::make('plant')
                    ->label('Filter Plant')
                    ->options(array_combine(PudRequest::PLANTS, PudRequest::PLANTS)),

                Tables\Filters\SelectFilter::make('division')
                    ->label('Filter Division')
                    ->options(function () {
                        return PudRequest::distinct()->orderBy('division')->pluck('division', 'division')->toArray();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_monitoring_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $requests = PudRequest::where('is_deleted', false)->latest('id')->get();
                        $filename = 'PUD_Monitoring_'.date('Ymd_His').'.csv';

                        return response()->streamDownload(function () use ($requests) {
                            $output = fopen('php://output', 'w');
                            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

                            fputcsv($output, [
                                'Request ID', 'Date Request', 'User Name', 'Division', 'Plant',
                                'Part', 'Brand', 'Type', 'Qty', 'Unit',
                                'Purpose', 'Specification', 'Status', 'Progress SLA',
                                'Selected Supplier', 'Item Code',
                                'Supplier 1', 'Price 01-1', 'Price 02-1',
                                'Supplier 2', 'Price 01-2', 'Price 02-2',
                                'Supplier 3', 'Price 01-3', 'Price 02-3',
                                'Feedback', 'Internal Notes',
                            ]);

                            foreach ($requests as $r) {
                                fputcsv($output, [
                                    $r->request_number,
                                    $r->request_date?->format('d/m/Y') ?: '',
                                    $r->user_name,
                                    $r->division,
                                    $r->plant,
                                    $r->part_name,
                                    $r->brand,
                                    $r->type,
                                    $r->qty,
                                    $r->unit,
                                    $r->purpose,
                                    $r->spec,
                                    $r->status,
                                    $r->sla_progress,
                                    $r->selected_supplier,
                                    $r->item_code,
                                    $r->supplier_1_name,
                                    $r->supplier_1_price_1,
                                    $r->supplier_1_price_2,
                                    $r->supplier_2_name,
                                    $r->supplier_2_price_1,
                                    $r->supplier_2_price_2,
                                    $r->supplier_3_name,
                                    $r->supplier_3_price_1,
                                    $r->supplier_3_price_2,
                                    $r->feedback,
                                    $r->internal_notes,
                                ]);
                            }
                            fclose($output);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Detail'),
                Tables\Actions\EditAction::make()->label('Open')->icon('heroicon-o-folder-open'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ViewEntry::make('detail_view')
                    ->view('filament.resources.pud-request.detail-view')
                    ->viewData(fn (?PudRequest $record): array => [
                        'record' => $record,
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPudMonitorings::route('/'),
            'view' => Pages\ViewPudMonitoring::route('/{record}'),
            'edit' => Pages\EditPudMonitoring::route('/{record}/edit'),
        ];
    }
}
