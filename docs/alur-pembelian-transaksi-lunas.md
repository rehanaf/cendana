# Alur Data Pembelian → Transaksi → Lunas

Dokumentasi alur data Pembelian sampai masuk ke Transaksi dan berstatus Lunas pada aplikasi Cendana (Laravel + Filament).

## 1. Buat Pembelian

- Form `PurchaseResource` (halaman `ManagePurchases`) dengan `CreateAction` "Tambah Pembelian".
- Otomatis diisi `created_by` = user login, default `coa_id` & `wallet_id` dari Settings (`coa_pembelian_id`, `wallet_pembelian_id`).
- Ada toggle **"Langsung Lunas"** (`pay_now`) yang hanya tampil saat create.

## 2. Masuk ke Transaksi

Ada 2 jalur:

- **Langsung lunas** (toggle `pay_now`): hook `after()` di `ManagePurchases.php:31-42` membuat `Transaction` dengan `amount = total`, `name = invoice_no`, di-link via `purchase_id`.
- **Bayar sebagian/belakangan**: aksi "Bayar" (`BayarAction.php:83-102`) membuka modal (Akun, Dompet, Jumlah, Tanggal, Keterangan) lalu membuat `Transaction` yang di-link ke `purchase_id`. Tombol ini hanya tampil saat `sisa > 0`.

## 3. Otomatis Lunas (Tanpa tombol khusus)

- `Transaction::booted()` → `refreshLinkedStatus()` → `Purchase::refreshStatus()` (`Purchase.php:80-85`).
- Status menjadi `'lunas'` jika `total_paid` (SUM `amount` transaksi terkait) >= `total`, dihitung dari accessor `getIsLunasAttribute()` (`Purchase.php:75-78`).
- Berlaku juga untuk Penjualan, Langganan, dan Retail melalui `refreshLinkedStatus()` yang sama.

## 4. Dampak ke Kas

- Setiap pembayaran memicu `recalculateBalance()` (`Transaction.php:68-82`).
- Karena COA pembelian berkategori `pengeluaran`, saldo dompet otomatis berkurang (pemasukan = ditambah, lainnya = dikurang).

## Catatan Penting

- **Tidak ada tabel pembayaran terpisah** (`purchase_payments` sudah di-drop). Semua pembayaran = baris `transactions` dengan kolom `purchase_id` (atau `sale_id`, `subscription_invoice_id`, `retail_invoice_id`) terisi.
- Status Lunas murni hasil derivasi dari penjumlahan transaksi terkait, disimpan ke kolom `status` lewat `refreshStatus()`.

## Risiko Transaksi Ganda (Double)

1. **Aksi "Bayar" tidak punya guard anti-duplikat** (`BayarAction.php:83-102`)
   - Field `amount` hanya `minValue(0)` — tidak ada `maxValue(sisa)` dan tidak ada cek `amount <= sisa` saat submit. Bisa bayar melebihi sisa (overpayment) atau bayar lagi meski sudah lunas jika data halaman masih lama (tombol hanya `->visible(sisa > 0)`, cek UI bukan validasi server).
   - Double-click / submit berulang pada modal Bayar → membuat 2 baris `transactions` dengan `purchase_id` sama. Tidak ada idempotency key / unique constraint.
2. **Toggle "Langsung Lunas" bisa double-create** (`ManagePurchases.php:27-43`)
   - Hook `after()` di `CreateAction` membuat transaksi penuh setiap kali create dijalankan. Kalau form create ter-submit ganda (retry/network/double-click), bisa lahir pembelian + transaksi duplikat.
   - `pay_now` hanya `hidden` saat edit, jadi risiko utama ada di submit ganda saat create.
3. **Tidak ada mekanisme rekonsiliasi**
   - `refreshLinkedStatus()` hanya menghitung ulang status (`sisa <= 0`). Sisa di-bounds ke `max(0, ...)`, jadi kelebihan bayar "hilang" tanpa jejak, dan tidak ada cek total pembayaran <= total.

### Rekomendasi Perbaikan

- Tambahkan validasi `maxValue(sisa)` / cek `amount <= sisa` di `BayarAction`.
- Guard `sisa > 0` di action closure (bukan hanya `->visible()`).
- Tombol Bayar di-disable saat `sisa <= 0`.
- Gunakan idempotency key (mis. hash `purchase_id + amount + date`) atau unique constraint untuk mencegah duplikat.