<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class TelegramKeyboardHelper
{
    /**
     * Universal keyboard validation function to prevent Telegram parsing errors
     */
    public static function validateTelegramKeyboard($keyboard, $context = "Unknown")
    {
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
            Log::warning("Telegram keyboard warning: Fixed keyboard errors in context: $context");
        }

        return $fixedKeyboard;
    }
}