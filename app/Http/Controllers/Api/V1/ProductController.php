<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\OrganisationUser;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Http\Resources\ProductResource;


class ProductController extends Controller
{
    /**
     * Search for products within an organization.
     */
    public function search(Request $request, $orgId)
    {
        $query = Product::where('org_id', $orgId);

        // Apply filters based on query parameters
        if ($request->has('product_name')) {
            $query->where('name', 'like', '%' . $request->query('product_name') . '%');
        }

        if ($request->has('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->has('minPrice')) {
            $query->where('price', '>=', $request->query('minPrice'));
        }

        if ($request->has('maxPrice')) {
            $query->where('price', '<=', $request->query('maxPrice'));
        }

        $products = $query->get();

        // If no products are found, return a 404 response
        if ($products->isEmpty()) {
            return response()->json([
                'type' => 'Not Found',
                'title' => 'No products found',
                'status' => 404,
                'detail' => 'No products match the search criteria.',
                'instance' => url()->current(),
            ], 404);
        }

        // Transform the products for the response
        $transformedProducts = $products->map(function ($product) {
            return [
                'id' => $product->product_id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'category' => $product->category,
                'created_at' => $product->created_at->toIso8601String(),
                'updated_at' => $product->updated_at->toIso8601String(),
            ];
        });

        return response()->json($transformedProducts, 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $page = (int) $request->query('page', 1);
            $limit = (int) $request->query('limit', 10);


            // Calculate offset
            $offset = ($page - 1) * $limit;

            $products = Product::select(
                'product_id',
                'name',
                'price',
                'imageUrl',
                'description',
                'created_at',
                'updated_at',
                'quantity',
                'status',
                'size',
                'category'
            )

                ->offset($offset)
                ->limit($limit)
                ->get();

            // Get total product count
            $totalItems = Product::count();
            $totalPages = ceil($totalItems / $limit);




            $transformedProducts = $products->map(function ($product) {
                return [
                    'name' => $product->name,
                    'price' => $product->price,
                    'cost_price' => $product->cost_price,
                    'image' => url($product->imageUrl),
                    'description' => $product->description,
                    'id' => $product->product_id,
                    'quantity' => $product->quantity,
                    'category' => $product->category,
                    'status' => $product->status,
                    'size' => $product->size,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'deletedAt' => $product->deletedAt,



                ];
            });


            return response()->json([
                'status_code' => 200,
                'message' => 'Products retrieved successfully',
                'data' => [
                    'products' => $transformedProducts,
                ],

                'pagination' => [
                    'totalItems' => $totalItems,
                    'totalPages' => $totalPages,
                    'currentPage' => $page,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'bad request',
                'message' => 'Invalid query params passed',
                'status_code' => 400,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateProductRequest $request, $org_id)
    {



        $isOwner = OrganisationUser::where('org_id', $org_id)->where('user_id', auth()->id())->exists();

        if (!$isOwner) {
            return response()->json([
                'status_code' => 403,
                'message' => 'You are not authorized to create products for this organization.',


            ], 403);
        }


        // Check if the file is present
        if ($request->hasFile('image_url')) {
            $file = $request->file('image_url');
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '.' . $extension;
            $path = 'uploads/product_images/';
            $file->move(public_path($path), $filename);

            $imageUrl = $path . $filename;
        } else {
            $imageUrl = null;
        }



        $product = Product::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'size' => $request->input('size'),
            'price' => $request->input('price'),
            'status' => $request->input('status'),
            'quantity' => $request->input('quantity'),

            'imageUrl' => $imageUrl,
            'user_id' => auth()->id(),
            'org_id' => $org_id,
            'category' => $request->input('category'),
        ]);

        $product = new ProductResource($product);
        return response()->json([
            'status' => 'success',
            "message" => "Product created successfully",
            'status_code' => 201,
            'data' => $product
        ]);
    }



    /**
     * Display the specified resource.
     */
    public function show(Request $request, $org_id, $product_id)
    {
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json([
                'status' => 'error',
                "message" => "Product not found",
                'status_code' => 404,
            ]);
        }

        $products = Product::select(
            'product_id',
            'name',
            'price',
            'imageUrl',
            'description',
            'created_at',
            'updated_at',
            'quantity',
            'status',
            'size',
            'category'
        )->get();

        $transformedProduct =  [
            'id' => $product->product_id,
            'name' => $product->name,
            'price' => $product->price,
            'cost_price' => $product->cost_price,
            'image' => url($product->imageUrl),
            'description' => $product->description,
            'quantity' => $product->quantity,
            'category' => $product->category,
            'status' => $product->status,
            'size' => $product->size,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'deletedAt' => $product->deletedAt,



        ];



        return response()->json([
            'status_code' => 200,
            "message" => "Product retrieved successfully",
            'data' => $transformedProduct
        ]);
    }

    public function showProduct($product_id)
    {
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json([
                'status' => 'error',
                "message" => "Product not found",
                'status_code' => 404,
            ]);
        }

        $products = Product::select(
            'product_id',
            'name',
            'price',
            'imageUrl',
            'description',
            'created_at',
            'updated_at',
            'quantity',
            'status',
            'size',
            'category'
        )->get();

        $transformedProduct =  [
            'id' => $product->product_id,
            'name' => $product->name,
            'price' => $product->price,
            'cost_price' => $product->cost_price,
            'image' => url($product->imageUrl),
            'description' => $product->description,
            'quantity' => $product->quantity,
            'category' => $product->category,
            'status' => $product->status,
            'size' => $product->size,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'deletedAt' => $product->deletedAt,



        ];



        return response()->json([
            'status_code' => 200,
            "message" => "Product retrieved successfully",
            'data' => $transformedProduct
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, string $org_id, string $product_id)
    {
        $org_id = $request->route('org_id');

        $isOwner = OrganisationUser::where('org_id', $org_id)->where('user_id', auth()->id())->exists();

        if (!$isOwner) {
            return response()->json(['message' => 'You are not authorized to update products for this organisation.'], 403);
        }

        $validated = $request->validated();

        $product = Product::findOrFail($product_id);
        $product->update([
            'name' => $validated['name'] ?? $product->name,
            'quantity' => $validated['quantity'] ?? $product->quantity,
            'price' => $validated['price'] ?? $product->price,
            'category' => $validated['category'] ?? $product->category,
            'description' => $validated['description'] ?? $product->description,

        ]);

        $transformedProduct =  [
            'name' => $product->name,
            'price' => $product->price,
            'cost_price' => $product->cost_price,
            'image' => url($product->imageUrl),
            'description' => $product->description,
            'id' => $product->product_id,
            'quantity' => $product->quantity,
            'category' => $product->category,
            'status' => $product->status,
            'size' => $product->size,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'deletedAt' => $product->deletedAt,



        ];




        return response()->json([
            'status_code' => 200,
            'message' => 'Products updated successfully',
            'data' => $transformedProduct


        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($org_id, $product_id)
    {

        $isOwner = OrganisationUser::where('org_id', $org_id)->where('user_id', auth()->id())->exists();
        // Check if the user's organisation matches the org_id in the request
        if (!$isOwner) {
            return response()->json(
                [
                    'status' => 'Forbidden',
                    'message' => 'You do not have permission to delete a product from this organisation.',
                    'status_code' => 403
                ],
                403
            );
        }

        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
                'message' => "The product with ID $product_id does not exist."
            ], 404);
        }

        // Check if the product belongs to the organisation
        if ($product->org_id !== $org_id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to delete this product.'
            ], 403);
        }

        $product->delete();

        return response()->json([
            'status_code' => 200,
            'message' => 'Product successfully deleted.',
            'data' => $product
        ], 200);
    }

    public function delete($product_id)
    {

       

        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
                'message' => "The product with ID $product_id does not exist."
            ], 404);
        }

       

        $product->delete();

        return response()->json([
            'status_code' => 204,
            'message' => 'Product successfully deleted.',
            // 'data' => $product
        ], 204);
    }
}
