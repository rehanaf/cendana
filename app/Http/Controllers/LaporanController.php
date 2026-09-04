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
        $build = function (array|string $types) use ($month, $year): Collection {
            $types = (array) $types;

            return Coa::query()
                ->whereIn('type', $types)
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
                });
        };

        $penjualan = $build('income');
        $pembelian = $build('cogs');
        $biaya = $build(['expense', 'tax']);

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
