<?php

use Livewire\Volt\Component;
use App\Models\InvRequestItem;
use App\Models\InvRequest;
use App\Notifications\RequestPushed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

new
#[Layout('components.layouts.app')]
#[Title('List Permintaan Part')]
class extends Component {

    // Data
    public Collection $rows;
    public array $seenReqIds = [];
    public array $announcedDelayedItemIds = []; // Track items announced for delay
    public array $sortBy = ['column' => 'requested_at', 'direction' => 'asc'];

    /**
     * Mount the component and load the initial data.
     */
    public function mount(): void
    {
        // We purposely do NOT pre-fill seen arrays so that
        // the very first poll speaks every existing item.
        $this->rows = collect();
        $this->refreshRows();
    }

    /**
     * Called every 5s by wire:poll.
     * Detects new items and items that have been waiting for over 30 minutes.
     */
    public function refreshRows(): void
    {
        try {
            // Fetch the latest request items, ensuring we sort by the request date
            $freshItems = InvRequestItem::query()
                ->with(['part', 'request.user'])
                ->where('fulfilled', false)
                ->whereHas('part')
                ->whereHas('request')
                ->join('inv_requests', 'inv_request_items.request_id', '=', 'inv_requests.id')
                ->orderBy('inv_requests.requested_at', 'asc') // Sort by request time ascending
                ->limit(50)
                ->get();

            // --- 1.  Detect completely new requests for immediate announcement ---
            $newRequestIds = $freshItems
                ->pluck('request_id')
                ->unique()
                ->filter(fn ($id) => !in_array($id, $this->seenReqIds))
                ->values()
                ->toArray();

            // --- 2.  Collect part numbers from NEW requests for immediate speech ---
            $partNumbersToSpeakImmediately = $freshItems
                ->filter(fn ($item) => in_array($item->request_id, $newRequestIds))
                ->pluck('part.part_number')
                ->toArray();

            // --- 3. Detect DELAYED items (older than 30 mins, not in a new request, and not yet announced) ---
            $thirtyMinutesAgo = Carbon::now()->subMinutes(30);
            $partNumbersToSpeakDelayed = $freshItems
                ->filter(function ($item) use ($newRequestIds, $thirtyMinutesAgo) {
                    // Condition 1: Not part of a brand new request
                    return !in_array($item->request_id, $newRequestIds)
                        // Condition 2: Requested more than 30 minutes ago
                        && $item->request->requested_at->lt($thirtyMinutesAgo)
                        // Condition 3: Not already announced as delayed
                        && !in_array($item->id, $this->announcedDelayedItemIds);
                })
                ->map(function ($item) {
                    // Mark as announced to prevent re-announcing on the next poll
                    $this->announcedDelayedItemIds[] = $item->id;
                    return $item->part->part_number;
                })
                ->values()
                ->toArray();

            // --- 4. Mark new requests as seen ---
            if (!empty($newRequestIds)) {
                $this->seenReqIds = array_unique(array_merge($this->seenReqIds, $newRequestIds));
            }

            // --- 5. Dispatch speech events ---
            if ($partNumbersToSpeakImmediately) {
                $this->dispatch('speak-part-numbers', part_numbers: $partNumbersToSpeakImmediately);
            }
            if ($partNumbersToSpeakDelayed) {
                $this->dispatch('speak-delayed-part-numbers', part_numbers: $partNumbersToSpeakDelayed);
            }

            // --- 6.  Push notification once per new request ---
            foreach ($newRequestIds as $reqId) {
                try {
                    $request = $freshItems->firstWhere('request_id', $reqId)?->request;
                    if ($request && $request->user) {
                        $itemCount = $request->items->count();
                        $url = url('/inventory/list-requests');
                        $notificationId = (string) Str::uuid();

                        $request->user->notify(new RequestPushed($notificationId, $itemCount, $url));
                    }
                } catch (\Throwable $e) {
                    Log::error('Push notification failed', ['request_id' => $reqId, 'error' => $e->getMessage()]);
                }
            }

            // --- 7.  Build rows for the table ---
            $this->rows = $freshItems->map(fn ($item) => [
                'item_id'          => $item->id,
                'request_id'       => $item->request_id,
                'part_number'      => $item->part->part_number,
                'requested_at'     => $item->request->requested_at->diffForHumans(), // Format for humans
                'destination'      => $item->request->destination,                      // Add destination
                'request_quantity' => $item->quantity,
                'request_uom'      => 'KBN',
                'status'           => $item->fulfilled ? 'close' : 'waiting',
                'is_new'           => in_array($item->request_id, $newRequestIds), // highlight entire request
            ]);
        } catch (\Throwable $e) {
            Log::error('refreshRows() error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Allow manual refresh via a dispatched event.
     */
    public function getListeners(): array
    {
        return [
            'refreshRows' => 'refreshRows',
        ];
    }
}; ?>

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
