<?php

use App\Models\Fastener;
use App\Models\MaterialSteel;
use App\Models\StampingPart;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $clean = function (?string $spec): ?string {
            if ($spec === null) {
                return null;
            }
            $spec = trim($spec);
            $spec = preg_replace('/\s*\(\s*\d+(?:\.\d+)?\s*(?:mm)?\s*\)\s*$/i', '', $spec);
            $spec = preg_replace('/(?:\s+|-|\s*–\s*|\s*—\s*)\s*\d+(?:\.\d+)?\s*(?:mm)?\s*$/i', '', $spec);
            $spec = preg_replace('/\s+mm\s*$/i', '', $spec);

            return trim($spec);
        };

        foreach (MaterialSteel::all() as $item) {
            $cleaned = $clean($item->steel_spec);
            if ($cleaned !== $item->steel_spec) {
                $item->updateQuietly(['steel_spec' => $cleaned]);
            }
        }

        foreach (StampingPart::all() as $item) {
            $cleaned = $clean($item->steel_spec);
            if ($cleaned !== $item->steel_spec) {
                $item->updateQuietly(['steel_spec' => $cleaned]);
            }
        }

        foreach (Fastener::all() as $item) {
            $cleaned = $clean($item->steel_spec);
            if ($cleaned !== $item->steel_spec) {
                $item->updateQuietly(['steel_spec' => $cleaned]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed as stripping unwanted thickness/mm preserves the canonical steel specification
    }
};
