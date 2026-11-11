<?php

namespace Database\Seeders;

use App\Models\CalendarCategory;
use Illuminate\Database\Seeder;

class CalendarCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Christmas / Holiday', 'slug' => 'christmas-holiday', 'icon' => 'gift', 'sort_order' => 1],
            ['name' => 'E-commerce Promotion', 'slug' => 'ecommerce-promotion', 'icon' => 'shopping-bag', 'sort_order' => 2],
            ['name' => 'Product Launch', 'slug' => 'product-launch', 'icon' => 'rocket', 'sort_order' => 3],
            ['name' => 'Community / Nonprofit', 'slug' => 'community-nonprofit', 'icon' => 'heart', 'sort_order' => 4],
            ['name' => 'Personal', 'slug' => 'personal', 'icon' => 'user', 'sort_order' => 5],
        ];

        foreach ($categories as $category) {
            CalendarCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
