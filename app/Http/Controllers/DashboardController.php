<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Dashboard asosiy sahifasi
     */
    public function index()
    {
        return view('dashboard.index');
    }

    /**
     * Foydalanuvchilar ro'yxati
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Qidiruv
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('chat_id', 'like', "%{$search}%");
            });
        }

        // Filtrlash
        if ($request->has('status') && !empty($request->status)) {
            if ($request->status === 'active') {
                $query->whereDate('updated_at', '>=', now()->subDays(7));
            } elseif ($request->status === 'inactive') {
                $query->whereDate('updated_at', '<', now()->subDays(7));
            }
        }

        $users = $query->withCount(['transactions'])
                      ->orderBy('created_at', 'desc')
                      ->paginate(20);

        // AJAX so'rovi uchun JSON qaytarish
        if ($request->expectsJson()) {
            return response()->json($users);
        }

        return view('dashboard.users', compact('users'));
    }

    /**
     * Foydalanuvchi ma'lumotlari
     */
    public function userDetails($id)
    {
        $user = User::withCount(['transactions'])
                   ->findOrFail($id);

        // AJAX so'rovi uchun JSON qaytarish
        if (request()->expectsJson()) {
            return response()->json($user);
        }

        return view('dashboard.user-details', compact('user'))->with('userId', $id);
    }

    /**
     * Foydalanuvchi tranzaksiyalari
     */
    public function userTransactions($id, Request $request)
    {
        $query = Transaction::where('user_id', $id);

        // Tur bo'yicha filtrlash
        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', $request->type);
        }

        $transactions = $query->orderBy('created_at', 'desc')
                             ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Foydalanuvchi statistikalari
     */
    public function userStats($id)
    {
        $stats = [
            'total_income' => Transaction::where('user_id', $id)
                                       ->where('type', 'income')
                                       ->sum('amount'),
            'total_expense' => Transaction::where('user_id', $id)
                                        ->where('type', 'expense')
                                        ->sum('amount'),
            'total_transfers' => Transaction::where('user_id', $id)
                                          ->where('type', 'transfer')
                                          ->count(),
            'avg_transaction' => Transaction::where('user_id', $id)
                                          ->avg('amount'),
        ];

        return response()->json($stats);
    }

    /**
     * Umumiy statistika API
     */
    public function apiStats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::whereDate('updated_at', '>=', now()->subDays(7))->count(),
            'total_transactions' => Transaction::count(),
            'total_amount' => Transaction::sum('amount'),
        ];

        return response()->json($stats);
    }

    /**
     * So'nggi foydalanuvchilar API
     */
    public function recentUsers()
    {
        $users = User::orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

        return response()->json($users);
    }

    /**
     * Foydalanuvchilar statistikalari API
     */
    public function userStatsApi()
    {
        $stats = [
            'active_count' => User::whereDate('updated_at', '>=', now()->subDays(7))->count(),
            'new_today' => User::whereDate('created_at', today())->count(),
            'new_week' => User::where('created_at', '>=', now()->subWeek())->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Foydalanuvchilar API (AJAX uchun)
     */
    public function usersApi(Request $request)
    {
        $query = User::query();

        // Qidiruv
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('chat_id', 'like', "%{$search}%");
            });
        }

        // Filtrlash
        if ($request->has('status') && !empty($request->status)) {
            if ($request->status === 'active') {
                $query->whereDate('updated_at', '>=', now()->subDays(7));
            } elseif ($request->status === 'inactive') {
                $query->whereDate('updated_at', '<', now()->subDays(7));
            }
        }

        $users = $query->withCount(['transactions'])
                      ->orderBy('created_at', 'desc')
                      ->paginate(20);

        return response()->json($users);
    }

    /**
     * Admin login: chat_id orqali token berish
     */
    public function adminLogin(Request $request)
    {
        $request->validate([
            'chat_id' => ['required']
        ]);

        $adminChatId = env('ADMIN_CHAT_ID');
        if ($adminChatId) {
            if ((string)$request->chat_id !== (string)$adminChatId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $user = User::where('chat_id', $request->chat_id)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $token = $user->createToken('admin')->plainTextToken;
        return response()->json(['token' => $token]);
    }

    /**
     * User details API: doimiy JSON qaytaradi
     */
    public function userDetailsApi($id)
    {
        $user = User::withCount(['transactions'])->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Broadcast xabarlar sahifasi
     */
    public function broadcast()
    {
        return view('dashboard.broadcast');
    }
}