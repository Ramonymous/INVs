<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Notifications\TestPushNotification;

new
#[Layout('components.layouts.app')]
#[Title('Dasbor')]
class extends Component {
    use Toast;

    public bool $showSubscriptionModal = false;
    public string $subscriptionStatus = 'initial'; // Bisa berupa: initial, loading, denied, error, success
    public string $subscriptionError = '';

    /**
     * Saat komponen dimuat, periksa apakah pengguna perlu berlangganan.
     */
    public function mount(): void
    {
        if (!auth()->user()->pushSubscriptions()->exists()) {
            $this->showSubscriptionModal = true;
        }
    }

    /**
     * Kirim notifikasi percobaan ke pengguna yang terautentikasi.
     */
    public function sendTestNotification(): void
    {
        if (!auth()->user()->pushSubscriptions()->exists()) {
            $this->error('Anda belum berlangganan notifikasi.');
            return;
        }

        try {
            // PERBAIKAN: Ini sekarang memberitahu HANYA pengguna yang terautentikasi dengan benar.
            auth()->user()->notify(new TestPushNotification());
            
            $this->success('Notifikasi percobaan terkirim!', 'Silakan periksa perangkat Anda.');
        } catch (\Exception $e) {
            $this->error('Tidak dapat mengirim notifikasi.', 'Silakan periksa pengaturan Anda.');
            Log::error('Kesalahan Notifikasi Push: ' . $e->getMessage());
        }
    }

    /**
     * Dipanggil dari JavaScript frontend dengan data langganan.
     */
    public function subscribe(?string $endpoint, ?string $publicKey, ?string $authToken, ?string $contentEncoding): void
    {
        $this->subscriptionStatus = 'loading';
        $this->subscriptionError = 'Menyimpan detail langganan ke server...';

        try {
            auth()->user()->updatePushSubscription($endpoint, $publicKey, $authToken, $contentEncoding);
            $this->subscriptionStatus = 'success';
            $this->success('Berhasil berlangganan!');
            $this->js("setTimeout(() => { window.location.reload(); }, 1500);");
        } catch (\Exception $e) {
            Log::error('Langganan push gagal: ' . $e->getMessage());
            $this->subscriptionStatus = 'error';
            $this->subscriptionError = 'Tidak dapat menyimpan langganan. Silakan coba lagi.';
            $this->error('Langganan gagal.');
        }
    }

    /**
     * Dipanggil dari JS jika pengguna menolak izin browser.
     */
    public function permissionDenied(): void
    {
        $this->subscriptionStatus = 'denied';
        $this->subscriptionError = 'Anda telah memblokir notifikasi. Harap aktifkan di pengaturan browser Anda untuk melanjutkan.';
    }
};
?>

<div>
    <!-- Header -->
    <x-header title="Dasbor" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            @if(auth()->user()->pushSubscriptions()->exists())
                <span class="badge badge-success">Push AKTIF</span>
            @else
                <span class="badge badge-error">Push NONAKTIF</span>
            @endif
        </x-slot:middle>
    </x-header>

    <!-- Kartu Uji Coba -->
    <x-card title="Uji Coba" class="mb-8">
        @if(auth()->user()->pushSubscriptions()->exists())
            <x-button
                label="Kirim Uji Coba"
                wire:click="sendTestNotification"
                icon="o-bell-alert"
                class="btn-warning"
                spinner="sendTestNotification" />
        @else
             <p>Berlangganan notifikasi untuk mengaktifkan pengujian.</p>
        @endif
    </x-card>

    <!-- Modal Langganan -->
    <x-modal wire:model="showSubscriptionModal" title="Aktifkan Notifikasi" persistent separator>
        @switch($subscriptionStatus)
            @case('initial')
                <div>
                    <p class="mb-4">Untuk tetap mendapatkan informasi terbaru, harap aktifkan notifikasi push.</p>
                    <p class="text-sm text-gray-500">Kami memerlukan izin Anda untuk mengirimi Anda pembaruan penting.</p>
                </div>
                <x-slot:actions>
                    <x-button label="Aktifkan Notifikasi" class="btn-primary" onclick="subscribeToPush()" />
                </x-slot:actions>
                @break

            @case('loading')
                {{-- DIMODIFIKASI: Ini sekarang menampilkan pembaruan status langsung --}}
                <div class="flex flex-col justify-center items-center p-6 min-h-[10rem]">
                    <x-loading class="loading-infinity" />
                    <p class="mt-4 text-sm text-gray-500 italic">{{ $subscriptionError }}</p>
                </div>
                @break

            @case('denied')
            @case('error')
                <x-alert :title="$subscriptionStatus == 'denied' ? 'Izin Ditolak' : 'Terjadi Kesalahan'" :description="$subscriptionError" icon="o-exclamation-triangle" class="alert-warning" />
                <x-slot:actions>
                    <x-button label="Coba Lagi" class="btn-primary" onclick="subscribeToPush()" />
                </x-slot:actions>
                @break

            @case('success')
                <x-alert title="Berhasil!" description="Anda sekarang sudah berlangganan. Jendela ini akan dimuat ulang." icon="o-check-circle" class="alert-success" />
                @break
        @endswitch
    </x-modal>

    @push('footer')
    <script>
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) { outputArray[i] = rawData.charCodeAt(i); }
            return outputArray;
        }

        async function subscribeToPush() {
            @this.set('subscriptionStatus', 'loading');
            @this.set('subscriptionError', 'Menginisialisasi...');

            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                @this.set('subscriptionStatus', 'error');
                @this.set('subscriptionError', 'Notifikasi push tidak didukung oleh browser Anda.');
                return;
            }

            try {
                @this.set('subscriptionError', 'Menunggu izin notifikasi dari Anda...');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    @this.call('permissionDenied');
                    return;
                }

                @this.set('subscriptionError', 'Izin diberikan. Mengakses service worker...');
                const registration = await navigator.serviceWorker.ready;
                
                @this.set('subscriptionError', 'Service worker siap. Memeriksa langganan yang ada...');
                let subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    @this.set('subscriptionError', 'Langganan tidak ditemukan. Membuat yang baru...');
                    const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]').getAttribute('content');
                    if (!vapidPublicKey) throw new Error('Kunci publik VAPID tidak ditemukan di tag meta.');
                    
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                    });
                }
                
                @this.set('subscriptionError', 'Langganan berhasil. Mengirim ke server...');
                const key = subscription.getKey('p256dh');
                const token = subscription.getKey('auth');
                const encoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0];

                @this.call(
                    'subscribe',
                    subscription.endpoint,
                    key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : null,
                    token ? btoa(String.fromCharCode.apply(null, new Uint8Array(token))) : null,
                    encoding
                );

            } catch (error) {
                console.error('Kesalahan Langganan Push:', error);
                @this.set('subscriptionStatus', 'error');
                @this.set('subscriptionError', 'Terjadi kesalahan tak terduga: ' + error.message);
            }
        }
    </script>
    @endpush
</div>
