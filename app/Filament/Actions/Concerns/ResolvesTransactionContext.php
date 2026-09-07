<?php

namespace App\Filament\Actions\Concerns;

use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Support\PaymentDescription;
use Illuminate\Database\Eloquent\Model;

trait ResolvesTransactionContext
{
    protected function linkColumnToModelClass(string $linkColumn): ?string
    {
        return match ($linkColumn) {
            'sale_id' => Sale::class,
            'retail_invoice_id' => RetailInvoice::class,
            'subscription_invoice_id' => SubscriptionInvoice::class,
            'purchase_id' => Purchase::class,
            default => null,
        };
    }

    protected function linkedRecordId(Model $record, string $linkColumn): mixed
    {
        $id = $record->getAttribute($linkColumn);

        if ($id !== null) {
            return $id;
        }

        return $record->getKey();
    }

    protected function linkedContextRecord(Model $record, string $linkColumn, mixed $id): ?Model
    {
        $modelClass = $this->linkColumnToModelClass($linkColumn);

        if ($modelClass === null) {
            return $record;
        }

        if ($record instanceof $modelClass) {
            return $record;
        }

        return $modelClass::query()->find($id);
    }

    protected function remainingForRecord(Model $record): float
    {
        $paid = $record->getAttribute('paid');

        if ($paid === null) {
            $paid = (float) $record->total_paid;
        }

        return max(0, (float) $record->total - (float) $paid);
    }

    protected function paymentDescriptionFor(Model $record, string $linkColumn): string
    {
        $context = $this->linkedContextRecord($record, $linkColumn, $this->linkedRecordId($record, $linkColumn));

        if ($context === null) {
            return $this->namePrefix;
        }

        $personName = method_exists($context, 'customer') && $context->customer?->name
            ? $context->customer->name
            : (method_exists($context, 'vendor') ? $context->vendor?->name : null);

        $type = in_array($linkColumn, ['subscription_invoice_id', 'retail_invoice_id'], true)
            ? 'langganan'
            : ($linkColumn === 'sale_id' ? 'penjualan' : 'pembelian');

        return PaymentDescription::make($this->namePrefix, $type, $context->notes ?? null, $personName);
    }
}
