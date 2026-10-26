<?php

namespace App\Filament\Resources\StampingPartResource\Pages;

use App\Filament\Resources\StampingPartResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListStampingParts extends ListRecords
{
    protected static string $resource = StampingPartResource::class;

    protected static string $view = 'filament.resources.stamping-parts.pages.list-stamping-parts';

    #[Url]
    public ?string $selectedYear = '';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ->when($this->selectedYear, fn (Builder $query) => $query->where('year', (int) $this->selectedYear));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('+ Tambah Stamping Part Baru'),
        ];
    }
}
