<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Setting;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchases extends ManageRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pembelian')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();
                    $data['coa_id'] = $data['coa_id'] ?? ((int) Setting::get('coa_pembelian_id') ?: null);
                    $data['wallet_id'] = $data['wallet_id'] ?? Setting::getWalletId('wallet_pembelian_id');

                    return $data;
                })
                ->after(function (CreateAction $action, array $data): void {
                    $isLunas = (bool) ($data['pay_now'] ?? false);
                    $purchase = $action->getRecord();

                    if ($isLunas) {
                        Transaction::create([
                            'name' => $purchase->invoice_no,
                            'user_id' => auth()->id(),
                            'wallet_id' => $purchase->wallet_id,
                            'coa_id' => $purchase->coa_id,
                            'amount' => $purchase->total,
                            'description' => 'Pembayaran ' . $purchase->invoice_no . ' - ' . ($purchase->vendor?->name ?? ''),
                            'transaction_date' => $purchase->date,
                            'purchase_id' => $purchase->id,
                        ]);
                    }
                }),
        ];
    }
}