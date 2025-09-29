<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class FinanceController extends Controller
{
    // Universal keyboard validation function to prevent Telegram parsing errors
    private function validateTelegramKeyboard($keyboard, $context = "Unknown") {
        if (!is_array($keyboard)) {
            Log::error("Telegram keyboard error: Keyboard is not an array in context: $context");
            return [];
        }

        $fixedKeyboard = [];
        $hasErrors = false;

        foreach ($keyboard as $rowIndex => $row) {
            if (!is_array($row)) {
                Log::error("Telegram keyboard error: Row $rowIndex is not an array in context: $context");
                $hasErrors = true;
                continue;
            }

            if (empty($row)) {
                continue; // Skip empty rows
            }

            $fixedRow = [];
            foreach ($row as $buttonIndex => $button) {
                if ($button === null) {
                    Log::error("Telegram keyboard error: Button at row $rowIndex, position $buttonIndex is null in context: $context");
                    $hasErrors = true;
                    continue;
                }

                if (!is_string($button)) {
                    // Handle different types more carefully
                    if (is_array($button)) {
                        Log::error("Telegram keyboard error: Button at row $rowIndex, position $buttonIndex is an array in context: $context");
                        $hasErrors = true;
                        continue; // Skip arrays completely
                    } elseif (is_object($button)) {
                        Log::error("Telegram keyboard error: Button at row $rowIndex, position $buttonIndex is an object in context: $context");
                        $hasErrors = true;
                        continue; // Skip objects completely
                    } else {
                        Log::warning("Telegram keyboard warning: Button at row $rowIndex, position $buttonIndex is not a string in context: $context, converting");
                        $button = (string)$button;
                        $hasErrors = true;
                    }
                }

                $button = trim($button);
                if (empty($button)) {
                    Log::error("Telegram keyboard error: Button at row $rowIndex, position $buttonIndex is empty in context: $context");
                    $hasErrors = true;
                    continue;
                }

                $fixedRow[] = $button;
            }

            if (!empty($fixedRow)) {
                $fixedKeyboard[] = $fixedRow;
            }
        }

        if ($hasErrors) {
            Log::warning("Telegram keyboard warning: Keyboard had errors and was fixed in context: $context");
        }

        return $fixedKeyboard;
    }

    public function showMainMenu($userChatId)
    {
        // Standart kategoriyalarni yaratish
        $this->createDefaultCategories();

        $keyboard = $this->validateTelegramKeyboard([
            ['💵 Kirim', '💸 Chiqim'],
            ['📊 Statistika', '📋 Barcha amaliyotlar']
        ], 'Main Menu');

        Log::info('Sending main menu keyboard: ' . json_encode($keyboard));

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "💰 Asosiy menyu\n\nQuyidagi bo'limlardan birini tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showIncomeMenu($userChatId)
    {
        $keyboard = $this->validateTelegramKeyboard([
            ['➕ Qo\'shish', '👁 Ko\'rish'],
            ['🔙 Orqaga']
        ], 'Income Menu');

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "💵 Kirim bo'limi",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showExpenseMenu($userChatId)
    {
        $keyboard = $this->validateTelegramKeyboard([
            ['➕ Qo\'shish', '👁 Ko\'rish'],
            ['🔙 Orqaga']
        ], 'Expense Menu');

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "💸 Chiqim bo'limi",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showIncomeCategories($userChatId)
    {
        $categories = Category::where('type', 'income')->get();

        $keyboard = [];
        foreach ($categories as $category) {
            $keyboard[] = [$category->name];
        }

        $keyboard[] = ['🔙 Orqaga'];
        $keyboard = $this->validateTelegramKeyboard($keyboard, 'Income Categories');

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "💵 Kirim kategoriyasini tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showExpenseCategories($userChatId)
    {
        $categories = Category::where('type', 'expense')->get();

        $keyboard = [];
        foreach ($categories as $category) {
            $keyboard[] = [$category->name];
        }

        $keyboard[] = ['🔙 Orqaga'];

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "💸 Chiqim kategoriyasini tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showIncomeView($userChatId, $period = 'today', $value = null, $page = 1)
    {
        // Cache pagination parameters
        \Illuminate\Support\Facades\Cache::put("pagination_page_{$userChatId}", $page, 300);
        \Illuminate\Support\Facades\Cache::put("pagination_period_{$userChatId}", $period, 300);
        \Illuminate\Support\Facades\Cache::put("pagination_value_{$userChatId}", $value, 300);
        $user = User::where('chat_id', $userChatId)->first();
        if (!$user) return;

        $query = Transaction::where('user_id', $user->id)
                           ->where('type', 'income')
                           ->with('category');

        // Sana filtri
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                $periodText = "bugungi";
                break;
            case 'yesterday':
                $query->whereDate('created_at', Carbon::yesterday());
                $periodText = "kechagi";
                break;
            case 'this_week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                $periodText = "bu haftadagi";
                break;
            case 'last_week':
                $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                $periodText = "o'tgan haftadagi";
                break;
            case 'this_month':
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
                $periodText = "bu oydagi";
                break;
            case 'last_month':
                $lastMonth = Carbon::now()->subMonth();
                $query->whereMonth('created_at', $lastMonth->month)
                      ->whereYear('created_at', $lastMonth->year);
                $periodText = "o'tgan oydagi";
                break;
            case 'month_year':
                Log::info("DEBUG EXPENSE: period={$period}, value={$value}");
                if ($value && strpos($value, '.') !== false) {
                    $parts = explode('.', $value);
                    Log::info("DEBUG EXPENSE: parts=" . json_encode($parts));
                    if (count($parts) === 2) {
                        $month = (int)$parts[0];
                        $year = (int)$parts[1];
                        Log::info("DEBUG EXPENSE: month={$month}, year={$year}");
                        $query->whereMonth('created_at', $month)
                              ->whereYear('created_at', $year);
                        $monthNames = [
                            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel',
                            5 => 'May', 6 => 'Iyun', 7 => 'Iyul', 8 => 'Avgust',
                            9 => 'Sentyabr', 10 => 'Oktyabr', 11 => 'Noyabr', 12 => 'Dekabr'
                        ];
                        $periodText = $monthNames[$month] . " {$year}";
                    } else {
                        Log::info("DEBUG EXPENSE: Invalid parts count, using today");
                        $query->whereDate('created_at', Carbon::today());
                        $periodText = "bugungi";
                    }
                } else {
                    Log::info("DEBUG EXPENSE: No value or no dot, using today");
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'month_year':
                if ($value && strpos($value, '.') !== false) {
                    $parts = explode('.', $value);
                    if (count($parts) === 2) {
                        $month = (int)$parts[0];
                        $year = (int)$parts[1];
                        $query->whereMonth('created_at', $month)
                              ->whereYear('created_at', $year);
                        $monthNames = [
                            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel',
                            5 => 'May', 6 => 'Iyun', 7 => 'Iyul', 8 => 'Avgust',
                            9 => 'Sentyabr', 10 => 'Oktyabr', 11 => 'Noyabr', 12 => 'Dekabr'
                        ];
                        $periodText = $monthNames[$month] . " {$year}";
                    } else {
                        $query->whereDate('created_at', Carbon::today());
                        $periodText = "bugungi";
                    }
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'month':
                if ($value) {
                    $currentYear = date('Y');
                    $query->whereMonth('created_at', $value)
                          ->whereYear('created_at', $currentYear);
                    $monthNames = [
                        '01' => 'Yanvar', '02' => 'Fevral', '03' => 'Mart', '04' => 'Aprel',
                        '05' => 'May', '06' => 'Iyun', '07' => 'Iyul', '08' => 'Avgust',
                        '09' => 'Sentyabr', '10' => 'Oktyabr', '11' => 'Noyabr', '12' => 'Dekabr'
                    ];
                    $periodText = $monthNames[$value] . " {$currentYear}";
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'year':
                if ($value) {
                    $query->whereYear('created_at', $value);
                    $periodText = "{$value} yildagi";
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'date':
                if ($value) {
                    try {
                        $date = Carbon::createFromFormat('d.m.Y', $value);
                        $query->whereDate('created_at', $date);
                        $periodText = $date->format('d.m.Y') . " sanasidagi";
                    } catch (\Exception $e) {
                        $query->whereDate('created_at', Carbon::today());
                        $periodText = "bugungi";
                    }
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            default:
                $query->whereDate('created_at', Carbon::today());
                $periodText = "bugungi";
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();
        $total = $transactions->sum('amount');

        // Pagination
        $perPage = 10;
        $totalTransactions = $transactions->count();
        $totalPages = ceil($totalTransactions / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedTransactions = $transactions->slice($offset, $perPage);

        $text = "💵 Kirimlar - {$periodText}\n";
        if ($totalPages > 1) {
            $text .= "📄 Sahifa {$page}/{$totalPages}\n";
        }
        $text .= "\n";

        if ($paginatedTransactions->count() > 0) {
            // Bugungi va kechagi uchun sana sarlavhasi qo'shish
            $showDateHeader = in_array($period, ['today', 'yesterday']);

            if ($showDateHeader) {
                $headerDate = $period === 'today' ? Carbon::today() : Carbon::yesterday();
                $text .= "📅 " . $headerDate->format('d.m.Y') . "\n\n";
            }

            foreach ($paginatedTransactions as $transaction) {
                $transactionDate = Carbon::parse($transaction->created_at);
                $category = $transaction->category ? $transaction->category->name : 'Kategoriyasiz';
                $description = $transaction->description ? " - {$transaction->description}" : "";

                if ($showDateHeader) {
                    // Bugungi/kechagi uchun faqat soat:daqiqa
                    $time = $transactionDate->format('H:i');
                    $text .= "🕐 {$time}\n";
                } else {
                    // Haftalik/oylik uchun sana va vaqt
                    $dateTime = $transactionDate->format('d.m.Y H:i');
                    $text .= "📅 {$dateTime}\n";
                }

                $text .= "📂 {$category}\n";
                $text .= "💰 " . number_format($transaction->amount, 0, '.', ' ') . " so'm\n";
                if ($description) {
                    $text .= "📝 {$description}\n";
                }
                $text .= "\n";
            }

            $text .= "💵 Jami: " . number_format($total, 0, '.', ' ') . " so'm";
        } else {
            $text .= "📭 {$periodText} kirimlar yo'q";
        }

        // Filtr tugmalari - sana tanlanganda sodda ko'rinish
        if ($period === 'date' && $value) {
            // Sana tanlanganda faqat asosiy tugmalar
            $keyboard = [];
            
            // Pagination tugmalari
            if ($totalPages > 1) {
                $paginationRow = [];
                if ($page > 1) {
                    $paginationRow[] = "⬅️ Oldingi";
                }
                if ($page < $totalPages) {
                    $paginationRow[] = "Keyingi ➡️";
                }
                if (!empty($paginationRow)) {
                    $keyboard[] = $paginationRow;
                }
            }
            
            $keyboard = array_merge($keyboard, [
                ['📅 Bugun', '📅 Kecha'],
                ['📅 Bu oy'],
                ['🔙 Orqaga']
            ]);
            $keyboard = $this->validateTelegramKeyboard($keyboard, 'Date Filter Menu');
        } else {
            // Boshqa holatlar uchun to'liq filtr
            $keyboard = [];
            
            // Pagination tugmalari
            if ($totalPages > 1) {
                $paginationRow = [];
                if ($page > 1) {
                    $paginationRow[] = "⬅️ Oldingi";
                }
                if ($page < $totalPages) {
                    $paginationRow[] = "Keyingi ➡️";
                }
                if (!empty($paginationRow)) {
                    $keyboard[] = $paginationRow;
                }
            }
            
            $keyboard = array_merge($keyboard, [
                ['📅 Bugun', '📅 Kecha'],
                ['📅 Bu hafta', '📅 O\'tgan hafta'],
                ['📅 Bu oy', '📅 O\'tgan oy'],
                ['📅 Oy tanlash', '📅 Yil tanlash'],
                ['📅 Kun tanlash'],
                ['🔙 Orqaga']
            ]);
            $keyboard = $this->validateTelegramKeyboard($keyboard, 'Filter Menu');
        }

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showExpenseView($userChatId, $period = 'today', $value = null, $page = 1)
    {
        // Cache pagination parameters
        \Illuminate\Support\Facades\Cache::put("pagination_page_{$userChatId}", $page, 300);
        \Illuminate\Support\Facades\Cache::put("pagination_period_{$userChatId}", $period, 300);
        \Illuminate\Support\Facades\Cache::put("pagination_value_{$userChatId}", $value, 300);
        $user = User::where('chat_id', $userChatId)->first();
        if (!$user) return;

        $query = Transaction::where('user_id', $user->id)
                           ->where('type', 'expense')
                           ->with('category');

        // Sana filtri
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                $periodText = "bugungi";
                break;
            case 'yesterday':
                $query->whereDate('created_at', Carbon::yesterday());
                $periodText = "kechagi";
                break;
            case 'this_week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                $periodText = "bu haftadagi";
                break;
            case 'last_week':
                $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                $periodText = "o'tgan haftadagi";
                break;
            case 'this_month':
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
                $periodText = "bu oydagi";
                break;
            case 'last_month':
                $lastMonth = Carbon::now()->subMonth();
                $query->whereMonth('created_at', $lastMonth->month)
                      ->whereYear('created_at', $lastMonth->year);
                $periodText = "o'tgan oydagi";
                break;
            case 'month_year':
                Log::info("DEBUG EXPENSE: period={$period}, value={$value}");
                if ($value && strpos($value, '.') !== false) {
                    $parts = explode('.', $value);
                    Log::info("DEBUG EXPENSE: parts=" . json_encode($parts));
                    if (count($parts) === 2) {
                        $month = (int)$parts[0];
                        $year = (int)$parts[1];
                        Log::info("DEBUG EXPENSE: month={$month}, year={$year}");
                        $query->whereMonth('created_at', $month)
                              ->whereYear('created_at', $year);
                        $monthNames = [
                            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel',
                            5 => 'May', 6 => 'Iyun', 7 => 'Iyul', 8 => 'Avgust',
                            9 => 'Sentyabr', 10 => 'Oktyabr', 11 => 'Noyabr', 12 => 'Dekabr'
                        ];
                        $periodText = $monthNames[$month] . " {$year}";
                    } else {
                        Log::info("DEBUG EXPENSE: Invalid parts count, using today");
                        $query->whereDate('created_at', Carbon::today());
                        $periodText = "bugungi";
                    }
                } else {
                    Log::info("DEBUG EXPENSE: No value or no dot, using today");
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'month_year':
                if ($value && strpos($value, '.') !== false) {
                    $parts = explode('.', $value);
                    if (count($parts) === 2) {
                        $month = (int)$parts[0];
                        $year = (int)$parts[1];
                        $query->whereMonth('created_at', $month)
                              ->whereYear('created_at', $year);
                        $monthNames = [
                            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel',
                            5 => 'May', 6 => 'Iyun', 7 => 'Iyul', 8 => 'Avgust',
                            9 => 'Sentyabr', 10 => 'Oktyabr', 11 => 'Noyabr', 12 => 'Dekabr'
                        ];
                        $periodText = $monthNames[$month] . " {$year}";
                    } else {
                        $query->whereDate('created_at', Carbon::today());
                        $periodText = "bugungi";
                    }
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'month':
                if ($value) {
                    $currentYear = date('Y');
                    $query->whereMonth('created_at', $value)
                          ->whereYear('created_at', $currentYear);
                    $monthNames = [
                        '01' => 'Yanvar', '02' => 'Fevral', '03' => 'Mart', '04' => 'Aprel',
                        '05' => 'May', '06' => 'Iyun', '07' => 'Iyul', '08' => 'Avgust',
                        '09' => 'Sentyabr', '10' => 'Oktyabr', '11' => 'Noyabr', '12' => 'Dekabr'
                    ];
                    $periodText = $monthNames[$value] . " {$currentYear}";
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'year':
                if ($value) {
                    $query->whereYear('created_at', $value);
                    $periodText = "{$value} yildagi";
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            case 'date':
                if ($value) {
                    try {
                        $date = Carbon::createFromFormat('d.m.Y', $value);
                        $query->whereDate('created_at', $date);
                        $periodText = $date->format('d.m.Y') . " sanasidagi";
                    } catch (\Exception $e) {
                        $query->whereDate('created_at', Carbon::today());
                        $periodText = "bugungi";
                    }
                } else {
                    $query->whereDate('created_at', Carbon::today());
                    $periodText = "bugungi";
                }
                break;
            default:
                $query->whereDate('created_at', Carbon::today());
                $periodText = "bugungi";
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();
        $total = $transactions->sum('amount');

        // Pagination
        $perPage = 10;
        $totalTransactions = $transactions->count();
        $totalPages = ceil($totalTransactions / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedTransactions = $transactions->slice($offset, $perPage);

        $text = "💸 Chiqimlar - {$periodText}\n";
        if ($totalPages > 1) {
            $text .= "📄 Sahifa {$page}/{$totalPages}\n";
        }
        $text .= "\n";

        if ($paginatedTransactions->count() > 0) {
            // Bugungi va kechagi uchun sana sarlavhasi qo'shish
            $showDateHeader = in_array($period, ['today', 'yesterday']);

            if ($showDateHeader) {
                $headerDate = $period === 'today' ? Carbon::today() : Carbon::yesterday();
                $text .= "📅 " . $headerDate->format('d.m.Y') . "\n\n";
            }

            foreach ($paginatedTransactions as $transaction) {
                $transactionDate = Carbon::parse($transaction->created_at);
                $category = $transaction->category ? $transaction->category->name : 'Kategoriyasiz';
                $description = $transaction->description ? " - {$transaction->description}" : "";

                if ($showDateHeader) {
                    // Bugungi/kechagi uchun faqat soat:daqiqa
                    $time = $transactionDate->format('H:i');
                    $text .= "🕐 {$time}\n";
                } else {
                    // Haftalik/oylik uchun sana va vaqt
                    $dateTime = $transactionDate->format('d.m.Y H:i');
                    $text .= "📅 {$dateTime}\n";
                }

                $text .= "📂 {$category}\n";
                $text .= "💰 " . number_format($transaction->amount, 0, '.', ' ') . " so'm\n";
                if ($description) {
                    $text .= "📝 {$description}\n";
                }
                $text .= "\n";
            }

            $text .= "💸 Jami: " . number_format($total, 0, '.', ' ') . " so'm";
        } else {
            $text .= "📭 {$periodText} chiqimlar yo'q";
        }

        // Filtr tugmalari - sana tanlanganda sodda ko'rinish
        if ($period === 'date' && $value) {
            // Sana tanlanganda faqat asosiy tugmalar
            $keyboard = [];
            
            // Pagination tugmalari
            if ($totalPages > 1) {
                $paginationRow = [];
                if ($page > 1) {
                    $paginationRow[] = "⬅️ Oldingi";
                }
                if ($page < $totalPages) {
                    $paginationRow[] = "Keyingi ➡️";
                }
                if (!empty($paginationRow)) {
                    $keyboard[] = $paginationRow;
                }
            }
            
            $keyboard = array_merge($keyboard, [
                ['📅 Bugun', '📅 Kecha'],
                ['📅 Bu oy'],
                ['🔙 Orqaga']
            ]);
            $keyboard = $this->validateTelegramKeyboard($keyboard, 'Date Filter Menu');
        } else {
            // Boshqa holatlar uchun to'liq filtr
            $keyboard = [];
            
            // Pagination tugmalari
            if ($totalPages > 1) {
                $paginationRow = [];
                if ($page > 1) {
                    $paginationRow[] = "⬅️ Oldingi";
                }
                if ($page < $totalPages) {
                    $paginationRow[] = "Keyingi ➡️";
                }
                if (!empty($paginationRow)) {
                    $keyboard[] = $paginationRow;
                }
            }
            
            $keyboard = array_merge($keyboard, [
                ['📅 Bugun', '📅 Kecha'],
                ['📅 Bu hafta', '📅 O\'tgan hafta'],
                ['📅 Bu oy', '📅 O\'tgan oy'],
                ['📅 Oy tanlash', '📅 Yil tanlash'],
                ['📅 Kun tanlash'],
                ['🔙 Orqaga']
            ]);
            $keyboard = $this->validateTelegramKeyboard($keyboard, 'Expense Filter Menu');
        }

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function startAddTransaction($userChatId, $categoryId, $type)
    {
        $user = User::firstOrCreate(['chat_id' => $userChatId]);
        $category = Category::find($categoryId);

        if (!$category) {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "❌ Kategoriya topilmadi!",
            ]);
            return;
        }

        $emoji = $type === 'income' ? '💰' : '💸';
        $typeText = $type === 'income' ? 'kirim' : 'chiqim';

        // Cache orqali tranzaksiya ma'lumotlarini saqlash
        Cache::put("transaction_process_{$userChatId}", [
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'type' => $type,
            'step' => 'date'
        ], 600); // 10 daqiqa

        // Sana tanlash menyusini ko'rsatish
        $this->showTransactionDateSelection($userChatId, $type, $category->name);
    }

    public function processAmountInput($userChatId, $amount)
    {
        $transactionData = Cache::get("transaction_process_{$userChatId}");

        if (!$transactionData || $transactionData['step'] !== 'amount') {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "❌ Tranzaksiya jarayoni topilmadi. Iltimos, qaytadan boshlang.",
            ]);
            return;
        }

        // Summani tekshirish
        if (!is_numeric($amount) || $amount <= 0) {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "❌ Noto'g'ri format. Iltimos, musbat raqam kiriting.",
            ]);
            return;
        }

        // Summani yangilash va keyingi bosqichga o'tish
        $transactionData['amount'] = $amount;
        $transactionData['step'] = 'description';

        Cache::put("transaction_process_{$userChatId}", $transactionData, 600);

        $category = Category::find($transactionData['category_id']);
        $emoji = $transactionData['type'] === 'income' ? '💰' : '💸';
        $typeText = $transactionData['type'] === 'income' ? 'kirim' : 'chiqim';
        $selectedDate = $transactionData['selected_date'] ?? 'Bugun';

        $message = "{$emoji} {$category->name} kategoriyasiga {$typeText} qo'shish\n";
        $message .= "📅 Sana: {$selectedDate}\n";
        $message .= "💰 Summa: " . number_format($amount, 0, '.', ' ') . " so'm\n";
        $message .= "\n📝 Izoh kiriting (ixtiyoriy):\n\n💡 Izoh kiritmaslik uchun /skip buyrug'ini yuboring.";

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'remove_keyboard' => true
            ])
        ]);
    }

    public function processDescriptionInput($userChatId, $description = null)
    {
        $transactionData = Cache::get("transaction_process_{$userChatId}");

        if (!$transactionData || $transactionData['step'] !== 'description') {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "❌ Tranzaksiya jarayoni topilmadi. Iltimos, qaytadan boshlang.",
            ]);
            return;
        }

        // Sana va vaqtni belgilash
        $selectedDate = $transactionData['selected_date'] ?? 'Bugun';

        if ($selectedDate === 'Bugun') {
            // Bugungi sana uchun joriy vaqt
            $transactionDate = now();
        } elseif ($selectedDate === 'Kecha') {
            // Kecha uchun 23:59 vaqti
            $transactionDate = Carbon::yesterday()->setTime(23, 59, 0);
        } else {
            // Boshqa sanalar uchun 23:59 vaqti
            try {
                $transactionDate = Carbon::createFromFormat('d.m.Y', $selectedDate)->setTime(23, 59, 0);
            } catch (\Exception $e) {
                // Agar sana formati noto'g'ri bo'lsa, bugungi sanani ishlatish
                $transactionDate = now();
            }
        }

        // Tranzaksiyani yaratish
        $transaction = Transaction::create([
            'user_id' => $transactionData['user_id'],
            'category_id' => $transactionData['category_id'],
            'type' => $transactionData['type'],
            'amount' => $transactionData['amount'],
            'description' => $description,
            'created_at' => $transactionDate,
            'updated_at' => $transactionDate,
        ]);

        $category = Category::find($transactionData['category_id']);
        $emoji = $transactionData['type'] === 'income' ? '💰' : '💸';
        $typeText = $transactionData['type'] === 'income' ? 'Kirim' : 'Chiqim';

        // Sana ko'rsatish uchun to'g'ri format
        $displayDate = '';
        if ($selectedDate === 'Bugun') {
            $displayDate = 'Bugun (' . $transactionDate->format('d.m.Y H:i') . ')';
        } elseif ($selectedDate === 'Kecha') {
            $displayDate = 'Kecha (' . $transactionDate->format('d.m.Y H:i') . ')';
        } else {
            $displayDate = $transactionDate->format('d.m.Y H:i');
        }

        $message = "✅ {$typeText} muvaffaqiyatli qo'shildi!\n\n";
        $message .= "{$emoji} Kategoriya: {$category->name}\n";
        $message .= "💰 Summa: " . number_format($transactionData['amount'], 0, '.', ' ') . " so'm\n";
        if ($description) {
            $message .= "📝 Izoh: {$description}\n";
        }
        $message .= "📅 Sana: " . $displayDate;

        // Cache dan o'chirish
        Cache::forget("transaction_process_{$userChatId}");

        // Tranzaksiya turiga qarab tegishli menyuga qaytish
        $backButtonText = $transactionData['type'] === 'income' ? '💰 Kirim bo\'limi' : '💸 Chiqim bo\'limi';
        $backButtonCallback = $transactionData['type'] === 'income' ? 'income_menu' : 'expense_menu';

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'remove_keyboard' => true,
                'inline_keyboard' => [
                    [
                        ['text' => $backButtonText, 'callback_data' => $backButtonCallback]
                    ],
                    [
                        ['text' => '🏠 Asosiy menyu', 'callback_data' => 'main_menu']
                    ]
                ]
            ])
        ]);
    }

    private function createDefaultCategories()
    {
        // Kirim kategoriyalari
        $incomeCategories = [
            '💼 Ish haqi',
            '💰 Biznes',
            '🎁 Sovg\'a',
            '💵 Qo\'shimcha daromad',
            '🏦 Investitsiya',
            '📈 Foydalar'
        ];

        foreach ($incomeCategories as $categoryName) {
            Category::firstOrCreate([
                'name' => $categoryName,
                'type' => 'income'
            ]);
        }

        // Chiqim kategoriyalari
        $expenseCategories = [
            '🍕 Oziq-ovqat',
            '🚗 Transport',
            '🏠 Uy-joy',
            '👕 Kiyim-kechak',
            '💊 Sog\'liq',
            '🎓 Ta\'lim',
            '🎮 O\'yin-kulgi',
            '📱 Aloqa',
            '⚡ Kommunal',
            '🛒 Xaridlar'
        ];

        foreach ($expenseCategories as $categoryName) {
            Category::firstOrCreate([
                'name' => $categoryName,
                'type' => 'expense'
            ]);
        }
    }

    public function showMonthSelection($userChatId, $type = 'income')
    {
        $currentMonth = (int)date('m');
        $currentYear = (int)date('Y');

        $months = [
            '01' => 'Yanvar',
            '02' => 'Fevral',
            '03' => 'Mart',
            '04' => 'Aprel',
            '05' => 'May',
            '06' => 'Iyun',
            '07' => 'Iyul',
            '08' => 'Avgust',
            '09' => 'Sentyabr',
            '10' => 'Oktyabr',
            '11' => 'Noyabr',
            '12' => 'Dekabr'
        ];

        $keyboard = [];

        // O'tgan oydan boshlab orqaga qarab 12 oy ko'rsatish (joriy oy chiqarildi)
        for ($i = 1; $i <= 12; $i++) {
            $monthNum = $currentMonth - $i;
            $year = $currentYear;

            // Agar oy 0 yoki manfiy bo'lsa, o'tgan yilga o'tish
            if ($monthNum <= 0) {
                $monthNum += 12;
                $year--;
            }

            $monthKey = sprintf('%02d', $monthNum);
            $monthName = $months[$monthKey];

            $keyboard[] = ["{$monthName} ({$year})"];
        }

        $keyboard[] = ['🔙 Orqaga'];
        $keyboard = $this->validateTelegramKeyboard($keyboard, 'Year Selection');

        $emoji = $type === 'income' ? '💵' : '💸';
        $typeText = $type === 'income' ? 'kirimlar' : 'chiqimlar';

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "{$emoji} {$typeText} uchun oy tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showYearSelection($userChatId, $type = 'income')
    {
        $currentYear = date('Y');
        $years = [];

        // Joriy yildan 5 yil oldingi yillargacha
        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
            $years[] = $year;
        }

        $keyboard = [];
        foreach ($years as $year) {
            $keyboard[] = [$year];
        }

        $keyboard[] = ['🔙 Orqaga'];

        // Validate keyboard before sending
        $keyboard = $this->validateTelegramKeyboard($keyboard, 'Year Selection');

        $emoji = $type === 'income' ? '💵' : '💸';
        $typeText = $type === 'income' ? 'kirimlar' : 'chiqimlar';

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "{$emoji} {$typeText} uchun yil tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showDaySelection($userChatId, $type = 'income')
    {
        $keyboard = $this->validateTelegramKeyboard([
            ['📅 Bugun', '📅 Kecha'],
            ['📅 3 kun oldin', '📅 1 hafta oldin'],
            ['📅 Aniq sana tanlash'],
            ['🔙 Orqaga']
        ], 'Day Selection');

        $emoji = $type === 'income' ? '💵' : '💸';
        $typeText = $type === 'income' ? 'kirimlar' : 'chiqimlar';

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "{$emoji} {$typeText} uchun kun tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showCustomDateSelection($userChatId, $type = 'income')
    {
        $currentDate = date('d.m.Y');
        $dates = [];

        // 2 kun avvaldan boshlab 30 kunlik sanalar
        for ($i = 2; $i <= 31; $i++) {
            $date = date('d.m.Y', strtotime("-{$i} days"));
            $dates[] = $date;
        }

        $keyboard = [];
        $row = [];
        foreach ($dates as $index => $date) {
            $row[] = $date;
            if (count($row) == 2 || $index == count($dates) - 1) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        $keyboard[] = ['🔙 Orqaga'];

        $emoji = $type === 'income' ? '💵' : '💸';
        $typeText = $type === 'income' ? 'kirimlar' : 'chiqimlar';

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "{$emoji} {$typeText} uchun aniq sana tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function showTransactionDateSelection($userChatId, $type, $categoryName)
    {
        $emoji = $type === 'income' ? '💰' : '💸';
        $typeText = $type === 'income' ? 'kirim' : 'chiqim';

        // Kechadan 1 oy avvalgi sanalar (kechani hisobga olmasdan)
        $dates = [];
        for ($i = 2; $i <= 30; $i++) { // 2 dan boshlaymiz, chunki kecha alohida tugma sifatida bor
            $date = date('d.m.Y', strtotime("-{$i} days"));
            $dates[] = $date;
        }

        $keyboard = [];
        $keyboard[] = ['📅 Bugun', '📅 Kecha']; // Bugun va Kecha tugmalari

        // Sanalarni 2 tadan qilib qo'yish
        $row = [];
        foreach ($dates as $index => $date) {
            $row[] = $date;
            if (count($row) == 2 || $index == count($dates) - 1) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        $keyboard[] = ['🔙 Orqaga'];

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "{$emoji} {$categoryName} kategoriyasiga {$typeText} qo'shish\n\n📅 Sana tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function processDateInput($userChatId, $selectedDate)
    {
        $transactionData = Cache::get("transaction_process_{$userChatId}");

        if (!$transactionData || $transactionData['step'] !== 'date') {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "❌ Tranzaksiya jarayoni topilmadi. Iltimos, qaytadan boshlang.",
            ]);
            return;
        }

        // Sana formatini to'g'rilash
        $formattedDate = $selectedDate;
        if ($selectedDate === '📅 Bugun') {
            $formattedDate = 'Bugun';
        } elseif ($selectedDate === '📅 Kecha') {
            $formattedDate = 'Kecha';
        } elseif (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $selectedDate)) {
            // Sana formatini standartlashtirish (dd.mm.yyyy)
            $dateParts = explode('.', $selectedDate);
            $formattedDate = sprintf('%02d.%02d.%s', $dateParts[0], $dateParts[1], $dateParts[2]);
        }

        // Sanani yangilash va keyingi bosqichga o'tish
        $transactionData['selected_date'] = $formattedDate;
        $transactionData['step'] = 'amount';

        Cache::put("transaction_process_{$userChatId}", $transactionData, 600);

        $category = Category::find($transactionData['category_id']);
        $emoji = $transactionData['type'] === 'income' ? '💰' : '💸';
        $typeText = $transactionData['type'] === 'income' ? 'kirim' : 'chiqim';

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "{$emoji} {$category->name} kategoriyasiga {$typeText} qo'shish\n📅 Sana: " . ($formattedDate === 'Bugun' ? 'Bugun' : ($formattedDate === 'Kecha' ? 'Kecha' : $formattedDate)) . "\n\n💰 Summani kiriting (faqat raqam):",
            'reply_markup' => json_encode([
                'remove_keyboard' => true
            ])
        ]);
    }
}
