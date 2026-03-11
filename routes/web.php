<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');
Route::view('/welcome', 'welcome')->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => view('pages.dashboard'))->name('dashboard');
});

require __DIR__.'/settings.php';
