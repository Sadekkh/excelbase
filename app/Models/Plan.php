<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'price_monthly', 'tagline', 'features', 'order', 'is_public'])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function allowsView(string $type): bool
    {
        $views = $this->features['views'] ?? ['grid', 'form', 'gallery'];
        $type = $type === 'survey' ? 'survey' : $type;

        return in_array($type, $views, true);
    }

    public function allowsField(string $type): bool
    {
        $premium = ['formula', 'ai', 'lookup', 'count'];
        if (! in_array($type, $premium, true)) {
            return true;
        }

        return in_array($type, $this->features['fields'] ?? [], true);
    }

    public function feature(string $key, mixed $default = false): mixed
    {
        return $this->features[$key] ?? $default;
    }

    public static function free(): self
    {
        return self::ensureDefaults()->firstWhere('slug', 'free') ?? self::query()->first();
    }

    public static function ensureDefaults(): \Illuminate\Support\Collection
    {
        $defs = self::catalog();
        foreach ($defs as $i => $def) {
            self::query()->updateOrCreate(['slug' => $def['slug']], $def + ['order' => $i + 1]);
        }

        return self::query()->orderBy('order')->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function catalog(): array
    {
        return [
            [
                'slug' => 'free',
                'name' => 'Free',
                'price_monthly' => 0,
                'tagline' => 'For one team getting started',
                'is_public' => true,
                'features' => [
                    'views' => ['grid', 'form', 'gallery'],
                    'fields' => [],
                    'comments' => false,
                    'personal_views' => false,
                    'exports' => ['csv'],
                    'automations' => 2,
                    'dashboards' => 1,
                    'members' => 3,
                    'roles' => false,
                ],
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'price_monthly' => 12,
                'tagline' => 'Views, AI, automations, and dashboards',
                'is_public' => true,
                'features' => [
                    'views' => ['grid', 'form', 'gallery', 'kanban', 'calendar', 'timeline', 'graph', 'survey'],
                    'fields' => ['formula', 'ai', 'lookup', 'count'],
                    'comments' => true,
                    'personal_views' => true,
                    'exports' => ['csv', 'json', 'xml', 'xls'],
                    'automations' => 50,
                    'dashboards' => 10,
                    'members' => 25,
                    'roles' => true,
                ],
            ],
            [
                'slug' => 'advanced',
                'name' => 'Advanced',
                'price_monthly' => 22,
                'tagline' => 'Roles, more seats, and higher limits',
                'is_public' => true,
                'features' => [
                    'views' => ['grid', 'form', 'gallery', 'kanban', 'calendar', 'timeline', 'graph', 'survey'],
                    'fields' => ['formula', 'ai', 'lookup', 'count'],
                    'comments' => true,
                    'personal_views' => true,
                    'exports' => ['csv', 'json', 'xml', 'xls'],
                    'automations' => 250,
                    'dashboards' => 50,
                    'members' => 100,
                    'roles' => true,
                ],
            ],
        ];
    }
}
