<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Plaques',
                'slug' => 'plaques',
                'sort_order' => 1,
                'subcategories' => [
                    [
                        'name' => 'Plaque Feu',
                        'slug' => 'plaque-feu',
                        'products' => [
                            ['sku' => 'pf-12.5', 'thickness' => '12.5 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 15.50, 'unit' => 'm²', 'coverage' => 3.33],
                            ['sku' => 'pf-15', 'thickness' => '15 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 18.75, 'unit' => 'm²', 'coverage' => 3.33],
                            ['sku' => 'pf-18', 'thickness' => '18 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 22.00, 'unit' => 'm²', 'coverage' => 3.33],
                        ]
                    ],
                    [
                        'name' => 'Plaque Hydro',
                        'slug' => 'plaque-hydro',
                        'products' => [
                            ['sku' => 'ph-12.5', 'thickness' => '12.5 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 14.00, 'unit' => 'm²', 'coverage' => 3.33],
                            ['sku' => 'ph-15', 'thickness' => '15 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 17.25, 'unit' => 'm²', 'coverage' => 3.33],
                            ['sku' => 'ph-18', 'thickness' => '18 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 20.50, 'unit' => 'm²', 'coverage' => 3.33],
                        ]
                    ],
                    [
                        'name' => 'Plaque Standard',
                        'slug' => 'plaque-standard',
                        'products' => [
                            ['sku' => 'ps-10', 'thickness' => '10 mm', 'dimensions' => '(2000–2600) × 1200 mm', 'price' => 8.50, 'unit' => 'm²', 'coverage' => 3.12],
                            ['sku' => 'ps-12.5', 'thickness' => '12.5 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 10.00, 'unit' => 'm²', 'coverage' => 3.33],
                            ['sku' => 'ps-15', 'thickness' => '15 mm', 'dimensions' => '(2000–3000) × 1200 mm', 'price' => 12.50, 'unit' => 'm²', 'coverage' => 3.33],
                        ]
                    ],
                ]
            ],
            [
                'name' => 'Ossature',
                'slug' => 'ossature',
                'sort_order' => 2,
                'subcategories' => [
                    [
                        'name' => 'Montant',
                        'slug' => 'montant',
                        'products' => [
                            ['sku' => 'om-48', 'thickness' => '48 mm', 'dimensions' => '3000 mm', 'price' => 3.20, 'unit' => 'unité', 'coverage' => null],
                            ['sku' => 'om-70', 'thickness' => '70 mm', 'dimensions' => '3000 mm', 'price' => 4.10, 'unit' => 'unité', 'coverage' => null],
                            ['sku' => 'om-90', 'thickness' => '90 mm', 'dimensions' => '3000 mm', 'price' => 5.00, 'unit' => 'unité', 'coverage' => null],
                        ]
                    ],
                    [
                        'name' => 'Rail',
                        'slug' => 'rail',
                        'products' => [
                            ['sku' => 'or-48', 'thickness' => '48 mm', 'dimensions' => '3000 mm', 'price' => 2.80, 'unit' => 'unité', 'coverage' => null],
                            ['sku' => 'or-70', 'thickness' => '70 mm', 'dimensions' => '3000 mm', 'price' => 3.50, 'unit' => 'unité', 'coverage' => null],
                            ['sku' => 'or-90', 'thickness' => '90 mm', 'dimensions' => '3000 mm', 'price' => 4.20, 'unit' => 'unité', 'coverage' => null],
                        ]
                    ],
                    [
                        'name' => 'Fourrure',
                        'slug' => 'fourrure',
                        'products' => [
                            ['sku' => 'of-standard', 'thickness' => '18 mm', 'dimensions' => '3000 mm', 'price' => 2.10, 'unit' => 'unité', 'coverage' => null],
                        ]
                    ],
                ]
            ],
            [
                'name' => 'Isolation',
                'slug' => 'isolation',
                'sort_order' => 3,
                'subcategories' => [
                    [
                        'name' => 'Laine de Verre',
                        'slug' => 'laine-de-verre',
                        'products' => [
                            ['sku' => 'ilv-45', 'thickness' => '45 mm', 'dimensions' => 'Rouleau 12 m²', 'price' => 35.00, 'unit' => 'rouleau', 'coverage' => 12],
                            ['sku' => 'ilv-75', 'thickness' => '75 mm', 'dimensions' => 'Rouleau 10 m²', 'price' => 45.00, 'unit' => 'rouleau', 'coverage' => 10],
                            ['sku' => 'ilv-100', 'thickness' => '100 mm', 'dimensions' => 'Rouleau 8 m²', 'price' => 55.00, 'unit' => 'rouleau', 'coverage' => 8],
                        ]
                    ],
                    [
                        'name' => 'Laine de Roche',
                        'slug' => 'laine-de-roche',
                        'products' => [
                            ['sku' => 'ilr-45', 'thickness' => '45 mm', 'dimensions' => 'Panneau 6 m²', 'price' => 42.00, 'unit' => 'panneau', 'coverage' => 6],
                            ['sku' => 'ilr-75', 'thickness' => '75 mm', 'dimensions' => 'Panneau 5 m²', 'price' => 58.00, 'unit' => 'panneau', 'coverage' => 5],
                            ['sku' => 'ilr-100', 'thickness' => '100 mm', 'dimensions' => 'Panneau 4 m²', 'price' => 72.00, 'unit' => 'panneau', 'coverage' => 4],
                        ]
                    ],
                    [
                        'name' => 'Polystyrène',
                        'slug' => 'polystyrene',
                        'products' => [
                            ['sku' => 'ip-30', 'thickness' => '30 mm', 'dimensions' => 'Panneau 1.2 × 0.6 m', 'price' => 8.50, 'unit' => 'panneau', 'coverage' => 0.72],
                            ['sku' => 'ip-50', 'thickness' => '50 mm', 'dimensions' => 'Panneau 1.2 × 0.6 m', 'price' => 12.00, 'unit' => 'panneau', 'coverage' => 0.72],
                            ['sku' => 'ip-80', 'thickness' => '80 mm', 'dimensions' => 'Panneau 1.2 × 0.6 m', 'price' => 16.50, 'unit' => 'panneau', 'coverage' => 0.72],
                        ]
                    ],
                ]
            ],
            [
                'name' => 'Finition',
                'slug' => 'finition',
                'sort_order' => 4,
                'subcategories' => [
                    [
                        'name' => 'Enduit',
                        'slug' => 'enduit',
                        'products' => [
                            ['sku' => 'fe-joint', 'thickness' => 'N/A', 'dimensions' => 'Sac 25 kg', 'price' => 12.00, 'unit' => 'sac', 'coverage' => null],
                            ['sku' => 'fe-finition', 'thickness' => 'N/A', 'dimensions' => 'Sac 25 kg', 'price' => 15.00, 'unit' => 'sac', 'coverage' => null],
                        ]
                    ],
                    [
                        'name' => 'Bande à Joint',
                        'slug' => 'bande-a-joint',
                        'products' => [
                            ['sku' => 'fb-papier', 'thickness' => 'Papier', 'dimensions' => 'Rouleau 150 m', 'price' => 8.00, 'unit' => 'rouleau', 'coverage' => null],
                            ['sku' => 'fb-fibre', 'thickness' => 'Fibre de verre', 'dimensions' => 'Rouleau 90 m', 'price' => 14.00, 'unit' => 'rouleau', 'coverage' => null],
                        ]
                    ],
                    [
                        'name' => 'Visserie',
                        'slug' => 'visserie',
                        'products' => [
                            ['sku' => 'fv-25', 'thickness' => '25 mm', 'dimensions' => 'Boîte 1000 pcs', 'price' => 18.00, 'unit' => 'boîte', 'coverage' => null],
                            ['sku' => 'fv-35', 'thickness' => '35 mm', 'dimensions' => 'Boîte 1000 pcs', 'price' => 20.00, 'unit' => 'boîte', 'coverage' => null],
                            ['sku' => 'fv-45', 'thickness' => '45 mm', 'dimensions' => 'Boîte 500 pcs', 'price' => 16.00, 'unit' => 'boîte', 'coverage' => null],
                        ]
                    ],
                ]
            ],
        ];

        $now = now();

        foreach ($categories as $categoryData) {
            $categoryId = DB::table('product_categories')->insertGetId([
                'name' => $categoryData['name'],
                'slug' => $categoryData['slug'],
                'sort_order' => $categoryData['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($categoryData['subcategories'] as $subIndex => $subcategoryData) {
                $subcategoryId = DB::table('product_subcategories')->insertGetId([
                    'category_id' => $categoryId,
                    'name' => $subcategoryData['name'],
                    'slug' => $subcategoryData['slug'],
                    'sort_order' => $subIndex + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($subcategoryData['products'] as $prodIndex => $product) {
                    DB::table('products')->insert([
                        'subcategory_id' => $subcategoryId,
                        'name' => $subcategoryData['name'] . ' ' . $product['thickness'],
                        'sku' => $product['sku'],
                        'thickness' => $product['thickness'],
                        'dimensions' => $product['dimensions'],
                        'price' => $product['price'],
                        'unit' => $product['unit'],
                        'coverage_per_piece' => $product['coverage'],
                        'stock_quantity' => rand(50, 500),
                        'min_stock_alert' => 10,
                        'sort_order' => $prodIndex + 1,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
