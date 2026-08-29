<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CompanyController::class, 'home'])->name('home');
Route::get('/sop', [SopController::class, 'index'])->name('sop.public');
Route::get('/sop/{sop}/view', [SopController::class, 'viewPdf'])->name('sop.view');
Route::get('/sop/{sop}/download', [SopController::class, 'download'])->name('sop.download');
Route::get('/invoice/template/{template}/preview', function (\App\Models\InvoiceTemplate $template) {
    abort_unless(auth()->check(), 403);

    $invoice = (object) [
        'invoice_no' => 'TEST-202608-001',
        'customer' => (object) [
            'name' => 'HOTEL ZAMRUD',
            'full_address' => 'Jl.Wahidin Sudirohusodo Cirebon - Jabar',
            'block_location' => 'Blok A',
            'wa' => '081300688753',
        ],
        'date' => \Illuminate\Support\Carbon::parse('2026-08-11'),
        'due_date' => \Illuminate\Support\Carbon::parse('2026-08-26'),
        'total' => 2000000,
        'sisa' => 2000000,
        'items' => [
            ['description' => 'Jasa Pemeliharaan & Akses Internet', 'qty' => 1, 'price' => 2000000, 'amount' => 2000000],
        ],
    ];

    return view('invoice', ['invoice' => $invoice, 'template' => $template]);
})->name('invoice.template.preview');
Route::get('/invoice/{type}/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoice.preview');
Route::get('/test-invoice', function () {
    $invoice = (object) [
        'invoice_no' => 'TEST-202608-001',
        'customer' => (object) [
            'name' => 'HOTEL ZAMRUD',
            'full_address' => 'Jl.Wahidin Sudirohusodo Cirebon - Jabar',
            'block_location' => 'Blok A',
            'wa' => '081300688753',
        ],
        'date' => \Illuminate\Support\Carbon::parse('2026-08-11'),
        'due_date' => \Illuminate\Support\Carbon::parse('2026-08-26'),
        'total' => 2000000,
        'sisa' => 2000000,
        'items' => [
            ['description' => 'Jasa Pemeliharaan & Akses Internet', 'qty' => 1, 'price' => 2000000, 'amount' => 2000000],
        ],
    ];

    $templateId = (int) request()->query('template')
        ?: (int) \App\Models\Setting::get('invoice_template_penjualan_id');

    $template = \App\Models\InvoiceTemplate::query()
        ->where('is_active', true)
        ->when($templateId, fn ($query) => $query->whereKey($templateId))
        ->orderBy('id')
        ->first();

    return view('invoice', [
        'invoice' => $invoice,
        'template' => $template,
        'templates' => \App\Models\InvoiceTemplate::query()->where('is_active', true)->orderBy('name')->get(),
    ]);
})->name('test-invoice');
