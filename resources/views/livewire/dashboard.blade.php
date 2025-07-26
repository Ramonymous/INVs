<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

new
#[Layout('components.layouts.app')]
#[Title('Dasbor')]
class extends Component {
    use Toast;
};
?>

<div>
    <!-- Header -->
    <x-header title="Dasbor" separator progress-indicator/>
</div>
