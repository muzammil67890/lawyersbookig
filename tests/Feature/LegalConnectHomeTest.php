<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalConnectHomeTest extends TestCase
{
    public function test_home_page_is_available(): void
    {
        $this->get('/')->assertOk()->assertSee('LEGAL CONNECT');
    }
}
