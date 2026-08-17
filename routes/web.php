<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\SopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CompanyController::class, 'home'])->name('home');
Route::get('/sop', [SopController::class, 'index'])->name('sop.public');
Route::get('/sop/{sop}/view', [SopController::class, 'viewPdf'])->name('sop.view');
Route::get('/sop/{sop}/download', [SopController::class, 'download'])->name('sop.download');
