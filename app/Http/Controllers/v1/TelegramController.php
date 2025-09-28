<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ChannelSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
    private $channelService;

    public function __construct(ChannelSubscriptionService $channelService)
    {
        $this->channelService = $channelService;
    }

    public function webhook(Request $request)
    {
        try {
            // Webhook dan kelgan ma'lumotlarni olish
            $update = Telegram::getWebhookUpdate();
            $message = $update->getMessage();
            $callbackQuery = $update->getCallbackQuery();

            // Callback query ishlov berish
            if ($callbackQuery) {
                $this->handleCallbackQuery($callbackQuery);
                return response('ok', 200);
            }

            if (!$message) {
                return response('ok', 200);
            }

            $userChatId = $message->getChat()->getId();
            $chatType = $message->getChat()->getType();
            
            // Faqat shaxsiy xabarlarni qayta ishlash (kanal va guruh xabarlarini e'tiborsiz qoldirish)
            if ($chatType !== 'private') {
                Log::info("Ignored non-private message from chat type: {$chatType}, chat_id: {$userChatId}");
                return response('ok', 200);
            }

            $username = $message->getChat()->getUsername();
            $name = trim(($message->getChat()->getFirstName() ?? '') . ' ' . ($message->getChat()->getLastName() ?? ''));

            // User bazaga saqlash
            $this->saveUser($userChatId, $username, $name);

            // Xabarlarni qayta ishlash
            if ($message->getText() === '/start') {
                $this->handleStartCommand($userChatId, $name);
            } else {
                $this->handleOtherMessages($message, $userChatId, $name);
            }

            return response('ok', 200);
        } catch (\Exception $e)
        {
            Log::error('Telegram bot error: ' . $e->getMessage());
            Log::error('Telegram bot error stack trace: ' . $e->getTraceAsString());
            return response('Error', 500);
        }
    }

    private function handleCallbackQuery($callbackQuery)
    {
        $userChatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if ($data === 'check_subscription') {
            $this->handleSubscriptionCheck($userChatId);
        } elseif ($data === 'manual_check_subscription') {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "✅ Rahmat! Siz kanalga obuna bo'ldingiz deb hisoblanadi.\n\n💰 Endi botdan to'liq foydalanishingiz mumkin.",
            ]);
        } else {
            // Finance callback larni boshqarish
            $this->handleFinanceCallback($userChatId, $data);
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);
    }

    private function handleFinanceCallback($userChatId, $data)
    {
        $financeController = new \App\Http\Controllers\v1\FinanceController();

        switch ($data) {
            case 'main_menu':
                $financeController->showMainMenu($userChatId);
                break;
            case 'income_menu':
                $financeController->showIncomeMenu($userChatId);
                break;
            case 'expense_menu':
                $financeController->showExpenseMenu($userChatId);
                break;
            case 'add_income':
                $financeController->showIncomeCategories($userChatId);
                break;
            case 'add_expense':
                $financeController->showExpenseCategories($userChatId);
                break;
            case 'view_income':
            case 'view_income_today':
                $financeController->showIncomeView($userChatId, 'today');
                break;
            case 'view_income_yesterday':
                $financeController->showIncomeView($userChatId, 'yesterday');
                break;
            case 'view_income_this_week':
                $financeController->showIncomeView($userChatId, 'this_week');
                break;
            case 'view_income_last_week':
                $financeController->showIncomeView($userChatId, 'last_week');
                break;
            case 'view_income_this_month':
                $financeController->showIncomeView($userChatId, 'this_month');
                break;
            case 'view_expense':
            case 'view_expense_today':
                $financeController->showExpenseView($userChatId, 'today');
                break;
            case 'view_expense_yesterday':
                $financeController->showExpenseView($userChatId, 'yesterday');
                break;
            case 'view_expense_this_week':
                $financeController->showExpenseView($userChatId, 'this_week');
                break;
            case 'view_expense_last_week':
                $financeController->showExpenseView($userChatId, 'last_week');
                break;
            case 'view_expense_this_month':
                $financeController->showExpenseView($userChatId, 'this_month');
                break;
            default:
                // Kategoriya tanlash callback lari
                if (strpos($data, 'income_cat_') === 0) {
                    $categoryId = str_replace('income_cat_', '', $data);
                    $financeController->startAddTransaction($userChatId, $categoryId, 'income');
                } elseif (strpos($data, 'expense_cat_') === 0) {
                    $categoryId = str_replace('expense_cat_', '', $data);
                    $financeController->startAddTransaction($userChatId, $categoryId, 'expense');
                }
                break;
        }
    }

    private function saveUser($userChatId, $username, $name)
    {
        User::updateOrCreate(
            ['chat_id' => $userChatId],
            [
                'name' => $name,
                'username' => $username,
                'chat_id' => $userChatId,
            ]
        );
    }

    private function handleStartCommand($userChatId, $name)
    {
        // Agar kanal ID bo'sh bo'lsa, obuna tekshirmasdan o'tkazish
        $channelId = env('TELEGRAM_CHANNEL_ID');
        if (empty($channelId)) {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "🎉 Assalomu alaykum, {$name}!\n\n💰 Moliyaviy nazorat botiga xush kelibsiz!\n\n📊 Bu bot orqali siz:\n• 💵 Kirimlaringizni\n• 💸 Chiqimlaringizni\n• 📈 Statistikangizni\n\nkuzatib borishingiz mumkin.\n\n🚀 Boshlash uchun /menu buyrug'ini yuboring!",
            ]);
            return;
        }

        if ($this->channelService->isUserSubscribed($userChatId)) {
            $welcomeText = $name ?
                "🎉 Assalomu alaykum, {$name}!\n\n💰 Moliyaviy nazorat botiga xush kelibsiz!\n\n📊 Bu bot orqali siz:\n• 💵 Kirimlaringizni\n• 💸 Chiqimlaringizni\n• 📈 Statistikangizni\n\nkuzatib borishingiz mumkin.\n\n🚀 Boshlash uchun /menu buyrug'ini yuboring!" :
                "✅ Siz kanalga obuna bo'lgansiz!\n\n💰 Botdan foydalanishingiz mumkin.";

            try {
                Telegram::sendMessage([
                    'chat_id' => $userChatId,
                    'text' => $welcomeText,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send welcome message: ' . $e->getMessage());
            }
        } else {
            $this->channelService->sendSubscriptionMessage($userChatId);
        }
    }

    private function handleSubscriptionCheck($userChatId)
    {
        if ($this->channelService->isUserSubscribed($userChatId)) {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "✅ Ajoyib! Siz kanalga muvaffaqiyatli obuna bo'ldingiz!\n\n💰 Endi botdan to'liq foydalanishingiz mumkin.",
            ]);
        } else {
            $this->channelService->sendSubscriptionMessage($userChatId);
        }
    }

    private function handleOtherMessages($message, $userChatId, $name)
    {
        // Agar kanal ID bo'sh bo'lsa, obuna tekshirmasdan o'tkazish
        $channelId = env('TELEGRAM_CHANNEL_ID');
        if (empty($channelId)) {
            // Kanal tekshiruvisiz ishlash
            $this->processUserMessage($message, $userChatId, $name);
            return;
        }

        // Obuna tekshirish
        if (!$this->channelService->isUserSubscribed($userChatId)) {
            $this->channelService->sendSubscriptionMessage($userChatId);
            return;
        }

        $this->processUserMessage($message, $userChatId, $name);
    }

    private function processUserMessage($message, $userChatId, $name)
    {
        // Boshqa buyruqlarni qayta ishlash
        $text = $message->getText();

        if ($text === '/help') {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "🤖 Bot buyruqlari:\n\n/start - Botni ishga tushirish\n/menu - Asosiy menyu\n/help - Yordam\n/skip - Izohni o'tkazib yuborish\n\n💰 Moliyaviy funksiyalar mavjud!",
            ]);
        } elseif ($text === '/menu') {
            // FinanceController dan foydalanish
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $financeController->showMainMenu($userChatId);
        } elseif ($text === '/skip') {
            // Izohni o'tkazib yuborish
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $financeController->processDescriptionInput($userChatId, null);
        } 
        // Regular keyboard tugmalarini qayta ishlash
        elseif ($text === '💵 Kirim' || $text === 'Kirim') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $financeController->showIncomeMenu($userChatId);
            // Kontekstni saqlash
            \Illuminate\Support\Facades\Cache::put("user_context_{$userChatId}", 'income', 300);
        } elseif ($text === '💸 Chiqim' || $text === 'Chiqim') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $financeController->showExpenseMenu($userChatId);
            // Kontekstni saqlash
            \Illuminate\Support\Facades\Cache::put("user_context_{$userChatId}", 'expense', 300);
        } elseif ($text === '📊 Statistika' || $text === 'Statistika') {
            // Statistika funksiyasi
            $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
            $statisticsController->showStatistics($userChatId);
        } elseif ($text === '📋 Barcha amaliyotlar' || $text === 'Barcha amaliyotlar') {
            // Barcha amaliyotlar funksiyasi
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "📋 Barcha amaliyotlar funksiyasi hozircha ishlab chiqilmoqda...",
            ]);
        } elseif ($text === '➕ Qo\'shish') {
            // Kontekstga qarab kirim yoki chiqim qo'shish
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            if ($context === 'expense') {
                $financeController->showExpenseCategories($userChatId);
            } else {
                $financeController->showIncomeCategories($userChatId);
            }
        } elseif ($text === '👁 Ko\'rish') {
            // Kontekstga qarab kirim yoki chiqim ko'rish
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            if ($context === 'expense') {
                $financeController->showExpenseView($userChatId, 'today');
            } else {
                $financeController->showIncomeView($userChatId, 'today');
            }
        } elseif ($text === '🔙 Orqaga') {
            // Kontekstlarni tozalash
            \Illuminate\Support\Facades\Cache::forget("statistics_context_{$userChatId}");
            \Illuminate\Support\Facades\Cache::forget("user_context_{$userChatId}");
            
            // Asosiy menyuga qaytish
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $financeController->showMainMenu($userChatId);
        } elseif ($text === '📅 Bugun') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, 'Bugun');
            } else {
                // Statistika kontekstini tekshirish
                $statisticsContext = \Illuminate\Support\Facades\Cache::get("statistics_context_{$userChatId}");
                if ($statisticsContext) {
                    // Statistika filtri
                    $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
                    $statisticsController->showStatisticsByFilter($userChatId, '📅 Bugun');
                } else {
                    // Oddiy ko'rish rejimi
                    $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                    if ($context === 'expense') {
                        $financeController->showExpenseView($userChatId, 'today');
                    } else {
                        $financeController->showIncomeView($userChatId, 'today');
                    }
                }
            }
        } elseif ($text === '📅 Kecha') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, 'Kecha');
            } else {
                // Statistika kontekstini tekshirish
                $statisticsContext = \Illuminate\Support\Facades\Cache::get("statistics_context_{$userChatId}");
                if ($statisticsContext) {
                    // Statistika filtri
                    $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
                    $statisticsController->showStatisticsByFilter($userChatId, '📅 Kecha');
                } else {
                    // Oddiy ko'rish rejimi
                    $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                    if ($context === 'expense') {
                        $financeController->showExpenseView($userChatId, 'yesterday');
                    } else {
                        $financeController->showIncomeView($userChatId, 'yesterday');
                    }
                }
            }
        } elseif ($text === '📅 Bu hafta') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Statistika kontekstini tekshirish
            $statisticsContext = \Illuminate\Support\Facades\Cache::get("statistics_context_{$userChatId}");
            if ($statisticsContext) {
                // Statistika filtri
                $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
                $statisticsController->showStatisticsByFilter($userChatId, '📅 Bu hafta');
            } else {
                // Oddiy ko'rish rejimi
                $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                if ($context === 'expense') {
                    $financeController->showExpenseView($userChatId, 'this_week');
                } else {
                    $financeController->showIncomeView($userChatId, 'this_week');
                }
            }
        } elseif ($text === '📅 O\'tgan hafta') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            if ($context === 'expense') {
                $financeController->showExpenseView($userChatId, 'last_week');
            } else {
                $financeController->showIncomeView($userChatId, 'last_week');
            }
        } elseif ($text === '📅 Bu oy') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Statistika kontekstini tekshirish
            $statisticsContext = \Illuminate\Support\Facades\Cache::get("statistics_context_{$userChatId}");
            if ($statisticsContext) {
                // Statistika filtri
                $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
                $statisticsController->showStatisticsByFilter($userChatId, '📅 Bu oy');
            } else {
                // Oddiy ko'rish rejimi
                $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                if ($context === 'expense') {
                    $financeController->showExpenseView($userChatId, 'this_month');
                } else {
                    $financeController->showIncomeView($userChatId, 'this_month');
                }
            }
        } elseif ($text === '📅 Oy tanlash') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            $financeController->showMonthSelection($userChatId, $context);
        } elseif ($text === '📅 Yil tanlash') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            $financeController->showYearSelection($userChatId, $context);
        } elseif ($text === '📅 Kun tanlash') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            $financeController->showDaySelection($userChatId, $context);
        } elseif ($text === '📅 Aniq sana tanlash') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            $financeController->showCustomDateSelection($userChatId, $context);
        } elseif (preg_match('/^(\w+) \((\d{4})\)$/', $text, $matches)) {
            // Oy tanlandi (masalan: "Yanvar (2024)")
            $monthName = $matches[1];
            $year = $matches[2];
            
            // Oy nomini raqamga aylantirish
            $monthNames = [
                'Yanvar' => '01', 'Fevral' => '02', 'Mart' => '03', 'Aprel' => '04',
                'May' => '05', 'Iyun' => '06', 'Iyul' => '07', 'Avgust' => '08',
                'Sentyabr' => '09', 'Oktyabr' => '10', 'Noyabr' => '11', 'Dekabr' => '12'
            ];
            
            $monthNum = $monthNames[$monthName] ?? '01';
            
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            if ($context === 'expense') {
                $financeController->showExpenseView($userChatId, 'month_year', $monthNum . '.' . $year);
            } else {
                $financeController->showIncomeView($userChatId, 'month_year', $monthNum . '.' . $year);
            }
        } elseif (preg_match('/^\d{4}$/', $text)) {
            // Yil tanlandi (masalan: "2024")
            $year = $text;
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
            if ($context === 'expense') {
                $financeController->showExpenseView($userChatId, 'year', $year);
            } else {
                $financeController->showIncomeView($userChatId, 'year', $year);
            }
        } elseif (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $text)) {
            // Aniq sana tanlandi (masalan: "15.01.2024")
            $date = $text;
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, $date);
            } else {
                // Oddiy ko'rish rejimi
                $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                if ($context === 'expense') {
                    $financeController->showExpenseView($userChatId, 'date', $date);
                } else {
                    $financeController->showIncomeView($userChatId, 'date', $date);
                }
            }
        } elseif ($text === '📅 Bugun') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, 'Bugun');
            } else {
                // Statistika kontekstini tekshirish
                $statisticsContext = \Illuminate\Support\Facades\Cache::get("statistics_context_{$userChatId}");
                if ($statisticsContext) {
                    // Statistika filtri
                    $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
                    $statisticsController->showStatisticsByFilter($userChatId, '📅 Bugun');
                } else {
                    // Oddiy ko'rish rejimi
                    $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                    if ($context === 'expense') {
                        $financeController->showExpenseView($userChatId, 'today');
                    } else {
                        $financeController->showIncomeView($userChatId, 'today');
                    }
                }
            }
        } elseif ($text === '📅 Kecha') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, 'Kecha');
            } else {
                // Statistika kontekstini tekshirish
                $statisticsContext = \Illuminate\Support\Facades\Cache::get("statistics_context_{$userChatId}");
                if ($statisticsContext) {
                    // Statistika filtri
                    $statisticsController = new \App\Http\Controllers\v1\StatisticsController();
                    $statisticsController->showStatisticsByFilter($userChatId, '📅 Kecha');
                } else {
                    // Oddiy ko'rish rejimi
                    $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                    if ($context === 'expense') {
                        $financeController->showExpenseView($userChatId, 'yesterday');
                    } else {
                        $financeController->showIncomeView($userChatId, 'yesterday');
                    }
                }
            }
        } elseif ($text === '📅 3 kun oldin') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, date('d.m.Y', strtotime('-3 days')));
            } else {
                // Oddiy ko'rish rejimi
                $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                if ($context === 'expense') {
                    $financeController->showExpenseView($userChatId, 'date', date('d.m.Y', strtotime('-3 days')));
                } else {
                    $financeController->showIncomeView($userChatId, 'date', date('d.m.Y', strtotime('-3 days')));
                }
            }
        } elseif ($text === '📅 1 hafta oldin') {
            $financeController = new \App\Http\Controllers\v1\FinanceController();
            
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");
            if ($transactionData && $transactionData['step'] === 'date') {
                // Tranzaksiya jarayonida - sana tanlash
                $financeController->processDateInput($userChatId, date('d.m.Y', strtotime('-1 week')));
            } else {
                // Oddiy ko'rish rejimi
                $context = \Illuminate\Support\Facades\Cache::get("user_context_{$userChatId}", 'income');
                if ($context === 'expense') {
                    $financeController->showExpenseView($userChatId, 'date', date('d.m.Y', strtotime('-1 week')));
                } else {
                    $financeController->showIncomeView($userChatId, 'date', date('d.m.Y', strtotime('-1 week')));
                }
            }
        } else {
            // Tranzaksiya jarayonini tekshirish
            $transactionData = \Illuminate\Support\Facades\Cache::get("transaction_process_{$userChatId}");

            if ($transactionData) {
                $financeController = new \App\Http\Controllers\v1\FinanceController();

                if ($transactionData['step'] === 'date') {
                    // Sana formatini tekshirish
                    $validDateFormats = ['Bugun', 'Kecha', '📅 Bugun', '📅 Kecha'];
                    $isValidDate = in_array($text, $validDateFormats) || 
                                   preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $text) ||
                                   preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $text);
                    
                    if ($isValidDate) {
                        // Sana tanlandi
                        $financeController->processDateInput($userChatId, $text);
                    } else {
                        // Noto'g'ri format
                        Telegram::sendMessage([
                            'chat_id' => $userChatId,
                            'text' => "❌ Noto'g'ri sana formati. Iltimos, to'g'ri sana tanlang yoki kiriting (masalan: 15.01.2024).",
                        ]);
                    }
                } elseif ($transactionData['step'] === 'amount' && is_numeric($text)) {
                    // Summa kiritilgan
                    $financeController->processAmountInput($userChatId, $text);
                } elseif ($transactionData['step'] === 'description') {
                    // Izoh kiritilgan
                    $financeController->processDescriptionInput($userChatId, $text);
                } elseif ($transactionData['step'] === 'amount' && !is_numeric($text)) {
                    // Amount bosqichida raqam emas
                    Telegram::sendMessage([
                        'chat_id' => $userChatId,
                        'text' => "❌ Noto'g'ri format. Iltimos, raqam kiriting yoki /skip buyrug'ini yuboring.",
                    ]);
                } else {
                    // Noma'lum holat
                    Telegram::sendMessage([
                        'chat_id' => $userChatId,
                        'text' => "❌ Xatolik yuz berdi. Iltimos, qaytadan boshlang.",
                    ]);
                }
            } else {
                // Kategoriya tanlash jarayonini tekshirish
                $financeController = new \App\Http\Controllers\v1\FinanceController();
                
                // Kirim kategoriyalarini tekshirish
                $incomeCategory = \App\Models\Category::where('type', 'income')->where('name', $text)->first();
                if ($incomeCategory) {
                    $financeController->startAddTransaction($userChatId, $incomeCategory->id, 'income');
                    return;
                }
                
                // Chiqim kategoriyalarini tekshirish
                $expenseCategory = \App\Models\Category::where('type', 'expense')->where('name', $text)->first();
                if ($expenseCategory) {
                    $financeController->startAddTransaction($userChatId, $expenseCategory->id, 'expense');
                    return;
                }
                
                // Agar kategoriya topilmasa
                if (is_numeric($text)) {
                    Telegram::sendMessage([
                        'chat_id' => $userChatId,
                        'text' => "💡 Tranzaksiya qo'shish uchun avval /menu orqali kategoriya tanlang.",
                    ]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $userChatId,
                        'text' => "❓ Noma'lum buyruq. /help buyrug'ini yuboring yoki /menu orqali asosiy menyuga o'ting.",
                    ]);
                }
            }
        }
    }

}
