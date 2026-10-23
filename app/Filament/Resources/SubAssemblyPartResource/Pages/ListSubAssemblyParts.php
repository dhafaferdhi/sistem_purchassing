<?php

namespace App\Filament\Resources\SubAssemblyPartResource\Pages;

use App\Filament\Resources\SubAssemblyPartResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListSubAssemblyParts extends ListRecords
{
    protected static string $resource = SubAssemblyPartResource::class;

    protected static string $view = 'filament.resources.sub-assembly-parts.pages.list-sub-assembly-parts';

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
                ->label('+ Tambah Sub-Assembly Baru'),
        ];
    }
}
