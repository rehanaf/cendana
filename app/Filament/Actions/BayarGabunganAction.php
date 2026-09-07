<?php

namespace App\Filament\Actions;

use App\Filament\Actions\Concerns\ResolvesTransactionContext;
use App\Models\Coa;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionReference;
use App\Models\Wallet;
use App\Services\WebhookService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class BayarGabunganAction extends BulkAction
{
    use ResolvesTransactionContext;

    public static function getDefaultName(): ?string
    {
        return 'bayar-gabungan';
    }

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

        $this->label('Bayar Gabungan')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->deselectRecordsAfterCompletion()
            ->modalHeading('Bayar Gabungan')
            ->modalDescription('Semua nota yang dipilih akan dibayar lunas sekaligus dan dicatat sebagai transaksi terpisah yang menunjuk ke satu nomor referensi — sesuai satu mutasi bank. Keterangan akan disimpan pada referensi, bukan disalin ke tiap transaksi.')
            ->modalWidth('2xl')
            ->schema(fn (): array => [
                Select::make('coa_id')
                    ->label('Akun')
                    ->options(fn (): array => Coa::where('is_active', true)
                        ->where('category', $this->coaCategory)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
                        ->toArray()
                    )
                    ->default(fn (): ?int => (int) Setting::get($this->coaSettingKey) ?: null)
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
                DatePicker::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->required()
                    ->default(now()),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->action(function ($records, array $data): void {
                $records = collect($records)
                    ->filter(fn ($record) => (int) ($record->is_retail ?? 0) !== 1)
                    ->filter(fn ($record) => $this->remainingForRecord($record) > 0);

                if ($records->isEmpty()) {
                    return;
                }

                $total = 0;

                [$reference, $transactions] = DB::transaction(function () use ($records, $data, &$total): array {
                    $reference = TransactionReference::query()->create([
                        'reference_no' => TransactionReference::generateReferenceNo(),
                        'description' => $data['description'] ?: null,
                        'user_id' => auth()->id(),
                    ]);

                    $transactions = $records->map(function ($record) use ($data, $reference, &$total): Transaction {
                        $linkColumn = $this->linkColumn;
                        $amount = $this->remainingForRecord($record);
                        $total += $amount;

                        $linkValue = $this->linkedRecordId($record, $linkColumn);
                        $contextRecord = $this->linkedContextRecord($record, $linkColumn, $linkValue);

                        $transaction = Transaction::create([
                            'name' => $record->invoice_no,
                            'user_id' => auth()->id(),
                            'wallet_id' => $data['wallet_id'],
                            'coa_id' => $data['coa_id'],
                            'amount' => $amount,
                            'description' => $this->paymentDescriptionFor($record, $this->linkColumn),
                            'transaction_reference_id' => $reference->id,
                            'transaction_date' => $data['transaction_date'],
                            $linkColumn => $linkValue,
                        ]);

                        app(WebhookService::class)->dispatch($transaction, $contextRecord);

                        return $transaction;
                    });

                    return [$reference, $transactions];
                });

                Notification::make()
                    ->title('Pembayaran gabungan berhasil')
                    ->body($records->count().' nota dibayar, total Rp '.number_format($total, 0, ',', '.').' • Referensi: '.$reference->reference_no)
                    ->success()
                    ->send();
            });
    }
}
