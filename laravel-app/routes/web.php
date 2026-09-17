<?php

use App\Http\Controllers\PreOpController;
use App\Http\Controllers\SmartAuthController;
use Illuminate\Support\Facades\Route;

// ─── SMART on FHIR Auth Routes ────────────────────────────────────────────────
Route::get('/smart/launch',       [SmartAuthController::class, 'launchPage'])->name('smart.launch-page');
Route::get('/smart/authorize',    [SmartAuthController::class, 'launch'])->name('smart.launch');
Route::get('/smart/callback',     [SmartAuthController::class, 'callback'])->name('smart.callback');
Route::get('/smart/dev-bypass',   [SmartAuthController::class, 'devBypass'])->name('smart.dev-bypass');

// ─── Root redirect ────────────────────────────────────────────────────────────
Route::get('/', function () {
    // If already authenticated via SMART, go to dashboard
    if (session()->has('smart_access_token')) {
        return redirect()->route('pre-op.dashboard');
    }
    return redirect()->route('smart.launch-page');
});

// ─── Pre-Op Dashboard (requires SMART session) ───────────────────────────────
Route::middleware(['web'])->group(function () {
    Route::get('/pre-op/dashboard', [PreOpController::class, 'dashboard'])->name('pre-op.dashboard');
    Route::post('/pre-op/confirm',  [PreOpController::class, 'confirm'])->name('pre-op.confirm');
    Route::get('/pre-op/export',    [PreOpController::class, 'export'])->name('pre-op.export');
});
