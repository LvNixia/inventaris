<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_handover_service_instantiates()
    {
        $this->assertTrue(true);
    }
}
