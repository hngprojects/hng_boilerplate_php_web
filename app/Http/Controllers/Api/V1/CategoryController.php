<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {

            $categories = Category::all();

            return response()->json([
                'status_code' => 200,
                'message' => 'Categories returned successfully',
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred while processing your request.',
                    'details' => [
                        'support_email' => 'support@example.com',
                    ],
                ],
            ], 500);
        }
    }
}
