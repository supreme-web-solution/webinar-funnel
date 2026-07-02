<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductTableSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['product_id' => 444707, 'name' => 'AffiliMachine Core', 'funnel' => 'FE'],
            ['product_id' => 444709, 'name' => 'AffiliMachine Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 445139, 'name' => 'AffiliMachine Fast-Pass', 'funnel' => 'FE'],
            ['product_id' => 445141, 'name' => 'AffiliMachine Fast-Pass', 'funnel' => 'FE'],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(
                ['product_id' => $product['product_id']],
                ['name' => $product['name'], 'funnel' => $product['funnel']],
            );
        }
    }
}
