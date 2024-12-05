<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use App\Models\User;
use Illuminate\Http\Response;

class HelpArticleController extends Controller
{


    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'status_code' => 401,
                'message' => 'Authentication failed',
                'data' => null
            ], 401);
        }

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 422,
                'message' => 'Invalid input data.',
                'errors' => $validator->errors(),
                'data' => null
            ], 422);
        }

        try {
            // Create a new help article
            $article = HelpArticle::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'content' => $request->content,
            ]);

            return response()->json([
                'status_code' => 201,
                'message' => 'Help article created successfully.',
                'data' => [
                    'id' => $article->article_id,
                    'title' => $article->title,
                    'content' => $article->content,
                    'author' => Auth::user()->name
                ]
            ], 201);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                return response()->json([
                    'status_code' => 409,
                    'message' => 'An article with this title already exists.',
                    'data' => null
                ], 409);
            }

            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to create help article. Please try again later.',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to create help article. Please try again later.',
                'error' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function update(Request $request, $articleId)
    {
        try {
            $article = HelpArticle::findOrFail($articleId);

            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255',
                'content' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'success' => false,
                    'message' => 'Invalid input data.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $article->update($request->only(['title', 'content']));
            $data = [
                'id' => $article->article_id,
                'title' => $article->title,
                'content' => $article->content,
                'author' => $article->user_id
            ];

            return response()->json([
                'status_code' => 200,
                'message' => 'Topic updated successfully',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 404,
                'message' => 'Help article not found',
                'data' => null
            ], 404);
        }
    }



    public function destroy($articleId)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status_code' => 401,
                'message' => 'Authentication failed',
                'data' => null
            ], 401);
        }

        try {
            // Find the article by ID
            $article = HelpArticle::find($articleId);

            if (!$article) {
                return response()->json([
                    'status_code' => 404,
                    'message' => 'Help article not found.',
                    'data' => null
                ], 404);
            }

            // Ensure only the authenticated user can delete the article
            if ($article->user_id !== Auth::id()) {
                return response()->json([
                    'status_code' => 403,
                    'message' => 'You do not have permission to access this resource.',
                    'data' => null
                ], 403);
            }

            // Delete the article
            $article->delete();

            return response()->json([
                'status_code' => 200,
                'message' => 'Topic deleted successfully',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'Failed to delete help article. Please try again later.',
                'data' => null
            ], 500);
        }
    }

    public function getArticles(Request $request)
    {
        // Validate query parameters
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'size' => 'nullable|integer|min:1|max:100',
            'category' => 'nullable|integer',
            'search' => 'nullable|string|min:3'
        ]);

        try {
            $query = HelpArticle::query();

            if ($request->has('search')) {
                $query->where(function ($query) use ($request) {
                    $query->where('title', 'like', '%' . $request->search . '%')
                        ->orWhere('content', 'like', '%' . $request->search . '%');
                });
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $page = $request->get('page', 1);
            $size = $request->get('size', 10);
            $articles = $query->paginate($size, ['*'], 'page', $page);

            return response()->json([
                'status_code' => 200,
                'message' => 'Articles retrieved successfully.',
                'data' =>  collect($articles->items())->map(function ($item) {
                    return [
                        'id' => $item->article_id,
                        'title' => $item->title,
                        'content' => $item->content,
                        'author' => $item->user->name,
                    ];
                })->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'success' => false,
                'message' => 'Failed to retrieve help articles. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function search(Request $request)
    {
        // Rate limiting
        $this->middleware('throttle:search,10'); // Allows 10 requests per minute, adjust as needed

        $title = $request->query('title');

        // Validate input
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input data.',
                'status_code' => 400,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Use article_id as defined in your schema
            $articles = HelpArticle::where('title', 'like', "%{$title}%")
                ->get(['article_id', 'title', 'content', 'user_id']);

            if ($articles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No articles found.',
                    'status_code' => 404
                ], 404);
            }

            // Fetch author names
            $articles->transform(function ($article) {
                $user = User::find($article->user_id); // Ensure you have a User model
                $article->author = $user ? $user->first_name . ' ' . $user->last_name : 'Unknown';
                return $article;
            });

            return response()->json([
                'success' => true,
                'message' => 'Articles retrieved successfully.',
                'status_code' => 200,
                'topics' => $articles
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging


            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve articles. Please try again later.',
                'status_code' => 500,
                'error' => $e->getMessage() // Include exception message in the response
            ], 500);
        }
    }

    public function show($articleId)
    {
        try {

            $article = HelpArticle::findOrFail($articleId);

            return response()->json([
                'status_code' => Response::HTTP_OK,
                'message' => 'Request completed successfully',
                'data' => [
                    'id' => $article->article_id,
                    'title' => $article->title,
                    'content' => $article->content,
                    'author' => $article->user?->name
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'data' => null,
                'error' => 'Help article not found',
                'message' => 'Help article not found',
                'status_code' => Response::HTTP_NOT_FOUND,
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
