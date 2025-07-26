<?php

use App\Models\InvIssuance;
use App\Models\InvReceiptItem;
use App\Models\InvRequestItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new
#[Layout('components.layouts.app')]
#[Title('Pengeluaran Barang')]
class extends Component
{
    use Toast;

    // --- COMPONENT STATE ---
    public const BATCH_SESSION_KEY = 'issuance_batch';

    public string $scannedCode = '';
    public array $batchItems = [];
    public bool $showConfirmModal = false;

    // --- LIFECYCLE HOOKS ---

    public function mount(): void
    {
        $this->loadBatchFromSession();
    }

    // --- EVENT LISTENERS ---

    #[On('scan-success')]
    public function handleScan(string $code): void
    {
        $this->addToBatch($code);
    }

    // --- BATCH MANAGEMENT ---

    public function manualProcess(): void
    {
        $this->validate(['scannedCode' => 'required|string']);
        $this->addToBatch($this->scannedCode);
    }

    public function addToBatch(string $code): void
    {
        $scannedCode = trim($code);
        if (empty($scannedCode)) return;

        if (isset($this->batchItems[$scannedCode])) {
            $this->warning("Kode `{$scannedCode}` sudah ada di dalam batch.");
            return;
        }

        $receiptItem = InvReceiptItem::with('part')->where('code', $scannedCode)->first();
        if (!$receiptItem) {
            $this->error("QR Code `{$scannedCode}` tidak valid atau tidak ditemukan.");
            return;
        }
        if ($receiptItem->available <= 0) {
            $this->warning("Stok untuk QR Code `{$scannedCode}` sudah habis.");
            return;
        }

        // --- Cek apakah ada permintaan terbuka (FIFO) ---
        $requestItem = InvRequestItem::query()
            ->where('child_part_id', $receiptItem->child_part_id)
            ->where('fulfilled', false)
            ->join('inv_requests', 'inv_request_items.request_id', '=', 'inv_requests.id')
            ->orderBy('inv_requests.requested_at', 'asc')
            ->select('inv_request_items.*', 'inv_requests.destination', 'inv_requests.id as request_id_alias')
            ->first();

        $qtyToIssue = match ($receiptItem->part->type) {
            'small'  => $receiptItem->available,
            'medium' => 100,
            'big'    => 72,
            default  => 1,
        };
        $qtyToIssue = min($qtyToIssue, $receiptItem->available);

        $this->batchItems[$scannedCode] = [
            'receipt_item_id' => $receiptItem->id,
            'code'            => $scannedCode,
            'part_number'     => $receiptItem->part->part_number,
            'part_name'       => $receiptItem->part->part_name,
            'available'       => $receiptItem->available,
            'qty_to_issue'    => $qtyToIssue,
            'has_request'     => (bool)$requestItem,
            'destination'     => $requestItem?->destination,
            'request_id'      => $requestItem?->request_id_alias,
        ];

        $this->updateSession();
        $this->success("Part `{$receiptItem->part->part_number}` ditambahkan ke batch.");
        $this->reset('scannedCode');
    }

    public function updateQuantity(string $code, $newQuantity): void
    {
        if (!isset($this->batchItems[$code])) return;

        $item = $this->batchItems[$code];
        $validatedQty = filter_var($newQuantity, FILTER_VALIDATE_INT);

        if ($validatedQty === false || $validatedQty <= 0) {
            $this->batchItems[$code]['qty_to_issue'] = 1;
        } elseif ($validatedQty > $item['available']) {
            $this->batchItems[$code]['qty_to_issue'] = $item['available'];
            $this->warning("Kuantitas tidak boleh melebihi stok tersedia ({$item['available']}).");
        } else {
            $this->batchItems[$code]['qty_to_issue'] = $validatedQty;
        }

        $this->updateSession();
    }

    public function removeFromBatch(string $code): void
    {
        unset($this->batchItems[$code]);
        $this->updateSession();
        $this->info("Item telah dihapus dari batch.");
    }

    public function clearBatch(): void
    {
        $this->batchItems = [];
        $this->updateSession();
        $this->warning("Semua item dalam batch telah dibersihkan.");
    }

    // --- SUBMISSION ---

    public function confirmSubmit(): void
    {
        if (empty($this->batchItems)) {
            $this->error("Batch kosong, tidak ada yang bisa disubmit.");
            return;
        }
        $this->showConfirmModal = true;
    }

