<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\PaymentDescription;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManageSales extends ManageRecords
{
    protected static string $resource = SaleResource::class;

    /**
     * Tabel Penjualan memakai query gabungan (unified) dari `sales` + `retail_invoices`
     * melalui subquery alias `penjualan`, sehingga primary key kualifikasi model (sales.id)
     * tidak valid di query itu. Filament me-resolve record aksi baris via find(id),
     * maka overriding ini memastikan resolusi memakai kolom `id` di query gabungan.
     *
     * @return Model|array<string, mixed>|null
     */
    protected function resolveTableRecord(?string $key): Model|array|null
    {
        if ($key === null) {
            return null;
        }

        return SaleResource::unifiedQuery()->where('id', (int) $key)->first();
    }

    public function getSelectedTableRecordsQuery(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Builder
    {
        $query = SaleResource::unifiedQuery();

        if ($this->isTrackingDeselectedTableRecords) {
            $query->whereNotIn('id', array_map('intval', $this->deselectedTableRecords));
        } else {
            $query->whereIn('id', array_map('intval', $this->selectedTableRecords));
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Penjualan')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();
                    $data['coa_id'] = $data['coa_id'] ?? ((int) Setting::get('coa_penjualan_id') ?: null);
                    $data['wallet_id'] = $data['wallet_id'] ?? Setting::getWalletId('wallet_penjualan_id');

                    return $data;
                })
                ->after(function (CreateAction $action, array $data): void {
                    $isLunas = (bool) ($data['pay_now'] ?? false);
                    $sale = $action->getRecord();

                    if ($isLunas) {
                        Transaction::create([
                            'name' => $sale->invoice_no,
                            'user_id' => auth()->id(),
                            'wallet_id' => $sale->wallet_id,
                            'coa_id' => $sale->coa_id,
                            'amount' => $sale->total,
                            'description' => PaymentDescription::make('Pembayaran', 'penjualan', $sale->notes, $sale->customer?->name),
                            'transaction_date' => $sale->date,
                            'sale_id' => $sale->id,
                        ]);
                    }
                }),
        ];
    }
}
