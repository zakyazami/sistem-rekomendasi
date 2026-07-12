<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [

            "Mie Instan",

            "Minuman",

            "Beras",

            "Minyak",

            "Gula",

            "Bumbu",

            "Snack",

            "Kopi",

            "Susu",

            "Sabun",

            "Shampoo",

            "Pasta Gigi",

            "Deterjen",

            "Pelembut",

            "Pembasmi Serangga",

            "Rumah Tangga",

            "Elektronik",

            "ATK"

        ];

        foreach ($categories as $category){

            Category::create([

                'name'=>$category

            ]);

        }
    }
}
