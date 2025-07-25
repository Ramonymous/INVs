<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Protected Routes
Route::middleware('auth')->group(function () {
    Volt::route('/', 'dashboard')->name('dashboard');

    //Admin Route
    Volt::route('/manage/users', 'admin.users')->name('admin.users');
    Volt::route('/manage/permissions', 'admin.permissions')->name('admin.permissions');
    Volt::route('/manage/childparts', 'admin.childparts')->name('admin.childparts');

    // Inventory Route
    Volt::route('/inventory/dashboard', 'inventory.dashboard')->name('inventory.dashboard');
    Volt::route('/inventory/receivings', 'inventory.receivings')->name('inventory.receivings');
    Volt::route('/inventory/requests', 'inventory.requests')->name('inventory.requests');
    Volt::route('/inventory/list-requests', 'inventory.list-requests')->name('inventory.list-requests');

    // Untuk test template label
    // Route::get('/receipt/{receipt_number}/labels', [\App\Http\Controllers\LabelController::class, 'receiptLabels'])
    //     ->name('receipt.labels');
});

require __DIR__.'/auth.php';