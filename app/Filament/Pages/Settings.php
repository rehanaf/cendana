<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transactions\TransactionsColumnDefaults;
use App\Models\Coa;
use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Models\TransactionReference;
use App\Models\Wallet;
use App\Services\WebhookService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class Settings extends Page
{
    public ?int $coa_penjualan_id = null;

    public ?int $wallet_penjualan_id = null;

    public ?int $coa_pembelian_id = null;

    public ?int $wallet_pembelian_id = null;

    public ?int $coa_langganan_id = null;

    public ?int $wallet_langganan_id = null;

    public bool $langganan_auto_generate = false;

    public int $langganan_generate_day = 1;

    public bool $webhook_corporate_enabled = false;

    public string $webhook_corporate_method = 'POST';

    public string $webhook_corporate_url = '';

    public string $webhook_corporate_secret = '';

    public string $webhook_corporate_headers = '';

    public string $webhook_corporate_body = '';

    public ?int $coa_retail_id = null;

    public ?int $wallet_retail_id = null;

    public bool $retail_auto_generate = false;

    public int $retail_generate_day = 1;

    public bool $webhook_retail_enabled = false;

    public string $webhook_retail_method = 'POST';

    public string $webhook_retail_url = '';

    public string $webhook_retail_secret = '';

    public string $webhook_retail_headers = '';

    public string $webhook_retail_body = '';

    /**
     * @var array<int, array{context: string, label: string, columns: array<int, string>}>
     */
    public array $transaction_columns = [];

    public static function getNavigationLabel(): string
    {
        return 'Pengaturan Keuangan';
    }

    public function getTitle(): string
    {
        return 'Pengaturan Keuangan';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengaturan';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->coa_penjualan_id = (int) Setting::get('coa_penjualan_id');
        $this->wallet_penjualan_id = (int) Setting::get('wallet_penjualan_id');
        $this->coa_pembelian_id = (int) Setting::get('coa_pembelian_id');
        $this->wallet_pembelian_id = (int) Setting::get('wallet_pembelian_id');

        $this->coa_langganan_id = (int) Setting::get('coa_langganan_id');
        $this->wallet_langganan_id = (int) Setting::get('wallet_langganan_id');
        $this->langganan_auto_generate = (bool) Setting::get('langganan_auto_generate', false);
        $this->langganan_generate_day = (int) Setting::get('langganan_generate_day', 1);
        $this->loadWebhook('corporate');

        $this->coa_retail_id = (int) Setting::get('coa_retail_id');
        $this->wallet_retail_id = (int) Setting::get('wallet_retail_id');
        $this->retail_auto_generate = (bool) Setting::get('retail_auto_generate', false);
        $this->retail_generate_day = (int) Setting::get('retail_generate_day', 1);
        $this->loadWebhook('retail');

        $this->transaction_columns = $this->resolveTransactionColumns();
    }

    private function resolveTransactionColumns(): array
    {
        $tabs = [['context' => 'all', 'label' => 'Semua']]
            + Wallet::orderBy('name')->get()
                ->mapWithKeys(fn (Wallet $wallet): array => [
                    $wallet->id => ['context' => 'wallet_'.$wallet->id, 'label' => $wallet->name],
                ])
                ->all();

        return collect($tabs)->map(fn (array $tab): array => [
            'context' => $tab['context'],
            'label' => $tab['label'],
            'columns' => TransactionsColumnDefaults::visibleForTab($tab['context'])
                ?? TransactionsColumnDefaults::nativeDefaultVisible(),
        ])->values()->all();
    }

    private function loadWebhook(string $type): void
    {
        $prefix = "webhook_{$type}_";

        $this->{"webhook_{$type}_enabled"} = (bool) Setting::get("{$prefix}enabled", false);
        $this->{"webhook_{$type}_method"} = (string) Setting::get("{$prefix}method", 'POST');
        $this->{"webhook_{$type}_url"} = (string) Setting::get("{$prefix}url", '');
        $this->{"webhook_{$type}_secret"} = (string) Setting::get("{$prefix}secret", '');
        $this->{"webhook_{$type}_headers"} = (string) Setting::get("{$prefix}headers", '');
        $this->{"webhook_{$type}_body"} = (string) Setting::get("{$prefix}body", '');
    }

    private function coaOptions(string $category): array
    {
        return Coa::where('is_active', true)
            ->where('category', $category)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code.' - '.$coa->name])
            ->toArray();
    }

    private function walletOptions(): array
    {
        return Wallet::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Wallet $wallet): array => [$wallet->id => $wallet->name.' (Rp '.number_format($wallet->balance, 0, ',', '.').')'])
            ->toArray();
    }

    private function webhookSection(string $type): array
    {
        $prefix = "webhook_{$type}_";

        return [
            Toggle::make("{$prefix}enabled")
                ->label('Kirim webhook aktif'),
            Select::make("{$prefix}method")
                ->label('Metode HTTP')
                ->options([
                    'POST' => 'POST',
                    'GET' => 'GET',
                    'PUT' => 'PUT',
                    'PATCH' => 'PATCH',
                    'DELETE' => 'DELETE',
                ])
                ->default('POST'),
            TextInput::make("{$prefix}url")
                ->label('URL Webhook')
                ->url()
                ->placeholder('https://example.com/hook/payment'),
            TextInput::make("{$prefix}secret")
                ->label('Secret (header X-Webhook-Secret)')
                ->helperText('Opsional. Dikirim sebagai header tambahan.'),
            Textarea::make("{$prefix}headers")
                ->label('Header Kustom (baris Nama=value)')
                ->helperText('Satu header per baris, value boleh memakai {{variabel}}.')
                ->placeholder("Content-Type=application/json\nAuthorization=Bearer {{customer.code}}")
                ->rows(3),
            Textarea::make("{$prefix}body")
                ->label('Body (Template JSON)')
                ->helperText('Template JSON dengan {{variabel}}. Kosongkan / tidak valid = kirim seluruh konteks JSON.')
                ->placeholder('{"event":"{{event}}","amount":{{transaction.amount}},"customer":"{{customer.name}}","invoice_no":"{{invoice.no}}"}')
                ->rows(5),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun Default')
                    ->description('COA dan dompet yang otomatis terpilih saat membayar melalui tombol Bayar. Masih bisa diubah saat pengisian.')
                    ->schema([
                        Select::make('coa_penjualan_id')
                            ->label('COA Penjualan')
                            ->options(fn (): array => $this->coaOptions('pemasukan'))
                            ->searchable(),
                        Select::make('wallet_penjualan_id')
                            ->label('Dompet Penjualan')
                            ->options(fn (): array => $this->walletOptions())
                            ->searchable(),
                        Select::make('coa_pembelian_id')
                            ->label('COA Pembelian')
                            ->options(fn (): array => $this->coaOptions('pengeluaran'))
                            ->searchable(),
                        Select::make('wallet_pembelian_id')
                            ->label('Dompet Pembelian')
                            ->options(fn (): array => $this->walletOptions())
                            ->searchable(),
                    ])
                    ->columns(2),
                Section::make('Tagihan / Pembayaran Langganan (Corporate)')
                    ->description('COA default, dompet, generate otomatis, dan webhook untuk pembayaran langganan corporate.')
                    ->schema([
                        Select::make('coa_langganan_id')
                            ->label('COA Langganan')
                            ->options(fn (): array => $this->coaOptions('pemasukan'))
                            ->searchable(),
                        Select::make('wallet_langganan_id')
                            ->label('Dompet Langganan')
                            ->options(fn (): array => $this->walletOptions())
                            ->searchable(),
                        Toggle::make('langganan_auto_generate')
                            ->label('Generate otomatis tiap bulan'),
                        Select::make('langganan_generate_day')
                            ->label('Hari Generate')
                            ->options(collect(range(1, 31))->mapWithKeys(fn (int $day): array => [$day => $day])->toArray())
                            ->visible(fn (): bool => $this->langganan_auto_generate),
                        Section::make('Webhook')
                            ->columnSpanFull()
                            ->columns(1)
                            ->collapsible()
                            ->collapsed()
                            ->schema($this->webhookSection('corporate'))
                            ->footerActions([
                                $this->testWebhookAction('corporate'),
                            ]),
                    ])
                    ->columns(3),
                Section::make('Tagihan / Pembayaran Retail')
                    ->description('COA default, dompet, generate otomatis, dan webhook untuk pembayaran retail (per paket internet).')
                    ->schema([
                        Select::make('coa_retail_id')
                            ->label('COA Retail (Akun Pemasukan)')
                            ->helperText('Akun default untuk transaksi pembayaran retail.')
                            ->options(fn (): array => $this->coaOptions('pemasukan'))
                            ->searchable(),
                        Select::make('wallet_retail_id')
                            ->label('Dompet Retail')
                            ->helperText('Dompet default untuk transaksi pembayaran retail.')
                            ->options(fn (): array => $this->walletOptions())
                            ->searchable(),
                        Toggle::make('retail_auto_generate')
                            ->label('Generate otomatis tiap bulan'),
                        Select::make('retail_generate_day')
                            ->label('Hari Generate')
                            ->options(collect(range(1, 31))->mapWithKeys(fn (int $day): array => [$day => $day])->toArray())
                            ->visible(fn (): bool => $this->retail_auto_generate),
                        Section::make('Webhook')
                            ->columnSpanFull()
                            ->columns(1)
                            ->collapsible()
                            ->collapsed()
                            ->schema($this->webhookSection('retail'))
                            ->footerActions([
                                $this->testWebhookAction('retail'),
                            ]),
                    ])
                    ->columns(3),
                Section::make('Kolom Tampil di Tabel Transaksi')
                    ->description('Pilih kolom yang tampil secara default di setiap tab dompet pada halaman Transaksi. Admin berprioritas: pengaturan ini berlaku setiap kali tab tersebut dibuka. Pengguna tetap bisa mengatur ulang lewat menu pengaturan kolom pada tabel.')
                    ->schema([
                        Repeater::make('transaction_columns')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('context'),
                                TextInput::make('label')
                                    ->label('Tab')
                                    ->disabled()
                                    ->dehydrated(false),
                                CheckboxList::make('columns')
                                    ->label('Kolom yang tampil')
                                    ->options(TransactionsColumnDefaults::labels())
                                    ->columns(2)
                                    ->columnSpan(2),
                            ])
                            ->columns(2),
                    ])
                    ->columns(1),
            ]);
    }

    public static function deleteEmptyInvoices(int $year, int $month): array
    {
        return DB::transaction(function () use ($year, $month): array {
            $counts = [];

            foreach ([
                'penjualan' => Sale::class,
                'pembelian' => Purchase::class,
                'langganan' => SubscriptionInvoice::class,
                'retail' => RetailInvoice::class,
            ] as $key => $model) {
                $counts[$key] = $model::query()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->get()
                    ->filter(fn ($invoice): bool => $invoice->journalTransactions()->count() === 0)
                    ->pipe(function ($items) {
                        $items->each->delete();

                        return $items;
                    })
                    ->count();
            }

            return $counts;
        });
    }

    public function save(): void
    {
        Setting::set('coa_penjualan_id', $this->coa_penjualan_id ?: '');
        Setting::set('wallet_penjualan_id', $this->wallet_penjualan_id ?: '');
        Setting::set('coa_pembelian_id', $this->coa_pembelian_id ?: '');
        Setting::set('wallet_pembelian_id', $this->wallet_pembelian_id ?: '');

        Setting::set('coa_langganan_id', $this->coa_langganan_id ?: '');
        Setting::set('wallet_langganan_id', $this->wallet_langganan_id ?: '');
        Setting::set('langganan_auto_generate', $this->langganan_auto_generate ? '1' : '0');
        Setting::set('langganan_generate_day', $this->langganan_generate_day);
        $this->saveWebhook('corporate');

        Setting::set('coa_retail_id', $this->coa_retail_id ?: '');
        Setting::set('wallet_retail_id', $this->wallet_retail_id ?: '');
        Setting::set('retail_auto_generate', $this->retail_auto_generate ? '1' : '0');
        Setting::set('retail_generate_day', $this->retail_generate_day);
        $this->saveWebhook('retail');

        $this->saveTransactionColumns();

        $this->dispatch('refresh-sidebar');
        Notification::make()
            ->success()
            ->title('Pengaturan tersimpan.')
            ->send();
    }

    private function saveTransactionColumns(): void
    {
        $groups = [];

        foreach ($this->transaction_columns as $item) {
            $context = $item['context'] ?? null;
            $columns = $item['columns'] ?? [];

            if (! $context) {
                continue;
            }

            $key = TransactionsColumnDefaults::settingKeyForTab($context);

            if (! $key) {
                continue;
            }

            $groups[$key] = json_encode(array_values(array_intersect($columns, TransactionsColumnDefaults::keys())), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        foreach ($groups as $key => $value) {
            Setting::set($key, $value);
        }
    }

    private function saveWebhook(string $type): void
    {
        $prefix = "webhook_{$type}_";

        Setting::set("{$prefix}enabled", $this->{"webhook_{$type}_enabled"} ? '1' : '0');
        Setting::set("{$prefix}method", $this->{"webhook_{$type}_method"} ?: 'POST');
        Setting::set("{$prefix}url", $this->{"webhook_{$type}_url"} ?: '');
        Setting::set("{$prefix}secret", $this->{"webhook_{$type}_secret"} ?: '');
        Setting::set("{$prefix}headers", $this->{"webhook_{$type}_headers"} ?: '');
        Setting::set("{$prefix}body", $this->{"webhook_{$type}_body"} ?: '');
    }

    private function testWebhookAction(string $type): Action
    {
        $label = $type === 'corporate' ? '(Corporate / Langganan)' : '(Retail)';

        return Action::make("testWebhook_{$type}")
            ->label('Test Webhook')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->outlined()
            ->modalHeading("Test Webhook {$label}")
            ->modalDescription('Mengirim data dummy memakai konfigurasi di atas (nilai yang sedang Anda edit, belum disimpan). Custom payload yang diisi akan menimpa data dummy, selebihnya memakai dummy.')
            ->modalWidth('2xl')
            ->schema([
                Textarea::make('custom_payload')
                    ->label('Custom Payload (baris key=value)')
                    ->helperText('Timpa data dummy, mis. customer.wa=08123456789 atau transaction.amount=200000.')
                    ->rows(4)
                    ->placeholder("customer.wa=08123456789\ntransaction.amount=200000"),
            ])
            ->action(function (Action $action, array $data, WebhookService $webhook) use ($type): void {
                $live = [
                    'method' => $this->{"webhook_{$type}_method"} ?: 'POST',
                    'url' => $this->{"webhook_{$type}_url"} ?? '',
                    'secret' => $this->{"webhook_{$type}_secret"} ?? '',
                    'headers' => $this->{"webhook_{$type}_headers"} ?? '',
                    'body' => $this->{"webhook_{$type}_body"} ?? '',
                ];

                $result = $webhook->test(
                    $type,
                    $live,
                    $this->parseKeyValue((string) ($data['custom_payload'] ?? '')),
                );

                if ($result['ok']) {
                    Notification::make()
                        ->success()
                        ->title('Webhook terkirim')
                        ->body('Status HTTP: '.($result['status'] ?? 'OK'))
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Webhook gagal dikirim')
                        ->body($result['error'] ?? 'Terjadi kesalahan.')
                        ->send();
                }
            });
    }

    private function parseKeyValue(string $input): array
    {
        $output = [];

        foreach (preg_split('/\R/', $input) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $output[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }

        return $output;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearAllTransactions')
                ->label('Kosongkan Semua Transaksi')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Kosongkan Semua Transaksi')
                ->modalDescription('PERINGATAN: Tindakan ini akan menghapus SELURUH catatan transaksi kas dan referensi pembayaran secara permanen, serta mereset saldo semua dompet menjadi Rp 0. Status pembayaran penjualan, pembelian, dan tagihan akan kembali menjadi belum lunas. Tindakan ini tidak dapat dibatalkan. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, Kosongkan Semua')
                ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                ->action(function (): void {
                    $count = Transaction::query()->count();

                    DB::transaction(function (): void {
                        Transaction::query()->delete();
                        TransactionReference::query()->delete();
                        Wallet::query()->update(['balance' => 0]);
                        Sale::all()->each->refreshStatus();
                        Purchase::all()->each->refreshStatus();
                        SubscriptionInvoice::all()->each->refreshStatus();
                        RetailInvoice::all()->each->refreshStatus();
                    });

                    Notification::make()
                        ->success()
                        ->title('Semua transaksi berhasil dikosongkan')
                        ->body("Sebanyak {$count} transaksi dan referensi telah dihapus. Saldo semua dompet telah di-reset ke Rp 0.")
                        ->send();

                    $this->dispatch('refresh-sidebar');
                }),
            Action::make('deleteEmptyInvoices')
                ->label('Hapus Invoice Tanpa Transaksi')
                ->icon('heroicon-o-document-minus')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hapus Invoice / Tagihan Tanpa Transaksi')
                ->modalDescription('Hapus semua invoice penjualan, pembelian, tagihan retail, dan tagihan langganan corporate pada bulan terpilih yang BELUM memiliki transaksi pembayaran sama sekali.')
                ->modalSubmitActionLabel('Ya, Hapus')
                ->schema([
                    Select::make('year')
                        ->label('Tahun')
                        ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                            ->mapWithKeys(fn (int $y): array => [$y => $y])
                            ->toArray())
                        ->default((string) now()->year)
                        ->live(),
                    Select::make('month')
                        ->label('Bulan')
                        ->options([
                            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                        ])
                        ->default('08'),
                ])
                ->action(function (Action $action, array $data): void {
                    $year = (int) $data['year'];
                    $month = (int) $data['month'];

                    $count = static::deleteEmptyInvoices($year, $month);

                    $total = array_sum($count);

                    if ($total === 0) {
                        Notification::make()
                            ->info()
                            ->title('Tidak ada invoice yang dihapus')
                            ->body('Semua invoice periode '.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.$year.' sudah memiliki transaksi pembayaran.')
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Invoice berhasil dihapus')
                        ->body(sprintf(
                            'Penjualan: %d, Pembelian: %d, Langganan: %d, Retail: %d.',
                            $count['penjualan'],
                            $count['pembelian'],
                            $count['langganan'],
                            $count['retail'],
                        ))
                        ->send();
                }),
            Action::make('recalculateBalances')
                ->label('Hitung Ulang Saldo')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hitung Ulang Saldo Dompet')
                ->modalDescription('Saldo semua dompet akan dihitung ulang dari seluruh catatan transaksi. Lanjutkan?')
                ->action(function (): void {
                    $wallets = Wallet::query()->orderBy('name')->get(['id', 'name', 'balance']);

                    Transaction::recalculateWalletBalances($wallets->pluck('id')->all());

                    $corrected = $wallets
                        ->map(fn (Wallet $wallet): array => [
                            'name' => $wallet->name,
                            'before' => (float) $wallet->balance,
                            'after' => (float) $wallet->fresh()->balance,
                        ])
                        ->filter(fn (array $wallet): bool => abs($wallet['before'] - $wallet['after']) > 0.001);

                    if ($corrected->isNotEmpty()) {
                        $detail = $corrected
                            ->map(fn (array $wallet): string => $wallet['name'].' ('.number_format($wallet['before'], 0, ',', '.').' → '.number_format($wallet['after'], 0, ',', '.').')')
                            ->implode(', ');

                        Notification::make()
                            ->warning()
                            ->title('Saldo berhasil dihitung ulang')
                            ->body($wallets->count().' dompet diperiksa, '.$corrected->count().' saldo dikoreksi: '.$detail)
                            ->send();
                    } else {
                        Notification::make()
                            ->success()
                            ->title('Saldo berhasil dihitung ulang')
                            ->body($wallets->count().' dompet diperiksa, semua saldo sudah sesuai.')
                            ->send();
                    }

                    $this->dispatch('refresh-sidebar');
                }),
            Action::make('webhookExample')
                ->label('Contoh Penggunaan Webhook')
                ->icon('heroicon-o-book-open')
                ->color('info')
                ->modalHeading('Contoh Penggunaan Webhook')
                ->modalWidth('3xl')
                ->modalContent(fn (): View => view('filament.webhook-example')),
            Action::make('save')
                ->label('Simpan')
                ->action('save'),
        ];
    }
}
