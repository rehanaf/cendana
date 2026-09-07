<?php

namespace Tests\Feature;

use App\Support\PaymentDescription;
use Tests\TestCase;

class PaymentDescriptionTest extends TestCase
{
    public function test_langganan_uses_customer_name_ignoring_notes(): void
    {
        $result = PaymentDescription::make('Pembayaran Langganan', 'langganan', 'Catatan Internal', 'PT Cendana Teknologi');

        $this->assertSame('Pembayaran Langganan PT Cendana Teknologi', $result);
    }

    public function test_penjualan_uses_notes_when_filled(): void
    {
        $result = PaymentDescription::make('Pembayaran', 'penjualan', 'Penjualan peralatan internet', 'PT Cendana Teknologi');

        $this->assertSame('Pembayaran Penjualan peralatan internet', $result);
    }

    public function test_pembelian_falls_back_to_vendor_name_without_notes(): void
    {
        $result = PaymentDescription::make('Pembayaran', 'pembelian', null, 'PT Supplier Teknologi');

        $this->assertSame('Pembayaran PT Supplier Teknologi', $result);
    }

    public function test_returns_prefix_only_when_no_notes_and_no_person(): void
    {
        $result = PaymentDescription::make('Pembayaran Retail', 'langganan', null, null);

        $this->assertSame('Pembayaran Retail', $result);
    }
}
