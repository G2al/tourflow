<?php

namespace Tests\Feature\Api;

use App\Models\Package;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Data fissa: il catalogo demo ha periodi da ottobre dell'anno corrente.
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function hurghada(): Package
    {
        return Package::where('slug', 'hurghada-all-inclusive')->firstOrFail();
    }

    protected function agencyToken(): string
    {
        return User::where('email', 'agenzia@tourflow.test')->firstOrFail()->createToken('test')->plainTextToken;
    }
}
