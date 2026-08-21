<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Transaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->color(fn (Transaction $record): string => match ($record->sumber_type) {
                        'sale' => 'success',
                        'purchase' => 'danger',
                        'subscription' => 'info',
                        'retail' => 'primary',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('coa.code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('coa.name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('coa.category')
                    ->label('Kategori')
                    ->badge()
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
                    ->color(fn (Transaction $record): string => match ($record->coa?->category) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('coa', fn (Builder $q) => $q->where('category', $data['value']))
                        : $query),
                SelectFilter::make('sumber')
                    ->label('Sumber')
                    ->options([
                        'sale' => 'Penjualan',
                        'purchase' => 'Pembelian',
                        'subscription' => 'Langganan',
                        'retail' => 'Retail',
                        'manual' => 'Manual',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'sale' => $query->whereNotNull('sale_id'),
                        'purchase' => $query->whereNotNull('purchase_id'),
                        'subscription' => $query->whereNotNull('subscription_invoice_id'),
                        'retail' => $query->whereNotNull('retail_invoice_id'),
                        'manual' => $query
                            ->whereNull('sale_id')
                            ->whereNull('purchase_id')
                            ->whereNull('subscription_invoice_id')
                            ->whereNull('retail_invoice_id'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('edit_transactions')),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('delete_transactions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih')
                        ->visible(fn (): bool => auth()->user()?->isAdmin() || auth()->user()?->hasPermission('delete_transactions')),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc');
    }
}