    public function submitBatch(): void
    {
        $batch = session(self::BATCH_SESSION_KEY, []);
        if (empty($batch)) return;

        try {
            DB::transaction(function () use ($batch) {
                foreach ($batch as $itemData) {
                    $receiptItem = InvReceiptItem::with('part')->find($itemData['receipt_item_id']);
                    if (!$receiptItem || $receiptItem->available < $itemData['qty_to_issue']) {
                        throw new \Exception("Stok tidak mencukupi untuk part {$itemData['part_number']}.");
                    }

                    $requestItem = InvRequestItem::query()
                        ->where('child_part_id', $receiptItem->child_part_id)
                        ->where('fulfilled', false)
                        ->join('inv_requests', 'inv_request_items.request_id', '=', 'inv_requests.id')
                        ->orderBy('inv_requests.requested_at', 'asc')
                        ->select('inv_request_items.*')
                        ->first();

                    InvIssuance::create([
                        'request_id'      => $requestItem?->request_id,
                        'request_item_id' => $requestItem?->id,
                        'receipt_item_id' => $receiptItem->id,
                        'issued_quantity' => $itemData['qty_to_issue'],
                        'issued_by'       => Auth::id(),
                        'issued_at'       => now(),
                        'is_forced'       => !$requestItem,
                    ]);

                    $receiptItem->decrement('available', $itemData['qty_to_issue']);
                    $receiptItem->part->decrement('stock', $itemData['qty_to_issue']);

                    if ($requestItem) {
                        $totalIssued = $requestItem->issuances()->sum('issued_quantity') + $itemData['qty_to_issue'];
                        if ($totalIssued >= $requestItem->quantity) {
                            $requestItem->update(['fulfilled' => true]);
                        }
                    }
                }
            });

            $this->success('Batch berhasil diproses!');
            $this->clearBatch();
            $this->showConfirmModal = false;

        } catch (\Exception $e) {
            $this->error('Gagal memproses batch: ' . $e->getMessage());
        }
    }


    // --- HELPER METHODS ---

    protected function loadBatchFromSession(): void
    {
        $this->batchItems = session(self::BATCH_SESSION_KEY, []);
    }

    protected function updateSession(): void
    {
        session([self::BATCH_SESSION_KEY => $this->batchItems]);
    }

    public function totalBatchItems(): int
    {
        return count($this->batchItems);
    }

    public function totalBatchQuantity(): int
    {
        return array_sum(array_column($this->batchItems, 'qty_to_issue'));
    }
};
?>

