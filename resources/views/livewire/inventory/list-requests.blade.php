<?php

use Livewire\Volt\Component;
use App\Models\InvRequestItem;
use App\Models\InvRequest;
use App\Notifications\RequestPushed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

new
#[Layout('components.layouts.app')]
#[Title('List Permintaan Part')]
class extends Component {

    // Data
    public Collection $rows;
    public array $seenItemIds = [];
    public array $seenReqIds  = [];
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
     * Called every 5 s by wire:poll.
     * Detects new items (per item) and new requests (per request).
     */
    public function refreshRows(): void
    {
        try {
            // Fetch the latest request items, ensuring we sort by the request date
            $freshItems = InvRequestItem::query()
                ->with(['part', 'request.user'])
                ->whereHas('part')
                ->whereHas('request')
                ->join('inv_requests', 'inv_request_items.request_id', '=', 'inv_requests.id')
                ->orderBy('inv_requests.requested_at', 'asc') // Sort by request time descending
                ->limit(50)
                ->get();

            // --- 1.  Detect completely new requests ---
            $newRequestIds = $freshItems
                ->pluck('request_id')
                ->unique()
                ->filter(fn ($id) => !in_array($id, $this->seenReqIds))
                ->values()
                ->toArray();

            // --- 2.  Collect every part number in those new requests ---
            $partNumbersToSpeak = $freshItems
                ->filter(fn ($item) => in_array($item->request_id, $newRequestIds))
                ->pluck('part.part_number')
                ->toArray();

            // --- 3.  Mark these requests & items as seen ---
            foreach ($freshItems as $item) {
                $this->seenItemIds[] = $item->id;
                if (!in_array($item->request_id, $this->seenReqIds)) {
                    $this->seenReqIds[] = $item->request_id;
                }
            }

            // --- 4.  Speak all part numbers in the new requests ---
            if ($partNumbersToSpeak) {
                $this->dispatch('speak-part-numbers', part_numbers: $partNumbersToSpeak);
            }

            // --- 5.  Push notification once per new request ---
            foreach ($newRequestIds as $reqId) {
                try {
                    $request = $freshItems->firstWhere('request_id', $reqId)?->request;
                    if ($request && $request->user) {
                        $itemCount = $request->items->count();
                        $url = url('/requests');
                        $notificationId = (string) Str::uuid();

                        $request->user->notify(new RequestPushed($notificationId, $itemCount, $url));
                    }
                } catch (\Throwable $e) {
                    Log::error('Push notification failed', ['request_id' => $reqId, 'error' => $e->getMessage()]);
                }
            }

            // --- 6.  Build rows for the table ---
            $this->rows = $freshItems->map(fn ($item) => [
                'item_id'          => $item->id,
                'request_id'       => $item->request_id,
                'part_number'      => $item->part->part_number,
                'requested_at'     => $item->request->requested_at->diffForHumans(), // Format for humans
                'destination'      => $item->request->destination,                  // Add destination
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
            @if(auth()->user()->pushSubscriptions()->exists())
                <span class="badge badge-success">Push ON</span>
            @else
                <span class="badge badge-secondary">Push OFF</span>
            @endif
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
            {{-- Highlight new rows --}}
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
    document.addEventListener('livewire:init', () => {
        // ... (kode notifikasi desktop tetap sama) ...
        Livewire.on('notify-browser', ({ title, body }) => {
            // ...
        });

        // ---------- Speech Synthesis with Logging ----------
        console.log("🎤 Speech synthesis script initialized.");
        let indonesianVoice = null;
        const speechQueue   = [];
        let isSpeaking      = false;

        function setIndonesianVoice() {
            const voices = speechSynthesis.getVoices();
            indonesianVoice = voices.find(v => v.lang.startsWith('id')) || voices.find(v => v.lang.startsWith('en'));
            if (indonesianVoice) {
                console.log("🗣️ Indonesian voice selected:", indonesianVoice.name);
            } else {
                indonesianVoice = voices[0]; // Fallback to the first available voice
                console.warn("⚠️ Indonesian voice (id-ID) not found. Using default:", indonesianVoice?.name);
            }
        }

        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = setIndonesianVoice;
        } else {
            setIndonesianVoice();
        }

        function processSpeechQueue() {
            if (isSpeaking) {
                console.log("🔵 Queue processor is waiting because another utterance is in progress.");
                return;
            }
            if (speechQueue.length === 0) {
                console.log("✅ Queue is empty. Processor is idle.");
                return;
            }

            isSpeaking = true;
            const utterance = speechQueue.shift();
            // console.info(`▶️ Speaking now: "${utterance.text}"`);

            utterance.onend = () => {
                // console.log(`✔️ Finished speaking: "${utterance.text}"`);
                isSpeaking = false;
                setTimeout(processSpeechQueue, 200); // Process next item after a short delay
            };

            utterance.onerror = (event) => {
                console.error(`❌ Speech synthesis error for "${utterance.text}":`, event.error);
                isSpeaking = false;
                setTimeout(processSpeechQueue, 200); // Try to process the next item anyway
            };

            speechSynthesis.speak(utterance);
        }

        Livewire.on('speak-part-numbers', ({ part_numbers }) => {
            console.log("🎧 Event 'speak-part-numbers' received with data:", part_numbers);

            if (!('speechSynthesis' in window)) {
                console.error("❌ Speech synthesis is not supported in this browser.");
                return;
            }
            if (!part_numbers?.length) {
                console.warn("⚠️ Event received but no part numbers to speak.");
                return;
            }

            setIndonesianVoice(); // Ensure voice is ready

            part_numbers.forEach(pn => {
                const text = `Part baru diminta: ${pn}`;
                const u = new SpeechSynthesisUtterance(text);
                u.lang = 'id-ID';
                u.voice = indonesianVoice;
                speechQueue.push(u);
                console.log(`🔵 Queued: "${text}"`);
            });

            processSpeechQueue();
        });
    });
</script>
@endpush
