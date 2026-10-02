<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\InvoiceTemplate;
use App\Models\PelangganCorporate;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDescriptionRowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator']);
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role_id' => $role->id,
        ]);

        $wallet = Wallet::create(['name' => 'Tunai', 'balance' => 0]);
        $coa = Coa::create([
            'code' => '40100',
            'name' => 'Pendapatan',
            'type' => 'income',
            'category' => 'pemasukan',
            'is_active' => true,
        ]);
        $customer = PelangganCorporate::create([
            'customer_code' => 'K001',
            'name' => 'PT Uji Coba',
            'is_subscription' => true,
            'monthly_fee' => 1000000,
            'due_day' => 1,
            'is_active' => true,
        ]);
        InvoiceTemplate::create(['name' => 'Default', 'content' => 'x', 'is_active' => true]);
        Setting::create(['key' => 'invoice_template_sale', 'value' => null]);

        $this->actingAs($user);

        Sale::create([
            'invoice_no' => 'SJ-1234',
            'date' => now(),
            'due_date' => now()->addDays(30),
            'customer_id' => $customer->id,
            'coa_id' => $coa->id,
            'wallet_id' => $wallet->id,
            'total' => 1000000,
            'marketing_cost' => 0,
            'notes' => "Internet 10 Mbps\nBiaya Marketing\nSewa Router",
            'status' => 'berjalan',
            'created_by' => $user->id,
        ]);
    }

    protected function serviceCells(): array
    {
        $html = $this->get('/invoice/sale/1/preview')->assertOk()->getContent();

        preg_match_all('/<td class="ct" colspan="2">(.*?)<\/td>/s', $html, $m);

        return array_map(static fn ($v) => trim(strip_tags($v)), $m[1]);
    }

    public function test_each_enter_in_keterangan_becomes_a_table_row(): void
    {
        $cells = $this->serviceCells();

        $this->assertContains('Internet 10 Mbps', $cells);
        $this->assertContains('Biaya Marketing', $cells);
        $this->assertContains('Sewa Router', $cells);
    }

    public function test_multiline_keterangan_keeps_single_total_amount(): void
    {
        $html = $this->get('/invoice/sale/1/preview')->assertOk()->getContent();

        preg_match_all('/<tr class="r50 vc">(.*?)<\/tr>/s', $html, $m);
        $rows = $m[1];

        $this->assertCount(3, $rows);

        // Hanya baris pertama yang membawa nominal, jadi subtotal & total tidak berlipat.
        foreach ($rows as $i => $row) {
            preg_match_all('/<td class="ct(?: ac| ar)?">(.*?)<\/td>/s', $row, $cells);
            $numbers = array_filter(array_map('trim', $cells[1]), static fn ($v) => $v !== '');

            if ($i === 0) {
                $this->assertCount(3, $numbers);
            } else {
                $this->assertCount(0, $numbers, 'Baris item tambahan harus tanpa nominal.');
            }
        }

        // Amount Due + Price + Amount + Subtotal + Total = 5, berapa pun jumlah baris keterangan.
        $this->assertSame(5, substr_count($html, '>1,000,000</td>'));
    }

    public function test_single_line_keterangan_still_one_row(): void
    {
        $sale = Sale::withoutGlobalScopes()->find(1);
        $sale->notes = 'Langganan Internet';
        $sale->saveQuietly();

        $cells = $this->serviceCells();

        $this->assertContains('Langganan Internet', $cells);
        $this->assertNotContains('Biaya Marketing', $cells);
    }
}
