<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Job;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class JobController extends Controller
{
    public function create(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'job_type' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'organization_id' => 'required|string|exists:organisations,org_id', // Ensure org_id is referenced
        ]);

        if ($validator->fails()) {
            \Log::error('Validation errors:', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Invalid request data',
                'errors' => $validator->errors(),
                'status_code' => 400,
            ], 400);
        }

        try {
            // Get the authenticated user
            $user = $request->user();

            // Log the authenticated user
            \Log::info('Authenticated User:', ['user' => $user]);

            // Get organization_id from the request
            $orgId = $request->organization_id;

            // Log the organization_id
            \Log::info('Request Organization ID:', ['organization_id' => $orgId]);

            // Create the job listing
            $job = Job::create([
                'id' => (string) Str::uuid(),
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'job_type' => $request->job_type,
                'company_name' => $request->company_name,
                'user_id' => $user->id, // Set the user_id from the authenticated user
                'organisation_id' => $orgId, // Set the organization_id from the request
            ]);

            return response()->json([
                'message' => 'Job listing created successfully',
                'status_code' => 201,
                'data' => $job,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Job creation error:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An error occurred',
                'status_code' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(string $id, Request $request)
    {
        // Get the authenticated user
        if(!$user = $request->user()) {
            return response()->json(['message' => 'Unauthorized', 'error' => 'Bad Request'], 400);
        }

        //Find the post by Id
        $job = Job::find($id);

        // Check if the job exists
        if (!$job) {
            return response()->json(['message' => 'Job not found', 'error' => 'Not Found'], 404);
        }

        // Check if the authenticated user is the owner of the job or related to the job
        // if (!$job->users->contains(Auth::id())) {
        //     return response()->json(['message' => 'Unauthorized', 'error' => 'Bad Request'], 400);
        // }

        // Delete the post
        $job->delete();

        // Return a response
        return response()->json(['message' => 'Job deleted successfully'], 200);
    }
}
