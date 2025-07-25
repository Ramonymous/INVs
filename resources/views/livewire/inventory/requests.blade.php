<?php

use App\Models\InvRequest;
use App\Models\InvRequestItem;
use App\Models\MasterChildpart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Carbon\Carbon;

new
#[Layout('components.layouts.app')]
#[Title('Buat Permintaan')]
class extends Component
{
    use Toast;

    // A constant for the session key to avoid magic strings.
    public const SCANNED_ITEMS_SESSION_KEY = 'scanned_items';

    // --- COMPONENT STATE ---

    #[Validate('required|string')]
    public string $destination;

    #[Validate('required|date')]
    public string $request_date;

    public ?int $child_part_id = null;
    public array $partsSearchable = [];
    public array $items = [];
    public bool $showConfirmModal = false;

    // --- LIFECYCLE HOOKS ---

    /**
     * Mount the component, initialize properties, and load session data.
     */
    public function mount(): void
    {
        // Set default values for form inputs.
        $this->destination = $this->destinationOptions()[0]['name'] ?? '';
        $this->request_date = now()->format('Y-m-d\TH:i');

        // Load any existing items from the session.
        $this->loadItemsFromSession();
    }

    // --- EVENT LISTENERS ---

    /**
     * Listen for the 'scan-success' event dispatched from the frontend.
     */
    #[On('scan-success')]
    public function scanSuccess(string $partNumber): void
    {
        // Trim whitespace from the scanned part number.
        $partNumber = trim($partNumber);
        if (empty($partNumber)) {
            return;
        }
        $this->addItemByPartNumber($partNumber);
    }

    // --- DATA METHODS ---

    /**
     * Search for child parts based on a query.
     * This is called from the frontend `x-choices` component.
     */
    public function search(string $query = ''): void
    {
        $this->partsSearchable = MasterChildpart::query()
            ->where('part_number', 'like', "%$query%")
            ->take(10)
            ->orderBy('part_number')
            ->get()
            ->map(fn($part) => [
                'id' => $part->id,
                'name' => $part->part_number // `x-choices` uses 'name' for the label by default
            ])->toArray();
    }

    /**
     * Add a manually selected item to the request list.
     */
    public function addManualItem(): void
    {
        $this->validate([
            'child_part_id' => 'required|exists:master_childparts,id',
        ]);

        $part = MasterChildpart::find($this->child_part_id);
        if ($part) {
            $this->addItemByPartNumber($part->part_number);
        } else {
            $this->error('Part tidak ditemukan.');
        }
    }

    /**
     * Clear all items from the session and the component state.
     */
    public function clearAllItems(): void
    {
        session()->forget(self::SCANNED_ITEMS_SESSION_KEY);
        $this->loadItemsFromSession();
        $this->info('Semua item telah dihapus.');
    }

    /**
     * Remove a specific item from the request list.
     */
    public function removeItem(string $part_number): void
    {
        $items = session(self::SCANNED_ITEMS_SESSION_KEY, []);
        if (isset($items[$part_number])) {
            unset($items[$part_number]);
            session([self::SCANNED_ITEMS_SESSION_KEY => $items]);
            $this->loadItemsFromSession();
            $this->success("Item {$part_number} dihapus.");
        }
    }

    // --- SUBMISSION LOGIC ---

    /**
     * Show the confirmation modal before submitting.
     */
    public function confirmSubmit(): void
    {
        if (empty($this->items)) {
            $this->error('Minimal 1 item harus ditambahkan.');
            return;
        }
        $this->validate();
        $this->showConfirmModal = true;
    }

