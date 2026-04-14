<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'EB Bill' => ['Light Bill', 'Maintenance'],
            'Rent' => ['Office Rent', 'Warehouse Rent'],
            'Salary' => ['Main Staff', 'Contract Staff'],
            'Travel' => ['Fuel', 'Toll', 'Repair'],
            'Office Supplies' => ['Stationery', 'Pantry'],
        ];

        foreach ($categories as $parentName => $subCategories) {
            $parent = \App\Models\ExpenseCategory::create(['name' => $parentName]);
            foreach ($subCategories as $subName) {
                \App\Models\ExpenseCategory::create([
                    'name' => $subName,
                    'parent_id' => $parent->id
                ]);
            }
        }
    }
}
