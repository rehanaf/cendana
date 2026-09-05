<?php

namespace App\Filament\Resources\Transactions;

use App\Models\Setting;

class TransactionsColumnDefaults
{
    public const COLUMNS = [
        'transaction_date' => 'Tanggal',
        'sumber' => 'Sumber',
        'coa.code' => 'Kode',
        'coa.name' => 'Nama',
        'description' => 'Keterangan',
        'reference.reference_no' => 'Referensi',
        'coa.category' => 'Kategori',
        'wallet.name' => 'Dompet Asal',
        'toWallet.name' => 'Dompet Tujuan',
        'amount' => 'Jumlah',
    ];

    /**
     * Kolom yang secara bawaan disembunyikan di tabel Transaksi.
     */
    protected const DEFAULT_HIDDEN = ['reference.reference_no', 'wallet.name', 'toWallet.name'];

    public static function keys(): array
    {
        return array_keys(static::COLUMNS);
    }

    public static function labels(): array
    {
        return static::COLUMNS;
    }

    /**
     * Key setting untuk sebuah tab ('all' atau 'wallet_{id}').
     */
    public static function settingKeyForTab(?string $tab): ?string
    {
        if (blank($tab) || $tab === 'all') {
            return 'transaction_columns_all';
        }

        if (str_starts_with((string) $tab, 'wallet_')) {
            return 'transaction_columns_wallet_'.str_replace('wallet_', '', (string) $tab);
        }

        return null;
    }

    /**
     * Kolom yang terlihat sesuai konfigurasi setting untuk tab tertentu.
     * Mengembalikan null bila tab belum dikonfigurasi (pakai perilaku bawaan tabel).
     */
    public static function visibleForTab(?string $tab): ?array
    {
        $key = static::settingKeyForTab($tab);

        if (! $key) {
            return null;
        }

        $raw = Setting::get($key);

        if (blank($raw)) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_intersect($decoded, static::keys()));
    }

    /**
     * Kolom yang terlihat secara bawaan (tanpa konfigurasi admin).
     */
    public static function nativeDefaultVisible(): array
    {
        return array_values(array_diff(static::keys(), static::DEFAULT_HIDDEN));
    }
}
