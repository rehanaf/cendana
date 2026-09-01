<?php

namespace App\Filament\Actions;

use App\Models\InvoiceTemplate;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;

class CetakInvoiceAction extends Action
{
    use InteractsWithRecord;

    protected string $type = '';

    public static function getDefaultName(): ?string
    {
        return 'cetak-invoice';
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
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
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->helperText('Keterangan/item yang tampil di invoice. Bisa diedit sebelum cetak.')
                    ->rows(3)
                    ->default(fn (Model $record): ?string => $record->notes ?? null),
            ])
            ->action(function (Model $record, array $data) {
                if ($record->exists && \Schema::hasColumn($record->getTable(), 'notes')) {
                    $record->notes = $data['keterangan'] ?? null;
                    $record->save();
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