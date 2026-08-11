<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Coa;
use App\Models\Wallet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('coa_id')
                    ->label('Nama Transaksi')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(fn (): array =>
                        Coa::where('is_active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Coa $coa): array => [$coa->id => $coa->code . ' - ' . $coa->name])
                            ->toArray()
                    )
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        $category = match (Coa::find($get('coa_id'))?->category) {
                            'pemasukan' => 'Pemasukan',
                            'pengeluaran' => 'Pengeluaran',
                            'transfer' => 'Transfer',
                            default => '-',
                        };
                        $set('kategori', $category);
                    }),
                TextInput::make('kategori')
                    ->label('Kategori')
                    ->disabled()
                    ->dehydrated(false)
                    ->default(fn (Get $get): string => match (Coa::find($get('coa_id'))?->category) {
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                        default => '-',
                    }),
                Select::make('wallet_id')
                    ->label(fn (Get $get): string =>
                        Coa::find($get('coa_id'))?->category === 'transfer' ? 'Dompet Asal' : 'Dompet'
                    )
                    ->options(fn (): array =>
                        Wallet::where('is_active', true)
                            ->get()
                            ->keyBy('id')
                            ->map(fn (Wallet $w): string => $w->name . ' (Rp ' . number_format($w->balance, 0, ',', '.') . ')')
                            ->toArray()
                    )
                    ->default(fn (): ?int => Wallet::where('is_active', true)->orderBy('name')->first()?->id)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $walletId = $get('wallet_id');
                        $toWalletId = $get('to_wallet_id');
                        if ($walletId && $toWalletId == $walletId) {
                            $firstOther = Wallet::where('is_active', true)
                                ->where('id', '!=', $walletId)
                                ->orderBy('name')
                                ->first();
                            $set('to_wallet_id', $firstOther?->id);
                        }
                    })
                    ->required(),
                Select::make('to_wallet_id')
                    ->label('Dompet Tujuan')
                    ->options(fn (Get $get): array =>
                        Wallet::where('is_active', true)
                            ->when($get('wallet_id'), fn ($q, $id) => $q->where('id', '!=', $id))
                            ->get()
                            ->keyBy('id')
                            ->map(fn (Wallet $w): string => $w->name . ' (Rp ' . number_format($w->balance, 0, ',', '.') . ')')
                            ->toArray()
                    )
                    ->default(fn (Get $get): ?int => Wallet::where('is_active', true)
                        ->where('id', '!=', $get('wallet_id'))
                        ->orderBy('name')
                        ->first()?->id
                    )
                    ->visible(fn (Get $get): bool =>
                        Coa::find($get('coa_id'))?->category === 'transfer' && Wallet::where('is_active', true)->count() > 1
                    )
                    ->required(fn (Get $get): bool => Coa::find($get('coa_id'))?->category === 'transfer'),
                TextInput::make('amount')
                    ->label('Jumlah')
                    ->numeric()
                    ->required()
                    ->prefix('Rp'),
                DatePicker::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->required()
                    ->default(now()),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
