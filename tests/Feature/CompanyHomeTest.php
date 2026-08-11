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
        foreach (['Direktur', 'General Manager', 'HR Manager', 'Finance', 'Sales', 'Technician'] as $role) {
            $response->assertSee($role);
        }
        $response->assertDontSee('Administrator');
    }
}
