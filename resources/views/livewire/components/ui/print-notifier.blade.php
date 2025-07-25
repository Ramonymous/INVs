<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public bool $isProcessing = false;
    public ?string $downloadUrl = null;
    public ?string $statusMessage = null;

    /**
     * Cek status saat komponen pertama kali dimuat.
     * Ini penting jika pengguna pindah halaman dan kembali lagi.
     */
    public function mount()
    {
        $this->checkStatus(false); // Jangan tandai sudah dibaca saat mount
    }

    /**
     * Dipicu oleh komponen lain untuk memulai proses.
     */
    #[On('print-job-started')]
    public function startProcessing()
    {
        $this->isProcessing = true;
        $this->downloadUrl = null;
        $this->statusMessage = null;
        // Hapus notifikasi lama agar tidak tercampur
        auth()->user()->unreadNotifications()->delete();
    }

    /**
     * Cek notifikasi terbaru dari database.
     */
    public function checkStatus($markAsRead = true)
    {
        $notification = auth()->user()->unreadNotifications()->latest()->first();

        if ($notification && $notification->type === 'App\Notifications\PrintJobCompleted') {
            $this->isProcessing = false; // Hentikan spinner
            $this->downloadUrl = $notification->data['url'];
            $this->statusMessage = $notification->data['message'];
            
            if ($markAsRead) {
                $notification->markAsRead();
            }
        }
    }
}; ?>

<div>
    {{-- Tampilkan spinner jika sedang memproses --}}
    @if($isProcessing)
        <div wire:poll.3s="checkStatus" class="flex items-center gap-4 p-4 bg-blue-100 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-lg">
            <x-loading class="w-8 h-8 text-blue-600 dark:text-blue-400" />
            <div class="dark:text-gray-200">
                <h4 class="font-bold">Memproses PDF, harap tunggu...</h4>
            </div>
        </div>
    @elseif($downloadUrl)
        <div class="flex items-center gap-4 p-4 bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded-lg">
            <x-icon name="o-check-circle" class="w-8 h-8 text-green-600 dark:text-green-400" />
            <div class="dark:text-gray-200">
                <h4 class="font-bold">Selesai!</h4>
                <a href="{{ $downloadUrl }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline font-semibold">{{ $statusMessage }}</a>
            </div>
        </div>
    @elseif($statusMessage)
         <div class="flex items-center gap-4 p-4 bg-yellow-100 dark:bg-yellow-900/50 border border-yellow-200 dark:border-yellow-700 rounded-lg">
             <x-icon name="o-exclamation-triangle" class="w-8 h-8 text-yellow-600 dark:text-yellow-400" />
             <div class="dark:text-gray-200">
                <h4 class="font-bold">Info</h4>
                <p>{{ $statusMessage }}</p>
            </div>
        </div>
    @endif
</div>