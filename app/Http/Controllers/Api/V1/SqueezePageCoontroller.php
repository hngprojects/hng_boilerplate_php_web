<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterSqueezeRequest;
use App\Http\Requests\DeleteSqueezeRequest;
use App\Models\SqueezePage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SqueezePageCoontroller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $plans = SqueezePage::select(['id', 'title', 'slug', 'created_at', 'status', 'activate'])->get();
            return response()->json([
                'status' => Response::HTTP_OK,
                'message' => 'Squeeze Pages retrieved successfully',
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function destroy(DeleteSqueezeRequest $request, string $squeeze_page)
    {
        try {
            SqueezePage::findOrFail($squeeze_page)->delete();
            return response()->json([
                'message' => 'Squeeze Page deleted successfully',
                'status' => Response::HTTP_OK,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal server error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function search(Request $request)
    {
            $request->validate([
                'q' => 'required|string|max:255',
            ]);

            $query = SqueezePage::query();

            if ($searchTerm = $request->input('q')) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('headline', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('sub_headline', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('title', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('slug', 'LIKE', "%{$searchTerm}%");
                });
            }

            $results = $query->get();

            return response()->json([
                'status' => Response::HTTP_OK,
                'message' => 'Search result retrieved',
                'data' => $results,
            ]);
    }

    public function filter(FilterSqueezeRequest $request)
    {
        $query = SqueezePage::query();

        // Filter by status if provided
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by slug if provided
        if ($slug = $request->input('slug')) {
            $query->where('slug', $slug);
        }

        // Get the results
        $results = $query->get();

        return response()->json([
            'status' => Response::HTTP_OK,
            'message' => 'Filtered results',
            'data' => $results,
        ]);
    }
}
