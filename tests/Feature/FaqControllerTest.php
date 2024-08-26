<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FaqControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'admin']);
        $this->token = JWTAuth::fromUser($this->superAdmin);
    }

    public function test_admin_can_create_faq()
    {
        $payload = [
            'question' => 'What is the return policy?',
            'answer' => 'Our return policy allows returns within 30 days of purchase.',
            'category' => 'Policies'
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $this->token"])
            ->postJson('/api/v1/faqs', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status_code',
                'success',
                'data' => [
                    'id',
                    'question',
                    'answer',
                    'category',
                    'created_at',
                    'updated_at',
                    'created_by'
                ]
            ]);

        $this->assertDatabaseHas('faqs', $payload);
    }

    public function test_unauthorized_user_cannot_create_faq()
    {
        $regularUser = User::factory()->create(['role' => 'user']);
        $token = JWTAuth::fromUser($regularUser);

        $payload = [
            'question' => 'Unauthorized question?',
            'answer' => 'This should not be created.',
            'category' => 'Test',
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/v1/faqs', $payload);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('faqs', $payload);
    }

    public function test_can_fetch_all_faqs()
    {
        Faq::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/faqs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status_code',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'created_at',
                        'updated_at',
                        'question',
                        'answer',
                        'category',
                    ]
                ]
            ]);

        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_faq_creation_fails_with_invalid_data()
    {
        $payload = [
            'question' => '',
            'answer' => '',
            'category' => ''
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $this->token"])
            ->postJson('/api/v1/faqs', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status_code',
                'message',
                'data'
            ]);
    }

    public function test_admin_can_update_faq()
    {
        $faq = Faq::factory()->create();
        $updatedData = [
            'question' => 'Updated question?',
            'answer' => 'Updated answer.',
            'category' => 'Updated Category'
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $this->token"])
            ->putJson("/api/v1/faqs/{$faq->id}", $updatedData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status_code',
                'message',
                'data' => [
                    'id',
                    'question',
                    'answer',
                    'category',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('faqs', $updatedData);
    }

    public function test_unauthorized_user_cannot_update_faq()
    {
        $faq = Faq::factory()->create();
        $regularUser = User::factory()->create(['role' => 'user']);
        $token = JWTAuth::fromUser($regularUser);

        $updatedData = [
            'question' => 'Unauthorized update',
            'answer' => 'This should not be updated.',
            'category' => 'Test'
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/v1/faqs/{$faq->id}", $updatedData);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('faqs', $updatedData);
    }

    public function test_admin_can_delete_faq()
    {
        $faq = Faq::factory()->create();

        $response = $this->withHeaders(['Authorization' => "Bearer $this->token"])
            ->deleteJson("/api/v1/faqs/{$faq->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status_code' => 200,
                'message' => 'FAQ successfully deleted'
            ]);

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_unauthorized_user_cannot_delete_faq()
    {
        $faq = Faq::factory()->create();
        $regularUser = User::factory()->create(['role' => 'user']);
        $token = JWTAuth::fromUser($regularUser);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->deleteJson("/api/v1/faqs/{$faq->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('faqs', ['id' => $faq->id]);
    }
}
