<?php

namespace App\Support\Erp;

use App\Support\Erp\Templates\AutoEntrepreneur;
use App\Support\Erp\Templates\Batiment;
use App\Support\Erp\Templates\Boulangerie;
use App\Support\Erp\Templates\CoffeeShop;

class TemplateCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            Boulangerie::pack(),
            Batiment::pack(),
            CoffeeShop::pack(),
            AutoEntrepreneur::pack(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $slug): array
    {
        foreach (self::all() as $pack) {
            if ($pack['slug'] === $slug) {
                return $pack;
            }
        }

        throw new \InvalidArgumentException('Unknown template: '.$slug);
    }
}
