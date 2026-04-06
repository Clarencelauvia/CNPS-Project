<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Admin;



class AdminAuthTest extends TestCase
{
    /**
     * A basic feature test example.
     */
     use RefreshDatabase;
    public function test_admin_can_login(): void
    {
           $admin = Admin::factory()->create([
            'email' => 'test@arena.com',
            'password' => bcrypt('password123'),
        ]);
  $response = $this->postJson('/api/admin/login', [
            'email' => 'test@arena.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'admin',
                     'token',
                     'message'
                 ]);
    }

       public function test_admin_cannot_login_with_wrong_credentials()
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'wrong@arena.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }
}
