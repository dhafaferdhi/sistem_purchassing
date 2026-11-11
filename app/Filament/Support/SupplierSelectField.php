<?php

namespace App\Filament\Support;

use App\Models\Supplier;
use Closure;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SupplierSelectField
{
    /**
     * Bangun field Pilih Supplier standar dengan fitur Tambah & Simpan (+), Ubah & Simpan (✎), dan Hapus (🗑) langsung di form.
     * Daftar supplier terisolasi per kategori modul agar supplier Material Steel terpisah dari Stamping Part dan Fastener.
     *
     * @param  array<int, string>|null  $allowedCategories
     */
    public static function make(
        string $fieldName = 'supplier_id',
        string $label = 'Pilih Supplier',
        string $defaultCategory = 'General',
        ?array $allowedCategories = null,
        bool $syncTextFields = true,
        bool|Closure $required = false,
        ?string $helperText = null,
        ?string $placeholder = null,
        int|array $columnSpan = 1,
        bool $prioritizeSteelVendors = false,
        ?Closure $onUpdated = null,
    ): Select {
        $select = Select::make($fieldName)
            ->label($label)
            ->relationship(
                name: 'supplier',
                titleAttribute: 'name',
                modifyQueryUsing: function (Builder $query, Get $get, ?Model $record) use ($fieldName, $allowedCategories, $prioritizeSteelVendors) {
                    $selectedId = $get($fieldName) ?? ($record?->{$fieldName} ?? null);

                    $query->where(function (Builder $q) use ($selectedId, $allowedCategories) {
                        $q->where(function (Builder $activeQuery) use ($allowedCategories) {
                            $activeQuery->where('is_active', true);
                            if (! empty($allowedCategories)) {
                                $activeQuery->whereIn('category', $allowedCategories);
                            }
                        });

                        if ($selectedId) {
                            $q->orWhere('id', $selectedId);
                        }
                    });

                    if ($prioritizeSteelVendors) {
                        $query->orderByRaw("CASE WHEN name LIKE '%HSSI%' OR name LIKE '%USCI%' THEN 0 ELSE 1 END");
                    }

                    $query->orderBy('name');
                }
            )
            ->searchable()
            ->preload()
            ->required($required)
            ->live()
            ->afterStateUpdated(function ($state, Set $set, Get $get) use ($syncTextFields, $onUpdated) {
                if ($syncTextFields) {
                    if ($state) {
                        $supplier = Supplier::find($state);
                        if ($supplier) {
                            $set('vendor_code', $supplier->code);
                            $set('supplier_name', $supplier->name);
                        }
                    } else {
                        $set('vendor_code', null);
                        $set('supplier_name', null);
                    }
                }

                if ($onUpdated) {
                    $onUpdated($get, $set);
                }
            })
            ->createOptionModalHeading('Tambah & Simpan Supplier Baru')
            ->createOptionAction(function (FormAction $action) {
                return $action
                    ->tooltip('Tambah & simpan supplier baru')
                    ->modalSubmitActionLabel('Simpan Supplier');
            })
            ->createOptionForm(fn () => static::buildModalSchema())
            ->createOptionUsing(function (array $data, Set $set, Get $get) use ($defaultCategory, $syncTextFields, $onUpdated) {
                $name = trim((string) ($data['name'] ?? ''));
                $code = Supplier::generateUniqueCode($name);

                $supplier = Supplier::create([
                    'name' => $name,
                    'code' => $code,
                    'category' => $defaultCategory,
                    'is_active' => true,
                ]);

                if ($syncTextFields) {
                    $set('vendor_code', $supplier->code);
                    $set('supplier_name', $supplier->name);
                }

                if ($onUpdated) {
                    $onUpdated($get, $set);
                }

                Notification::make()
                    ->title('Supplier Berhasil Disimpan')
                    ->body("{$supplier->name} ditambahkan ke daftar pilihan.")
                    ->success()
                    ->send();

                return $supplier->getKey();
            })
            ->editOptionModalHeading('Ubah & Simpan Nama Supplier')
            ->editOptionAction(function (FormAction $action) {
                return $action
                    ->tooltip('Ubah & simpan supplier terpilih')
                    ->modalSubmitActionLabel('Simpan Perubahan');
            })
            ->editOptionForm(fn () => static::buildModalSchema())
            ->fillEditOptionActionFormUsing(function (Select $component): ?array {
                $supplierId = $component->getState();
                $supplier = $supplierId ? Supplier::find($supplierId) : null;

                return $supplier ? ['name' => $supplier->name] : null;
            })
            ->updateOptionUsing(function (Select $component, array $data, Set $set, Get $get) use ($syncTextFields, $onUpdated): void {
                $supplierId = $component->getState();
                $supplier = $supplierId ? Supplier::find($supplierId) : null;
                if (! $supplier) {
                    return;
                }

                $name = trim((string) ($data['name'] ?? $supplier->name));
                $supplier->update(['name' => $name]);

                if ($syncTextFields) {
                    $set('vendor_code', $supplier->code);
                    $set('supplier_name', $supplier->name);
                }

                if ($onUpdated) {
                    $onUpdated($get, $set);
                }

                Notification::make()
                    ->title('Perubahan Supplier Disimpan')
                    ->body("{$supplier->name} berhasil diperbarui.")
                    ->success()
                    ->send();
            })
            ->suffixAction(
                FormAction::make('deleteOption')
                    ->label('Hapus Supplier')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Hapus data supplier terpilih')
                    ->visible(fn (Select $component): bool => filled($component->getState()))
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Data Supplier')
                    ->modalDescription(function (Select $component) use ($defaultCategory): string {
                        $supplierId = $component->getState();
                        $supplier = $supplierId ? Supplier::find($supplierId) : null;
                        if (! $supplier) {
                            return 'Kosongkan pilihan supplier pada form ini?';
                        }

                        $usages = $supplier->getReferenceCounts();
                        if (empty($usages)) {
                            return "Supplier {$supplier->name} akan dihapus permanen dan dikosongkan dari form ini. Lanjutkan?";
                        }

                        $summary = static::formatUsageSummary($usages);

                        return "Supplier {$supplier->name} sedang digunakan pada {$summary}. Supplier akan dihapus dari pilihan modul ini tanpa menghapus data pada modul lain.";
                    })
                    ->modalSubmitActionLabel(function (Select $component): string {
                        $supplierId = $component->getState();
                        $supplier = $supplierId ? Supplier::find($supplierId) : null;
                        if ($supplier && ! empty($supplier->getReferenceCounts())) {
                            return 'Hapus dari Modul Ini';
                        }

                        return 'Ya, Hapus Supplier';
                    })
                    ->action(function (Select $component, Set $set, Get $get) use ($fieldName, $defaultCategory, $syncTextFields, $onUpdated): void {
                        $supplierId = $component->getState();
                        $supplier = $supplierId ? Supplier::find($supplierId) : null;

                        if (! $supplier) {
                            $set($fieldName, null);
                            if ($syncTextFields) {
                                $set('vendor_code', null);
                                $set('supplier_name', null);
                            }

                            return;
                        }

                        DB::transaction(function () use ($supplier, $set, $get, $fieldName, $defaultCategory, $syncTextFields, $onUpdated): void {
                            $usages = $supplier->getReferenceCounts();
                            $supplierName = $supplier->name;

                            if (empty($usages)) {
                                $supplier->delete();
                                $notifTitle = 'Supplier Berhasil Dihapus';
                                $notifBody = "{$supplierName} telah dihapus permanen dari daftar supplier.";
                            } else {
                                // Isolasikan data modul lain (Stamping Part / Fastener / Material Steel) agar tidak ikut terhapus atau nonaktif
                                static::detachCrossModuleReferences($supplier, $defaultCategory);

                                $remainingUsages = $supplier->getReferenceCounts();
                                if (empty($remainingUsages)) {
                                    $supplier->delete();
                                } else {
                                    $supplier->update(['is_active' => false]);
                                }

                                $notifTitle = 'Supplier Dihapus dari Modul Ini';
                                $notifBody = "{$supplierName} telah dihapus dari pilihan modul ini tanpa memengaruhi data modul lain.";
                            }

                            $set($fieldName, null);
                            if ($syncTextFields) {
                                $set('vendor_code', null);
                                $set('supplier_name', null);
                            }

                            if ($onUpdated) {
                                $onUpdated($get, $set);
                            }

                            Notification::make()
                                ->title($notifTitle)
                                ->body($notifBody)
                                ->success()
                                ->send();
                        });
                    })
            )
            ->columnSpan($columnSpan);

        if ($helperText !== null) {
            $select->helperText($helperText);
        }

        if ($placeholder !== null) {
            $select->placeholder($placeholder);
        }

        return $select;
    }

    /**
     * Pastikan Stamping Part & Fastener tetap memiliki record supplier mandiri dan tidak ikut terhapus/nonaktif
     * saat supplier pada modul Material Steel dihapus.
     */
    protected static function detachCrossModuleReferences(Supplier $supplier, string $currentModuleCategory): void
    {
        $modules = [
            'Steel' => ['relation' => 'materialSteels', 'targetCategory' => 'Steel', 'withVendorText' => true],
            'Stamping & Steel' => ['relation' => 'stampingParts', 'targetCategory' => 'Stamping & Steel', 'withVendorText' => true],
            'Stamping' => ['relation' => 'stampingParts', 'targetCategory' => 'Stamping & Steel', 'withVendorText' => true],
            'Fastener' => ['relation' => 'fasteners', 'targetCategory' => 'Fastener', 'withVendorText' => true],
            'Component' => ['relation' => 'subAssemblyParts', 'targetCategory' => 'Component', 'withVendorText' => true],
        ];

        $handledRelations = [];
        foreach ($modules as $cat => $cfg) {
            $relationName = $cfg['relation'];
            if (in_array($relationName, $handledRelations, true)) {
                continue;
            }
            $handledRelations[] = $relationName;

            $belongsToCurrent = ($currentModuleCategory === $cat)
                || ($currentModuleCategory === 'Stamping & Steel' && $cat === 'Stamping')
                || ($currentModuleCategory === 'Stamping' && $cat === 'Stamping & Steel');

            if ($belongsToCurrent) {
                continue;
            }

            if ($supplier->{$relationName}()->exists()) {
                $targetSup = Supplier::resolveScopedSupplier($supplier->name, $cfg['targetCategory'], null, $supplier->id);
                if (! $targetSup->is_active) {
                    $targetSup->update(['is_active' => true]);
                }
                $supplier->{$relationName}()->update([
                    'supplier_id' => $targetSup->id,
                    'vendor_code' => $targetSup->code,
                    'supplier_name' => $targetSup->name,
                ]);
            }
        }
    }

    /**
     * Skema field modal Tambah / Ubah Supplier (hanya Nama Supplier).
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function buildModalSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nama Supplier')
                ->placeholder('Contoh: PT. POSMI STEEL INDONESIA')
                ->required()
                ->maxLength(255),
        ];
    }

    /**
     * Format daftar relasi yang masih menggunakan supplier menjadi teks ringkas.
     *
     * @param  array<string, int>  $usages
     */
    public static function formatUsageSummary(array $usages): string
    {
        $parts = [];
        foreach ($usages as $module => $count) {
            $parts[] = "{$module} ({$count})";
        }

        return implode(', ', $parts);
    }
}
