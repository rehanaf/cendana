### Catatan Perubahan Filament (v4 & v5)

* **Pemisahan & Pemusatan Schema Namespace:** 
  Mulai **Filament v4** dan dilanjutkan pada **Filament v5**, komponen tata letak (layout components) seperti `Grid`, `Section`, `Tabs`, dan komponen layout lainnya dipusatkan ke dalam namespace **`Filament\Schemas\Components\`**.
  * Sebelumnya (di v3 dan versi di bawahnya), komponen layout terpisah di masing-masing builder (misalnya `Filament\Forms\Components\` atau `Filament\Tables\Columns\Layout\`).
  * Pastikan untuk menyesuaikan *statement use* (`use`) pada kode Anda ketika melakukan migrasi atau menulis komponen struktur form/tabel baru di Filament v4/v5.

### Aturan Konvensi Database & Tampilan

* **Nama Tabel, Kolom, dan Key Settings SELALU bahasa Inggris (English).**
  * Contoh tabel: `sales`, `purchases`, `subscription_invoices`, `corporate_customers`, `internet_packages`, `settings`.
  * Contoh kolom: `invoice_no`, `due_date`, `monthly_fee`, `due_day`, `is_subscription`, `customer_code`, `period`.
  * Contoh key settings: `coa_penjualan_id`, `coa_pembelian_id`, `coa_langganan_id`, `langganan_auto_generate`, `langganan_generate_day`.
  * JANGAN membuat tabel/kolom/key baru dengan nama bahasa Indonesia (mis. `no_nota`, `tgl_tagih`, `biaya_bulanan`).
* **Tampilan (label, heading, helperText, notifikasi) SELALU bahasa Indonesia.**
  * Gunakan `->label('...')`, `->getNavigationLabel()`, dsb. untuk menampilkan teks Indonesia meskipun kolomnya Inggris.
* **Pembayaran/piutang direpresentasikan sebagai Transaksi yang di-link** (kolom `sale_id`, `purchase_id`, `subscription_invoice_id` di tabel `transactions`). Tidak ada tabel pembayaran terpisah.

### Aturan Permission / Hak Akses

* **SEMUA resource Filament WAJIB dibuatkan permission-nya di database** (`permissions` + `permission_role`), supaya hak akses bisa dikelola langsung dari halaman Permission di panel — bukan dikode-keras berdasarkan nama role.
  * Setiap resource membuat 4 permission: `view_{entity_en}`, `create_{entity_en}`, `edit_{entity_en}`, `delete_{entity_en}` (nama entity pakai tabel bahasa Inggris, mis. `view_sales`, `create_internet_packages`).
  * Buat permission baru lewat **migration baru** (jangan ubah migration lama yang sudah dijalankan), dan attach ke role yang sesuai di migration tersebut (Administrator/Direktur dapat penuh, role operasional biasanya hanya `view` + `create` sesuai kebutuhan).
  * Bila ada role yang harus dibatasi (mis. Finance hanya `view` + `create`), cukup TIDAK attach permission `edit_*/delete_*` untuk role tsb. Setelah migration dijalankan, permission tetap bisa diubah dari halaman Permission.
* **Gunakan trait `App\Filament\Resources\Concerns\HasResourcePermissions`** di resource dengan `$permissionPrefix` = nama entity (mis. `'sales'`, `'retail_customers'`). Trait ini otomatis memetakan `canViewAny`/`canCreate`/`canEdit`/`canDelete`/`canDeleteAny` ke permission DB, dengan fallback `isAdmin()` → full akses.
  * Contoh: `class XResource extends Resource { use HasResourcePermissions; protected static ?string $permissionPrefix = 'x_table_name_en'; ... }`.
  * JANGAN menulis `can*()` manual yang mengecek nama role (`isFinance()` dsb.) — kecuali untuk kas (COA/Wallet/Transaksi) yang memang diatur permission-khusus seperti yang sudah ada.
* Buat permission secara idempotent (`firstOrCreate`) di migration supaya tidak duplikat saat dijalankan ulang.
* Untuk fresh install, permission & attach-nya disimpan di `database/seeders/DatabaseSeeder.php`; untuk database yang sudah ada gunakan migration tambahan.

