<?php

namespace App\Support\Erp;

use App\Models\Database;
use App\Models\Field;
use App\Models\Table;
use App\Models\Workspace;
use App\Support\FieldTypes;

class BuildAssistant
{
    /**
     * @return array{kind: string, slug?: string, name?: string, database?: string, tables?: list<array{name: string, fields: list<string>}> , message: string}
     */
    public static function plan(string $prompt): array
    {
        $text = mb_strtolower(trim($prompt));
        foreach (TemplateCatalog::all() as $pack) {
            $needles = [$pack['slug'], mb_strtolower($pack['name']), mb_strtolower($pack['sector'])];
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($text, $needle)) {
                    return [
                        'kind' => 'template',
                        'slug' => $pack['slug'],
                        'name' => $pack['name'],
                        'message' => 'This matches the '.$pack['name'].' template. Install it, then adjust fields in Build.',
                    ];
                }
            }
        }

        $tables = self::guessTables($prompt);

        return [
            'kind' => 'schema',
            'database' => self::guessDatabaseName($prompt),
            'tables' => $tables,
            'message' => 'I will create '.count($tables).' linked tables. You can rename fields and add formulas after.',
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{database_id: int, table_ids: list<int>}
     */
    public static function apply(Workspace $workspace, array $plan): array
    {
        if (($plan['kind'] ?? '') === 'template' && ! empty($plan['slug'])) {
            $installed = TemplateInstaller::install($workspace, $plan['slug']);

            return ['database_id' => $installed->manifest['database_ids'][0] ?? 0, 'table_ids' => array_values($installed->manifest['tables'] ?? [])];
        }

        $database = Database::create([
            'workspace_id' => $workspace->id,
            'name' => $plan['database'] ?? 'Nouveau module',
            'order' => ((int) $workspace->databases()->max('order')) + 1,
        ]);
        $tableIds = [];
        $created = [];
        foreach ($plan['tables'] ?? [] as $i => $def) {
            $table = Table::create([
                'database_id' => $database->id,
                'name' => $def['name'] ?? 'Table '.($i + 1),
                'order' => $i + 1,
            ]);
            $order = 0;
            foreach ($def['fields'] ?? ['Nom'] as $fieldName) {
                $order++;
                $type = self::guessType($fieldName);
                Field::create([
                    'table_id' => $table->id,
                    'name' => $fieldName,
                    'type' => $type,
                    'primary' => $order === 1,
                    'width' => 180,
                    'options' => FieldTypes::defaultOptions($type),
                    'order' => $order,
                ]);
            }
            if ($order === 0) {
                Field::create([
                    'table_id' => $table->id,
                    'name' => 'Nom',
                    'type' => 'text',
                    'primary' => true,
                    'width' => 220,
                    'options' => [],
                    'order' => 1,
                ]);
            }
            \App\Models\View::create([
                'table_id' => $table->id,
                'name' => 'Grille',
                'type' => 'grid',
                'filters' => [],
                'sorts' => [],
                'groups' => [],
                'hidden_fields' => [],
                'field_options' => [],
                'row_height' => 'small',
                'order' => 1,
            ]);
            $created[] = $table;
            $tableIds[] = $table->id;
        }

        if (count($created) >= 2) {
            $first = $created[0]->load('fields');
            $second = $created[1];
            Field::create([
                'table_id' => $second->id,
                'name' => $first->name,
                'type' => 'link_row',
                'primary' => false,
                'width' => 180,
                'options' => ['linked_table_id' => $first->id],
                'order' => ((int) $second->fields()->max('order')) + 1,
            ]);
        }

        return ['database_id' => $database->id, 'table_ids' => $tableIds];
    }

    /**
     * @return list<array{name: string, fields: list<string>}>
     */
    private static function guessTables(string $prompt): array
    {
        $chunks = preg_split('/[,;\n]| et | and | then /u', $prompt) ?: [];
        $tables = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (mb_strlen($chunk) < 3) {
                continue;
            }
            if (preg_match('/\b(table|fiche|module|suivi|gestion)\b/iu', $chunk, $m, PREG_OFFSET_CAPTURE)) {
                $name = trim(preg_replace('/\b(table|fiche|module|suivi|gestion|des|de|du|les|the|a|an)\b/iu', ' ', $chunk) ?? $chunk);
            } else {
                $name = ucfirst(mb_substr($chunk, 0, 40));
            }
            $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
            if ($name === '') {
                continue;
            }
            $tables[] = [
                'name' => mb_convert_case($name, MB_CASE_TITLE, 'UTF-8'),
                'fields' => self::defaultFields($name),
            ];
            if (count($tables) >= 6) {
                break;
            }
        }
        if (! $tables) {
            $tables[] = ['name' => 'Éléments', 'fields' => self::defaultFields('Éléments')];
            $tables[] = ['name' => 'Lignes', 'fields' => ['Nom', 'Quantité', 'Montant', 'Notes']];
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private static function defaultFields(string $table): array
    {
        $base = ['Nom', 'Statut', 'Date', 'Notes'];
        if (preg_match('/client|customer|compte/i', $table)) {
            return ['Nom', 'Email', 'Téléphone', 'SIRET', 'Adresse'];
        }
        if (preg_match('/facture|invoice|devis/i', $table)) {
            return ['Réf', 'Client', 'Montant', 'Statut', 'Échéance'];
        }
        if (preg_match('/stock|produit|article/i', $table)) {
            return ['Nom', 'SKU', 'Stock', 'Prix', 'Seuil'];
        }

        return $base;
    }

    private static function guessType(string $name): string
    {
        $n = mb_strtolower($name);

        return match (true) {
            str_contains($n, 'email') => 'email',
            str_contains($n, 'tél') || str_contains($n, 'phone') => 'phone',
            str_contains($n, 'date') || str_contains($n, 'échéance') => 'date',
            str_contains($n, 'prix') || str_contains($n, 'montant') || str_contains($n, 'stock') || str_contains($n, 'qté') || str_contains($n, 'quant') || str_contains($n, 'heure') => 'number',
            str_contains($n, 'statut') || str_contains($n, 'état') => 'single_select',
            str_contains($n, 'note') || str_contains($n, 'adresse') => 'long_text',
            str_contains($n, 'url') || str_contains($n, 'site') => 'url',
            default => 'text',
        };
    }

    private static function guessDatabaseName(string $prompt): string
    {
        $line = trim(explode("\n", $prompt)[0] ?? 'Nouveau module');

        return mb_substr($line, 0, 80) ?: 'Nouveau module';
    }
}
