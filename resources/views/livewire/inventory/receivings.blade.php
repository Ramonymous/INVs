<?php
use App\Models\InvReceipt;
use App\Models\InvReceiptItem;
use App\Models\MasterChildpart;
use App\Jobs\GenerateReceiptLabelsJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Livewire\Wireable;

new
#[Layout('components.layouts.app')]
#[Title('Buat Penerimaan')]
class extends Component {
    use Toast;

    public bool $askPrint = false;
    public array $freshItemIds = [];

    /* ---------- FORM STATE ---------- */
    #[Validate('required|string|max:255')]
    public string $receipt_number = '';

    #[Validate('required|date')]
    public string $received_at = '';

    #[Validate('required|exists:master_childparts,id')]
    public int $child_part_id = 0;
    public array $partsSearchable = [];

    #[Validate('required|int|min:1')]
    public ?int $quantity = 1;

    /* ---------- ITEM BASKET ---------- */
    public array $items = [];

    /* ---------- COMPUTED ---------- */
    public function getTotalItemsProperty(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    public function getHasItemsProperty(): bool
    {
        return ! empty($this->items);
    }

    public function totalItems(): int
    {
        return array_sum(array_column($this->items, 'quantity'));
    }

    public function hasItems(): bool
    {
        return !empty($this->items);
    }

    public function search(string $query = ''): void
    {
        $cacheKey = 'parts_search_' . md5($query);
        $results = cache()->remember($cacheKey, 3600, function () use ($query) {
            $selected = $this->child_part_id
                ? MasterChildpart::where('id', $this->child_part_id)->get()
                : collect();
            return MasterChildpart::query()
                ->where('part_number', 'like', "%$query%")
                ->take(10)
                ->orderBy('part_number')
                ->get()
                ->merge($selected);
        });

        $this->partsSearchable = $results->map(fn ($p) => [
            'id' => $p->id,
            'part_number' => $p->part_number
        ])->toArray();
    }
    
    /* ---------- LIFECYCLE ---------- */
    public function mount(): void
    {
        $this->received_at = now()->format('Y-m-d\TH:i');
    }

    /* ---------- ITEM HELPERS ---------- */
    public function addItem(): void
    {
        $this->validate([
            'child_part_id' => 'required|exists:master_childparts,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $part = MasterChildpart::find($this->child_part_id);
        if (!$part) {
            $this->error('Part tidak ditemukan.');
            return;
        }

        $this->items[] = [
            'child_part_id' => $part->id,
            'part_number' => $part->part_number,
            'quantity' => $this->quantity,
        ];

        $this->success("Item {$part->part_number} berhasil ditambahkan.");
        $this->reset('child_part_id', 'quantity');
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    public function clearAllItems(): void
    {
        $this->items = [];
    }

    public function updateItemQuantity(int $index, ?int $qty): void
    {
        if ($qty === null || $qty < 1) {
            $this->error('Quantity minimal 1.');
            return;
        }
        $this->items[$index]['quantity'] = $qty;
    }

    /* ---------- SUBMIT ---------- */
    private function validateSubmission(): void
    {
        if (empty($this->items)) {
            throw new \Exception('Minimal 1 item harus ditambahkan.');
        }
        $this->validate(['received_at' => 'required|date']);
    }

    private function generateReceiptNumber(): string
    {
        $date = now()->format('Ymd');
        $seq  = InvReceipt::whereDate('created_at', today())->max('id') + 1;
        return 'BATCH-' . $date . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateItemCode(int $receiptId, int $idx): string
    {
        $date = now()->format('Ymd');
        $seq  = ($receiptId * 100) + ($idx + 1);
        return 'RCPT-' . $date . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function submit(): void
    {
        try {
            $this->validateSubmission();
            $receipt = DB::transaction(function () {
                $rcpt = InvReceipt::create([
                    'receipt_number' => $this->generateReceiptNumber(),
                    'received_at' => Carbon::parse($this->received_at),
                    'received_by' => Auth::id(),
                ]);

                $itemsToInsert = collect($this->items)->map(function ($item, $i) use ($rcpt) {
                    return [
                        'receipt_id' => $rcpt->id,
                        'child_part_id' => $item['child_part_id'],
                        'quantity' => $item['quantity'],
                        'available' => $item['quantity'],
                        'code' => $this->generateItemCode($rcpt->id, $i),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                InvReceiptItem::insert($itemsToInsert);

                foreach ($this->items as $item) {
                    MasterChildpart::where('id', $item['child_part_id'])->increment('stock', $item['quantity']);
                }

                $this->freshItemIds = InvReceiptItem::where('receipt_id', $rcpt->id)
                    ->pluck('id')
                    ->toArray();

                return $rcpt;
            });

            $this->reset('items');
            $this->success('Penerimaan berhasil disimpan.');
            $this->askPrint = true;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /* ---------- QUEUED LABEL ---------- */
    public function printLabels(): void
    {
        if (empty($this->freshItemIds)) return;
        GenerateReceiptLabelsJob::dispatch($this->freshItemIds, Auth::user());
        $this->dispatch('print-job-started');
    }
};
?>

<div>
    <x-header title="Buat Penerimaan" subtitle="Form penerimaan single parts" icon="o-clipboard-document-check" separator>
        <x-slot:actions>
            <livewire:components.ui.print-notifier />
        </x-slot:actions>
    </x-header>

    <form wire:submit="submit" class="space-y-6">
        <!-- Receipt Info -->
        <x-card title="Informasi Penerimaan" shadow separator>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="PIC" type="text" value="{{ Auth::user()->name }}" disabled/>
                <x-input label="Tanggal Penerimaan" type="datetime-local" wire:model.live="received_at" required/>
            </div>
        </x-card>

        <!-- Add Item -->
        <x-card title="Tambah Item" shadow separator>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-5">
                    <x-choices label="Pilih Part" wire:model.live.debounce.500ms="child_part_id"
                               :options="$this->partsSearchable"
                               placeholder="- Pilih Part -"
                               option-label="part_number" option-value="id"
                               searchable single/>
                </div>
                <div class="md:col-span-3">
                    <x-input label="Jumlah" type="number" min="1" wire:model.defer="quantity"/>
                </div>
                <div class="md:col-span-2">
                    <x-button wire:click="addItem" icon="o-plus" class="btn-primary w-full"
                        :disabled="!$child_part_id"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addItem">Tambah</span>
                        <span wire:loading wire:target="addItem">Menambahkan...</span>
                    </x-button>
                </div>
                <div class="md:col-span-2">
                    <x-button wire:click="clearAllItems" icon="o-trash" class="btn-outline btn-error w-full"
                              :disabled="!$this->has_items"
                              wire:confirm="Yakin ingin menghapus semua item?">Clear All</x-button>
                </div>
            </div>
        </x-card>

        <!-- Items List -->
        <x-card title="Item yang akan diterima ({{ count($items) }} item)" shadow>
            <div class="space-y-3">
                @forelse($items as $index => $item)
                    <div class="flex justify-between items-center p-3">
                        <div class="flex items-center space-x-4">
                            <x-icon name="o-cube" class="w-5 h-5 text-primary"/>
                            <div>
                                <span class="font-medium">{{ $item['part_number'] }}</span>
                                <span class="text-sm text-gray-500 ml-2">— {{ $item['quantity'] }} pcs</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <x-input type="number" min="1"
                                wire:model.lazy="items.{{ $index }}.quantity"
                                class="w-20 text-center"/>
                            <x-button icon="o-trash" wire:click="removeItem({{ $index }})"
                                class="btn-circle btn-ghost btn-sm text-error hover:bg-error/10"
                                wire:confirm="Yakin ingin menghapus item ini?"/>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <x-icon name="o-inbox" class="w-16 h-16 text-gray-300 mx-auto mb-4"/>
                        <p class="text-gray-500">Belum ada item yang ditambahkan</p>
                        <p class="text-sm text-gray-400">Pilih part dan tambahkan ke list penerimaan</p>
                    </div>
                @endforelse
            </div>
            @if($this->hasItems())
                <x-slot:actions>
                    <div class="text-sm text-gray-600">
                        <strong>Total: {{ $this->totalItems() }} pcs</strong> dari {{ count($items) }} jenis part
                    </div>
                </x-slot:actions>
            @endif
        </x-card>

        <!-- Submit -->
        <div class="flex justify-end space-x-4">
            <x-button type="button" icon="o-arrow-left" class="btn-outline"
                      onclick="history.back()">Kembali</x-button>
            <x-button type="submit" icon="o-check" class="btn-primary"
                      :disabled="!$this->has_items"
                      wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Simpan Penerimaan</span>
                <span wire:loading wire:target="submit">Menyimpan...</span>
            </x-button>
        </div>
    </form>

    <!-- Confirmation Modal -->
    @if($askPrint)
        <x-modal wire:model="askPrint" title="Cetak Label?" persistent separator>
            <div>Penerimaan berhasil disimpan. Apakah Anda ingin mencetak label sekarang?</div>
            <x-slot:actions>
                <x-button label="Nanti" @click="$wire.set('askPrint', false)" class="btn-outline"/>
                <x-button label="Cetak" class="btn-primary"
                        wire:click="printLabels"
                        @click="$wire.set('askPrint', false)"/>
            </x-slot:actions>
        </x-modal>
    @endif
</div>