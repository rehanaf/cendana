<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<title>Test Invoice</title>
<style>
html,body{margin:0;background:#fff;}
body{font-family:Arial,sans-serif;color:#000;}
table.grid{border-collapse:collapse;table-layout:fixed;width:664px;margin:0 auto;}
table.grid tr{height:21px;}
table.grid td{padding:0;border:none;vertical-align:top;font-size:15px;}
table.grid td.ct{padding:2px 3px;}
table.grid tr.bt td{border-top:1px solid #b7b7b7;}
table.grid tr.bb td{border-bottom:1px solid #b7b7b7;}
.bgc{background:#fff2cc;}
table.grid tr.r50{height:50px;}
table.grid tr.r42{height:42px;}
table.grid tr.r71{height:71px;}
.t1{font-size:43.5px;line-height:1;}
.fb{font-weight:bold;}
.fi{font-style:italic;}
.f18{font-size:18px;}
.ar{text-align:right;}
.ac{text-align:center;}
table.grid tr.vc td{vertical-align:middle;}
td.imgcell{position:relative;height:100%;}
.imgwrap{position:absolute;inset:0;overflow:hidden;}
  .imgwrap.xc{display:flex;justify-content:center;align-items:center;}
  .imgwrap img{display:block;object-fit:contain;object-position:top center;}
.imgwrap .imgph{height:100%;box-sizing:border-box;background:#f3f3f3;border:1px dashed #bbb;color:#999;font-size:12px;display:flex;align-items:center;justify-content:center;}
.imgwrap.xc .imgph{width:100%;}
.inv-toolbar{display:flex;align-items:center;gap:8px;max-width:664px;margin:0 auto 8px;font-size:13px;color:#374151;}
.inv-toolbar label{margin:0;}
.inv-toolbar select{padding:6px 10px;font-size:14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#111827;outline:none;}
.inv-toolbar select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.inv-btn{padding:8px 16px;font-size:14px;font-family:Arial,sans-serif;border:1px solid #2563eb;background:#2563eb;color:#fff;border-radius:6px;cursor:pointer;margin-left:auto;}
.inv-btn:hover{background:#1d4ed8;}
.no-print{display:block;}
  .page-shell{padding:16px;}
  .page-sheet{background:#fff;max-width:calc(664px + 24mm);margin:0 auto;padding:12mm;box-shadow:0 2px 24px rgba(0,0,0,.12);}
  @media print{
    @page{size:A4;margin:12mm;}
    html,body{margin:0;}
    body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .page-shell{padding:0;}
    .page-sheet{max-width:none;padding:0;margin:0;box-shadow:none;background:#fff;}
    .grid{width:664px;margin:0 auto;}
    .no-print{display:none !important;}
  }
</style>
</head>
<body>
  @php
      $tpl = $template ?? null;

      $companyName = trim((string) ($tpl?->company_name ?? ''));
      $companyAddress = $tpl?->company_address ?? '';
      $companyLines = $companyAddress !== '' && $companyAddress !== null
          ? preg_split('/\R/', trim($companyAddress))
          : [];

      $footerText = $tpl?->footer_text ?? '';
      $footerLines = $footerText !== '' && $footerText !== null
          ? preg_split('/\R/', trim($footerText))
          : [];

      $logoImage = $tpl?->logo_image ?? '';
      $logoWidth = trim((string) ($tpl?->logo_width ?? ''));
      $logoHeight = trim((string) ($tpl?->logo_height ?? ''));
      $signatureImage = $tpl?->signature_image ?? '';
      $signatureWidth = trim((string) ($tpl?->signature_width ?? ''));
      $signatureHeight = trim((string) ($tpl?->signature_height ?? ''));

      $imgSrc = function (string $path): string {
          if ($path === '') {
              return '';
          }
          if (str_starts_with($path, 'http') || str_starts_with($path, 'data:')) {
              return $path;
          }
          return asset('storage/' . $path);
      };

      $imgStyle = function (string $width, string $height): string {
          $hasW = $width !== '';
          $hasH = $height !== '';
          $w = $hasW ? "width:{$width}px;" : 'width:auto;';
          $h = $hasH
              ? "height:{$height}px;"
              : ($hasW ? 'height:auto;' : 'height:100%;');
          return $w . $h . 'max-width:100%;object-fit:contain;';
      };
      $logoStyle = $imgStyle($logoWidth, $logoHeight);
      $signatureStyle = $imgStyle($signatureWidth, $signatureHeight);

      $fmt = fn ($n) => $n === null || $n === '' ? '0' : number_format((float) $n, 0, '.', ',');

      $invNo = $invoice->invoice_no ?? '';
      $customerName = $invoice->customer->name ?? ($invoice->customer_name ?? '');
      $customerAddress = $invoice->customer->full_address
          ?? ($invoice->customer->address ?? ($invoice->customer_address ?? ''));
      $customerCity = $invoice->customer->city ?? ($invoice->customer_city ?? '');
      $customerTelp = $invoice->customer->wa
          ? 'Telp : ' . $invoice->customer->wa
          : ($invoice->customer->phone ?? '');
      $contactPerson = $invoice->customer->contact_person ?? '';

      $invoiceDate = isset($invoice->date) ? \Illuminate\Support\Carbon::parse($invoice->date)->format('F j, Y') : '';
      $dueDate = isset($invoice->due_date) ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('F j, Y') : '';
      $total = $fmt($invoice->total ?? 0);
      $sisa = $fmt($invoice->sisa ?? ($invoice->total ?? 0));

      $items = $invoice->items ?? [];
      $items = $items instanceof \Illuminate\Support\Collection ? $items->all() : (is_array($items) ? $items : []);
  @endphp
  @php
      $autoPrint = request()->boolean('print');
  @endphp
  @if(! $autoPrint)
    @isset($templates)
      @if($templates->isNotEmpty())
      <div class="inv-toolbar no-print">
        <label>Template Invoice:</label>
        <select onchange="if(this.value){window.location.href=this.value;}">
          @foreach($templates as $tpl)
            <option value="{{ request()->fullUrlWithQuery(['template' => $tpl->id]) }}" @if($template && $template->id === $tpl->id) selected @endif>{{ $tpl->name }}</option>
          @endforeach
        </select>
        <button type="button" class="inv-btn" onclick="window.print()">Cetak / Simpan PDF</button>
      </div>
      @endif
    @endisset
  @endif
  @if($autoPrint)
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () { window.print(); }, 400);
    });
  </script>
  @endif
  <div class="page-shell"><div class="page-sheet">
  <table class="grid">
    <colgroup>
      <col style="width:126px"/>
      <col style="width:233px"/>
      <col style="width:70px"/>
      <col style="width:103px"/>
      <col style="width:132px"/>
    </colgroup>
    <tr class="r50">
      <td rowspan="4" colspan="2" class="imgcell">
        <div class="imgwrap">
          @if($logoImage)
            <img src="{{ $imgSrc($logoImage) }}" style="{{ $logoStyle }}" alt="logo"/>
          @else
            <div class="imgph">Logo</div>
          @endif
        </div>
      </td>
      <td></td>
      <td colspan="2" class="ar"><div class="t1">INVOICE</div></td>
    </tr>
    <tr><td colspan="3" class="ar"><div class="fb">{{ $companyName }}</div></td></tr>
    <tr><td colspan="3" class="ar"><div>{{ $companyLines[0] ?? '' }}</div></td></tr>
    <tr><td colspan="3" class="ar"><div>{{ $companyLines[1] ?? '' }}</div></td></tr>
    <tr><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    <tr class="bt"><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    <tr><td class="ct">BILL TO</td><td></td><td class="ct ar" colspan="2">Invoice No :</td><td class="ct">{{ $invNo }}</td></tr>
    <tr><td class="ct fb" colspan="2">{{ $customerName }}</td><td class="ct ar" colspan="2">Invoice Date :</td><td class="ct">{{ $invoiceDate }}</td></tr>
    <tr style="height:21px"><td class="ct" colspan="2" style="white-space:nowrap">{{ $customerAddress }}{{ $customerCity ? ', ' . $customerCity : '' }}</td><td class="ct ar" colspan="2">Payment Due :</td><td class="ct">{{ $dueDate }}</td></tr>
    <tr><td class="ct" colspan="2">{{ $customerTelp }}</td><td class="ct" colspan="2"></td><td></td></tr>
    <tr><td class="ct fi" colspan="2">{{ $contactPerson ? 'Contact Person : ' . $contactPerson : '' }}</td><td class="ct bgc fb ar" colspan="2">Amount Due (IDR) :</td><td class="ct bgc fb">{{ $sisa }}</td></tr>
    <tr><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    <tr><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    <tr class="r42 vc"><td class="ct fb ac">Service</td><td></td><td class="ct fb ac">Quantity</td><td class="ct fb ac">Price</td><td class="ct fb ac">Amount</td></tr>
    <tr class="r42 vc bt bb"><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    @forelse($items as $item)
    <tr class="r50 vc">
      <td class="ct" colspan="2">{{ $item['description'] ?? $item->description ?? '' }}</td>
      <td class="ct ac">{{ $fmt($item['qty'] ?? $item->qty ?? 0) }}</td>
      <td class="ct ar">{{ $fmt($item['price'] ?? $item->price ?? 0) }}</td>
      <td class="ct ar">{{ $fmt($item['amount'] ?? $item->amount ?? 0) }}</td>
    </tr>
  @empty
    <tr class="r50 vc">
      <td class="ct" colspan="2"></td>
      <td class="ct ac">-</td>
      <td class="ct ar">{{ $total }}</td>
      <td class="ct ar">{{ $total }}</td>
    </tr>
  @endforelse
    <tr class="r42 vc"><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    <tr class="r42 vc bt">
      <td class="ct ac" colspan="2" style="vertical-align:bottom">Accounting Signature :</td>
      <td></td><td class="ct ar">Subtotal :</td><td class="ct ar">{{ $total }}</td>
    </tr>
    <tr class="r71 vc bb">
      <td class="ct imgcell" colspan="2">
        <div class="imgwrap xc">
          @if($signatureImage)
            <img src="{{ $imgSrc($signatureImage) }}" style="{{ $signatureStyle }}" alt="signature"/>
          @else
            <div class="imgph">Stempel</div>
          @endif
        </div>
      </td>
      <td></td><td class="ct fb ar">Total :</td><td class="ct fb ar">{{ $total }}</td>
    </tr>
    <tr><td class="ct"></td><td></td><td></td><td></td><td></td></tr>
    @foreach($footerLines as $line)
    <tr>
      <td class="ct fi f18 ac" colspan="5">{{ $line }}</td>
    </tr>
  @endforeach
  </table>
  </div></div>
</body>
</html>
