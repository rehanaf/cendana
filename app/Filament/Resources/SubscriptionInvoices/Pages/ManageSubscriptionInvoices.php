<?php

namespace App\Filament\Resources\SubscriptionInvoices\Pages;

use App\Filament\Resources\SubscriptionInvoices\SubscriptionInvoiceResource;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\SubscriptionInvoiceService;
use App\Services\WebhookService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscriptionInvoices extends ManageRecords
{
    protected static string $resource = SubscriptionInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInvoice')
                ->label('Generate Invoice')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generate Invoice Bulan Ini')
                ->modalDescription('Buat tagihan langganan untuk semua pelanggan aktif pada periode bulan berjalan. Tagihan yang sudah ada untuk periode tersebut tidak dibuat ulang.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (SubscriptionInvoiceService $service): void {
                    $result = $service->generateForPeriod(now()->year, now()->month);

                    Notification::make()
                        ->success()
                        ->title('Selesai')
                        ->body("Dibuat: {$result['created']}, dilewati: {$result['skipped']}.")
                        ->send();
                }),
            CreateAction::make()
                ->label('Tambah Tagihan')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['created_by'] = auth()->id();
                    $data['coa_id'] = $data['coa_id'] ?? ((int) Setting::get('coa_langganan_id') ?: null);
                    $data['wallet_id'] = $data['wallet_id'] ?? Setting::getWalletId('wallet_langganan_id');

                    return $data;
                })
                ->after(function (CreateAction $action, array $data, WebhookService $webhook): void {
                    $isLunas = (bool) ($data['pay_now'] ?? false);
                    $invoice = $action->getRecord();

                    if ($isLunas) {
                        $transaction = Transaction::create([
                            'name' => $invoice->invoice_no,
                            'user_id' => auth()->id(),
                            'wallet_id' => $invoice->wallet_id,
                            'coa_id' => $invoice->coa_id,
                            'amount' => $invoice->total,
                            'description' => 'Pembayaran ' . $invoice->invoice_no . ' - ' . ($invoice->customer?->name ?? ''),
                            'transaction_date' => $invoice->date,
                            'subscription_invoice_id' => $invoice->id,
                        ]);

                        $webhook->dispatch($transaction, $invoice);
                    }
                }),
        ];
    }
}