    /**
     * Submit the final request to the database.
     */
    public function submitRequest(): void
    {
        $items = session(self::SCANNED_ITEMS_SESSION_KEY, []);
        if (empty($items)) {
            $this->error('Tidak ada item untuk disimpan.');
            return;
        }

        try {
            DB::transaction(function () use ($items) {
                $request = InvRequest::create([
                    'requested_by' => Auth::id(),
                    'requested_at' => Carbon::parse($this->request_date),
                    'destination' => $this->destination,
                    'status' => 'Pending', // Example status
                ]);

                $itemsToInsert = collect($items)->map(fn($item) => [
                    'request_id' => $request->id,
                    'child_part_id' => $item['child_part_id'],
                    'quantity' => $item['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                InvRequestItem::insert($itemsToInsert);
            });

            session()->forget(self::SCANNED_ITEMS_SESSION_KEY);
            $this->loadItemsFromSession();
            $this->showConfirmModal = false;
            $this->success('Permintaan berhasil dikirim!');
            // Optionally, redirect the user after success
            // return $this->redirect('/requests', navigate: true);

        } catch (\Exception $e) {
            $this->error('Gagal menyimpan permintaan: ' . $e->getMessage());
        }
    }

    // --- HELPER & UTILITY METHODS ---

    /**
     * Core logic to add an item by its part number.
     * Handles both scanned and manually added items.
     */
    protected function addItemByPartNumber(string $partNumber): void
    {
        $part = MasterChildpart::where('part_number', $partNumber)->first();

        if (!$part) {
            $this->error("Part `{$partNumber}` tidak ditemukan di master data.");
            return;
        }

        $items = session(self::SCANNED_ITEMS_SESSION_KEY, []);

        // Each scan/add increments the quantity by 1 KBN.
        $items[$part->part_number] = [
            'child_part_id' => $part->id,
            'part_number' => $part->part_number,
            'quantity' => ($items[$part->part_number]['quantity'] ?? 0) + 1,
        ];

        session([self::SCANNED_ITEMS_SESSION_KEY => $items]);

        $this->success("{$part->part_number} ditambahkan (Total: {$items[$part->part_number]['quantity']} KBN).");
        $this->loadItemsFromSession();
        $this->reset('child_part_id'); // Reset manual selection
    }

    /**
     * Load items from the PHP session into the component's public `$items` property.
     */
    public function loadItemsFromSession(): void
    {
        $this->items = array_values(session(self::SCANNED_ITEMS_SESSION_KEY, []));
    }

    /**
     * Provides the options for the destination select input.
     */
    public function destinationOptions(): array
    {
        return [
            ['id' => 'Line KS', 'name' => 'Line KS'],
            ['id' => 'Line SU', 'name' => 'Line SU'],
            ['id' => 'Gudang', 'name' => 'Gudang'],
        ];
    }

    /**
     * A computed property to get the total quantity of all items.
     */
    public function totalItems(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    /**
     * A computed property to check if there are any items in the list.
     */
    public function hasItems(): bool
    {
        return !empty($this->items);
    }
};
?>

<div>
    <x-header title="Buat Permintaan" subtitle="Form permintaan single parts" icon="o-clipboard-document-list" separator />

    <!-- Row 1: Scan Part & Input Manual | Detail Permintaan -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
        <!-- Left Side: Scan Part & Input Manual (2/3 width) -->
        <div class="xl:col-span-2 grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Scan Part Card -->
            <div x-data="scanner()" x-init="init()">
                <x-card title="Scan Part" shadow class="h-full">
                    <x-slot:menu>
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full" :class="isScanning ? 'bg-green-500 animate-pulse' : 'bg-gray-400'"></div>
                            <span class="text-xs text-gray-500" x-text="isScanning ? 'Aktif' : 'Nonaktif'"></span>
                        </div>
                    </x-slot:menu>

                    <!-- Scanner Viewport -->
                    <div class="mb-6 relative w-full aspect-square bg-gray-50 dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center overflow-hidden">
                        <!-- Video feed will be shown here -->
                        <video id="qr-video" playsinline class="w-full h-full object-cover" :class="{'opacity-100': isScanning, 'opacity-0': !isScanning}"></video>
                        
                        <!-- Canvas for drawing the detection box over the video -->
                        <canvas id="detection-canvas" class="absolute top-0 left-0 w-full h-full"></canvas>

                        <!-- Hidden canvas for processing frames -->
                        <canvas id="qr-canvas" class="hidden"></canvas>

                        <!-- Scanner State Messages (shown when not scanning) -->
                        <div x-show="!isScanning && !cameraError" class="absolute text-center p-4">
                            <template x-if="!isPaused">
                                <div class="space-y-3">
                                    <div class="mx-auto w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center">
                                        <x-icon name="o-qr-code" class="w-10 h-10 text-white" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-700 dark:text-gray-300">Arahkan kamera ke QR Code</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Setiap scan menambah 1 KBN</p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="isPaused">
                                <div class="space-y-3">
                                    <div class="mx-auto w-20 h-20 bg-green-500 rounded-2xl flex items-center justify-center">
                                        <x-icon name="o-check-circle" class="w-10 h-10 text-white" />
                                    </div>
                                    <div>
                                        <p class="font-semibold text-green-600 dark:text-green-400">Scan Berhasil!</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Aktif lagi dalam <span x-text="countdown" class="font-bold text-blue-600"></span> detik</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Camera Error Message -->
                        <div x-show="cameraError" class="absolute text-center p-4">
                            <div class="mx-auto w-20 h-20 bg-red-500 rounded-2xl flex items-center justify-center mb-3">
                                <x-icon name="o-exclamation-triangle" class="w-10 h-10 text-white" />
                            </div>
                            <p class="text-red-600 dark:text-red-400" x-text="cameraError"></p>
                        </div>
                    </div>


                    <x-slot:actions>
                        <x-button 
                            x-show="!isScanning && !isPaused" 
                            @click="start()" 
                            icon="o-camera" 
                            class="btn-primary w-full" 
                            label="Mulai Scanner" 
                        />
                         <x-button 
                            x-show="isPaused" 
                            icon="o-clock" 
                            class="btn-disabled w-full" 
                            x-bind:label="`Cooldown... ${countdown}s`" 
                        />
                        <x-button 
                            x-show="isScanning" 
                            @click="stop()" 
                            icon="o-stop-circle" 
                            class="btn-warning w-full" 
                            label="Hentikan Scanner" 
                            spinner 
                        />
                    </x-slot:actions>
                </x-card>
            </div>

            <!-- Input Manual Card -->
            <x-card title="Input Manual" shadow class="h-full">
                <x-slot:menu>
                    <x-icon name="o-pencil-square" class="w-4 h-4 text-gray-400" />
                </x-slot:menu>

                <div class="space-y-6">
                    <div>
                        <x-choices
                            label="Pilih Part Number"
                            wire:model.live="child_part_id"
                            :options="$this->partsSearchable"
                            placeholder="Ketik untuk mencari..."
                            wire:search="search"
                            searchable
                            single
                            class="select-bordered"
                        />
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-icon name="o-information-circle" class="w-3 h-3 inline mr-1" />
                            Ketik minimal 2 karakter untuk pencarian
                        </div>
                    </div>
                    
                    <x-button
                        wire:click="addManualItem"
                        icon="o-plus"
                        class="btn-primary w-full"
                        :disabled="!$child_part_id"
                        wire:loading.attr="disabled"
                        spinner="addManualItem"
                        label="Tambah ke Daftar"
                    />
                </div>
            </x-card>
        </div>

        <!-- Right Side: Detail Permintaan -->
        <div class="xl:col-span-1">
            <x-card title="Detail Permintaan" shadow class="h-full">
                <x-slot:menu>
                    <div class="flex items-center gap-1">
                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                        <span class="text-xs text-gray-500">Wajib diisi</span>
                    </div>
                </x-slot:menu>

                <div class="space-y-6">
                    <div>
                        <x-select 
                            label="Pilih Tujuan" 
                            :options="$this->destinationOptions()" 
                            wire:model="destination"
                            placeholder="Pilih tujuan pengiriman"
                            class="select-bordered"
                        />
                    </div>
                    
                    <div>
                        <x-datetime 
                            label="Tanggal & Waktu Permintaan" 
                            wire:model="request_date" 
                            type="datetime-local" 
                            required
                            class="input-bordered"
                        />
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-icon name="o-clock" class="w-3 h-3 inline mr-1" />
                            Permintaan akan diproses sesuai jadwal ini
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/30 dark:to-indigo-950/30 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Status Form:</span>
                            @if($this->hasItems()) 
                                <span class="font-medium text-green-600 dark:text-green-400">Siap Kirim</span>
                            @else 
                                <span class="font-medium text-orange-600 dark:text-orange-400">Perlu Item</span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Row 2: Item Diminta -->
    <div class="mb-8">
        <x-card shadow>
            <x-slot:title>
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <x-icon name="o-cube" class="w-5 h-5 text-primary" />
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Item Diminta</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($items) }} jenis part</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        @if($this->hasItems())
                            <div class="text-right">
                                <div class="text-2xl font-bold text-primary">{{ $this->totalItems() }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Total KBN</div>
                            </div>
                        @endif
                    </div>
                </div>
            </x-slot:title>

            <x-slot:menu>
                @if($this->hasItems())
                    <x-button
                        icon="o-trash"
                        wire:click="clearAllItems"
                        class="btn-ghost btn-sm text-error hover:bg-error/10"
                        wire:confirm="Yakin ingin menghapus SEMUA item?"
                        label="Hapus Semua"
                        spinner
                    />
                @endif
            </x-slot:menu>

            @if($this->hasItems())
                <div class="space-y-3">
                    @foreach($items as $index => $item)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-all duration-200" wire:key="item-{{ $item['part_number'] }}">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="font-bold text-gray-800 dark:text-gray-200 text-lg">{{ $item['part_number'] }}</div>
                                    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        <span class="flex items-center gap-1">
                                            <x-icon name="o-cube" class="w-4 h-4" />
                                            {{ $item['quantity'] }} KBN
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <x-icon name="o-clock" class="w-4 h-4" />
                                            Ditambahkan {{ now()->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-badge value="{{ $item['quantity'] }}" class="badge-primary" />
                                <x-button
                                    icon="o-trash"
                                    wire:click="removeItem('{{ $item['part_number'] }}')"
                                    class="btn-circle btn-ghost btn-sm text-error hover:bg-error/10"
                                    wire:confirm="Yakin ingin menghapus item ini?"
                                    spinner
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
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
            @endif
        </x-card>
    </div>

    <!-- Form Actions -->
    <form wire:submit="confirmSubmit">
        <div class="flex items-center justify-between p-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-lg">
            <div class="flex items-center gap-3">
                <x-icon name="o-information-circle" class="w-5 h-5 text-blue-500" />
                <div>
                    <div class="font-medium text-gray-700 dark:text-gray-300">
                        @if($this->hasItems())
                            Siap untuk mengirim {{ count($items) }} jenis part
                        @else
                            Tambahkan minimal 1 item untuk melanjutkan
                        @endif
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        @if($this->hasItems())
                            Total {{ $this->totalItems() }} KBN akan dikirim ke {{ $destination ?? 'tujuan yang dipilih' }}
                        @else
                            Gunakan scanner atau input manual di atas
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <x-button 
                    label="Kembali" 
                    icon="o-arrow-left" 
                    class="btn-outline" 
                    link="{{ url()->previous() }}" 
                />
                <x-button
                    type="submit"
                    label="Kirim Permintaan"
                    icon-right="o-paper-airplane"
                    class="btn-primary"
                    :disabled="!$this->hasItems()"
                    spinner="confirmSubmit"
                />
            </div>
        </div>
    </form>

    <!-- Confirmation Modal -->
    <x-modal wire:model="showConfirmModal" title="Konfirmasi Pengiriman" persistent separator class="backdrop-blur-sm">
        <div class="space-y-4">
            <div class="bg-blue-50 dark:bg-blue-950/30 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                <div class="flex items-start gap-3">
                    <x-icon name="o-information-circle" class="w-5 h-5 text-blue-600 mt-0.5" />
                    <div>
                        <h4 class="font-semibold text-blue-900 dark:text-blue-100">Detail Permintaan</h4>
                        <div class="mt-2 space-y-1 text-sm text-blue-800 dark:text-blue-200">
                            <div>• <strong>{{ count($items) }} jenis part</strong> dengan total <strong>{{ $this->totalItems() }} KBN</strong></div>
                            <div>• Tujuan: <strong>{{ $destination ?? '-' }}</strong></div>
                            <div>• Tanggal: <strong>{{ $request_date ? \Carbon\Carbon::parse($request_date)->format('d/m/Y H:i') : '-' }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-gray-700 dark:text-gray-300">Apakah semua data sudah benar dan Anda yakin ingin mengirim permintaan ini?</p>
        </div>
        
        <x-slot:actions>
            <x-button 
                label="Periksa Ulang" 
                @click="$wire.showConfirmModal = false" 
                class="btn-outline" 
                icon="o-arrow-left"
            />
            <x-button 
                label="Ya, Kirim Sekarang" 
                class="btn-primary" 
                wire:click="submitRequest" 
                spinner="submitRequest"
                icon="o-paper-airplane"
            />
        </x-slot:actions>
    </x-modal>
</div>

@push('footer')
<!-- 
    IMPORTANT: Since you installed jsqr via npm, you should
    import it in your main javascript file (e.g., resources/js/app.js)
    like this:
    
    import jsQR from 'jsqr';
    window.jsQR = jsQR;
-->
<script>
    function scanner() {
        return {
            isScanning: false,
            isPaused: false, // Is the scanner in cooldown?
            cooldownSeconds: 3,
            countdown: 0,
            cameraError: null,
            
            // --- jsQR specific properties ---
            video: null,
            canvas: null, // The hidden canvas for processing
            canvasCtx: null, // The context for the processing canvas
            detectionCanvas: null, // The visible canvas for drawing lines
            detectionCanvasCtx: null,
            stream: null,
            animationFrameId: null,
            countdownInterval: null,

            init() {
                // Check if jsQR library is available globally
                if (typeof jsQR === 'undefined') {
                    this.cameraError = 'Scanner library (jsQR) not loaded. Please import it.';
                    console.error(this.cameraError);
                    return;
                }
                this.video = document.getElementById('qr-video');
                this.canvas = document.getElementById('qr-canvas');
                this.detectionCanvas = document.getElementById('detection-canvas');

                // Get canvas contexts once for performance
                this.canvasCtx = this.canvas.getContext('2d', { willReadFrequently: true });
                this.detectionCanvasCtx = this.detectionCanvas.getContext('2d');

                // Listen for Livewire navigation and stop the scanner to release the camera
                document.addEventListener('livewire:navigating', () => {
                    this.stop();
                });
            },
            
            // Helper function to draw the bounding box
            drawLine(begin, end, color) {
                this.detectionCanvasCtx.beginPath();
                this.detectionCanvasCtx.moveTo(begin.x, begin.y);
                this.detectionCanvasCtx.lineTo(end.x, end.y);
                this.detectionCanvasCtx.lineWidth = 4;
                this.detectionCanvasCtx.strokeStyle = color;
                this.detectionCanvasCtx.stroke();
            },

            start() {
                if (this.isPaused) return;
                this.cameraError = null;
                
                // Clear any previous drawings
                this.detectionCanvasCtx.clearRect(0, 0, this.detectionCanvas.width, this.detectionCanvas.height);
                
                // Request access to the camera
                navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
                    .then((stream) => {
                        this.isScanning = true;
                        this.stream = stream;
                        this.video.srcObject = stream;
                        this.video.play();
                        // Start the scanning loop
                        this.animationFrameId = requestAnimationFrame(this.tick.bind(this));
                    })
                    .catch((err) => {
                        this.isScanning = false;
                        this.cameraError = `Gagal memulai kamera: ${err.message}. Pastikan Anda memberikan izin kamera.`;
                        console.error(err);
                    });
            },

            tick() {
                // Check if the video is ready to be processed
                if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
                    // Set canvas sizes to match the video feed
                    this.canvas.height = this.video.videoHeight;
                    this.canvas.width = this.video.videoWidth;
                    this.detectionCanvas.height = this.video.videoHeight;
                    this.detectionCanvas.width = this.video.videoWidth;

                    this.canvasCtx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
                    const imageData = this.canvasCtx.getImageData(0, 0, this.canvas.width, this.canvas.height);
                    
                    // Attempt to decode a QR code from the video frame
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });

                    if (code && code.data) {
                        // QR code found! Draw the bounding box.
                        this.drawLine(code.location.topLeftCorner, code.location.topRightCorner, "#FF3B58");
                        this.drawLine(code.location.topRightCorner, code.location.bottomRightCorner, "#FF3B58");
                        this.drawLine(code.location.bottomRightCorner, code.location.bottomLeftCorner, "#FF3B58");
                        this.drawLine(code.location.bottomLeftCorner, code.location.topLeftCorner, "#FF3B58");
                        
                        // Dispatch the data to Livewire and pause.
                        Livewire.dispatch('scan-success', { partNumber: code.data });
                        this.pauseScanner();
                        return; // Stop the loop for this frame
                    }
                }
                
                // Continue to the next frame if we are still in a scanning state
                if (this.isScanning) {
                    this.animationFrameId = requestAnimationFrame(this.tick.bind(this));
                }
            },

            stop() {
                this.isScanning = false;
                
                // Stop the animation loop
                if (this.animationFrameId) {
                    cancelAnimationFrame(this.animationFrameId);
                    this.animationFrameId = null;
                }

                // Stop the camera stream to release the camera resource
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                    this.stream = null;
                }
                if (this.video) {
                    this.video.srcObject = null;
                }
                
                // Clear the drawing
                if (this.detectionCanvasCtx) {
                    this.detectionCanvasCtx.clearRect(0, 0, this.detectionCanvas.width, this.detectionCanvas.height);
                }
            },

            pauseScanner() {
                // This method stops scanning and starts a cooldown timer.
                this.isScanning = false;
                
                // Stop the camera but keep the last frame with the detection box visible
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                }
                
                this.isPaused = true;
                this.countdown = this.cooldownSeconds;

                // Start the visual countdown timer
                this.countdownInterval = setInterval(() => {
                    this.countdown--;
                    if (this.countdown <= 0) {
                        clearInterval(this.countdownInterval);
                        this.isPaused = false;
                        // Automatically restart the scanner
                        this.start();
                    }
                }, 1000);
            }
        };
    }
</script>
@endpush
