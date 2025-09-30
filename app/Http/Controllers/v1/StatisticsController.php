<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Helpers\TelegramKeyboardHelper;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class StatisticsController extends Controller
{
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
        $keyboard = TelegramKeyboardHelper::validateTelegramKeyboard($keyboard, 'Statistics Filter');
        
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
    private function generateReport($userChatId, $period, $year = null, $value = null)
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
                'date_range' => $this->getDateRange($period, $year, $value)
            ];
        }
        
        $dateRange = $this->getDateRange($period, $year, $value);
        
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
    private function getDateRange($period, $year = null, $value = null)
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
            case 'month_year':
                if ($value) {
                    $parts = explode('.', $value);
                    if (count($parts) === 2) {
                        $month = $parts[0];
                        $year = $parts[1];
                        return [
                            'start' => Carbon::create($year, $month, 1)->startOfDay(),
                            'end' => Carbon::create($year, $month, 1)->endOfMonth()->endOfDay()
                        ];
                    }
                }
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
            case 'date':
                if ($value) {
                    try {
                        $date = Carbon::createFromFormat('d.m.Y', $value);
                        return [
                            'start' => $date->copy()->startOfDay(),
                            'end' => $date->copy()->endOfDay()
                        ];
                    } catch (\Exception $e) {
                        // Fallback to today
                    }
                }
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay()
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
     * Oy tanlash klaviaturasini ko'rsatish
     */
    public function showMonthSelection($userChatId)
    {
        // Statistika kontekstini saqlash
        \Illuminate\Support\Facades\Cache::put("statistics_context_{$userChatId}", true, 300);
        
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
        
        // O'tgan oydan boshlab orqaga qarab 12 oy ko'rsatish
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
            
            $keyboard[] = ["📊 {$monthName} ({$year})"];
        }
        
        $keyboard[] = ['🔙 Orqaga'];

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "📊 Statistika uchun oy tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Sana tanlash klaviaturasini ko'rsatish (30 kunlik)
     */
    public function showDateSelection($userChatId)
    {
        // Statistika kontekstini saqlash
        \Illuminate\Support\Facades\Cache::put("statistics_context_{$userChatId}", true, 300);
        
        $dates = [];
        
        // 2 kun avvaldan boshlab 30 kun ko'rsatish
        for ($i = 2; $i <= 31; $i++) {
            $date = date('d.m.Y', strtotime("-{$i} days"));
            $dates[] = $date;
        }

        $keyboard = [];
        $row = [];
        foreach ($dates as $index => $date) {
            $row[] = "📊 {$date}";
            if (count($row) == 2 || $index == count($dates) - 1) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        
        $keyboard[] = ['🔙 Orqaga'];

        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => "📊 Statistika uchun sana tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Tanlangan oy bo'yicha statistika ko'rsatish
     */
    public function showMonthlyStatistics($userChatId, $monthYear)
    {
        $parts = explode('.', $monthYear);
        if (count($parts) !== 2) return;
        
        $month = $parts[0];
        $year = $parts[1];
        
        $report = $this->generateReport($userChatId, 'month_year', null, $month . '.' . $year);
        $message = $this->formatMonthlyReportMessage($report, $month, $year);
        
        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'keyboard' => $this->getFilterKeyboard(),
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Tanlangan sana bo'yicha statistika ko'rsatish
     */
    public function showDateStatistics($userChatId, $date)
    {
        $report = $this->generateReport($userChatId, 'date', null, $date);
        $message = $this->formatDateReportMessage($report, $date);
        
        Telegram::sendMessage([
            'chat_id' => $userChatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'keyboard' => $this->getFilterKeyboard(),
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
    
    /**
     * Oylik hisobot xabarini formatlash
     */
    private function formatMonthlyReportMessage($report, $month, $year)
    {
        $months = [
            '01' => 'Yanvar', '02' => 'Fevral', '03' => 'Mart', '04' => 'Aprel',
            '05' => 'May', '06' => 'Iyun', '07' => 'Iyul', '08' => 'Avgust',
            '09' => 'Sentyabr', '10' => 'Oktyabr', '11' => 'Noyabr', '12' => 'Dekabr'
        ];
        
        $monthName = $months[$month] ?? 'Noma\'lum';
        
        $message = "📊 {$monthName} {$year} hisoboti\n\n";
        
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
     * Kunlik hisobot xabarini formatlash
     */
    private function formatDateReportMessage($report, $date)
    {
        $message = "📊 {$date} hisoboti\n\n";
        
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
            ['📅 Oy tanlash', '📅 Sana tanlash'],
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
        $message = "📊 {$year} yillik hisoboti\n\n";
        
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
}
