<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function partQuotations(): HasMany
    {
        return $this->hasMany(PartQuotation::class);
    }

    public function vendorItems(): HasMany
    {
        return $this->hasMany(VendorItem::class);
    }

    public function materialSteels(): HasMany
    {
        return $this->hasMany(MaterialSteel::class);
    }

    public function stampingParts(): HasMany
    {
        return $this->hasMany(StampingPart::class);
    }

    public function fasteners(): HasMany
    {
        return $this->hasMany(Fastener::class);
    }

    public function subAssemblyParts(): HasMany
    {
        return $this->hasMany(SubAssemblyPart::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function toolings(): HasMany
    {
        return $this->hasMany(Tooling::class);
    }

    public function partPriceHistories(): HasMany
    {
        return $this->hasMany(PartPriceHistory::class);
    }

    public function comparativeQuotations(): HasMany
    {
        return $this->hasMany(ComparativeQuotation::class);
    }

    /**
     * Hitung penggunaan supplier di seluruh modul untuk menjaga integritas data sebelum hapus.
     *
     * @return array<string, int>
     */
    public function getReferenceCounts(): array
    {
        $counts = [
            'Material Steel' => $this->materialSteels()->count(),
            'Stamping Part' => $this->stampingParts()->count(),
            'Fastener' => $this->fasteners()->count(),
            'Sub-Assembly' => $this->subAssemblyParts()->count(),
            'Purchase Order' => $this->purchaseOrders()->count(),
            'Goods Receipt' => $this->goodsReceipts()->count(),
            'Material' => $this->materials()->count(),
            'Part Quotation' => $this->partQuotations()->count(),
            'Vendor Item' => $this->vendorItems()->count(),
            'Tooling' => $this->toolings()->count(),
            'Price History' => $this->partPriceHistories()->count(),
            'Comparative Quotation' => $this->comparativeQuotations()->count(),
        ];

        return array_filter($counts, static fn (int $count): bool => $count > 0);
    }

    /**
     * Hasilkan kode vendor unik dari nama supplier.
     */
    public static function generateUniqueCode(string $name, ?int $ignoreId = null): string
    {
        $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5));
        $prefix = 'VND-'.($clean ?: 'SUP');

        $query = static::where('code', $prefix);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if (! $query->exists()) {
            return $prefix;
        }

        for ($seq = 1; $seq <= 999; $seq++) {
            $candidate = sprintf('%s-%02d', $prefix, $seq);
            $existsQuery = static::where('code', $candidate);
            if ($ignoreId) {
                $existsQuery->where('id', '!=', $ignoreId);
            }
            if (! $existsQuery->exists()) {
                return $candidate;
            }
        }

        return $prefix.'-'.uniqid();
    }

    /**
     * Cari atau buat supplier khusus untuk kategori modul tertentu tanpa mencampurkan modul lain.
     */
    public static function resolveScopedSupplier(string $name, string $category, ?string $preferredCode = null): static
    {
        $existing = static::where('name', $name)
            ->where('category', $category)
            ->first();
        if ($existing) {
            return $existing;
        }

        $code = $preferredCode ?: static::generateUniqueCode($name);
        if (static::where('code', $code)->exists()) {
            $code = static::generateUniqueCode($name);
        }

        return static::create([
            'name' => $name,
            'code' => $code,
            'category' => $category,
            'is_active' => true,
        ]);
    }
}
