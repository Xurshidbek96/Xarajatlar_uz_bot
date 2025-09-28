<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $incomeCategories = [
            'Oylik',
            'Qo‘shimcha daromad',
            'Sovg‘a / Bonus',
            'Boshqa',
        ];

        $expenseCategories = [
            'Ovqat-ichimlik',
            'Transport',
            'Uy / Kommunal to‘lovlar',
            'Sog‘liq',
            'Ta’lim',
            'Kiyim-kechak',
            'O‘yin-kulgi / Dam olish',
            'Aloqa',
            'Kredit / Qarzdorlik to‘lovi',
            'Boshqa',
        ];

        foreach ($incomeCategories as $name) {
            Category::create([
                'name' => $name,
                'type' => 'income',
            ]);
        }

        foreach ($expenseCategories as $name) {
            Category::create([
                'name' => $name,
                'type' => 'expense',
            ]);
        }
    }
}
