<?php

$path = __DIR__ . '/customer_retail.csv';
$handle = fopen($path, 'r');

$records = [];
while (($row = fgetcsv($handle)) !== false) {
    $records[] = array_map('trim', $row);
}
fclose($handle);

echo 'Total csv records (incl title/blank/header): ' . count($records) . PHP_EOL;

// Cari record header
$headerIdx = null;
foreach ($records as $i => $r) {
    $joined = implode('|', $r);
    if (str_contains($joined, 'ID_Pel') && str_contains($joined, 'Nama Pelanggan')) {
        $headerIdx = $i;
        break;
    }
}
echo 'Header at record index: ' . $headerIdx . PHP_EOL;
echo 'Header cols: ' . implode(' | ', $records[$headerIdx]) . PHP_EOL . PHP_EOL;

// Data rows = setelah header
$data = array_slice($records, $headerIdx + 1);
$data = array_values(array_filter($data, fn ($r) => count(array_filter($r, fn ($v) => trim($v) !== '')) > 0));
echo 'Data rows: ' . count($data) . PHP_EOL . PHP_EOL;

// Kolom yang relevan (indeks) berdasarkan header
$cols = $records[$headerIdx];
$colIndex = function (string $needle) use ($cols): ?int {
    foreach ($cols as $i => $c) {
        if (str_contains(strtolower($c), $needle)) {
            return $i;
        }
    }
    return null;
};
$iIdPel = $colIndex('id_pel');
$iNama = $colIndex('nama');
$iEmail = $colIndex('email');
$iTgl = $colIndex('tanggal');
$iPaket = $colIndex('paket');
$iTarif = $colIndex('tarif');
$iLokasi = $colIndex('lokasi');
$iAlamat = $colIndex('alamat lengkap');
$iWa = $colIndex('whatsapp');
$iNik = $colIndex('nik');
$iBilling = $colIndex('billing');
$iUsername = $colIndex('username');
$iMarketing = $colIndex('refferal');
$iLama = $colIndex('lama berlanggan');
echo "col idx: id_pel={$iIdPel} nama={$iNama} email={$iEmail} tgl={$iMulai} paket={$iPaket} tarif={$iTarif} lokasi={$iLokasi} alamat={$iAlamat} wa={$iWa} nik={$iNik} billing={$iBilling} username={$iUsername} marketing={$iMarketing} lama={$iLama}\n\n";

// Rekapitulasi unik paket + tarif
$pk = [];
foreach ($data as $r) {
    $pkg = trim($r[$iPaket] ?? '');
    $tarif = trim($r[$iTarif] ?? '');
    $key = "{$pkg} | {$tarif}";
    $pk[$key] = ($pk[$key] ?? 0) + 1;
}
echo "UNIK PAKET + TARIF:\n";
foreach ($pk as $k => $cnt) {
    echo "  [{$cnt}x] {$k}\n";
}
echo "\nSAMPLE ROW 0:\n";
print_r($data[0]);
echo "\nSAMPLE ROW 4:\n";
print_r($data[4]);

// Ide supaya 'data mulai' jelas
echo "\nRecord indexes 0..5 raw:\n";
for ($i = 0; $i <= 5; $i++) {
    echo "  csv-line-around-header+{$i}: " . implode(' | ', $records[$headerIdx + $i]) . "\n";
}
