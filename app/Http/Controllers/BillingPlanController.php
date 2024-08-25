<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class BillingPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $plans = SubscriptionPlan::select(['id', 'name', 'price', 'created_at'])->get();
            return response()->json([
                'status' => Response::HTTP_OK,
                'message' => 'Billing plans retrieved successfully',
                'data' => $plans,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'frequency' => 'required|string|in:Monthly,Yearly',
            'is_active' => 'boolean',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

         // If validation fails, return a 400 Bad Request response
         if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'error' => 'Bad Request',
                'message' => $validator->errors()->first(),
                'status_code' => Response::HTTP_BAD_REQUEST
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Create a new billing plan
            $billingPlan = SubscriptionPlan::create([
                'name' => $request->input('name'),
                'duration' => $request->input('frequency'),
                // 'is_active' => $request->input('is_active'),
                'price' => $request->input('amount'),
                'description' => $request->input('description'),
            ]);

            // Return a 201 Created response with the billing plan data
            return response()->json([
                'data' => [
                    'id' => $billingPlan->id,
                    'name' => $billingPlan->name,
                    'frequency' => $billingPlan->duration,
                    // 'is_active' => $billingPlan->is_active,
                    'amount' => $billingPlan->price,
                    'description' => $billingPlan->description,
                    'created_at' => $billingPlan->created_at->toIso8601String(),
                    'updated_at' => $billingPlan->updated_at->toIso8601String(),
                ],
                'message' => 'Billing plan created successfully',
                'status_code' => Response::HTTP_CREATED
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            // If an unexpected error occurs, return a 500 Internal Server Error response
            return response()->json([
                'data' => null,
                'error' => 'Internal Server Error',
                'message' => 'An unexpected error occurred',
                'status_code' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getBillingPlan($id)
    {


        // Validate the id parameter
        if (!is_string($id) || empty($id)) {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid billing plan ID'
            ], 400);
        }

        try {
            // Retrieve the billing plan by ID
            $billingPlan = SubscriptionPlan::find($id);

            if (!$billingPlan) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Billing plan not found'
                ], 404);
            }

            // Return the billing plan details
            return response()->json([
                'status' => 200,
                'message' => 'Billing plans retrieved successfully',
                'data' => [
                    'id' => $billingPlan->id,
                    'name' => $billingPlan->name,
                    'price' => $billingPlan->price,
                ]
            ], 200);
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'status' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $plans = SubscriptionPlan::find($id);

        if (!$plans) {
            return response()->json([
                'status_code' => 404,
                'error' => 'Not Found',
                'message' => 'Plan not found'
            ], 404);
        }

        try {
            $plans->delete();

            // Return success response
            return response()->json([
                'data' => true,
                'status_code' => 200,
                'message' => 'Plan deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
