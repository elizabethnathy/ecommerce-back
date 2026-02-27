<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register(): void
    {
        $this->postJson('/api/auth/register', [
            'nombre'                => 'Alice',
            'email'                 => 'alice@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201)
          ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    /** @test */
    public function register_fails_with_duplicate_email(): void
    {
        User::create(['nombre' => 'Alice', 'email' => 'alice@example.com', 'password' => Hash::make('pass')]);

        $this->postJson('/api/auth/register', [
            'nombre'                => 'Alice 2',
            'email'                 => 'alice@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    /** @test */
    public function user_can_login(): void
    {
        User::create(['nombre' => 'Bob', 'email' => 'bob@example.com', 'password' => Hash::make('password123')]);

        $this->postJson('/api/auth/login', [
            'email'    => 'bob@example.com',
            'password' => 'password123',
        ])->assertStatus(200)
          ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    /** @test */
    public function login_fails_with_wrong_password(): void
    {
        User::create(['nombre' => 'Bob', 'email' => 'bob@example.com', 'password' => Hash::make('correct')]);

        $this->postJson('/api/auth/login', [
            'email'    => 'bob@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    /** @test */
    public function user_can_logout(): void
    {
        $user  = User::create(['nombre' => 'Carlos', 'email' => 'c@example.com', 'password' => Hash::make('pass')]);
        $token = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->assertStatus(200);

        // Token revocado — siguiente request falla
        $this->getJson('/api/cart', [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->assertStatus(401);
    }
}
