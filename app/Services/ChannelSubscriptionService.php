<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class ChannelSubscriptionService
{
    /**
     * Foydalanuvchi kanal obunasini tekshirish
     */
    public function isUserSubscribed($userChatId): bool
    {
        try {
            $channelId = env('TELEGRAM_CHANNEL_ID');
            
            // Agar kanal ID bo'sh bo'lsa, obuna tekshirmasdan o'tkazish
            if (empty($channelId)) {
                return true;
            }
            
            $channelUsername = $this->extractChannelUsername($channelId);
            
            Log::info("Checking subscription for user: {$userChatId}, channel: {$channelUsername}");
            
            $member = Telegram::getChatMember([
                'chat_id' => $channelUsername,
                'user_id' => $userChatId,
            ]);

            Log::info("Member status: " . $member->status);

            $isSubscribed = in_array($member->status, ['member', 'administrator', 'creator']);
            Log::info("Is subscribed: " . ($isSubscribed ? 'true' : 'false'));
            
            return $isSubscribed;
            
        } catch (\Exception $e) {
            Log::error('Channel subscription check error: ' . $e->getMessage());
            Log::error('Channel ID: ' . env('TELEGRAM_CHANNEL_ID'));
            Log::error('User ID: ' . $userChatId);
            
            // Agar "chat not found" xatosi bo'lsa, bu bot kanalga admin qilinmaganligini anglatadi
            if (strpos($e->getMessage(), 'chat not found') !== false) {
                Log::error('Bot is not added as admin to the channel: ' . $channelId);
                // Bu holatda obuna tekshirmasdan o'tkazamiz
                return true;
            }
            
            // PARTICIPANT_ID_INVALID xatosi - test user yoki mavjud bo'lmagan user
            if (strpos($e->getMessage(), 'PARTICIPANT_ID_INVALID') !== false) {
                Log::info('Test user or invalid user ID, allowing access');
                return true;
            }
            
            if (strpos($e->getMessage(), 'member list is inaccessible') !== false) {
                Log::error('Bot is not admin in the channel or channel privacy settings prevent member list access');
                return false;
            }
            
            // Boshqa xatolarda ham obuna tekshirmasdan o'tkazamiz
            return true;
        }
    }

    /**
     * Obuna xabarini yuborish
     */
    public function sendSubscriptionMessage($userChatId): void
    {
        $channelId = env('TELEGRAM_CHANNEL_ID');
        $username = $this->extractChannelUsername($channelId);
        $username = ltrim($username, '@');
        $channelUrl = 'https://t.me/' . $username;
        
        Log::info("Generated Channel URL: " . $channelUrl);
        Log::info("Original Channel ID: " . $channelId);
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📢 Kanalga o\'tish',
                        'url' => $channelUrl
                    ]
                ],
                [
                    [
                        'text' => '✅ Tekshirish',
                        'callback_data' => 'check_subscription'
                    ]
                ]
            ]
        ];

        try {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "🚫 Siz kanalga obuna bo'lmagansiz!\n\n📢 Botdan foydalanish uchun avval kanalimizga obuna bo'ling va keyin \"Tekshirish\" tugmasini bosing.",
                'reply_markup' => json_encode($keyboard)
            ]);
        } catch (\Exception $e) {
            Log::error('Send subscription message error: ' . $e->getMessage());
        }
    }

    /**
     * Manual obuna tekshirish xabarini yuborish
     */
    public function sendManualSubscriptionCheck($userChatId): void
    {
        $channelId = env('TELEGRAM_CHANNEL_ID');
        $username = $this->extractChannelUsername($channelId);
        $username = ltrim($username, '@');
        $channelUrl = 'https://t.me/' . $username;

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '📢 Kanalga o\'tish',
                        'url' => $channelUrl
                    ]
                ],
                [
                    [
                        'text' => '✅ Obuna bo\'ldim, tekshiring',
                        'callback_data' => 'manual_check_subscription'
                    ]
                ]
            ]
        ];

        try {
            Telegram::sendMessage([
                'chat_id' => $userChatId,
                'text' => "⚠️ Obuna tekshirishda muammo yuz berdi.\n\n📢 Iltimos, qo'lda tekshiring:\n1. Kanalga o'ting\n2. Kanalga obuna bo'ling\n3. \"Obuna bo'ldim, tekshiring\" tugmasini bosing",
                'reply_markup' => json_encode($keyboard)
            ]);
        } catch (\Exception $e) {
            Log::error('Send manual subscription check error: ' . $e->getMessage());
        }
    }

    /**
     * Kanal username ini ajratib olish
     */
    private function extractChannelUsername($channelId): string
    {
        if (strpos($channelId, 'https://t.me/') === 0) {
            $username = str_replace('https://t.me/', '', $channelId);
            return '@' . $username;
        }
        
        if (strpos($channelId, '@') === 0) {
            return $channelId;
        }
        
        return '@' . $channelId;
    }
}