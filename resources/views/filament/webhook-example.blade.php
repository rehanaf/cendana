@verbatim
<div class="space-y-4 text-sm text-slate-600 dark:text-slate-300">

    <p>
        Webhook dikirim saat pembayaran langganan (corporate) atau retail dicatat — baik lewat tombol
        <strong>Bayar</strong> maupun opsi <strong>Langsung Lunas</strong> (pay_now). Setting diisi per jenis
        (corporate &amp; retail), setiap jenis bisa memakai method, header, dan body yang berbeda.
    </p>

    <div>
        <p class="font-semibold text-slate-800 dark:text-slate-100">Contoh Konfigurasi</p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-100 p-4 text-xs leading-5 text-slate-800 dark:bg-slate-800 dark:text-slate-200">METHOD  : POST
URL     : https://example.com/hook/payment
HEADERS : Content-Type=application/json
          Authorization=Bearer {{customer.code}}
BODY    : {
            "event": "{{event}}",
            "transaction_id": {{transaction.id}},
            "amount": {{transaction.amount}},
            "invoice_no": "{{invoice.no}}",
            "customer_code": "{{customer.customer_code}}",
            "customer_name": "{{customer.name}}",
            "customer_email": "{{customer.email}}",
            "period": "{{invoice.period}}",
            "due_date": "{{invoice.due_date}}",
            "status": "{{invoice.status}}"
          }</pre>
    </div>

    <div>
        <p class="font-semibold text-slate-800 dark:text-slate-100">Variabel yang Tersedia</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li><code>event</code> — selalu <code>payment.received</code></li>
            <li><code>transaction.*</code> — <code>id</code>, <code>name</code>, <code>amount</code>, <code>description</code>, <code>transaction_date</code>, <code>wallet_id</code>, <code>coa_id</code></li>
            <li><code>invoice.*</code> — <code>no</code>, <code>total</code>, <code>period</code>, <code>due_date</code>, <code>status</code></li>
            <li><code>customer.*</code> — semua kolom pelanggan, mis. <code>name</code>, <code>customer_code</code>, <code>email</code>, <code>wa</code>, <code>block_location</code>, <code>internet_package_id</code></li>
        </ul>
        <p class="mt-2 text-xs">
            Tulis variabel di dalam <code>{{ variabel }}</code>. Jika body dikosongkan atau JSON tidak valid,
            sistem mengirim seluruh konteks (event, transaction, invoice, customer) sebagai JSON.
        </p>
    </div>

    <div>
        <p class="font-semibold text-slate-800 dark:text-slate-100">Header</p>
        <p class="mt-1">
            Tulis satu header per baris dengan format <code>Nama=value</code>; value juga bisa memakai variabel.
            Secret yang diisi otomatis ditambahkan sebagai header <code>X-Webhook-Secret</code>.
        </p>
    </div>
</div>
@endverbatim