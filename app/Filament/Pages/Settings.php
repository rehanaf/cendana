<?php

namespace App\Filament\Pages;

use App\Models\Coa;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WebhookService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

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

    public static function getNavigationLabel(): string
    {
        return 'Pengaturan Keuangan';
    }

    public function getTitle(): string
    {
        return 'Pengaturan Keuangan';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Pengaturan';
    }

    public static function getNavigationIcon(): string | BackedEnum | null
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
            ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code . ' - ' . $coa->name])
            ->toArray();
    }

    private function walletOptions(): array
    {
        return Wallet::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Wallet $wallet): array => [$wallet->id => $wallet->name . ' (Rp ' . number_format($wallet->balance, 0, ',', '.') . ')'])
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
            ]);
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

        $this->dispatch('refresh-sidebar');
        Notification::make()
            ->success()
            ->title('Pengaturan tersimpan.')
            ->send();
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
                        ->body('Status HTTP: ' . ($result['status'] ?? 'OK'))
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
                            ->map(fn (array $wallet): string => $wallet['name'] . ' (' . number_format($wallet['before'], 0, ',', '.') . ' → ' . number_format($wallet['after'], 0, ',', '.') . ')')
                            ->implode(', ');

                        Notification::make()
                            ->warning()
                            ->title('Saldo berhasil dihitung ulang')
                            ->body($wallets->count() . ' dompet diperiksa, ' . $corrected->count() . ' saldo dikoreksi: ' . $detail)
                            ->send();
                    } else {
                        Notification::make()
                            ->success()
                            ->title('Saldo berhasil dihitung ulang')
                            ->body($wallets->count() . ' dompet diperiksa, semua saldo sudah sesuai.')
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