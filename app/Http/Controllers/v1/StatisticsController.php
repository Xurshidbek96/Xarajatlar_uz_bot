<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class StatisticsController extends Controller
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

    /**
     * Statistika bo'limini ko'rsatish (default: oylik hisobot)
     */
    public function showStatistics($userChatId, $period = 'month')
    {
        // Statistika kontekstini saqlash
        \Illuminate\Support\Facades\Cache::put("statistics_context_{$userChatId}", true, 300);
        
        $report = $this->generateReport($userChatId, $period);
        
        $message = $this->formatReportMessage($report, $period);
        $keyboard = $this->getFilterKeyboard();
        
        // Keyboard validation qo'shish
        $keyboard = $this->validateTelegramKeyboard($keyboard, 'Statistics Filter');
        
        Log::info('Sending statistics keyboard: ' . json_encode($keyboard));
        
        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Hisobot yaratish
     */
    private function generateReport($userChatId, $period, $year = null)
    {
        // Chat ID orqali user_id ni olish
        $user = User::where('chat_id', $userChatId)->first();
        if (!$user) {
            return [
                'incomes' => collect(),
                'expenses' => collect(),
                'total_income' => 0,
                'total_expense' => 0,
                'balance' => 0,
                'period' => $period,
                'date_range' => $this->getDateRange($period, $year)
            ];
        }
        
        $dateRange = $this->getDateRange($period, $year);
        
        // Kirimlar kategoriyalar bo'yicha
        $incomes = Transaction::where('user_id', $user->id)
            ->where('type', 'income')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderBy('total', 'desc')
            ->with('category')
            ->get();
            
        // Chiqimlar kategoriyalar bo'yicha
        $expenses = Transaction::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderBy('total', 'desc')
            ->with('category')
            ->get();
            
        $totalIncome = $incomes->sum('total');
        $totalExpense = $expenses->sum('total');
        $balance = $totalIncome - $totalExpense;
        
        return [
            'incomes' => $incomes->filter(fn($item) => $item->total > 0),
            'expenses' => $expenses->filter(fn($item) => $item->total > 0),
            'income_categories' => $incomes->map(fn($item) => ['name' => $item->category->name, 'total' => $item->total]),
            'expense_categories' => $expenses->map(fn($item) => ['name' => $item->category->name, 'total' => $item->total]),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $balance,
            'balance' => $balance,
            'period' => $period,
            'date_range' => $dateRange
        ];
    }
    
    /**
     * Sana oralig'ini aniqlash
     */
    private function getDateRange($period, $year = null)
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay()
                ];
            case 'yesterday':
                return [
                    'start' => $now->copy()->subDay()->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay()
                ];
            case 'week':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek()
                ];
            case 'last_week':
                return [
                    'start' => $now->copy()->subWeek()->startOfWeek(),
                    'end' => $now->copy()->subWeek()->endOfWeek()
                ];
            case 'month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
            case 'last_month':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth()
                ];
            case 'year':
                $targetYear = $year ?? $now->year;
                return [
                    'start' => Carbon::create($targetYear, 1, 1)->startOfDay(),
                    'end' => Carbon::create($targetYear, 12, 31)->endOfDay()
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
        }
    }
    
    /**
     * Hisobot xabarini formatlash
     */
    private function formatReportMessage($report, $period)
    {
        $periodText = $this->getPeriodText($period);
        $message = "📊 {$periodText} hisoboti\n\n";
        
        // Kirimlar bo'limi
        if ($report['incomes']->count() > 0) {
            $message .= "💰 KIRIMLAR:\n";
            foreach ($report['incomes'] as $income) {
                $message .= "• {$income->category->name}: " . number_format($income->total, 0, '.', ' ') . " so'm\n";
            }
            $message .= "Jami kirim: " . number_format($report['total_income'], 0, '.', ' ') . " so'm\n\n";
        }
        
        // Chiqimlar bo'limi
        if ($report['expenses']->count() > 0) {
            $message .= "💸 CHIQIMLAR:\n";
            foreach ($report['expenses'] as $expense) {
                $message .= "• {$expense->category->name}: " . number_format($expense->total, 0, '.', ' ') . " so'm\n";
            }
            $message .= "Jami chiqim: " . number_format($report['total_expense'], 0, '.', ' ') . " so'm\n\n";
        }
        
        // Balans
        $balanceEmoji = $report['balance'] >= 0 ? '✅' : '❌';
        $message .= "{$balanceEmoji} BALANS: " . number_format($report['balance'], 0, '.', ' ') . " so'm";
        
        if ($report['incomes']->count() == 0 && $report['expenses']->count() == 0) {
            $message .= "\n\n📝 Bu davrda hech qanday tranzaksiya amalga oshirilmagan.";
        }
        
        return $message;
    }
    
    /**
     * Davr nomini olish
     */
    private function getPeriodText($period)
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                return 'Bugungi (' . $now->format('d.m.Y') . ')';
            case 'yesterday':
                return 'Kechagi (' . $now->subDay()->format('d.m.Y') . ')';
            case 'week':
                $startOfWeek = $now->copy()->startOfWeek();
                $endOfWeek = $now->copy()->endOfWeek();
                return 'Bu haftalik (' . $startOfWeek->format('d.m') . ' - ' . $endOfWeek->format('d.m.Y') . ')';
            case 'last_week':
                $startOfLastWeek = $now->copy()->subWeek()->startOfWeek();
                $endOfLastWeek = $now->copy()->subWeek()->endOfWeek();
                return 'O\'tgan haftalik (' . $startOfLastWeek->format('d.m') . ' - ' . $endOfLastWeek->format('d.m.Y') . ')';
            case 'month':
                return $now->format('F Y') . ' oylik';
            case 'last_month':
                $lastMonth = $now->copy()->subMonth();
                $monthNames = [
                    'January' => 'Yanvar', 'February' => 'Fevral', 'March' => 'Mart', 'April' => 'Aprel',
                    'May' => 'May', 'June' => 'Iyun', 'July' => 'Iyul', 'August' => 'Avgust',
                    'September' => 'Sentyabr', 'October' => 'Oktyabr', 'November' => 'Noyabr', 'December' => 'Dekabr'
                ];
                $monthName = $monthNames[$lastMonth->format('F')];
                return $monthName . ' ' . $lastMonth->format('Y') . ' oylik';
            default:
                $monthNames = [
                    'January' => 'Yanvar', 'February' => 'Fevral', 'March' => 'Mart', 'April' => 'Aprel',
                    'May' => 'May', 'June' => 'Iyun', 'July' => 'Iyul', 'August' => 'Avgust',
                    'September' => 'Sentyabr', 'October' => 'Oktyabr', 'November' => 'Noyabr', 'December' => 'Dekabr'
                ];
                $monthName = $monthNames[$now->format('F')];
                return $monthName . ' ' . $now->format('Y') . ' oylik';
        }
    }
    
    /**
     * Filter klaviaturasini yaratish
     */
    private function getFilterKeyboard()
    {
        return [
            ['📅 Bugun', '📅 Kecha'],
            ['📅 Bu hafta', '📅 O\'tgan hafta'],
            ['📅 Bu oy', '📅 O\'tgan oy'],
            ['📊 Yillik hisobot'],
            ['🔙 Orqaga']
        ];
    }
    
    /**
     * Filter bo'yicha statistika ko'rsatish
     */
    public function showStatisticsByFilter($userChatId, $filter)
    {
        $period = $this->mapFilterToPeriod($filter);
        $this->showStatistics($userChatId, $period);
    }
    
    /**
     * Filterni davrga moslashtirish
     */
    private function mapFilterToPeriod($filter)
    {
        switch ($filter) {
            case '📅 Bugun':
                return 'today';
            case '📅 Kecha':
                return 'yesterday';
            case '📅 Bu hafta':
                return 'week';
            case '📅 O\'tgan hafta':
                return 'last_week';
            case '📅 Bu oy':
                return 'month';
            case '📅 O\'tgan oy':
                return 'last_month';
            case '📊 Yillik hisobot':
                return 'yearly';
            default:
                return 'month';
        }
    }
    
    /**
     * Yil tanlash klaviaturasini yaratish
     */
    public function showYearSelection($userChatId)
    {
        $currentYear = date('Y');
        $years = [];
        
        // Oxirgi 5 yil va kelgusi yil
        for ($i = $currentYear - 4; $i <= $currentYear + 1; $i++) {
            $years[] = "📅 {$i}";
        }
        
        // Klaviaturani 2 ta ustunda tashkil qilish
        $keyboard = [];
        for ($i = 0; $i < count($years); $i += 2) {
            if (isset($years[$i + 1])) {
                $keyboard[] = [$years[$i], $years[$i + 1]];
            } else {
                $keyboard[] = [$years[$i]];
            }
        }
        
        $keyboard[] = ['🔙 Orqaga'];
        
        $message = "📊 Yillik hisobot uchun yilni tanlang:";
        
        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Yillik hisobot ko'rsatish
     */
    public function showYearlyReport($userChatId, $year)
    {
        // Statistika kontekstini saqlash
        \Illuminate\Support\Facades\Cache::put("statistics_context_{$userChatId}", true, 300);
        
        $report = $this->generateReport($userChatId, 'year', $year);
        $message = $this->formatYearlyReportMessage($report, $year);
        
        $keyboard = [
            ['🔙 Orqaga']
        ];
        
        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Yillik hisobot xabarini formatlash
     */
    private function formatYearlyReportMessage($report, $year)
    {
        $message = "📊 {$year} yillik hisobot\n\n";
        
        $message .= "💵 Jami kirimlar: " . number_format($report['total_income'], 0, '.', ' ') . " so'm\n";
        $message .= "💸 Jami chiqimlar: " . number_format($report['total_expense'], 0, '.', ' ') . " so'm\n";
        $message .= "💰 Sof foyda: " . number_format($report['net_profit'], 0, '.', ' ') . " so'm\n\n";
        
        if (!empty($report['income_categories'])) {
            $message .= "💵 Kirim kategoriyalari:\n";
            foreach ($report['income_categories'] as $category) {
                $message .= "• {$category['name']}: " . number_format($category['total'], 0, '.', ' ') . " so'm\n";
            }
            $message .= "\n";
        }
        
        if (!empty($report['expense_categories'])) {
            $message .= "💸 Chiqim kategoriyalari:\n";
            foreach ($report['expense_categories'] as $category) {
                $message .= "• {$category['name']}: " . number_format($category['total'], 0, '.', ' ') . " so'm\n";
            }
        }
        
        return $message;
    }
}
