<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompanyHomeTest extends TestCase
{
    public function test_home_renders(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Cendana Solusindo');
        $response->assertSee('theme-toggle');
        $response->assertSee('/admin');
        foreach (['Direksi', 'General Manager', 'Human Resource Dept', 'Finance Dept', 'Marketing & Sales Dept', 'Operational Dept'] as $dept) {
            $response->assertSee($dept);
        }
        $response->assertDontSee('Administrator');
    }
}
