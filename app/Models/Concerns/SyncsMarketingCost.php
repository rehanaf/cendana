<?php

namespace App\Models\Concerns;

use App\Models\Coa;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PaymentDescription;
use Illuminate\Database\Eloquent\Model;

/**
 * Sinkronkan transaksi biaya marketing untuk invoice langganan/retail.
 *
 * Biaya marketing di-snapshot per invoice (kolom marketing_cost di invoice),
 * terisi otomatis dari data pelanggan (marketing_cost) saat invoice dibuat,
 * dan bisa diedit manual per invoice. Saat invoice disimpan, transaksi expense
 * dibuat/diperbarui meniru pola Sale::syncMarketingCostTransaction().
 * Transaksi tidak di-link ke subscription_invoice_id / retail_invoice_id
 * (agar tidak merusak perhitungan total_paid / status lunas), cukup ditandai
 * lewat kolom marketing_for_*_invoice_id.
 */
trait SyncsMarketingCost
{
    protected static function bootSyncsMarketingCost(): void
    {
        static::saved(function (Model $invoice) {
            $invoice->snapshotMarketingCost();
            $invoice->syncMarketingCostTransaction();
        });

        static::deleting(function (Model $invoice) {
            Transaction::query()
                ->where($invoice->marketingTransactionColumn(), $invoice->getKey())
                ->get()
                ->each->delete();
        });
    }

    abstract protected function marketingTransactionColumn(): string;

    abstract protected function marketingWalletSettingKey(): string;

    abstract protected function marketingDescriptionType(): string;

    abstract protected function marketingCostColumn(): string;

    protected function snapshotMarketingCost(): void
    {
        if (filled($this->getAttribute($this->marketingCostColumn()))) {
            return;
        }

        $value = (float) ($this->customer?->marketing_cost ?? 0);
        $this->setAttribute($this->marketingCostColumn(), $value);
        static::query()->whereKey($this->getKey())->update([$this->marketingCostColumn() => $value]);
    }

    public function syncMarketingCostTransaction(): void
    {
        $cost = (float) ($this->getAttribute($this->marketingCostColumn()) ?? 0);
        $column = $this->marketingTransactionColumn();

        $existing = Transaction::query()
            ->where($column, $this->getKey())
            ->first();

        if ($cost <= 0) {
            $existing?->delete();

            return;
        }

        $coaId = (int) Setting::get('coa_marketing_id')
            ?: (int) Coa::query()->where('code', '60410')->value('id');

        $coa = $coaId ? Coa::find($coaId) : null;

        if (! $coa || $coa->category === 'transfer') {
            $existing?->delete();

            return;
        }

        $walletId = $this->wallet_id ?? Setting::getWalletId($this->marketingWalletSettingKey());

        if (! $walletId) {
            return;
        }

        $customer = $this->customer;
        $attributes = [
            'name' => 'Biaya Marketing • '.$this->invoice_no,
            'user_id' => (auth()->check() ? auth()->id() : null)
                ?? $this->created_by
                ?? User::query()->value('id'),
            'wallet_id' => $walletId,
            'coa_id' => $coa->id,
            'amount' => $cost,
            'description' => PaymentDescription::make('Biaya Marketing', $this->marketingDescriptionType(), $this->notes, $customer?->name),
            'transaction_date' => $this->date?->format('Y-m-d') ?: now()->toDateString(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        Transaction::query()->create($attributes + [$column => $this->getKey()]);
    }
}
