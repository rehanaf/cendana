<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi - Cendana Corp (A4)</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            background-color: #ffffff !important;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            width: 100%;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            background-color: #ffffff;
            position: relative;
            margin: 0 auto;
        }

        .report-container {
            width: 100%;
            display: flex;
            justify-content: center;
            background-color: #ffffff;
        }

        .report-table {
            border-collapse: collapse;
            font-size: 13px;
            color: #000;
            width: 100%;
            max-width: 620px;
            background-color: #ffffff;
        }

        .report-table td, .report-table th {
            padding: 2px 8px;
            vertical-align: bottom;
        }

        .header-cell {
            background-color: #12335b;
            color: #ffffff;
            text-align: center;
            padding: 12px 10px !important;
            font-weight: bold;
            border-top: 1.5px solid #000;
            border-left: 1.5px solid #000;
            border-right: 1.5px solid #000;
        }

        .header-cell h1 {
            margin: 0;
            font-size: 16px;
            letter-spacing: 0.5px;
        }

        .header-cell h2 {
            margin: 4px 0;
            font-size: 15px;
            letter-spacing: 0.5px;
        }

        .header-cell p {
            margin: 0;
            font-size: 14px;
        }

        .box-left {
            border-left: 1.5px solid #000;
        }

        .box-right {
            border-right: 1.5px solid #000;
        }

        .box-bottom {
            border-bottom: 1.5px solid #000;
        }

        .border-middle {
            border-left: 1.5px solid #000;
        }

        .border-top {
            border-top: 1px solid #000;
        }

        .border-bottom {
            border-bottom: 1px solid #000;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .italic {
            font-style: italic;
        }

        .section-header {
            font-weight: bold;
            text-decoration: underline;
            padding-top: 8px;
            padding-bottom: 2px;
        }

        .indent {
            padding-left: 12px !important;
        }

        .col-detail {
            width: 280px;
        }

        .col-subtotal {
            width: 110px;
        }

        .col-amount {
            width: 120px;
        }

        .percentage-cell {
            text-align: right;
            padding-left: 15px !important;
            white-space: nowrap;
            width: 60px;
        }

        .spacer-row td {
            height: 12px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            html, body {
                background-color: #ffffff !important;
                padding: 0;
                margin: 0;
                display: block;
            }

            .page {
                width: 210mm;
                height: 297mm;
                padding: 15mm 20mm;
                box-shadow: none;
                margin: 0;
                outline: none;
                page-break-after: avoid;
                page-break-inside: avoid;
                background-color: #ffffff;
            }
        }
    </style>
</head>
<body>

    <div class="page">
        <div class="report-container">
            <table class="report-table">
                @php
                    $pct = fn (float $n): string => $basis > 0 ? number_format($n / $basis * 100, 2, '.', ',') . '%' : '0.00%';
                @endphp
                <thead>
                    <tr>
                        <th colspan="3" class="header-cell">
                            <h1>CENDANA CORP</h1>
                            <h2>LAPORAN LABA RUGI</h2>
                            <p>PERIODE {{ mb_strtoupper($periodLabel) }}</p>
                            <p>( DALAM RUPIAH )</p>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $section)
                        @php
                            $isFirst = $loop->first;
                            $isLast = $loop->last;
                            $rowCount = $section['rows']->count();
                        @endphp

                        <tr>
                            <td class="section-header box-left col-detail">{{ $section['label'] }}</td>
                            <td class="col-subtotal"></td>
                            <td class="border-middle box-right col-amount"></td>
                            <td class="percentage-cell"></td>
                        </tr>

                        @foreach ($section['rows'] as $ri => $baris)
                            @php $isLastRow = $ri === $rowCount - 1; @endphp
                            <tr>
                                <td class="indent box-left">{{ $baris['nama'] }}</td>
                                <td class="text-right{{ ($isLast && $isLastRow) ? ' border-bottom' : '' }}">{{ $baris['jumlah_text'] }}</td>
                                <td class="border-middle box-right"></td>
                                <td class="percentage-cell">{{ ! $isFirst && $baris['jumlah'] > 0 ? $pct($baris['jumlah']) : '' }}</td>
                            </tr>
                        @endforeach

                        @if ($isLast)
                        <tr>
                            <td class="text-right bold italic box-left" colspan="2">{{ $section['total_label'] }}</td>
                            <td class="text-right border-middle box-right border-bottom">{{ $section['total_text'] }}</td>
                            <td class="percentage-cell">{{ ! $isFirst && $section['total'] > 0 ? $pct($section['total']) : '' }}</td>
                        </tr>
                        @else
                        <tr>
                            <td class="text-right bold box-left" colspan="2">{{ $section['total_label'] }}</td>
                            <td class="text-right bold border-middle box-right border-top border-bottom">{{ $section['total_text'] }}</td>
                            <td class="percentage-cell">{{ ! $isFirst && $section['total'] > 0 ? $pct($section['total']) : '' }}</td>
                        </tr>
                        @endif

                        <tr class="spacer-row">
                            <td class="box-left" colspan="2"></td>
                            <td class="border-middle box-right"></td>
                            <td class="percentage-cell"></td>
                        </tr>
                    @endforeach

                    <tr>
                        <td class="text-right bold box-left" colspan="2">LABA / RUGI USAHA SEBELUM PAJAK :</td>
                        <td class="text-right bold border-middle box-right border-bottom">{{ $labaRugiText }}</td>
                        <td class="percentage-cell">{{ $labaRugi > 0 ? $pct($labaRugi) : '' }}</td>
                    </tr>

                    <tr style="height: 50px;">
                        <td class="box-left box-bottom" colspan="2"></td>
                        <td class="border-middle box-right box-bottom"></td>
                        <td class="percentage-cell"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($autoPrint)
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
    @endif

</body>
</html>
