<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'password' => Hash::make(Str::password(12)),
                'social_id' => $googleUser->id,
                'is_verified' => true,
                'signup_type' => 'Google',
                'is_active' => true,
            ]
        );

        if ($user->profile) {
            $user->profile->update([
                'first_name' => $googleUser->user['given_name'],
                'last_name' => $googleUser->user['family_name'],
                'avatar_url' => $googleUser->attributes['avatar_original'],
            ]);
        } else {
            $user->profile()->create([
                'first_name' => $googleUser->user['given_name'],
                'last_name' => $googleUser->user['family_name'],
                'avatar_url' => $googleUser->attributes['avatar_original'],
            ]);
        }

        $token = JWTAuth::fromUser($user);

        $response = [
            'status_code' => 200,
            'message' => 'User successfully authenticated',
            'access_token' => $token,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $googleUser->user['given_name'],
                'last_name' => $googleUser->user['family_name']
            ]
        ];

        return response()->json($response);
    }

    public function saveGoogleRequest(Request $request)
    {
        // Extract Google user data from the request
        $googleUser = (object) $request->input('google_user');

        $user = User::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'password' => Hash::make(Str::password(12)),
                'social_id' => $googleUser->id,
                'is_verified' => true,
                'signup_type' => 'Google',
                'is_active' => true,
            ]
        );

        if ($user->profile) {
            $user->profile->update([
                'first_name' => $googleUser->user['given_name'],
                'last_name' => $googleUser->user['family_name'],
                'avatar_url' => $googleUser->attributes['avatar_original'],
            ]);
        } else {
            $user->profile()->create([
                'first_name' => $googleUser->user['given_name'],
                'last_name' => $googleUser->user['family_name'],
                'avatar_url' => $googleUser->attributes['avatar_original'],
            ]);
        }

        $token = JWTAuth::fromUser($user);

        $response = [
            'status_code' => 201,
            'message' => 'User successfully authenticated',
            'access_token' => $token,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $googleUser->user['given_name'],
                'last_name' => $googleUser->user['family_name']
            ]
        ];

        return response()->json($response);
    }

    public function saveGoogleRequestPost(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email:rfc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 422,
                'message' => $validator->errors()
            ], 422);
        }
        
        // Extract Google user data from the request
        $google_token = $request->input('token');
        $email = $request->input('email');


        try {
            // Retrieve user information from Google
            $googleUser = Socialite::driver('google')->userFromToken($google_token);
            
            // Check if the token email matches the email provided
            if ($googleUser->email !== $email) {
                return response()->json([
                    'status_code' => 400,
                    "error" => "Bad Request",
                    'message' => 'Invalid Google token'
                ], 401);
            }
    
            // Create or update the user
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'password' => Hash::make(Str::random(12)), // Random password for social sign-ins
                    'social_id' => $googleUser->id,
                    'is_verified' => true,
                    'signup_type' => 'Google',
                    'is_active' => true,
                ]
            );
    
            // Handle profile update or creation
            if ($user->profile) {
                $user->profile->update([
                    'first_name' => $googleUser->user['given_name'],
                    'last_name' => $googleUser->user['family_name'],
                    'avatar_url' => $googleUser->user['picture'],
                ]);
            } else {
                $user->profile()->create([
                    'first_name' => $googleUser->user['given_name'],
                    'last_name' => $googleUser->user['family_name'],
                    'avatar_url' => $googleUser->user['picture'],
                ]);
            }
    
            // Generate JWT token
            $token = JWTAuth::fromUser($user);
    
            return response()->json([
                'status_code' => 200,
                'message' => 'User Created Successfully',
                'access_token' => $token,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'first_name' => $googleUser->user['given_name'],
                        'last_name' => $googleUser->user['family_name'],
                        'fullname' => $googleUser->user['given_name'].' '.$googleUser->user['family_name'],
                        'role' => $user->role,
                    ]
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function loginUsingFacebook()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function callbackFromFacebook()
    {
        $facebookUser = Socialite::driver('facebook')->stateless()->user();

        $user = User::updateOrCreate(
            ['email' => $facebookUser->email],
            [
                'password' => Hash::make(Str::password(12)),
                'social_id' => $facebookUser->id,
                'is_verified' => true,
                'signup_type' => 'Facebook',
                'is_active' => true,
            ]
        );

        if ($user->profile) {
            $user->profile->update([
                'first_name' => $this->getFirstName($facebookUser->name),
                'last_name' => $this->getLastName($facebookUser->name),
                'avatar_url' => $facebookUser->avatar,
            ]);
        } else {
            $user->profile()->create([
                'first_name' => $this->getFirstName($facebookUser->name),
                'last_name' => $this->getLastName($facebookUser->name),
                'avatar_url' => $facebookUser->avatar,
            ]);
        }

        $token = JWTAuth::fromUser($user);

        $response = [
            'status_code' => 200,
            'message' => 'User successfully authenticated',
            'access_token' => $token,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $this->getFirstName($facebookUser->name),
                'last_name' => $this->getLastName($facebookUser->name)
            ]
        ];

        return response()->json($response);
    }

    public function saveFacebookRequest(Request $request)
    {
        // Extract Facebook user data from the request
        $facebookUser = (object) $request->input('facebook_user');

        $user = User::updateOrCreate(
            ['email' => $facebookUser->email],
            [
                'password' => Hash::make(Str::password(12)),
                'social_id' => $facebookUser->id,
                'is_verified' => true,
                'signup_type' => 'Facebook',
                'is_active' => true,
            ]
        );

        if ($user->profile) {
            $user->profile->update([
                'first_name' => $this->getFirstName($facebookUser->name),
                'last_name' => $this->getLastName($facebookUser->name),
                'avatar_url' => $facebookUser->avatar,
            ]);
        } else {
            $user->profile()->create([
                'first_name' => $this->getFirstName($facebookUser->name),
                'last_name' => $this->getLastName($facebookUser->name),
                'avatar_url' => $facebookUser->avatar,
            ]);
        }

        $token = JWTAuth::fromUser($user);

        $response = [
            'status' => 201,
            'message' => 'User successfully authenticated',
            'access_token' => $token,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $this->getFirstName($facebookUser->name),
                'last_name' => $this->getLastName($facebookUser->name)
            ]
        ];

        return response()->json($response);
    }

    private function getFirstName($fullName)
    {
        $names = explode(' ', $fullName);
        return count($names) > 2 ? $names[0] : $names[0];
    }

    private function getLastName($fullName)
    {
        $names = explode(' ', $fullName);
        return count($names) > 2 ? end($names) : (count($names) > 1 ? $names[1] : '');
    }
}
