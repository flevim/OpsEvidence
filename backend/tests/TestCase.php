<?php

namespace Tests;

use App\Support\AccountContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El contexto de tenant es estado estatico: si un test lo deja puesto,
        // el siguiente heredaria un aislamiento que no le corresponde.
        AccountContext::forget();
    }

    protected function tearDown(): void
    {
        AccountContext::forget();

        parent::tearDown();
    }
}
