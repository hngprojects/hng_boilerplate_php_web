<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class FaqController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
                'category' => 'required|string',
            ]);

            $faq = Faq::create([
                'id' => Str::uuid(),
                'question' => $validatedData['question'],
                'answer' => $validatedData['answer'],
                'category' => $validatedData['category'],
                'created_by' => auth()->user()->name,
            ]);

            return response()->json([
                'status_code' => 201,
                // 'message' => 'FAQ created successfully',
                'success' => true,
                'data' => $faq
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status_code' => 422,
                'message' => 'Validation failed',
                'data' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'An error occurred while creating the FAQ',
                'data' => []
            ], 500);
        }
    }


    public function index()
    {
        try {
            $faqs = Faq::all()->map(function ($faq) {
                return [
                    'id' => $faq->id,
                    'created_at' => $faq->created_at->toIso8601String(),
                    'updated_at' => $faq->updated_at->toIso8601String(),
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'created_by' => $faq->created_by
                ];
            });

            return response()->json([
                'status_code' => 200,
                'message' => 'Faq fetched successfully',
                'data' => $faqs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'An error occurred while fetching FAQs',
                'data' => []
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
                'category' => 'required|string',
            ]);

            $faq = Faq::findOrFail($id);

            $faq->update([
                'question' => $validatedData['question'],
                'answer' => $validatedData['answer'],
                'category' => $validatedData['category'],
            ]);

            return response()->json([
                'status_code' => 200,
                'message' => 'FAQ updated successfully',
                'data' => [
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'id' => $faq->id,
                    'created_at' => $faq->created_at->toIso8601String(),
                    'updated_at' => $faq->updated_at->toIso8601String(),
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status_code' => 422,
                'message' => 'Validation failed',
                'data' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'An error occurred while updating the FAQ',
                'data' => []
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->delete();

            return response()->json([
                'status_code' => 200,
                'message' => 'FAQ successfully deleted'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 500,
                'message' => 'An error occurred while deleting the FAQ',
                'data' => []
            ], 500);
        }
    }
}