<div class="space-y-8">
    <!-- Section 1: Scanner -->
    <div x-data="scanner()" x-init="init()">
        <x-card title="Scan atau Input Part" shadow>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <!-- Camera Viewport -->
                <div class="w-full">
                     <div class="relative w-full aspect-video bg-gray-50 dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center overflow-hidden">
                        <video id="qr-video" playsinline class="w-full h-full object-cover" :class="{'opacity-100': isScanning, 'opacity-0': !isScanning}"></video>
                        <canvas id="detection-canvas" class="absolute top-0 left-0 w-full h-full"></canvas>
                        <canvas id="qr-canvas" class="hidden"></canvas>

                        <!-- Scanner State Messages -->
                        <div x-show="!isScanning && !cameraError" class="absolute text-center p-4">
                            <template x-if="!isPaused">
                                <div class="space-y-3">
                                    <x-icon name="o-qr-code" class="w-16 h-16 mx-auto text-gray-400" />
                                    <p class="font-medium text-gray-700 dark:text-gray-300">Arahkan kamera ke QR Code</p>
                                </div>
                            </template>
                             <template x-if="isPaused">
                                <div class="space-y-3">
                                    <x-icon name="o-check-circle" class="w-16 h-16 mx-auto text-green-500" />
                                    <p class="font-semibold text-green-600 dark:text-green-400">Scan Berhasil!</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Aktif lagi dalam <span x-text="countdown" class="font-bold"></span> detik</p>
                                </div>
                            </template>
                        </div>
                        <div x-show="cameraError" class="absolute text-center p-4">
                            <x-icon name="o-exclamation-triangle" class="w-16 h-16 mx-auto text-red-400 mb-2" />
                            <p class="text-red-600 dark:text-red-400" x-text="cameraError"></p>
                        </div>
                    </div>
                </div>

                <!-- Manual Input & Actions -->
                <div class="w-full space-y-4">
                     <div>
                        <x-input
                            label="Atau Input Manual Kode Part"
                            wire:model="scannedCode"
                            placeholder="Ketik kode di sini..."
                            icon="o-pencil-square"
                            wire:keydown.enter="manualProcess"
                            hint="Tekan Enter untuk memproses"
                        />
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                         <x-button x-show="!isScanning && !isPaused" @click="start()" icon="o-camera" class="btn-primary flex-1" label="Mulai Scanner" />
                         <x-button x-show="isPaused" icon="o-clock" class="btn-disabled w-full flex-1" x-bind:label="`Cooldown... ${countdown}s`" />
                         <x-button x-show="isScanning" @click="stop()" icon="o-stop-circle" class="btn-warning flex-1" label="Hentikan Scanner" spinner />
                         <x-button label="Proses Manual" wire:click="manualProcess" class="btn-outline flex-1" spinner="manualProcess" />
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    <!-- Section 2: Batch List -->
    <div>
        <x-card title="Batch Pengeluaran" shadow>
            <x-slot:menu>
                @if($this->totalBatchItems() > 0)
                    <x-button icon="o-trash" label="Bersihkan Batch" class="btn-ghost btn-sm text-error" wire:click="clearBatch" wire:confirm="Yakin ingin membersihkan semua item di batch?" />
                @endif
            </x-slot:menu>

            <div class="space-y-3 max-h-[60vh] overflow-y-auto p-1">
                @forelse($batchItems as $code => $item)
                    <div wire:key="batch-{{ $code }}" class="p-4 rounded-lg border {{ !$item['has_request'] ? 'border-warning/50 bg-warning/5' : 'border-gray-200 dark:border-gray-700 bg-base-200/50' }}">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <div class="font-bold text-lg">{{ $item['part_number'] }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $item['part_name'] }}</div>
                                @if($item['has_request'])
                                    <x-badge value="Req #{{ $item['request_id'] }} ke {{ $item['destination'] }}" class="badge-info badge-outline mt-2" />
                                @else
                                    <x-badge value="Tanpa Permintaan (Force Issue)" class="badge-warning badge-outline mt-2" icon="o-exclamation-triangle" />
                                @endif
                            </div>
                            <x-button icon="o-x-mark" class="btn-circle btn-ghost btn-sm" wire:click="removeFromBatch('{{ $code }}')" />
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-4 items-end">
                            <div>
                                <x-input
                                    label="Qty Keluar (Pcs)"
                                    wire:model.live.debounce.500ms="batchItems.{{ $code }}.qty_to_issue"
                                    wire:change="updateQuantity('{{ $code }}', $event.target.value)"
                                    type="number"
                                    min="1"
                                    max="{{ $item['available'] }}"
                                    class="input-sm"
                                />
                            </div>
                            <div class="text-sm text-gray-500 text-right">
                                Stok: <span class="font-semibold">{{ $item['available'] }}</span> Pcs
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16">
                        <div class="mx-auto w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 rounded-3xl flex items-center justify-center mb-6">
                            <x-icon name="o-inbox" class="w-12 h-12 text-gray-400" />
                        </div>
                        <div class="max-w-sm mx-auto">
                            <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">Belum Ada Item</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Scan QR code atau pilih manual untuk menambahkan part ke daftar permintaan.</p>
                            <div class="flex items-center justify-center gap-4 text-sm text-gray-400">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-qr-code" class="w-4 h-4" />
                                    <span>Scan QR</span>
                                </div>
                                <div class="w-px h-4 bg-gray-300"></div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-pencil-square" class="w-4 h-4" />
                                    <span>Input Manual</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            @if($this->totalBatchItems() > 0)
                <x-slot:actions>
                    <div class="w-full flex justify-between items-center p-2">
                        <div>
                            <div class="text-lg font-bold">{{ $this->totalBatchItems() }} Jenis Item</div>
                            <div class="text-sm text-gray-500">{{ $this->totalBatchQuantity() }} Pcs Total</div>
                        </div>
                        <x-button label="Submit Batch" icon-right="o-paper-airplane" class="btn-primary btn-lg" wire:click="confirmSubmit" spinner />
                    </div>
                </x-slot:actions>
            @endif
        </x-card>
    </div>

    <!-- Modal Konfirmasi Submit Batch -->
    <x-modal wire:model="showConfirmModal" title="Konfirmasi Pengeluaran Batch" persistent separator>
        <div>
            Anda akan memproses pengeluaran untuk <strong>{{ $this->totalBatchItems() }}</strong> item dengan total <strong>{{ $this->totalBatchQuantity() }}</strong> Pcs.
            <br><br>
            Aksi ini akan mengurangi stok secara permanen. Pastikan semua data sudah benar.
        </div>
        <x-slot:actions>
            <x-button label="Batal" @click="$wire.showConfirmModal = false" class="btn-ghost" />
            <x-button label="Ya, Proses Sekarang" class="btn-primary" wire:click="submitBatch" spinner="submitBatch" />
        </x-slot:actions>
    </x-modal>
