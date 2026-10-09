<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductTableSeeder extends Seeder
{
    /**
     * JVZoo product IDs (cproditem) => role granted on purchase. Any product not listed here is removed.
     */
    public function run(): void
    {
        $products = [
            ['product_id' => 455425, 'name' => 'FE', 'funnel' => 'FE'],
            ['product_id' => 455427, 'name' => 'Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 456171, 'name' => 'Fast-Pass Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 456173, 'name' => 'Fast-Pass Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 456225, 'name' => 'Reseller', 'funnel' => 'Reseller'],
            ['product_id' => 456227, 'name' => 'Reseller', 'funnel' => 'Reseller'],
            ['product_id' => 456183, 'name' => 'Affiliate Campaign Vault', 'funnel' => 'Affiliate Campaign Vault'],
            ['product_id' => 456187, 'name' => 'Affiliate Campaign Vault', 'funnel' => 'Affiliate Campaign Vault'],
            ['product_id' => 456221, 'name' => 'Profit Multiplier', 'funnel' => 'Profit Multiplier'],
            ['product_id' => 456223, 'name' => 'Profit Multiplier', 'funnel' => 'Profit Multiplier'],
        ];

        Product::query()
            ->whereNotIn('product_id', array_column($products, 'product_id'))
            ->delete();

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['product_id' => $product['product_id']],
                ['name' => $product['name'], 'funnel' => $product['funnel']],
            );
        }
    }
}
