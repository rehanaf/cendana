<?php

namespace App\Filament\Pages\Laporan;

use App\Models\Purchase;
use App\Models\RetailInvoice;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Models\Wallet;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class LaporanNeraca extends BaseReportPage
{
    public static function reportLabel(): string
    {
        return 'Neraca';
    }

    public static function getReportSlug(): string
    {
        return 'laporan-neraca';
    }

    public function getReportIcon(): \BackedEnum | string | null
    {
        return 'heroicon-o-scale';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make(static::reportLabel())
                    ->description('Posisi keuangan per ' . \Carbon\Carbon::parse($this->asOfDate())->format('d F Y'))
                    ->afterHeader($this->reportFilterComponents())
                    ->schema([
                        View::make('filament.pages.laporan.neraca')
                            ->viewData($this->getNeracaData()),
                    ]),
            ]);
    }

    public function getStatsGrid(): \Filament\Schemas\Components\Grid
    {
        $data = $this->getNeracaData();

        return \Filament\Schemas\Components\Grid::make(3)
            ->schema([
                $this->stat('Total Aset', $data['total_aset'], 'success'),
                $this->stat('Total Kewajiban', $data['total_kewajiban'], 'danger'),
                $this->stat('Total Ekuitas', $data['total_ekuitas'], 'info'),
            ]);
    }

    protected function getNeracaData(): array
    {
        $asOf = $this->asOfDate();

        $kas = (float) Wallet::query()
            ->select('wallets.*')
            ->selectRaw('COALESCE((
                SELECT SUM(CASE WHEN c.category = \'pemasukan\' THEN t.amount ELSE -t.amount END)
                FROM transactions t JOIN coas c ON c.id = t.coa_id
                WHERE t.wallet_id = wallets.id AND t.transaction_date <= ?
            ), 0) + COALESCE((
                SELECT SUM(amount) FROM transactions WHERE to_wallet_id = wallets.id AND transaction_date <= ?
            ), 0) as saldo', [$asOf, $asOf])
            ->get()
            ->sum(fn (Wallet $wallet): float => (float) $wallet->saldo);

        $piutang = 0
            + Sale::query()->whereDate('date', '<=', $asOf)->get()->sum(fn (Sale $s): float => $s->sisa)
            + SubscriptionInvoice::query()->whereDate('date', '<=', $asOf)->get()->sum(fn ($s): float => $s->sisa)
            + RetailInvoice::query()->whereDate('date', '<=', $asOf)->get()->sum(fn ($s): float => $s->sisa);

        $utang = Purchase::query()->whereDate('date', '<=', $asOf)->get()->sum(fn (Purchase $p): float => $p->sisa);

        $income = (float) \App\Models\Transaction::query()
            ->whereDate('transaction_date', '<=', $asOf)
            ->whereHas('coa', fn ($q) => $q->where('category', 'pemasukan'))
            ->sum('amount');

        $expense = (float) \App\Models\Transaction::query()
            ->whereDate('transaction_date', '<=', $asOf)
            ->whereHas('coa', fn ($q) => $q->where('category', 'pengeluaran'))
            ->sum('amount');

        $laba = $income - $expense;

        $totalAset = $kas + $piutang;
        $totalKewajiban = $utang;
        $totalEkuitas = $laba;

        return [
            'date' => $asOf,
            'kas' => $kas,
            'piutang' => $piutang,
            'utang' => $utang,
            'laba' => $laba,
            'total_aset' => $totalAset,
            'total_kewajiban' => $totalKewajiban,
            'total_ekuitas' => $totalEkuitas,
        ];
    }

    protected function getQuery()
    {
        return \App\Models\Transaction::query()->whereRaw('0 = 1');
    }

    protected function getColumns(): array
    {
        return [];
    }
}
