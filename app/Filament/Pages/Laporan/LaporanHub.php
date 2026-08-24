<?php

namespace App\Filament\Pages\Laporan;

class LaporanHub
{
    public static function categories(): array
    {
        return [
            'Laporan Lama' => [
                'icon' => 'heroicon-o-archive-box',
                'items' => [
                    ['label' => 'Laporan Keuangan', 'description' => 'Ringkasan pemasukan, pengeluaran, dan transaksi harian', 'url' => \App\Filament\Pages\LaporanKeuangan::getUrl(), 'icon' => 'heroicon-o-document-chart-bar'],
                    ['label' => 'Laporan Penjualan', 'description' => 'Rekap penjualan dari kas dan nota', 'url' => \App\Filament\Pages\LaporanPenjualan::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                    ['label' => 'Laporan Pembelian', 'description' => 'Rekap pembelian dan pengeluaran kas', 'url' => \App\Filament\Pages\LaporanPembelian::getUrl(), 'icon' => 'heroicon-o-shopping-bag'],
                    ['label' => 'Laporan Langganan', 'description' => 'Rekap tagihan langganan corporate', 'url' => \App\Filament\Pages\LaporanLangganan::getUrl(), 'icon' => 'heroicon-o-receipt-percent'],
                    ['label' => 'Laporan Piutang', 'description' => 'Tagihan penjualan yang belum lunas', 'url' => \App\Filament\Pages\LaporanPiutang::getUrl(), 'icon' => 'heroicon-o-arrow-trending-up'],
                    ['label' => 'Laporan Hutang', 'description' => 'Tagihan pembelian yang belum lunas', 'url' => \App\Filament\Pages\LaporanHutang::getUrl(), 'icon' => 'heroicon-o-arrow-trending-down'],
                    ['label' => 'Hutang Pelanggan Corporate', 'description' => 'Saldo tagihan per pelanggan corporate', 'url' => \App\Filament\Pages\LaporanHutangPelanggan::getUrl(), 'icon' => 'heroicon-o-users'],
                ],
            ],
            'Sekilas Bisnis' => [
                'icon' => 'heroicon-o-squares-2x2',
                'items' => [
                    ['label' => 'Neraca', 'description' => 'Posisi aset, kewajiban, dan ekuitas', 'url' => LaporanNeraca::getUrl(), 'icon' => 'heroicon-o-scale'],
                    ['label' => 'Buku Besar', 'description' => 'Rincian transaksi per akun', 'url' => LaporanBukuBesar::getUrl(), 'icon' => 'heroicon-o-book-open'],
                    ['label' => 'Laba Rugi', 'description' => 'Pendapatan dan beban dalam periode', 'url' => LaporanLabaRugi::getUrl(), 'icon' => 'heroicon-o-chart-bar'],
                    ['label' => 'Arus Kas', 'description' => 'Pemasukan dan pengeluaran kas', 'url' => LaporanArusKas::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                ],
            ],
            'Penjualan' => [
                'icon' => 'heroicon-o-shopping-cart',
                'items' => [
                    ['label' => 'Daftar Penjualan', 'description' => 'Semua transaksi penjualan & tagihan', 'url' => LaporanDaftarPenjualan::getUrl(), 'icon' => 'heroicon-o-receipt-refund'],
                    ['label' => 'Penjualan Per Pelanggan', 'description' => 'Rekap penjualan berdasarkan pelanggan', 'url' => LaporanPenjualanPerPelanggan::getUrl(), 'icon' => 'heroicon-o-user-group'],
                    ['label' => 'Piutang Pelanggan', 'description' => 'Tagihan penjualan yang belum lunas', 'url' => LaporanPiutangPelanggan::getUrl(), 'icon' => 'heroicon-o-arrow-trending-up'],
                    ['label' => 'Usia Piutang', 'description' => 'Analisis umur piutang pelanggan', 'url' => LaporanUsiaPiutang::getUrl(), 'icon' => 'heroicon-o-clock'],
                    ['label' => 'Penjualan Per Produk', 'description' => 'Rekap penjualan berdasarkan produk', 'url' => LaporanPenjualanPerProduk::getUrl(), 'icon' => 'heroicon-o-cube'],
                    ['label' => 'Laporan Langganan', 'description' => 'Rekap tagihan langganan corporate', 'url' => LaporanTagihanLangganan::getUrl(), 'icon' => 'heroicon-o-receipt-percent'],
                    ['label' => 'Hutang Pelanggan Corporate', 'description' => 'Saldo tagihan per pelanggan corporate', 'url' => LaporanHutangPelangganCorporate::getUrl(), 'icon' => 'heroicon-o-users'],
                ],
            ],
            'Pembelian' => [
                'icon' => 'heroicon-o-shopping-bag',
                'items' => [
                    ['label' => 'Daftar Pembelian', 'description' => 'Semua transaksi pembelian', 'url' => LaporanDaftarPembelian::getUrl(), 'icon' => 'heroicon-o-receipt-refund'],
                    ['label' => 'Pembelian Per Supplier', 'description' => 'Rekap pembelian berdasarkan supplier', 'url' => LaporanPembelianPerSupplier::getUrl(), 'icon' => 'heroicon-o-truck'],
                    ['label' => 'Utang Supplier', 'description' => 'Pembelian yang belum lunas', 'url' => LaporanUtangSupplier::getUrl(), 'icon' => 'heroicon-o-arrow-trending-down'],
                    ['label' => 'Usia Utang', 'description' => 'Analisis umur utang supplier', 'url' => LaporanUsiaUtang::getUrl(), 'icon' => 'heroicon-o-clock'],
                    ['label' => 'Daftar Pengeluaran', 'description' => 'Rekap transaksi pengeluaran kas', 'url' => LaporanDaftarPengeluaran::getUrl(), 'icon' => 'heroicon-o-arrow-trending-down'],
                    ['label' => 'Pembelian Per Produk', 'description' => 'Rekap pembelian berdasarkan produk', 'url' => LaporanPembelianPerProduk::getUrl(), 'icon' => 'heroicon-o-cube'],
                ],
            ],
            'Produk' => [
                'icon' => 'heroicon-o-cube',
                'items' => [
                    ['label' => 'Ringkasan Persediaan Barang', 'description' => 'Ringkasan stok dan nilai persediaan', 'url' => LaporanRingkasanPersediaan::getUrl(), 'icon' => 'heroicon-o-inbox-stack'],
                    ['label' => 'Detail Persediaan Barang', 'description' => 'Rincian stok per barang', 'url' => LaporanDetailPersediaan::getUrl(), 'icon' => 'heroicon-o-clipboard-document-list'],
                ],
            ],
            'Aset' => [
                'icon' => 'heroicon-o-building-office',
                'items' => [
                    ['label' => 'Ringkasan Aset Tetap', 'description' => 'Ringkasan saldo aset tetap', 'url' => LaporanRingkasanAsetTetap::getUrl(), 'icon' => 'heroicon-o-building-office'],
                    ['label' => 'Detail Aset Tetap', 'description' => 'Rincian transaksi aset tetap', 'url' => LaporanDetailAsetTetap::getUrl(), 'icon' => 'heroicon-o-clipboard-document-list'],
                ],
            ],
            'Bank' => [
                'icon' => 'heroicon-o-building-library',
                'items' => [
                    ['label' => 'Ringkasan Rekonsiliasi Bank', 'description' => 'Ringkasan saldo per rekening/dompet', 'url' => LaporanRekonsiliasiBank::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                    ['label' => 'Mutasi Rekening Koran', 'description' => 'Mutasi transaksi per rekening', 'url' => LaporanMutasiKoran::getUrl(), 'icon' => 'heroicon-o-list-bullet'],
                ],
            ],
            'Pajak' => [
                'icon' => 'heroicon-o-document-text',
                'items' => [
                    ['label' => 'Pajak Pemotongan', 'description' => 'Rekap pajak yang dipotong', 'url' => LaporanPajakPemotongan::getUrl(), 'icon' => 'heroicon-o-banknotes'],
                    ['label' => 'Pajak Penjualan', 'description' => 'Rekap pajak atas penjualan', 'url' => LaporanPajakPenjualan::getUrl(), 'icon' => 'heroicon-o-receipt-percent'],
                ],
            ],
        ];
    }
}
