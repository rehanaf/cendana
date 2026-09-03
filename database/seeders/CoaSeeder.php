<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CoaSeeder extends Seeder
{
    public function run(): void
    {
        $coas = [
            [1,  '4-1000', 'Pendapatan Jasa', 'income', 'pemasukan', 0],
            [3,  '60100', 'Gaji', 'expense', 'pengeluaran', 1],
            [4,  '60110', 'THR', 'expense', 'pengeluaran', 1],
            [5,  '60300', 'Transport (BBM)', 'expense', 'pengeluaran', 1],
            [6,  '60220', 'Makan Karyawan', 'expense', 'pengeluaran', 1],
            [7,  '60130', 'BPJS', 'expense', 'pengeluaran', 1],
            [8,  '10101', 'Kas Tunai Kantor', 'asset', 'transfer', 1],
            [9,  '20100', 'Utang Usaha', 'liability', 'pengeluaran', 1],
            [10, '10201', 'Bank Mandiri', 'asset', 'transfer', 1],
            [11, '10202', 'Kas Gopay Kantor', 'asset', 'transfer', 1],
            [12, '10300', 'Piutang Usaha', 'asset', 'transfer', 1],
            [13, '10500', 'Persediaan Barang Cendana', 'asset', 'pengeluaran', 1],
            [14, '12300', 'Peralatan Kantor', 'asset', 'pengeluaran', 1],
            [15, '12400', 'Komputer & Laptop', 'asset', 'pengeluaran', 1],
            [16, '12500', 'Peralatan Jaringan', 'asset', 'pengeluaran', 1],
            [17, '30300', 'Prive', 'expense', 'pengeluaran', 1],
            [18, '40100', 'Pendapatan Kontrak Retail Bulanan', 'income', 'pemasukan', 1],
            [19, '40200', 'Pendapatan Kontrak Corporate Bulanan', 'income', 'pemasukan', 1],
            [20, '40300', 'Pendapatan CS Komputer( (Umum & Lain-lain)', 'income', 'pemasukan', 1],
            [21, '40400', 'Pendapatan Project Pemerintahan', 'income', 'pemasukan', 1],
            [22, '40500', 'Pendapatan Project Corporate (Non Bulanan)', 'income', 'pemasukan', 1],
            [23, '60120', 'Bonus', 'expense', 'pengeluaran', 1],
            [24, '60200', 'Listrik', 'expense', 'pengeluaran', 1],
            [25, '60210', 'Air', 'expense', 'pengeluaran', 1],
            [26, '60240', 'ATK', 'expense', 'pengeluaran', 1],
            [27, '60310', 'Service Kendaraan', 'expense', 'pengeluaran', 1],
            [28, '60400', 'Iklan', 'expense', 'pengeluaran', 1],
            [29, '60410', 'Marketing Freelance', 'expense', 'pengeluaran', 1],
            [30, '60500', 'Bandwith Internet', 'expense', 'pengeluaran', 1],
            [31, '60510', 'Maintenance Jaringan Internal', 'expense', 'pengeluaran', 1],
            [32, '60600', 'Perawatan Kantor', 'expense', 'pengeluaran', 1],
            [33, '60620', 'Software Berlangganan', 'expense', 'pengeluaran', 1],
            [34, '80100', 'PPH 21', 'tax', 'pengeluaran', 1],
            [35, '80200', 'PPH 22', 'tax', 'pengeluaran', 1],
            [36, '80300', 'PPH 23', 'tax', 'pengeluaran', 1],
            [37, '80400', 'PPH 25', 'tax', 'pengeluaran', 1],
            [38, '80500', 'PPH Tahunan', 'tax', 'pengeluaran', 1],
            [39, '80600', 'PPN Masukan', 'tax', 'pengeluaran', 1],
            [40, '80700', 'PPN Keluaran', 'tax', 'pengeluaran', 1],
            [41, '60403', 'Biaya Coaching & Pengembangan Karyawan', 'expense', 'pengeluaran', 1],
            [42, '60420', 'Beban Jamuan & Relasi Pelanggan', 'expense', 'pengeluaran', 1],
            [43, '10900', 'Ayat Silang', 'asset', 'pengeluaran', 1],
            [44, '60430', 'Beban Sumbangan & Sosial', 'expense', 'pengeluaran', 1],
            [46, '10901', 'Ayat Penyesuaian Debet', 'asset', 'pemasukan', 1],
            [47, '10902', 'Ayat Penyesuaian Kredit ', 'asset', 'pengeluaran', 1],
        ];

        Schema::disableForeignKeyConstraints();

        DB::table('coas')->delete();

        $now = now();
        $rows = collect($coas)->map(fn (array $r): array => [
            'id' => $r[0],
            'code' => $r[1],
            'name' => $r[2],
            'type' => $r[3],
            'category' => $r[4],
            'is_active' => $r[5],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('coas')->insert($rows);

        Schema::enableForeignKeyConstraints();
    }
}
