<?php

namespace App\Filament\Resources\Wallets\Pages;

use App\Filament\Resources\Wallets\WalletResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWallets extends ManageRecords
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Dompet')
                ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('manage_wallets'))
                ->using(function (array $data, string $model): \Illuminate\Database\Eloquent\Model {
                    $initialBalance = (float) ($data['initial_balance'] ?? 0);
                    unset($data['initial_balance']);

                    $wallet = $model::create($data);

                    if ($initialBalance > 0) {
                        $coaId = \App\Models\Coa::where('is_active', true)->where('category', 'pemasukan')->where('code', '4-2000')->value('id')
                            ?? \App\Models\Coa::where('is_active', true)->where('category', 'pemasukan')->first()?->id;

                        \App\Models\Transaction::create([
                            'name' => 'Saldo Awal',
                            'user_id' => auth()->id(),
                            'wallet_id' => $wallet->id,
                            'coa_id' => $coaId,
                            'amount' => $initialBalance,
                            'transaction_date' => now()->format('Y-m-d'),
                            'description' => "Saldo awal pembuatan dompet {$wallet->name}",
                        ]);
                    }

                    return $wallet;
                }),
        ];
    }
}
