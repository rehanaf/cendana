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

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('coa.code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coa.name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
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
                    ->sortable(),
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
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('coa.category')
                    ->label('Kategori')
                    ->relationship('coa', 'category')
                    ->options([
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                    ]),
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
