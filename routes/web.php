<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Communication\InboxController;
use App\Http\Controllers\Communication\MessageController;
use App\Http\Controllers\Communication\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Models\SzablonWiadomosci;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::get('/search', GlobalSearchController::class)
    ->middleware(['auth'])
    ->name('global.search');

// Communication Hub
Route::middleware(['auth'])->group(function () {
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{watek}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{watek}/reply', [InboxController::class, 'reply'])->name('inbox.reply');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');

    // Messages
    Route::post('/messages/preview', [MessageController::class, 'preview'])->name('messages.preview');
    Route::post('/messages/send', [MessageController::class, 'send'])->name('messages.send');
    Route::get('/messages/available-attachments/{watek}', [MessageController::class, 'availableAttachments'])->name('messages.attachments');
    Route::get('/messages/templates', function () {
        return SzablonWiadomosci::all();
    })->name('messages.templates');
});

Route::post('/notes', function () {
    return back()->with('success', 'Notatka zapisana pomyślnie.');
});

// Admin Routes
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    // Users Management
    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    // Roles Management
    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Klienci & Zamowienia (Placeholders for Step 1.2.2 functionality)
    Route::middleware('permission:manage_clients')->group(function () {
        Route::get('/klienci/create', fn () => inertia('Admin/Klienci/Create'))->name('klienci.create');
        Route::get('/klienci/{klient}/edit', fn () => inertia('Admin/Klienci/Edit'))->name('klienci.edit');
    });

    Route::middleware('permission:manage_orders')->group(function () {
        Route::get('/zamowienia/create', fn () => inertia('Admin/Zamowienia/Create'))->name('zamowienia.create');
    });
});
