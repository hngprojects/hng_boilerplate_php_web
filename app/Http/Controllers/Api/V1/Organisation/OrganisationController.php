<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganisationRequest;
use App\Models\Organisation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\OrganisationResource;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

class OrganisationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($user_id)
    {
        try {
            // Ensure that the authenticated user is accessing their own organisations
            $authUser = auth('api')->user();
            if (!$authUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                    'status_code' => 401
                ], 401);
            }

            // Check if the authenticated user is trying to access their own data
            if ($authUser->id !== (string) $user_id) {
                Log::info('Authenticated User ID:', ['id' => $authUser->id]);
                Log::info('Requested User ID:', ['requested_id' => $user_id]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden',
                    'status_code' => 403
                ], 403);
            }

            // Find the user by ID, handling potential non-existence
            $user = User::where('id', $user_id)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'status_code' => 404
                ], 404);
            }

            // Retrieve the user's organisations
            $organisations = $user->organisations;

            if ($organisations->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No organisations available',
                    'status_code' => 200,
                    'data' => [
                        'organisations' => []
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Organisations retrieved successfully',
                'status_code' => 200,
                'data' => [
                    'organisations' => OrganisationResource::collection($organisations)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('An error occurred while retrieving organisations:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred',
                'status_code' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganisationRequest $request)
    {
        if ($validPayload = $request->validated()) {
            $user = auth()->user();
            if (!$user) {
                return ResponseHelper::response("Authentication failed", 401, null);
            }
            $validPayload['user_id'] = $user->id;
            DB::beginTransaction();
            try {
                $organisation = Organisation::create($validPayload);
                $organisation->users()->attach($user->id);
                DB::commit();
                return ResponseHelper::response("Organisation created successfully", 201, $organisation->getPublicColumns());
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                DB::rollBack();
                return ResponseHelper::response("Client error", 400, null);
            }
        } else {
            return ResponseHelper::response("Client error", 400, null);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreOrganisationRequest $request, $org_id)
    {
        if ($validPayload = $request->validated()) {
            $user = auth('api')->user();
            if (!$user) return ResponseHelper::response("Authentication failed", 401, null);
            $organisation = Organisation::find($org_id);
            if (!$organisation) return ResponseHelper::response("Organisation not found", 404, null);
            if (!$organisation->users->contains($user->id)) return ResponseHelper::response("You are not authorised to perform this action", 403, null);
            try {
                unset($validPayload['email']);
                $organisation->update($validPayload);
                return ResponseHelper::response("Organisation updated successfully", 200, $organisation->getPublicColumns());
            } catch (\Exception $e) {
                return ResponseHelper::response("Client error", 400, null);
            }
        } else {
            return ResponseHelper::response("Client error", 400, null);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($org_id)
    {
        $user = auth('api')->user();
        if (!$user) return ResponseHelper::response("Authentication failed", 401, null);
        $organisation = Organisation::find($org_id);
        if (!$organisation) return ResponseHelper::response("Organisation not found", 404, null);
        if (!$organisation->users->contains($user->id)) return ResponseHelper::response("You are not authorised to perform this action", 401, null);
        try {
            // Soft delete the org
            $organisation->delete();
            return ResponseHelper::response("Organisation deleted successfully", 204, null);
        } catch (\Exception $e) {
            return ResponseHelper::response("Client error", 400, null);
        }
    }

    public function removeUser(Request $request, $org_id, $user_id)
    {
        $organisation = Organisation::findOrFail($org_id);

        if (!$organisation) {
            return response()->json([
                'data' => null,
                'error' => 'Not Found',
                'message' => 'Organization not found',
                'status_code' => 404
            ], 404);
        }

        // Use $request->auth instead of Auth::user()
        if (!$request->user()->can('removeUser', $organisation)) {
            return response()->json([
                'data' => null,
                'error' => 'Unauthorized',
                'message' => 'Only admin can remove users',
                'status_code' => 403
            ], 403);
        }

        $user = User::find($user_id);

        if (!$user || !$organisation->users()->detach($user)) {
            return response()->json([
                'data' => null,
                'error' => 'Not Found',
                'message' => 'User not found',
                'status_code' => 404
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'user deleted successfully',
            'status_code' => 200
        ], 200);
    }


    public function getRoleId(Request $request, $org_id, $role_id)
    {
        $role = Role::where('id', $role_id)->where('org_id', $org_id)->first();

        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found or does not belong to the specified organisation',
                'status_code' => 404
            ], 404);
        }

        $user = Auth::user();
        if (!$user->organisations->contains('org_id', $org_id)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'User does not have permission to access this role',
                'status_code' => 403
            ], 403);
        }


        return response()->json([
            'status' => 'success',
            'message' => 'Role retrieved successfully',
            'status_code' => 200,
            'data' => [
                'role_id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'org_id' => $role->org_id,
                'is_active' => $role->is_active,
                'is_default' => $role->is_default,
            ]
        ]);
    }
}
