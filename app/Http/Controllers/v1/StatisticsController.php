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
    private function generateReport($userChatId, $period)
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
                'date_range' => $this->getDateRange($period)
            ];
        }
        
        $dateRange = $this->getDateRange($period);
        
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
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $balance,
            'period' => $period,
            'date_range' => $dateRange
        ];
    }
    
    /**
     * Sana oralig'ini aniqlash
     */
    private function getDateRange($period)
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
            case 'month':
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
        switch ($period) {
            case 'today':
                return 'Bugungi';
            case 'yesterday':
                return 'Kechagi';
            case 'week':
                return 'Bu haftalik';
            case 'month':
            default:
                return 'Bu oylik';
        }
    }
    
    /**
     * Filter klaviaturasini yaratish
     */
    private function getFilterKeyboard()
    {
        return [
            ['📅 Bugun', '📅 Kecha'],
            ['📅 Bu hafta', '📅 Bu oy'],
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
            case '📅 Bu oy':
            default:
                return 'month';
        }
    }
}
