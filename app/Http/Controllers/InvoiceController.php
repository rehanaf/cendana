<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    protected const TYPES = [
        'retail' => ['model' => RetailInvoice::class, 'permission' => 'view_retail_invoices'],
        'subscription' => ['model' => SubscriptionInvoice::class, 'permission' => 'view_subscription_invoices'],
        'sale' => ['model' => Sale::class, 'permission' => 'view_sales'],
    ];

    protected const TEMPLATE_SETTINGS = [
        'retail' => 'invoice_template_retail_id',
        'subscription' => 'invoice_template_langganan_id',
        'sale' => 'invoice_template_penjualan_id',
        'purchase' => 'invoice_template_pembelian_id',
    ];

    public function preview(string $type, int $invoice): View
    {
        $config = static::TYPES[$type] ?? null;

        abort_unless($config, 404);

        $user = auth()->user();

        abort_unless($user, 403);
        abort_unless($user->isAdmin() || $user->hasPermission($config['permission']), 403);

        $record = $config['model']::query()->findOrFail($invoice);

        $templateId = (int) request()->query('template')
            ?: (int) Setting::get(static::TEMPLATE_SETTINGS[$type] ?? '', 0);

        $template = InvoiceTemplate::query()
            ->where('is_active', true)
            ->when($templateId, fn ($query) => $query->whereKey($templateId))
            ->orderBy('id')
            ->first();

        return view('invoice', [
            'invoice' => $record,
            'template' => $template,
            'templates' => InvoiceTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
