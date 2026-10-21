<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserRequestResource\Pages;
use App\Models\PudRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserRequestResource extends Resource
{
    protected static ?string $model = PudRequest::class;

    protected static ?string $navigationGroup = 'Kalkulator & Purchasing';

    protected static ?string $navigationLabel = 'Request User';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Request User';

    protected static ?string $pluralModelLabel = 'Request User';

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pemohon & Lokasi')
                    ->description('Satu Request hanya untuk satu item/part. Division dan Plant disesuaikan dengan lokasi pemohon.')
                    ->schema([
                        Forms\Components\TextInput::make('request_number')
                            ->label('Request ID')
                            ->default(fn () => PudRequest::generateRequestNumber())
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('request_date')
                            ->label('Tanggal Request')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('user_name')
                            ->label('User Name *')
                            ->placeholder('Masukkan nama Anda')
                            ->default(fn () => auth()->user()?->name)
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(1),

                        Forms\Components\Select::make('plant')
                            ->label('Plant *')
                            ->options(array_combine(PudRequest::PLANTS, PudRequest::PLANTS))
                            ->default('KIIC')
                            ->required()
                            ->live()
                            ->columnSpan(1),

                        Forms\Components\Select::make('division')
                            ->label('Division *')
                            ->options(function (Get $get, ?PudRequest $record) {
                                $plant = $get('plant') ?: ($record?->plant ?: 'KIIC');
                                $divs = PudRequest::PLANT_DIVISIONS[$plant] ?? PudRequest::PLANT_DIVISIONS['KIIC'];
                                $options = array_combine($divs, $divs);
                                if ($record && $record->division && ! isset($options[$record->division])) {
                                    $options[$record->division] = $record->division;
                                }

                                return $options;
                            })
                            ->searchable()
                            ->required()
                            ->columnSpan(2),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Detail Barang / Kebutuhan')
                    ->schema([
                        Forms\Components\TextInput::make('part_name')
                            ->label('Part Name *')
                            ->placeholder('Nama barang / sparepart / consumable')
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('brand')
                            ->label('Brand / Merk *')
                            ->placeholder('Brand / Merk barang')
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('type')
                            ->label('Type / Model *')
                            ->placeholder('Tipe atau model barang')
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('qty')
                            ->label('Qty *')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('unit')
                            ->label('Unit Qty *')
                            ->options(array_combine(PudRequest::UNITS, PudRequest::UNITS))
                            ->default('Pcs')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('purpose')
                            ->label('Use / Purpose *')
                            ->placeholder('Tujuan / kegunaan barang')
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('spec')
                            ->label('Specification *')
                            ->placeholder('Spesifikasi teknis, dimensi, material, nomor katalog')
                            ->required()
                            ->rows(3)
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->columnSpan(3),

                        Forms\Components\FileUpload::make('photo')
                            ->label('Photo Barang')
                            ->image()
                            ->directory('pud-requests')
                            ->imagePreviewHeight('200')
                            ->columnSpan(3)
                            ->helperText('Upload foto barang/part untuk mempermudah identifikasi teknis.'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Hasil Pemrosesan PUD')
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('Status Request')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('selected_supplier')
                            ->label('Supplier Terpilih')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('item_code')
                            ->label('Item Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('feedback')
                            ->label('Feedback / Result PUD')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->visible(fn (?PudRequest $record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('is_deleted', false)->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('ID')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_date')
                    ->label('Date')
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
                        PudRequest::STATUS_CANCELLED => 'Cancelled by User',
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
                Tables\Actions\Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $requests = PudRequest::where('is_deleted', false)->latest('id')->get();
                        $filename = 'PUD_Request_Information_'.date('Ymd_His').'.csv';

                        return response()->streamDownload(function () use ($requests) {
                            $output = fopen('php://output', 'w');
                            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

                            fputcsv($output, [
                                'Request ID', 'Date', 'User Name', 'Division', 'Plant',
                                'Part', 'Brand', 'Type', 'Qty', 'Unit',
                                'Purpose', 'Specification', 'Status', 'Progress SLA',
                                'Supplier Terpilih', 'Item Code',
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
                                ]);
                            }
                            fclose($output);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('cancel_request')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PudRequest $record): bool => ! in_array($record->status, [PudRequest::STATUS_CLOSED, PudRequest::STATUS_CANCELLED]))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pembatalan Request')
                    ->modalDescription('Apakah Anda yakin ingin membatalkan request ini? Request tetap tersimpan di arsip dengan status Cancelled by User.')
                    ->action(function (PudRequest $record) {
                        $oldStatus = $record->status;
                        $record->status = PudRequest::STATUS_CANCELLED;
                        $record->cancelled_at = now();
                        $record->addHistory("Request Cancelled by User (from {$oldStatus})", auth()->user()?->name ?: $record->user_name);
                        $record->save();

                        Notification::make()
                            ->title('Request Dibatalkan')
                            ->body("Request {$record->request_number} telah berhasil dibatalkan.")
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListUserRequests::route('/'),
            'create' => Pages\CreateUserRequest::route('/create'),
            'view' => Pages\ViewUserRequest::route('/{record}'),
            'edit' => Pages\EditUserRequest::route('/{record}/edit'),
        ];
    }
}
