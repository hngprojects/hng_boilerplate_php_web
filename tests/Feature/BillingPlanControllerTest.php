<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Tests\TestCase;

class BillingPlanControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and log them in
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_creates_a_billing_plan_successfully()
    {
        // Prepare the data to be sent in the request
        $data = [
            'name' => 'Basic Plan',
            'frequency' => 'Monthly',
            'is_active' => true,
            'amount' => 1000, // Assuming this is in kobo
            'description' => 'This is a basic plan',
        ];

        // Send the POST request to create the billing plan
        $response = $this->postJson('/api/v1/billing-plans', $data);

        // Assert that the response status is 201 Created
        $response->assertStatus(Response::HTTP_CREATED);

        // Assert that the response JSON structure is as expected
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'frequency',
                'amount',
                'description',
                'created_at',
                'updated_at',
            ],
            'message',
            'status_code',
        ]);

        // Assert that the response contains the correct data
        $response->assertJson([
            'data' => [
                'name' => 'Basic Plan',
                'frequency' => 'Monthly',
                'amount' => 1000,
                'description' => 'This is a basic plan',
            ],
            'message' => 'Billing plan created successfully',
            'status_code' => Response::HTTP_CREATED,
        ]);

        // Assert that the billing plan was created in the database
        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Basic Plan',
            'duration' => 'Monthly',
            'price' => 1000,
            'description' => 'This is a basic plan',
        ]);
    }

    /** @test */
    public function it_fails_to_create_a_billing_plan_due_to_validation_error()
    {
        // Prepare invalid data (missing required fields)
        $data = [
            'name' => '',
            'frequency' => 'Weekly', // Invalid frequency
            'is_active' => 'yes', // Invalid boolean value
            'amount' => -100, // Negative amount
        ];

        // Send the POST request to create the billing plan
        $response = $this->postJson('/api/v1/billing-plans', $data);

        // Assert that the response status is 400 Bad Request
        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        // Assert that the response JSON contains the validation errors
        $response->assertJsonStructure([
            'data',
            'error',
            'message',
            'status_code',
        ]);

        // Ensure no billing plan was created in the database
        $this->assertDatabaseMissing('subscription_plans', [
            'name' => $data['name'],
        ]);
    }
}
