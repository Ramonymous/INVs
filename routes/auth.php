<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Authentication Routes
Volt::route('/login', 'login')->name('login');
// Volt::route('/register', 'register')->name('register');

Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
 
    return redirect('/');
})->name('logout');