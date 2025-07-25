<?php
namespace App\Jobs;

use App\Models\InvReceiptItem;
use App\Models\User;
use App\Notifications\PrintJobCompleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class GenerateReceiptLabelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int> */
    public array $itemIds;
    public User $user;

    public function __construct($itemIds, User $user)
    {
        $this->itemIds = is_array($itemIds) ? $itemIds : [$itemIds];
        $this->user    = $user;
    }

    public function handle(): void
    {
        try {
            Log::debug('📦 Mulai GenerateReceiptLabelsJob', [
                'user_id' => $this->user->id,
                'item_ids' => $this->itemIds
            ]);

            $items = InvReceiptItem::with(['part', 'receipt.user'])
                ->whereIn('id', $this->itemIds)
                ->get();

            if ($items->isEmpty()) {
                Log::warning('⚠️ Tidak ada item ditemukan untuk label generation.', [
                    'item_ids' => $this->itemIds
                ]);

                $this->user->notify(
                    new PrintJobCompleted(null, 'Tidak ada item yang dipilih.')
                );
                return;
            }

            $barcode = new BarcodeGeneratorPNG();

            $labels = $items->map(function ($item) use ($barcode) {
                Log::debug('🔧 Memproses item:', ['code' => $item->code]);

                return [
                    'code'        => $item->code,
                    'part_number' => $item->part->part_number,
                    'part_name'   => $item->part->part_name,
                    'qty'         => $item->quantity,
                    'penerima'    => $item->receipt->user->name,
                    'batch'       => $item->receipt->receipt_number,
                    'diterima'    => $item->receipt->received_at->format('d/m/Y H:i'),
                    'qr_base64'   => base64_encode(
                        QrCode::format('png')->size(260)->generate($item->code)
                    ),
                    'barcode_base64' => base64_encode(
                        $barcode->getBarcode($item->receipt->receipt_number, $barcode::TYPE_CODE_128)
                    ),
                ];
            });

            $dir  = 'public/print/receipts';
            $file = 'labels-' . $this->user->id . '-' . now()->timestamp . '.pdf';

            Storage::makeDirectory($dir);
            Log::info('📁 Direktori pembuatan label dipastikan ada.', ['dir' => $dir]);

            Pdf::view('livewire.inventory.labels.a4', ['labels' => $labels])
                ->format('A4')
                ->save(storage_path("app/{$dir}/{$file}"));

            Log::info('✅ PDF label berhasil dibuat.', ['file' => $file]);

            $this->user->notify(
                new PrintJobCompleted(Storage::url("{$dir}/{$file}"), 'Download label.')
            );
        } catch (Throwable $e) {
            Log::error('❌ Gagal generate label.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->user->notify(
                new PrintJobCompleted(null, 'Gagal membuat label: ' . $e->getMessage())
            );
            throw $e;
        }
    }
}
