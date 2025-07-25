<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
 
new
#[Layout('components.layouts.auth')]
#[Title('Login')]
class extends Component {
 
    #[Rule('required|email')]
    public string $email = '';
 
    #[Rule('required')]
    public string $password = '';
 
    public function mount()
    {
        // It is logged in
        if (auth()->user()) {
            return redirect('/');
        }
    }
 
    public function login()
    {
        $credentials = $this->validate();
 
        if (auth()->attempt($credentials, remember: true)) {
            request()->session()->regenerate();
 
            return redirect()->intended('/');
        }
 
        $this->addError('email', 'The provided credentials do not match our records.');
    }
};?>

<div class="md:w-96">
    <div class="mb-10">
        <x-app-brand />
    </div>
 
    <x-form wire:submit="login">
        <x-input placeholder="E-mail" wire:model="email" icon="o-envelope" />
        <x-input placeholder="Password" wire:model="password" type="password" icon="o-key" />
 
        <x-slot:actions>
            @if(Route::has('register'))
                <x-button label="Create an account" class="btn-ghost" link="{{ route('register') }}" />
            @endif
            <x-button label="Login" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="login" />
        </x-slot:actions>
    </x-form>
</div>
