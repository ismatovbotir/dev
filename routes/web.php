<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RegistrationStepController;
use App\Http\Controllers\Admin\RequestController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{shopRequest}', [RequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{shopRequest}/reply', [RequestController::class, 'reply'])->name('requests.reply');
        Route::patch('/requests/{shopRequest}/status', [RequestController::class, 'updateStatus'])->name('requests.status');
        Route::get('/requests/{shopRequest}/drawing', [RequestController::class, 'drawing'])->name('requests.drawing');
        Route::get('/requests/{shopRequest}/attachments/{index}', [RequestController::class, 'attachment'])->whereNumber('index')->name('requests.attachment');

        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');

        Route::get('/steps', [RegistrationStepController::class, 'index'])->name('steps.index');
        Route::get('/steps/create', [RegistrationStepController::class, 'create'])->name('steps.create');
        Route::post('/steps', [RegistrationStepController::class, 'store'])->name('steps.store');
        Route::get('/steps/{registrationStep}/edit', [RegistrationStepController::class, 'edit'])->name('steps.edit');
        Route::put('/steps/{registrationStep}', [RegistrationStepController::class, 'update'])->name('steps.update');
        Route::patch('/steps/{registrationStep}/toggle', [RegistrationStepController::class, 'toggle'])->name('steps.toggle');
        Route::post('/steps/{registrationStep}/move', [RegistrationStepController::class, 'move'])->name('steps.move');
        Route::delete('/steps/{registrationStep}', [RegistrationStepController::class, 'destroy'])->name('steps.destroy');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::post('/settings/plan-examples', [SettingsController::class, 'updatePlanExamples'])->name('settings.plan-examples');
        Route::get('/settings/plan-examples/{example}', [SettingsController::class, 'planExample'])->whereNumber('example')->name('settings.plan-example');
        Route::post('/settings/bot-check', [SettingsController::class, 'checkBot'])->name('settings.bot-check');
    });
});
