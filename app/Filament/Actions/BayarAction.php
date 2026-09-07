<?php

namespace App\Filament\Actions;

use App\Filament\Actions\Concerns\ResolvesTransactionContext;
use App\Models\Coa;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WebhookService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

class BayarAction extends Action
{
    use InteractsWithRecord;
    use ResolvesTransactionContext;

    protected string $linkColumn = '';

    protected string $coaSettingKey = '';

    protected string $walletSettingKey = '';

    protected string $namePrefix = 'Pembayaran';

    protected string $coaCategory = 'pemasukan';

    public function linkColumn(string $column): static
    {
        $this->linkColumn = $column;

        return $this;
    }

    public function coaSettingKey(string $key): static
    {
        $this->coaSettingKey = $key;

        return $this;
    }

    public function walletSettingKey(string $key): static
    {
        $this->walletSettingKey = $key;

        return $this;
    }

    public function namePrefix(string $prefix): static
    {
        $this->namePrefix = $prefix;

        return $this;
    }

    public function coaCategory(string $category): static
    {
        $this->coaCategory = $category;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bayar')
            ->icon('heroicon-o-banknotes')
            ->iconButton()
            ->color('warning')
            ->modalHeading('Bayar')
            ->modalWidth('2xl')
            ->schema(fn (): array => $this->getFormSchema())
            ->fillForm(fn (BayarAction $action): array => [
                'coa_id' => (int) Setting::get($this->coaSettingKey) ?: null,
                'wallet_id' => Setting::getWalletId($this->walletSettingKey),
                'amount' => 0,
            ])
            ->action(function (BayarAction $action, array $data): void {
                $record = $action->getRecord();

                if (! $record || (float) $data['amount'] <= 0) {
                    return;
                }

                $linkColumn = $this->linkColumn;
                $linkValue = $this->linkedRecordId($record, $linkColumn);
                $contextRecord = $this->linkedContextRecord($record, $linkColumn, $linkValue);

                $transaction = Transaction::create([
                    'name' => $record->invoice_no,
                    'user_id' => auth()->id(),
                    'wallet_id' => $data['wallet_id'],
                    'coa_id' => $data['coa_id'],
                    'amount' => $data['amount'],
                    'description' => $data['description'] ?: $this->paymentDescriptionFor($record, $this->linkColumn),
                    'transaction_date' => $data['transaction_date'],
                    $linkColumn => $linkValue,
                ]);

                $this->dispatchWebhook($transaction, $contextRecord);
            });
    }

    public function getFormSchema(): array
    {
        $defaultCoa = (int) Setting::get($this->coaSettingKey);

        return [
            Select::make('coa_id')
                ->label('Akun')
                ->options(fn (): array => Coa::where('is_active', true)
                    ->where('category', $this->coaCategory)
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
                    ->toArray()
                )
                ->default($defaultCoa ?: null)
                ->searchable()
                ->required(),
            Select::make('wallet_id')
                ->label('Dompet')
                ->options(fn (): array => Wallet::where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->keyBy('id')
                    ->map(fn (Wallet $w): string => $w->name.' (Rp '.number_format($w->balance, 0, ',', '.').')')
                    ->toArray()
                )
                ->default(fn (): ?int => Setting::getWalletId($this->walletSettingKey))
                ->searchable()
                ->required(),
            TextInput::make('amount')
                ->label('Jumlah')
                ->numeric()
                ->minValue(0)
                ->required()
                ->prefix('Rp')
                ->suffixAction(
                    Action::make('isiSisa')
                        ->label('Isi Sisa')
                        ->icon('heroicon-m-arrow-down-circle')
                        ->color('warning')
                        ->action(function (Set $set): void {
                            $set('amount', $this->remainingForRecord($this->getRecord()));
                        })
                ),
            DatePicker::make('transaction_date')
                ->label('Tanggal Transaksi')
                ->required()
                ->default(now()),
            Textarea::make('description')
                ->label('Keterangan')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    protected function dispatchWebhook(Transaction $transaction, Model $record): Transaction
    {
        app(WebhookService::class)->dispatch($transaction, $record);

        return $transaction;
    }
}
