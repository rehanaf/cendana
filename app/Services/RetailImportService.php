<?php

namespace App\Services;

use App\Models\PelangganRetail;
use Carbon\Carbon;

class RetailImportService
{
    /**
     * Import data pelanggan retail dari file CSV.
     *
     * Memetakan kolom CSV ke kolom tabel, otomatis menghubungkan paket internet
     * berdasarkan kecepatan yang dilihat pada kolom "Paket Internet", lalu
     * mengakui (upsert) pelanggan berdasarkan customer_code (ID_Pel).
     *
     * @return array<string, mixed> statistik
     */
    public function import(string $path): array
    {
        if (! is_readable($path)) {
            throw new \RuntimeException("File tidak ditemukan atau tidak dapat dibaca: {$path}");
        }

        $handle = fopen($path, 'r');

        $records = [];
        while (($row = fgetcsv($handle)) !== false) {
            $records[] = array_map('trim', $row);
        }
        fclose($handle);

        $packagesBySpeed = $this->packagesBySpeed();

        $stats = [
            'created' => 0,
            'updated' => 0,
            'rows' => 0,
            'no_package' => 0,
            'issues' => [],
        ];

        $columns = $this->locateColumns($records);

        foreach ($columns['data'] as $row) {
            $code = trim($row[$columns['id_pel']] ?? '');
            $name = trim($row[$columns['name']] ?? '');

            if ($code === '' || $name === '') {
                $stats['issues'][] = "Baris dengan customer_code \"{$code}\" / nama kosong: dilewati";
                continue;
            }

            $packageText = trim($row[$columns['package']] ?? '');
            $priceText = trim($row[$columns['price']] ?? '');
            $packageId = $this->resolvePackageId($packageText, $priceText, $packagesBySpeed);
            if (! $packageId) {
                $stats['no_package']++;
                $stats['issues'][] = "[{$code}] Paket tidak ditemukan: \"{$packageText}\" ({$priceText}) -> internet_package_id NULL";
            }

            $payload = $this->buildPayload($row, $columns);
            $payload['internet_package_id'] = $packageId;

            $customer = PelangganRetail::query()->where('customer_code', $code)->first();
            if ($customer) {
                $customer->update($payload);
                $stats['updated']++;
            } else {
                PelangganRetail::create($payload);
                $stats['created']++;
            }

            $stats['rows']++;
        }

        return $stats;
    }

    /**
     * @return array{id_pel:int,name:int,email:int,subscription_start:int,package:int,price:int,block:int,address:int,wa:int,nik:int,billing:int,username:int,reference:int,data:array}
     */
    protected function locateColumns(array $records): array
    {
        $headerIdx = null;
        foreach ($records as $i => $row) {
            $joined = strtolower(implode('|', $row));
            if (str_contains($joined, 'id_pel') && str_contains($joined, 'nama pelanggan')) {
                $headerIdx = $i;
                break;
            }
        }

        if ($headerIdx === null) {
            throw new \RuntimeException('Header CSV tidak ditemukan (membutuh kolom ID_Pel dt dan Nama Pelanggan).');
        }

        $header = $records[$headerIdx];
        $col = function (string $needle) use ($header): ?int {
            foreach ($header as $i => $label) {
                if (str_contains(strtolower($label), $needle)) {
                    return $i;
                }
            }

            return null;
        };

        $data = array_filter(array_slice($records, $headerIdx + 1), fn ($row) => count(array_filter($row, fn ($v) => $v !== '')) > 0);
        $data = array_values($data);

        return [
            'id_pel' => $col('id_pel') ?? 1,
            'name' => $col('nama pelanggan') ?? 2,
            'email' => $col('email') ?? 0,
            'subscription_start' => $col('tanggal mulai') ?? 0,
            'package' => $col('paket') ?? 0,
            'price' => $col('tarif') ?? 0,
            'block' => $col('lokasi') ?? 0,
            'address' => $col('alamat lengkap') ?? 0,
            'wa' => $col('whatsapp') ?? 0,
            'nik' => $col('nik') ?? 0,
            'billing' => $col('billing') ?? 0,
            'username' => $col('username') ?? 0,
            'reference' => $col('refferal') ?? 0,
            'data' => $data,
        ];
    }

    protected function packagesBySpeed(): array
    {
        $map = [];
        foreach (\App\Models\PaketInternet::query()->where('is_active', true)->get() as $package) {
            $speed = $this->extractSpeed($package->speed);
            if ($speed > 0) {
                $map[$speed] = $package;
            }
        }

        return $map;
    }

    protected function resolvePackageId(string $packageText, string $priceText, array $packagesBySpeed): ?int
    {
        $speed = $this->extractSpeed($packageText);

        if ($speed <= 0 || ! isset($packagesBySpeed[$speed])) {
            return null;
        }

        $candidates = $packagesBySpeed[$speed];

        if (is_object($candidates)) {
            return $candidates->id;
        }

        $price = $this->extractNumber($priceText);
        foreach ($candidates as $package) {
            if ($price > 0 && abs((float) $package->price - $price) < 0.001) {
                return $package->id;
            }
        }

        return $candidates[0]->id;
    }

    protected function extractSpeed(string $value): int
    {
        if (! preg_match('/(\d+)\s*mbps?/i', $value, $m)) {
            return (int) preg_replace('/\D/', '', $value);
        }

        return (int) $m[1];
    }

    protected function parseNumber(string $value): int
    {
        $value = str_replace(['.', ' ', 'Rp'], '', $value);

        return (int) preg_replace('/\D/', '', $value);
    }

    protected function cleanNullable(string $value): ?string
    {
        $value = trim($value);

        return ($value === '' || $value === '-') ? null : $value;
    }

    protected function parseStartDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['n/j/y', 'n/j/Y', 'n/j/y g:ia', 'n/j/Y g:ia'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        foreach (['d/m/y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return  array<string, mixed>
     */
    protected function buildPayload(array $row, array $columns): array
    {
        return [
            'customer_code' => trim($row[$columns['id_pel']] ?? ''),
            'name' => trim($row[$columns['name']] ?? ''),
            'email' => $this->cleanNullable($row[$columns['email']] ?? ''),
            'subscription_start_date' => $this->parseStartDate($row[$columns['subscription_start']] ?? ''),
            'block_location' => $this->cleanNullable($row[$columns['block']] ?? ''),
            'full_address' => $this->cleanNullable($row[$columns['address']] ?? ''),
            'wa' => $this->cleanNullable($row[$columns['wa']] ?? ''),
            'nik' => $this->cleanNullable($row[$columns['nik']] ?? ''),
            'billing_customer_id' => $this->cleanNullable($row[$columns['billing']] ?? ''),
            'billing_username' => $this->cleanNullable($row[$columns['username']] ?? ''),
            'reference' => $this->cleanNullable($row[$columns['reference']] ?? ''),
            'is_active' => true,
        ];
    }
}