<?php

use App\Models\InvRequest;
use App\Models\InvRequestItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app')]
#[Title('List Permintaan Part')]
class extends Component {

    // Data
    public Collection $rows;
    public array $seenReqIds = [];
    public array $announcedDelayedItemIds = []; // Track items announced for delay

    /**
     * Mount the component and load the initial data.
     */
    public function mount(): void
    {
        $this->rows = collect();
        $this->refreshRows();
    }

    /**
     * Called every 5s by wire:poll or manually via an event.
     * This method is optimized to use a cleaner query and a single processing loop.
     */
    #[On('refreshRows')]
    public function refreshRows(): void
    {
        try {
            // OPTIMASI 1: Query dibuat lebih "Eloquent" tanpa manual JOIN.
            $freshItems = InvRequestItem::query()
                ->with(['part', 'request.user']) // Eager loading (sudah baik)
                ->where('fulfilled', false)
                ->whereHas('request') // Memastikan request ada
                ->orderBy(
                    InvRequest::select('requested_at')
                        ->whereColumn('inv_requests.id', 'inv_request_items.request_id')
                , 'asc')
                ->limit(50)
                ->get();

            // OPTIMASI 2: Logika diproses dalam satu perulangan untuk kejelasan dan efisiensi.
            $newlySeenRequestIds = $freshItems->pluck('request_id')->unique()->diff($this->seenReqIds)->values()->all();
            $thirtyMinutesAgo = now()->subMinutes(30);

            $partNumbersToSpeakImmediately = [];
            $partNumbersToSpeakDelayed = [];
            $rowsForTable = [];

            foreach ($freshItems as $item) {
                // Lewati jika relasi part tidak ada (pengaman)
                if (!$item->part || !$item->request) {
                    continue;
                }

                $isNewRequest = in_array($item->request_id, $newlySeenRequestIds);

                // --- Logika untuk pengumuman suara ---
                if ($isNewRequest) {
                    $partNumbersToSpeakImmediately[] = $item->part->part_number;
                }
                // Cek item yang tertunda
                elseif ($item->request->requested_at->lt($thirtyMinutesAgo) && !in_array($item->id, $this->announcedDelayedItemIds)) {
                    $partNumbersToSpeakDelayed[] = $item->part->part_number;
                    $this->announcedDelayedItemIds[] = $item->id; // Tandai agar tidak diumumkan lagi
                }

                // --- Membangun baris untuk tabel ---
                $rowsForTable[] = [
                    'item_id'          => $item->id,
                    'request_id'       => $item->request_id,
                    'part_number'      => $item->part->part_number,
                    'requested_at'     => $item->request->requested_at->diffForHumans(),
                    'destination'      => $item->request->destination,
                    'request_quantity' => $item->quantity,
                    'request_uom'      => 'KBN',
                    'status'           => $item->fulfilled ? 'close' : 'waiting',
                    'is_new'           => $isNewRequest,
                ];
            }

            // --- Update ID yang sudah terlihat ---
            if (!empty($newlySeenRequestIds)) {
                $this->seenReqIds = array_unique(array_merge($this->seenReqIds, $newlySeenRequestIds));
            }

            // --- Kirim event ke frontend (hanya jika ada data baru) ---
            if (!empty($partNumbersToSpeakImmediately)) {
                $this->dispatch('speak-part-numbers', part_numbers: array_unique($partNumbersToSpeakImmediately));
            }
            if (!empty($partNumbersToSpeakDelayed)) {
                $this->dispatch('speak-delayed-part-numbers', part_numbers: array_unique($partNumbersToSpeakDelayed));
            }

            // --- Update data tabel di properti komponen ---
            $this->rows = collect($rowsForTable);

        } catch (\Throwable $e) {
            Log::error('refreshRows() error', ['error' => $e->getMessage()]);
        }
    }
};
?>

