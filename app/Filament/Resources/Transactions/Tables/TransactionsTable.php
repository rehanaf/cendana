<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Filament\Actions\GabungkanReferensiAction;
use App\Models\Coa;
use App\Models\Transaction;
use App\Models\TransactionReference;
use App\Models\Wallet;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn ($state, Transaction $record): string => $record->is_ref ? 'Referensi' : $state)
                    ->color(fn (Transaction $record): string => match (true) {
                        $record->purchase_id !== null && ($record->is_ref || $record->sumber_type === 'purchase') => 'danger',
                        $record->sale_id !== null || $record->subscription_invoice_id !== null || $record->retail_invoice_id !== null => 'success',
                        default => match ($record->sumber_type) {
                            'sale' => 'success',
                            'purchase' => 'danger',
                            'subscription' => 'info',
                            'retail' => 'primary',
                            default => 'gray',
                        },
                    })
                    ->toggleable(),
                TextColumn::make('coa.code')
                    ->label('Kode')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->is_ref
                        ? $record->coa_codes
                        : $record->coa?->code)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('coa.name')
                    ->label('Nama')
                    ->getStateUsing(fn (Transaction $record): ?string => $record->is_ref
                        ? $record->coa_names
                        : $record->coa?->name)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('reference.reference_no')
                    ->label('Referensi')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coa.category')
                    ->label('Kategori')
                    ->badge()
                    ->getStateUsing(fn (Transaction $record): ?string => $record->is_ref
                        ? ($record->coa_categories ?: null)
                        : $record->coa?->category)
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('wallet.name')
                    ->label('Dompet Asal')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('toWallet.name')
                    ->label('Dompet Tujuan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR', decimalPlaces: 0)
                    ->color(function (Transaction $record): string {
                        $category = $record->is_ref
                            ? strtok((string) $record->coa_categories, ',')
                            : $record->coa?->category;

                        return match ($category) {
                            'pemasukan' => 'success',
                            'pengeluaran' => 'danger',
                            'transfer' => 'warning',
                            default => $record->purchase_id !== null ? 'danger' : 'gray',
                        };
                    })
                    ->sortable()
                    ->toggleable(),
            ])
            ->modifyQueryUsing(function (Builder $query, \Livewire\Component $livewire): Builder {
                $filters = $livewire->tableFilters ?? [];
                $showRefs = filled($filters['tampilkanReferensi']['isActive'] ?? null);
                $kategori = $filters['kategori']['value'] ?? null;
                $sumber = $filters['sumber']['value'] ?? null;

                if (! $showRefs) {
                    if (in_array($kategori, ['pemasukan', 'pengeluaran', 'transfer'])) {
                        $query->whereHas('coa', fn (Builder $q) => $q->where('category', $kategori));
                    }

                    match ($sumber) {
                        'sale' => $query->whereNotNull('sale_id'),
                        'purchase' => $query->whereNotNull('purchase_id'),
                        'subscription' => $query->whereNotNull('subscription_invoice_id'),
                        'retail' => $query->whereNotNull('retail_invoice_id'),
                        'manual' => $query
                            ->whereNull('sale_id')
                            ->whereNull('purchase_id')
                            ->whereNull('subscription_invoice_id')
                            ->whereNull('retail_invoice_id'),
                        default => null,
                    };

                    return $query;
                }

                $plain = (clone $query)
                    ->whereNull('transactions.transaction_reference_id');

                if (in_array($kategori, ['pemasukan', 'pengeluaran', 'transfer'])) {
                    $plain->whereHas('coa', fn (Builder $q) => $q->where('category', $kategori));
                } else {
                    match ($sumber) {
                        'sale' => $plain->whereNotNull('transactions.sale_id'),
                        'purchase' => $plain->whereNotNull('transactions.purchase_id'),
                        'subscription' => $plain->whereNotNull('transactions.subscription_invoice_id'),
                        'retail' => $plain->whereNotNull('transactions.retail_invoice_id'),
                        'manual' => $plain
                            ->whereNull('transactions.sale_id')
                            ->whereNull('transactions.purchase_id')
                            ->whereNull('transactions.subscription_invoice_id')
                            ->whereNull('transactions.retail_invoice_id'),
                        default => null,
                    };
                }

                $plain
                    ->select([
                        'transactions.id',
                        'transactions.name',
                        'transactions.user_id',
                        'transactions.wallet_id',
                        'transactions.to_wallet_id',
                        'transactions.amount',
                        'transactions.description',
                        'transactions.transaction_date',
                        'transactions.coa_id',
                        'transactions.sale_id',
                        'transactions.purchase_id',
                        'transactions.subscription_invoice_id',
                        'transactions.retail_invoice_id',
                    ])
                    ->selectRaw('0 as is_ref, NULL as coa_codes, NULL as coa_names, NULL as coa_categories, NULL as transaction_reference_id');

                $refs = Transaction::query()
                    ->from('transactions as t')
                    ->join('transaction_references as tr', 'tr.id', '=', 't.transaction_reference_id')
                    ->leftJoin('coas as c', 'c.id', '=', 't.coa_id')
                    ->selectRaw('CAST(-MIN(t.id) AS SIGNED) as id, tr.reference_no as name')
                    ->selectRaw('MIN(t.user_id) as user_id, MIN(t.wallet_id) as wallet_id, MIN(t.to_wallet_id) as to_wallet_id')
                    ->selectRaw('SUM(t.amount) as amount')
                    ->selectRaw('tr.description as description, MIN(t.transaction_date) as transaction_date')
                    ->selectRaw('NULL as coa_id')
                    ->selectRaw('MAX(t.sale_id) as sale_id, MAX(t.purchase_id) as purchase_id, MAX(t.subscription_invoice_id) as subscription_invoice_id, MAX(t.retail_invoice_id) as retail_invoice_id')
                    ->selectRaw('1 as is_ref')
                    ->selectRaw("REPLACE(GROUP_CONCAT(DISTINCT c.code), ',', ', ') as coa_codes")
                    ->selectRaw("REPLACE(GROUP_CONCAT(DISTINCT c.name), ',', ', ') as coa_names")
                    ->selectRaw("REPLACE(GROUP_CONCAT(DISTINCT c.category), ',', ', ') as coa_categories")
                    ->selectRaw('t.transaction_reference_id')
                    ->groupBy('t.transaction_reference_id', 'tr.reference_no', 'tr.description');

                if (in_array($kategori, ['pemasukan', 'pengeluaran', 'transfer'])) {
                    $refs->where('c.category', $kategori);
                }

                match ($sumber) {
                    'sale' => $refs->whereNotNull('t.sale_id'),
                    'purchase' => $refs->whereNotNull('t.purchase_id'),
                    'subscription' => $refs->whereNotNull('t.subscription_invoice_id'),
                    'retail' => $refs->whereNotNull('t.retail_invoice_id'),
                    default => null,
                };

                // Bungkus union sebagai subquery ber-alias 'transactions'
                // agar ORDER BY qualified (mis. transactions.id dari paginator) tetap valid.
                return Transaction::query()
                    ->fromSub($plain->unionAll($refs)->toBase(), 'transactions');
            })
            ->filters([
                Filter::make('tampilkanReferensi')
                    ->label('Tampilkan Referensi')
                    ->toggle(),
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                    ]),
                SelectFilter::make('sumber')
                    ->label('Sumber')
                    ->options([
                        'sale' => 'Penjualan',
                        'purchase' => 'Pembelian',
                        'subscription' => 'Langganan',
                        'retail' => 'Retail',
                        'manual' => 'Manual',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (Transaction $record): bool => ! $record->is_ref
                        && (auth()->user()?->isAdmin() || auth()->user()?->hasPermission('edit_transactions'))),
                self::editReferenceAction(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Transaction $record): bool => ! $record->is_ref
                        && (auth()->user()?->isAdmin() || auth()->user()?->hasPermission('delete_transactions'))),
                self::deleteReferenceAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    GabungkanReferensiAction::make()
                        ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('edit_transactions')),
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih')
                        ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('delete_transactions')),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public static function editReferenceAction(): Action
    {
        return Action::make('editReference')
            ->label('Edit Referensi')
            ->icon(Heroicon::PencilSquare)
            ->color('primary')
            ->iconButton()
            ->visible(fn (Transaction $record): bool => (bool) $record->is_ref
                && (auth()->user()?->isAdmin() || auth()->user()?->hasPermission('edit_transactions')))
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $reference = self::resolveReference($action);

                if (! $reference || ! $schema) {
                    return;
                }

                $schema->fill([
                    'reference_no' => $reference->reference_no,
                    'description' => $reference->description,
                    'transactions' => $reference->transactions()
                        ->orderBy('transaction_date')
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Transaction $t): array => ['record-' . $t->id => [
                            'id' => $t->id,
                            'coa_id' => $t->coa_id,
                            'wallet_id' => $t->wallet_id,
                            'amount' => $t->amount,
                            'transaction_date' => $t->transaction_date?->format('Y-m-d'),
                            'description' => $t->description,
                        ]])
                        ->all(),
                ]);
            })
            ->schema([
                Section::make('Data Referensi')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference_no')
                            ->label('No. Referensi')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('description')
                            ->label('Keterangan Referensi')
                            ->maxLength(65535),
                    ]),
                Section::make('Transaksi dalam Referensi')
                    ->description('Ubah atau hapus baris transaksi yang tergabung dalam referensi ini.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('transactions')
                            ->hiddenLabel()
                            ->columns(2)
                            ->minItems(1)
                            ->addable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('id'),
                                Select::make('coa_id')
                                    ->label('Nama Transaksi')
                                    ->options(fn (): array => self::coaOptions())
                                    ->searchable()
                                    ->required(),
                                Select::make('wallet_id')
                                    ->label('Dompet')
                                    ->options(fn (): array => self::walletOptions())
                                    ->searchable()
                                    ->required(),
                                TextInput::make('amount')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->required()
                                    ->prefix('Rp'),
                                DatePicker::make('transaction_date')
                                    ->label('Tanggal Transaksi')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Keterangan')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->action(function (array $data, Action $action): void {
                $reference = self::resolveReference($action);

                if (! $reference) {
                    return;
                }

                if (TransactionReference::where('reference_no', $data['reference_no'])
                    ->whereKeyNot($reference->getKey())
                    ->exists()) {
                    Notification::make()
                        ->title('Gagal menyimpan')
                        ->body('No. referensi sudah digunakan oleh referensi lain.')
                        ->danger()
                        ->send();
                    $action->halt();
                }

                DB::transaction(function () use ($reference, $data): void {
                    $reference->update([
                        'reference_no' => $data['reference_no'],
                        'description' => $data['description'] ?? null,
                    ]);

                    $keptIds = [];

                    foreach ($data['transactions'] ?? [] as $item) {
                        $attributes = collect($item)
                            ->only(['coa_id', 'wallet_id', 'amount', 'transaction_date', 'description'])
                            ->all();

                        $transaction = filled($item['id'] ?? null)
                            ? Transaction::find((int) $item['id'])
                            : null;

                        if ($transaction
                            && (int) $transaction->transaction_reference_id === (int) $reference->getKey()) {
                            $transaction->update($attributes);
                        } else {
                            $transaction = $reference->transactions()->create($attributes + [
                                'user_id' => auth()->id(),
                            ]);
                        }

                        $keptIds[] = $transaction->id;
                    }

                    $reference->transactions()->whereNotIn('id', $keptIds)->get()->each->delete();
                });

                Notification::make()
                    ->title('Referensi berhasil diperbarui')
                    ->success()
                    ->send();
            });
    }

    public static function deleteReferenceAction(): Action
    {
        return Action::make('deleteReference')
            ->label('Hapus Referensi')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->modalHeading('Hapus Referensi')
            ->modalDescription('Semua transaksi yang tergabung dalam referensi ini akan ikut terhapus.')
            ->visible(fn (Transaction $record): bool => (bool) $record->is_ref
                && (auth()->user()?->isAdmin() || auth()->user()?->hasPermission('delete_transactions')))
            ->action(function (Action $action): void {
                $reference = self::resolveReference($action);

                if (! $reference) {
                    return;
                }

                DB::transaction(function () use ($reference): void {
                    $reference->transactions()->get()->each->delete();
                    $reference->delete();
                });

                Notification::make()
                    ->title('Referensi berhasil dihapus')
                    ->success()
                    ->send();
            });
    }

    protected static function resolveReference(Action $action): ?TransactionReference
    {
        $record = $action->getRecord();

        if (! $record instanceof Transaction || ! (bool) $record->is_ref) {
            return null;
        }

        return TransactionReference::find((int) $record->transaction_reference_id);
    }

    protected static function coaOptions(): array
    {
        return Coa::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code . ' - ' . $coa->name])
            ->toArray();
    }

    protected static function walletOptions(): array
    {
        return Wallet::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Wallet $w): array => [$w->id => $w->name . ' (Rp ' . number_format($w->balance, 0, ',', '.') . ')'])
            ->toArray();
    }
}
