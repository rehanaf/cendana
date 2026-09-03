<?php

namespace App\Http\Controllers;

use App\Models\Coa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LaporanController extends Controller
{
    public function cetakLabaRugi(Request $request): View
    {
        abort_unless(auth()->check(), 403);

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        $date = Carbon::create($year, $month, 1);
        $periodLabel = $date->translatedFormat('F Y');

        $sections = $request->boolean('preview')
            ? $this->sampleSections()
            : $this->realSections($month, $year);

        $basis = (float) $sections->first()['total'];
        $totalBeban = (float) $sections->slice(1)->sum('total');
        $labaRugi = $basis - $totalBeban;

        return view('filament.pages.laporan.laba-rugi-cetak', [
            'periodLabel' => $periodLabel,
            'sections' => $sections,
            'basis' => $basis,
            'labaRugi' => $labaRugi,
            'labaRugiText' => number_format($labaRugi, 0, '.', ','),
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    protected function realSections(int $month, int $year): Collection
    {
        $build = fn (string $category) => Coa::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Coa $coa) use ($month, $year): array {
                $jumlah = (float) $coa->transactions()
                    ->whereYear('transaction_date', $year)
                    ->whereMonth('transaction_date', $month)
                    ->sum('amount');

                return [
                    'nama' => $coa->name,
                    'jumlah' => $jumlah,
                    'jumlah_text' => number_format($jumlah, 0, '.', ','),
                ];
            })
            ->filter(fn (array $baris): bool => $baris['jumlah'] > 0)
            ->values();

        $pendapatan = $build('pemasukan');
        $beban = $build('pengeluaran');

        return collect([
            $this->makeSection('Pendapatan :', 'Total Pendapatan', $pendapatan),
            $this->makeSection('Beban - Beban :', 'Total Biaya :', $beban),
        ]);
    }

    protected function dummySections(int $month, int $year): Collection
    {
        $rows = function (array $defs) use ($month, $year): Collection {
            return collect($defs)->map(function (array $def) use ($month, $year): array {
                $jumlah = (float) Coa::query()
                    ->whereKey($def['coa_id'])
                    ->where('is_active', true)
                    ->first()
                    ?->transactions()
                    ->whereYear('transaction_date', $year)
                    ->whereMonth('transaction_date', $month)
                    ->sum('amount') ?? 0;

                return [
                    'nama' => $def['teks'],
                    'jumlah' => $jumlah,
                    'jumlah_text' => number_format($jumlah, 0, '.', ','),
                ];
            })->values();
        };

        $penjualan = $rows([
            ['teks' => 'Penjualan Rutin Corporate :', 'coa_id' => 19],
            ['teks' => 'Penjualan Rutin Retail :', 'coa_id' => 18],
            ['teks' => 'Pendapatan dari Piutang bulan sebelumnya', 'coa_id' => 20],
            ['teks' => 'Penjualan Pekerjaan Lain-Lain :', 'coa_id' => 22],
        ]);

        $pembelian = $rows([
            ['teks' => 'Belanja Internet', 'coa_id' => 30],
            ['teks' => 'Pembelian Cash:', 'coa_id' => 13],
            ['teks' => 'Pembelian Transfer:', 'coa_id' => 9],
        ]);

        $biaya = $rows([
            ['teks' => 'Biaya Gaji bulanan', 'coa_id' => 3],
            ['teks' => 'Biaya Marketing', 'coa_id' => 29],
            ['teks' => 'Bea Aktivasi yang dibagikan', 'coa_id' => 23],
            ['teks' => 'Biaya Umum dan Administrasi', 'coa_id' => 26],
            ['teks' => 'Biaya Upah / Honor', 'coa_id' => 42],
            ['teks' => 'Biaya Air & Listrik', 'coa_id' => 24],
            ['teks' => 'Biaya Sosial', 'coa_id' => 44],
            ['teks' => 'Biaya Lain-Lain, ManMin', 'coa_id' => 6],
            ['teks' => 'Biaya Pajak', 'coa_id' => 34],
            ['teks' => 'Biaya BBM + Perawatan Kend', 'coa_id' => 5],
        ]);

        return collect([
            $this->makeSection('Penjualan :', 'Total Penjualan', $penjualan),
            $this->makeSection('Pembelian :', 'Total Pembelian', $pembelian),
            $this->makeSection('Biaya - Biaya', 'Total Biaya :', $biaya),
        ]);
    }

    protected function sampleSections(): Collection
    {
        $row = fn (string $nama, float $jumlah): array => [
            'nama' => $nama,
            'jumlah' => $jumlah,
            'jumlah_text' => number_format($jumlah, 0, '.', ','),
        ];

        $penjualan = collect([
            $row('Penjualan Rutin Corporate :', 44715000),
            $row('Penjualan Rutin Retail :', 25168000),
            $row('Pendapatan dari Piutang bulan sebelumnya', 6023000),
            $row('Penjualan Pekerjaan Lain-Lain :', 8115000),
        ]);

        $pembelian = collect([
            $row('Belanja Internet', 11730915),
            $row('Pembelian Cash:', 2276000),
            $row('Pembelian Transfer:', 9107371),
        ]);

        $biaya = collect([
            $row('Biaya Gaji bulanan', 25783000),
            $row('Biaya Marketing', 1928000),
            $row('Bea Aktivasi yang dibagikan', 560000),
            $row('Biaya Umum dan Administrasi', 1656000),
            $row('Biaya Upah / Honor', 400000),
            $row('Biaya Air & Listrik', 1608004),
            $row('Biaya Sosial', 1160000),
            $row('Biaya Lain-Lain, ManMin', 3342500),
            $row('Biaya Pajak', 33186),
            $row('Biaya BBM + Perawatan Kend', 5096000),
        ]);

        return collect([
            $this->makeSection('Penjualan :', 'Total Penjualan', $penjualan),
            $this->makeSection('Pembelian :', 'Total Pembelian', $pembelian),
            $this->makeSection('Biaya - Biaya', 'Total Biaya :', $biaya),
        ]);
    }

    protected function makeSection(string $label, string $totalLabel, Collection $rows): array
    {
        return [
            'label' => $label,
            'total_label' => $totalLabel,
            'rows' => $rows,
            'total' => (float) $rows->sum('jumlah'),
            'total_text' => number_format((float) $rows->sum('jumlah'), 0, '.', ','),
        ];
    }
}