<div wire:poll.5s="refreshRows">
    <x-header title="Real-time Part Requests" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-button class="btn-sm" icon="o-speaker-wave" label="Inisialisasi Suara" @click="initializeSpeech()" />
        </x-slot:middle>
    </x-header>

    <x-card title="List of Requests" shadow>
        <x-table
            :headers="[
                ['key' => 'part_number',      'label' => 'Part #'],
                ['key' => 'request_quantity', 'label' => 'Qty'],
                ['key' => 'request_uom',      'label' => 'UoM'],
                ['key' => 'requested_at',     'label' => 'Requested'],
                ['key' => 'destination',      'label' => 'Destination'],
                ['key' => 'status',           'label' => 'Status'],
            ]"
            :rows="$rows"
            wire:key="requests-table-{{ now() }}"
        >
            @scope('cell_status', $row)
                <div class="inline-grid *:[grid-area:1/1]">
                <div class="status status-error animate-ping"></div>
                <div class="status status-error"></div>
                </div> {{ Str::upper($row['status']) }}
            @endscope

            @scope('row-decoration', $row)
                @if($row['is_new'])
                    <div class="bg-yellow-200/50 dark:bg-yellow-500/20 absolute inset-0 -z-10 animate-pulse"></div>
                @endif
            @endscope
        </x-table>
    </x-card>
</div>

@push('footer')
<script>
let indonesianVoice = null;
const speechQueue = [];
let isSpeaking = false;
let initialized = false;

function processSpeechQueue() {
    if (isSpeaking || speechQueue.length === 0) return;

    isSpeaking = true;
    const utterance = speechQueue.shift();

    utterance.onend = () => {
        isSpeaking = false;
        setTimeout(processSpeechQueue, 200);
    };

    utterance.onerror = (e) => {
        console.error("❌ Error saat menyuarakan:", utterance.text, e);
        isSpeaking = false;
        setTimeout(processSpeechQueue, 200);
    };

    speechSynthesis.speak(utterance);
}

function initializeSpeech() {
    if (initialized) {
        alert("✅ Suara sudah aktif.");
        return;
    }
    initialized = true;

    if (!('speechSynthesis' in window)) {
        alert("❌ Browser tidak mendukung speech synthesis.");
        return;
    }

    function setVoiceAndListen() {
        const voices = speechSynthesis.getVoices();
        indonesianVoice = voices.find(v => v.lang.startsWith('id')) || voices.find(v => v.lang.startsWith('en'));

        if (!indonesianVoice && voices.length > 0) {
            indonesianVoice = voices[0];
        }

        if (!indonesianVoice) {
            alert("⚠️ Tidak ada suara tersedia.");
            return;
        }

        console.log("🗣️ Voice ready:", indonesianVoice.name);

        // Listener for new part requests
        Livewire.on('speak-part-numbers', ({ part_numbers }) => {
            if (!part_numbers?.length) {
                console.warn("⚠️ Tidak ada part number baru yang dikirim.");
                return;
            }

            part_numbers.forEach(pn => {
                const text = `Part baru diminta: ${pn.split('').join(' ')}`; // Spell out the part number
                const u = new SpeechSynthesisUtterance(text);
                u.lang = 'id-ID';
                u.voice = indonesianVoice;
                speechQueue.push(u);
                console.log(`🔵 Queued New: "${text}"`);
            });

            processSpeechQueue();
        });

        // Listener for delayed part requests
        Livewire.on('speak-delayed-part-numbers', ({ part_numbers }) => {
            if (!part_numbers?.length) {
                console.warn("⚠️ Tidak ada part number tertunda yang dikirim.");
                return;
            }

            part_numbers.forEach(pn => {
                const text = `Keterlambatan suplai untuk part: ${pn.split('').join(' ')}`; // Spell out the part number
                const u = new SpeechSynthesisUtterance(text);
                u.lang = 'id-ID';
                u.voice = indonesianVoice;
                speechQueue.push(u);
                console.log(`🟠 Queued Delayed: "${text}"`);
            });

            processSpeechQueue();
        });


        alert("✅ Suara berhasil diaktifkan.");
    }

    if (speechSynthesis.getVoices().length > 0) {
        setVoiceAndListen();
    } else {
        speechSynthesis.onvoiceschanged = setVoiceAndListen;
    }
}
</script>
@endpush
