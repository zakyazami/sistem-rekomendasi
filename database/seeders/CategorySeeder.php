<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'ATK',
            'Beras',
            'Biskuit',
            'Bumbu',
            'Deterjen',
            'Elektronik',
            'Gula',
            'Kopi',
            'Mie Instan',
            'Minuman',
            'Minyak',
            'Pasta Gigi',
            'Pelembut',
            'Pembasmi Serangga',
            'Protein',
            'Rumah Tangga',
            'Sabun',
            'Shampoo',
            'Snack',
            'Susu',
            'Tissue',
        ];

        foreach ($categories as $category) {
            Category::query()->firstOrCreate(['name' => $category]);
        }
    }
}
