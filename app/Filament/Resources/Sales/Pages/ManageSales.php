<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\PaymentDescription;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSales extends ManageRecords
{
    protected static string $resource = SaleResource::class;

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
