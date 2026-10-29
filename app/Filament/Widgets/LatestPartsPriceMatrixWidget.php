<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StampingPartResource;
use App\Models\StampingPart;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestPartsPriceMatrixWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = '📋 Rekapitulasi Harga Komponen Stamping Terkini';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $year = (int) ($this->filters['year'] ?? date('Y'));
        $quarter = $this->filters['quarter'] ?? 'all';
        $supplierId = $this->filters['supplier_id'] ?? null;

        $query = StampingPart::query()
            ->with(['supplier', 'currency'])
            ->where('year', $year)
            ->when($quarter !== 'all', fn ($q) => $q->where('quarter', $quarter))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->latest('id');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Kuartal')
                    ->badge()
                    ->state(fn (StampingPart $record) => "{$record->year} - {$record->quarter}")
                    ->color(fn (StampingPart $record): string => match ($record->quarter) {
                        'Q1' => 'info',
                        'Q2' => 'success',
                        'Q3' => 'warning',
                        'Q4' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('part_number')
                    ->label('Part Number')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('part_name')
                    ->label('Part Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('steel_spec')
                    ->label('Spec Material')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('material_total_cost')
                    ->label('(1) Material (Rp)')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('process_total_cost')
                    ->label('(2) Process (Rp)')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Final Part Cost (Rp)')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp '.number_format((float) $state, 2, ',', '.') : '-')
                    ->weight('black')
                    ->color('success')
                    ->alignRight(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_detail')
                    ->label('Detail / Edit')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StampingPart $record) => StampingPartResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}