</div>


@push('footer')
<!-- Library untuk memindai QR Code -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
    function scanner() {
        return {
            isScanning: false,
            isPaused: false,
            cooldownSeconds: 3,
            countdown: 0,
            cameraError: null,
            video: null, canvas: null, canvasCtx: null,
            detectionCanvas: null, detectionCanvasCtx: null,
            stream: null, animationFrameId: null, countdownInterval: null,

            init() {
                if (typeof jsQR === 'undefined') { this.cameraError = 'Scanner library (jsQR) not loaded.'; return; }
                this.video = document.getElementById('qr-video');
                this.canvas = document.getElementById('qr-canvas');
                this.detectionCanvas = document.getElementById('detection-canvas');
                this.canvasCtx = this.canvas.getContext('2d', { willReadFrequently: true });
                this.detectionCanvasCtx = this.detectionCanvas.getContext('2d');
                document.addEventListener('livewire:navigating', () => this.stop());
            },
            drawLine(begin, end, color) {
                this.detectionCanvasCtx.beginPath(); this.detectionCanvasCtx.moveTo(begin.x, begin.y);
                this.detectionCanvasCtx.lineTo(end.x, end.y); this.detectionCanvasCtx.lineWidth = 4;
                this.detectionCanvasCtx.strokeStyle = color; this.detectionCanvasCtx.stroke();
            },
            start() {
                if (this.isPaused) return;
                this.cameraError = null;
                this.detectionCanvasCtx.clearRect(0, 0, this.detectionCanvas.width, this.detectionCanvas.height);
                navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
                    .then((stream) => {
                        this.isScanning = true; this.stream = stream; this.video.srcObject = stream;
                        this.video.play(); this.animationFrameId = requestAnimationFrame(this.tick.bind(this));
                    })
                    .catch((err) => {
                        this.isScanning = false; this.cameraError = `Gagal memulai kamera: ${err.name}.`;
                    });
            },
            tick() {
                if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
                    this.canvas.height = this.video.videoHeight; this.canvas.width = this.video.videoWidth;
                    this.detectionCanvas.height = this.video.videoHeight; this.detectionCanvas.width = this.video.videoWidth;
                    this.canvasCtx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
                    const imageData = this.canvasCtx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });
                    if (code && code.data) {
                        this.drawLine(code.location.topLeftCorner, code.location.topRightCorner, "#FF3B58");
                        this.drawLine(code.location.topRightCorner, code.location.bottomRightCorner, "#FF3B58");
                        this.drawLine(code.location.bottomRightCorner, code.location.bottomLeftCorner, "#FF3B58");
                        this.drawLine(code.location.bottomLeftCorner, code.location.topLeftCorner, "#FF3B58");
                        Livewire.dispatch('scan-success', { code: code.data });
                        this.pauseScanner(); // Panggil pause, bukan stop
                        return;
                    }
                }
                if (this.isScanning) { this.animationFrameId = requestAnimationFrame(this.tick.bind(this)); }
            },
            stop() {
                this.isScanning = false;
                if (this.animationFrameId) { cancelAnimationFrame(this.animationFrameId); this.animationFrameId = null; }
                if (this.stream) { this.stream.getTracks().forEach(track => track.stop()); this.stream = null; }
                if (this.video) { this.video.srcObject = null; }
            },
            pauseScanner() {
                this.isScanning = false;
                if (this.stream) { this.stream.getTracks().forEach(track => track.stop()); }
                
                this.isPaused = true;
                this.countdown = this.cooldownSeconds;

                this.countdownInterval = setInterval(() => {
                    this.countdown--;
                    if (this.countdown <= 0) {
                        clearInterval(this.countdownInterval);
                        this.isPaused = false;
                        this.start(); // Mulai ulang scanner
                    }
                }, 1000);
            }
        };
    }
</script>
@endpush
