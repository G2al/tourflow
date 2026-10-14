<?php

namespace Tests\Feature\Api;

use App\Models\User;

class AuthApiTest extends ApiTestCase
{
    private function registrationPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Giulia Neri',
            'email' => 'giulia@neriviaggi.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'agency' => [
                'company_name' => 'Neri Viaggi',
                'vat_number' => '11122233344',
                'address' => 'Via Po 3',
                'city' => 'Torino',
                'zip_code' => '10100',
                'province' => 'TO',
            ],
        ], $overrides);
    }

    public function test_agency_can_register_and_operate_immediately(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJsonPath('user.role', 'agency')
            ->assertJsonPath('user.agency.company_name', 'Neri Viaggi')
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/packages')
            ->assertJsonPath('data.0.priceType', 'net');
    }

    public function test_registration_validates_vat_number_uniqueness(): void
    {
        $errors = $this->postJson('/api/v1/auth/register', $this->registrationPayload(['agency' => ['vat_number' => '09876543210']]))
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Il valore del campo partita IVA è già in uso.', $errors['agency.vat_number'][0]);
    }

    public function test_login_logout_and_profile(): void
    {
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'agenzia@tourflow.test', 'password' => 'password'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.agency.company_name', 'Viaggi Sole Srl');

        $this->withToken($token)->putJson('/api/v1/auth/me', ['agency' => ['city' => 'Bergamo']])
            ->assertOk()
            ->assertJsonPath('data.agency.city', 'Bergamo');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_wrong_credentials_and_disabled_accounts_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'agenzia@tourflow.test', 'password' => 'sbagliata'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Credenziali non valide.');

        User::where('email', 'agenzia@tourflow.test')->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'agenzia@tourflow.test', 'password' => 'password'])
            ->assertUnprocessable();
    }
}
