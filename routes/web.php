<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profile (account self-deletion removed on purpose — users are deactivated, not deleted)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ── Phase 3: Clients ──
    Route::patch('/clients/{client}/restore', [ClientController::class, 'restore'])
        ->withTrashed()
        ->name('clients.restore');
    Route::resource('clients', ClientController::class);

    // ── Phase 4: Cases (model is LegalCase — `case` is reserved in PHP) ──
    Route::patch('/cases/{legal_case}/restore', [LegalCaseController::class, 'restore'])
        ->withTrashed()
        ->name('cases.restore');
    Route::resource('cases', LegalCaseController::class)
        ->parameters(['cases' => 'legal_case']);

    // ── Phase 5: Documents & attachments ──
    Route::patch('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->withTrashed()
        ->name('documents.restore');
    Route::resource('documents', DocumentController::class);

    Route::post('/documents/{document}/attachments', [DocumentAttachmentController::class, 'store'])
        ->name('documents.attachments.store');
    Route::get('/attachments/{attachment}/download', [DocumentAttachmentController::class, 'download'])
        ->name('attachments.download');
    Route::delete('/attachments/{attachment}', [DocumentAttachmentController::class, 'destroy'])
        ->name('attachments.destroy');

    // ── Placeholders: each gets replaced by a real controller in its phase ──
    Route::view('/search', 'coming-soon', ['title' => 'Search', 'phase' => 7])->name('search');
    Route::view('/reports', 'coming-soon', ['title' => 'Reports', 'phase' => 9])->name('reports.index');

    // ── Admin only ──
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::view('/users', 'coming-soon', ['title' => 'User Management', 'phase' => 8])->name('users.index');
        Route::view('/document-types', 'coming-soon', ['title' => 'Document Types', 'phase' => 8])->name('document-types.index');
        Route::view('/activity-logs', 'coming-soon', ['title' => 'Activity Log', 'phase' => 8])->name('activity-logs.index');
    });
});

require __DIR__.'/auth.php';
