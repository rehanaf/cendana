<?php

namespace Tests\Feature;

use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UnifiedSalesTableSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('database.sqlite'));
        DB::purge('sqlite');
    }

    public function test_sales_page_lists_corporate_and_retail(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Test ini untuk SQLite.');
        }

        $user = User::query()->first();
        $this->assertNotNull($user, 'No user in DB');
        $this->actingAs($user);

        $component = Livewire::test(ManageSales::class)
            ->assertOk()
            ->assertSee('Corporate')
            ->assertSee('Retail')
            ->assertSee('PT Cendana Teknologi')
            ->assertSee('Budi Santoso');
    }
}
