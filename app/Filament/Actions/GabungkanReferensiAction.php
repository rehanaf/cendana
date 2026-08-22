<?php

namespace App\Filament\Actions;

use App\Models\Transaction;
use App\Models\TransactionReference;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class GabungkanReferensiAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'gabungkan-referensi';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Gabungkan Referensi')
            ->icon('heroicon-o-link')
            ->color('info')
            ->deselectRecordsAfterCompletion()
            ->modalHeading('Gabungkan Referensi')
            ->modalDescription('Semua transaksi yang dipilih akan digabung ke dalam satu nomor referensi. Hanya transaksi dengan kategori sama dan belum memiliki referensi yang dapat digabungkan.')
            ->schema(fn (): array => [
                Textarea::make('description')
                    ->label('Keterangan Referensi')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, BulkAction $action, $records): void {
                $records = collect($records)
                    ->filter(fn ($record): bool => $record instanceof Transaction && ! (bool) $record->is_ref)
                    ->filter(fn (Transaction $record): bool => $record->transaction_reference_id === null);

                if ($records->isEmpty()) {
                    Notification::make()
                        ->title('Tidak ada transaksi yang bisa digabungkan')
                        ->body('Pilih transaksi yang belum memiliki referensi.')
                        ->warning()
                        ->send();
                    $action->halt();
                }

                $categories = $records
                    ->map(fn (Transaction $record): ?string => $record->coa?->category)
                    ->unique()
                    ->values();

                if ($categories->count() > 1) {
                    Notification::make()
                        ->title('Kategori transaksi harus sama')
                        ->body('Transaksi yang dipilih memiliki kategori berbeda: ' . $categories->implode(', ') . '. Pilih hanya transaksi dengan satu kategori yang sama.')
                        ->danger()
                        ->send();
                    $action->halt();
                }

                [$reference, $total] = DB::transaction(function () use ($records, $data): array {
                    $reference = TransactionReference::query()->create([
                        'reference_no' => TransactionReference::generateReferenceNo(),
                        'description' => $data['description'] ?: null,
                        'user_id' => auth()->id(),
                    ]);

                    Transaction::query()
                        ->whereIn('id', $records->pluck('id'))
                        ->update(['transaction_reference_id' => $reference->id]);

                    return [$reference, (float) $records->sum('amount')];
                });

                Notification::make()
                    ->title('Referensi berhasil digabungkan')
                    ->body($records->count() . ' transaksi digabungkan, total Rp ' . number_format($total, 0, ',', '.') . ' • Referensi: ' . $reference->reference_no)
                    ->success()
                    ->send();
            });
    }
}
