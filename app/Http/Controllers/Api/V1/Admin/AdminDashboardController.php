<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Models\UserSubscription;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function getUsers(Request $request)
    {
        // Get the 'status' and 'is_disabled' query parameters
        $status = $request->query('status'); // For filtering by active or inactive status
        $isDisabled = $request->query('is_disabled'); // For filtering by disabled status
        $createdAtFrom = $request->query('created_at_from'); // Start date for filtering
        $createdAtTo = $request->query('created_at_to'); // End date for filtering

        // Build the query
        $query = User::select('id', 'name', 'email', 'is_active', 'created_at',)
            ->orderBy('created_at', 'desc');

        // Apply filters if provided
        if ($status !== null) {
            if ($status === 'true') {
                $query->where('status', 'true');
            } elseif ($status === 'false') {
                $query->where('status', 'false');
            }
        }

        if ($isDisabled !== null) {
            $isDisabled = filter_var($isDisabled, FILTER_VALIDATE_BOOLEAN); // Convert to boolean
            $query->where('is_disabled', $isDisabled);
        }

        if ($createdAtFrom) {
            $query->where('created_at', '>=', $createdAtFrom);
        }

        if ($createdAtTo) {
            $query->where('created_at', '<=', $createdAtTo);
        }
        // Paginate results
        $users = $query->paginate(15);

        return response()->json($users);
    }
    public function getStatistics()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Revenue statistics
        $currentMonthRevenue = Order::where('created_at', '>=', $currentMonth)
            ->sum('total_amount');
        $lastMonthRevenue = Order::whereBetween('created_at', [$lastMonth, $currentMonth])
            ->sum('total_amount');
        $revenuePercentageDifference = $this->calculatePercentageDifference($currentMonthRevenue, $lastMonthRevenue);

        // User statistics
        $totalUsers = User::count();
        $lastMonthUsers = User::where('created_at', '<', $currentMonth)->count();
        $userPercentageDifference = $this->calculatePercentageDifference($totalUsers, $lastMonthUsers);

        // Product statistics
        $totalProducts = Product::count();
        $lastMonthProducts = Product::where('created_at', '<', $currentMonth)->count();
        $productPercentageDifference = $this->calculatePercentageDifference($totalProducts, $lastMonthProducts);

        // Lifetime sales statistics
        $lifetimeSales = Order::sum('total_amount');
        $lastMonthSales = Order::where('created_at', '<', $currentMonth)->sum('total_amount');
        $salesPercentageDifference = $this->calculatePercentageDifference($lifetimeSales, $lastMonthSales);

        return response()->json([
            'message' => 'Dashboard statistics retrieved successfully',
            'status_code' => Response::HTTP_OK,
            'data' => [
                'total_revenue' => [
                    'current_month' => $currentMonthRevenue,
                    'previous_month' => $lastMonthRevenue,
                    'percentage_difference' => round($revenuePercentageDifference, 2) . '%',
                ],
                'total_users' => [
                    'current_month' => $totalUsers,
                    'previous_month' => $lastMonthUsers,
                    'percentage_difference' => round($userPercentageDifference, 2) . '%',
                ],
                'total_products' => [
                    'current_month' => $totalProducts,
                    'previous_month' => $lastMonthProducts,
                    'percentage_difference' => round($productPercentageDifference, 2) . '%',
                ],
                'lifetime_sales' => [
                    'current_month' => $lifetimeSales,
                    'previous_month' => $lastMonthSales,
                    'percentage_difference' => round($salesPercentageDifference, 2) . '%',
                ],
            ]
        ]);
    }

    public function dashboardStatistics()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Revenue statistics
        $revenue = Order::sum('total_amount');
        // User statistics
        $totalUsers = User::count();
        

        // Lifetime sales statistics
        $lifetimeSales = Order::sum('total_amount');
        $monthSales = Order::latest()->first();
        $subscriptions = UserSubscription::count();
        $activeSubscription = UserSubscription::where('cancellation_reason', "")->count();

        return response()->json([
            'message' => 'Dashboard statistics retrieved successfully',
            'status_code' => Response::HTTP_OK,
            'data' => [
                'revenue' => $revenue,
                'sales' => $lifetimeSales,
                'subscriptions' => $subscriptions,
                'activeSubscription'=>$activeSubscription,
                'monthSales'=>$monthSales
                
            ]
        ]);
    }

    private function calculatePercentageDifference($current, $previous)
    {
        if ($previous > 0 && $current > 0) {
            return (($current - $previous) / $previous) * 100;
        } elseif ($current > 0 && $previous === 0) {
            return 100;
        }
        return 0;
    }

    public function getTopProducts($limit = 6)
    {
        $topProducts = Product::withCount('orders')
            ->orderByDesc('orders_count')
            ->take($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'total_sales' => $product->orders_count
                ];
            });

        return response()->json([
            'message' => 'Top products retrieved successfully',
            'status_code' => Response::HTTP_OK,
            'data' => [
                'top_products' => $topProducts,
                'total_products' => Product::count()
            ]
        ]);
    }

    public function getAllProductsSortedBySales()
    {
        $allProducts = Product::withCount('orders')
            ->orderByDesc('orders_count')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'total_sales' => $product->orders_count
                ];
            });

        return response()->json([
            'message' => 'All products sorted by sales retrieved successfully',
            'status_code' => Response::HTTP_OK,
            'data' => $allProducts
        ]);
    }
}
