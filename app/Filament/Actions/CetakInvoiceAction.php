<?php

namespace App\Filament\Actions;

use App\Models\InvoiceTemplate;
use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CetakInvoiceAction extends Action
{
    use InteractsWithRecord;

    protected string $type = '';

    protected string $keyColumn = '';

    public static function getDefaultName(): ?string
    {
        return 'cetak-invoice';
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function keyColumn(string $column): static
    {
        $this->keyColumn = $column;

        return $this;
    }

    protected function typeToModelClass(string $type): ?string
    {
        return match ($type) {
            'retail' => RetailInvoice::class,
            'subscription' => SubscriptionInvoice::class,
            'sale' => Sale::class,
            'purchase' => Purchase::class,
            default => null,
        };
    }

    protected function templateSettingKey(): ?string
    {
        return [
            'retail' => 'invoice_template_retail_id',
            'subscription' => 'invoice_template_langganan_id',
            'sale' => 'invoice_template_penjualan_id',
            'purchase' => 'invoice_template_pembelian_id',
        ][$this->type] ?? null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Cetak Invoice')
            ->icon('heroicon-o-printer')
            ->iconButton()
            ->color('primary')
            ->tooltip('Cetak Invoice')
            ->modalHeading('Cetak Invoice')
            ->modalSubmitActionLabel('Cetak')
            ->modalWidth('sm')
            ->form([
                Select::make('template')
                    ->label('Template Invoice')
                    ->options(fn (): array => InvoiceTemplate::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => (int) Setting::get($this->templateSettingKey() ?? '', 0) ?: null)
                    ->required(),
                TextInput::make('total')
                    ->label('Total Tagihan')
                    ->helperText('Bisa diubah sebagian, misalnya tagihan Rp 1.000.000 dipecah menjadi Rp 400.000 di invoice ini.')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->default(fn (Model $record): mixed => $record->total ?? null)
                    ->visible(fn (Model $record): bool => \Schema::hasColumn($record->getTable(), 'total')),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->helperText('Keterangan/item yang tampil di invoice. Bisa diedit sebelum cetak. Tekan Enter untuk membuat baris tabel baru.')
                    ->rows(3)
                    ->default(fn (Model $record): ?string => $record->notes ?? null),
            ])
            ->action(function (Model $record, array $data) {
                $modelClass = $this->typeToModelClass($this->type);

                if ($modelClass === null) {
                    return;
                }

                $id = $this->keyColumn && $record->getAttribute($this->keyColumn) !== null
                    ? $record->getAttribute($this->keyColumn)
                    : $record->getKey();

                $record = $modelClass::query()->find($id);

                if (! $record) {
                    return;
                }

                $changes = [];

                if ($record->exists && \Schema::hasColumn($record->getTable(), 'notes')) {
                    $record->notes = $data['keterangan'] ?? null;
                }

                if (\Schema::hasColumn($record->getTable(), 'total') && filled($data['total'] ?? null)) {
                    $total = round((float) $data['total'], 2);
                    $paid = (float) ($record->total_paid ?? 0);

                    if ($total < $paid) {
                        Notification::make()
                            ->title('Total tidak boleh lebih kecil dari yang sudah dibayar')
                            ->body('Sudah dibayar Rp '.number_format($paid, 0, ',', '.').'.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if ($total !== round((float) $record->total, 2)) {
                        $record->total = $total;
                        $changes[] = 'total';
                    }
                }

                if ($record->exists && $record->isDirty()) {
                    $record->save();
                }

                if ($changes !== [] && method_exists($record, 'refreshStatus')) {
                    $record->refreshStatus();
                }

                return redirect()->route('invoice.preview', [
                    'type' => $this->type,
                    'invoice' => $record->getKey(),
                    'template' => $data['template'],
                    'print' => 1,
                ]);
            })
            ->hidden(fn (Model $record): bool => blank($this->type) || $record->getKey() === null);
    }
}
