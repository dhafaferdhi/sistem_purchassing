<?php

namespace App\Jobs;

use App\Services\ExcelImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportSpreadsheetJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        public string $module,
        public string $filePath,
        public array $defaults = [],
        public bool $updateExisting = true,
        public ?int $userId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $result = match ($this->module) {
                'material_steel' => ExcelImportService::importMaterialSteel($this->filePath, $this->defaults, $this->updateExisting),
                'stamping_part' => ExcelImportService::importStampingPart($this->filePath, $this->defaults, $this->updateExisting),
                'fastener' => ExcelImportService::importFastener($this->filePath, $this->defaults, $this->updateExisting),
                default => ['imported' => 0, 'updated' => 0, 'total' => 0, 'errors' => ["Modul impor tidak dikenal: {$this->module}"]],
            };

            Log::info("Queued Excel Import ({$this->module}) selesai", [
                'user_id' => $this->userId,
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'total' => $result['total'],
                'error_count' => count($result['errors']),
            ]);
        } finally {
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
        }
    }
}
