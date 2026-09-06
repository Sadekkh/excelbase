<?php

namespace App\Support\Erp\Templates;

class Boulangerie
{
    /**
     * @return array<string, mixed>
     */
    public static function pack(): array
    {
        $vat = ['options' => [['5,5 %', 'green'], ['10 %', 'orange'], ['20 %', 'red']]];
        $cat = ['options' => [['Pain', 'brown'], ['Viennoiserie', 'orange'], ['Pâtisserie', 'pink'], ['Traiteur', 'green']]];

        return [
            'slug' => 'boulangerie',
            'name' => 'Boulangerie',
            'sector' => 'Commerce alimentaire',
            'audience' => 'Boulangeries et boulangeries-pâtisseries en France',
            'summary' => 'Produits, recettes, stock, fournisseurs, ventes du jour, planning et factures B2B (TVA 5,5 %).',
            'database' => 'Boulangerie',
            'look' => [
                'tagline' => 'Fournil, caisse et factures B2B',
                'brand_color' => '#c45c26',
                'sidebar_color' => '#fff7f0',
            ],
            'invoice' => [
                'legal_name' => 'Fournil du Marais',
                'address' => "12 rue des Archives\n75004 Paris",
                'siret' => '812 456 789 00014',
                'tva_number' => 'FR34812456789',
                'ape' => '1071C',
                'franchise_tva' => false,
                'default_vat' => 5.5,
                'payment_days' => 30,
                'prefix' => 'FA',
                'clients' => 'clients',
            ],
            'tables' => [
                'fournisseurs' => [
                    'name' => 'Fournisseurs',
                    'fields' => [
                        ['Nom', 'text', true, 220],
                        ['Contact', 'phone', false, 140],
                        ['Email', 'email', false, 200],
                        ['SIRET', 'text', false, 160],
                        ['Spécialité', 'text', false, 160],
                        ['Délai jours', 'number', false, 110],
                    ],
                    'rows' => [
                        ['Meunerie Dupont', '01 42 00 11 22', 'commandes@meunerie-dupont.fr', '301 112 223 00018', 'Farines', 2],
                        ['Beurre des Prés', '02 99 44 12 08', 'hello@beurredespres.fr', '421 998 110 00022', 'Beurre AOP', 3],
                        ['Fruits Île-de-France', '01 48 77 09 31', 'livraison@fruits-idf.fr', '533 221 009 00031', 'Fruits', 1],
                    ],
                ],
                'ingredients' => [
                    'name' => 'Ingrédients',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Unité', 'single_select', false, 100, ['options' => [['kg', 'blue'], ['L', 'cyan'], ['pièce', 'gray']]]],
                        ['Stock', 'number', false, 100, ['decimal_places' => 2]],
                        ['Seuil', 'number', false, 90, ['decimal_places' => 2]],
                        ['Coût', 'number', false, 110, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['Fournisseur', 'link_row', false, 180, ['link' => 'fournisseurs']],
                    ],
                    'rows' => [
                        ['Farine T65', 'kg', 180, 40, 0.72, 'Meunerie Dupont'],
                        ['Beurre doux', 'kg', 24, 8, 8.40, 'Beurre des Prés'],
                        ['Levure', 'kg', 3.2, 1, 4.10, 'Meunerie Dupont'],
                        ['Pommes', 'kg', 12, 6, 1.80, 'Fruits Île-de-France'],
                    ],
                ],
                'produits' => [
                    'name' => 'Produits',
                    'fields' => [
                        ['Nom', 'text', true, 220],
                        ['Catégorie', 'single_select', false, 140, $cat],
                        ['Prix TTC', 'number', false, 110, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['TVA', 'single_select', false, 90, $vat],
                        ['Stock', 'number', false, 90],
                        ['Seuil', 'number', false, 90],
                        ['Actif', 'boolean', false, 80],
                        ['Notes', 'long_text', false, 240],
                        ['Idée IA', 'ai', false, 220, ['source' => 'Notes', 'mode' => 'summarize']],
                    ],
                    'rows' => [
                        ['Baguette tradition', 'Pain', 1.35, '5,5 %', 90, 25, true, 'Farine T65, 20 min de pousse. Meilleure vente du midi.'],
                        ['Croissant beurre', 'Viennoiserie', 1.40, '5,5 %', 48, 20, true, 'Tourage le soir. Sortie 6h30.'],
                        ['Tarte aux pommes', 'Pâtisserie', 18.50, '10 %', 6, 3, true, 'Pommes France, 6/8 parts. Commande vitrine.'],
                        ['Quiche lorraine', 'Traiteur', 4.20, '10 %', 10, 4, true, 'Pause déjeuner entreprises du quartier.'],
                    ],
                ],
                'recettes' => [
                    'name' => 'Recettes',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Produit', 'link_row', false, 180, ['link' => 'produits']],
                        ['Rendement', 'number', false, 110],
                        ['Ingrédient', 'link_row', false, 180, ['link' => 'ingredients']],
                        ['Quantité', 'number', false, 110, ['decimal_places' => 2]],
                    ],
                    'rows' => [
                        ['Pâte baguette', 'Baguette tradition', 40, 'Farine T65', 25],
                        ['Pâte croissant', 'Croissant beurre', 36, 'Beurre doux', 4.5],
                        ['Garniture tarte', 'Tarte aux pommes', 4, 'Pommes', 2.2],
                    ],
                ],
                'clients' => [
                    'name' => 'Clients B2B',
                    'fields' => [
                        ['Nom', 'text', true, 220],
                        ['Email', 'email', false, 200],
                        ['Téléphone', 'phone', false, 140],
                        ['SIRET', 'text', false, 160],
                        ['Adresse', 'long_text', false, 240],
                        ['Fréquence', 'single_select', false, 130, ['options' => [['Quotidien', 'green'], ['Hebdo', 'blue'], ['Ponctuel', 'gray']]]],
                    ],
                    'rows' => [
                        ['Hôtel des Archives', 'cuisine@archives-hotel.fr', '01 44 78 12 00', '552 100 334 00029', "8 rue des Archives\n75004 Paris", 'Quotidien'],
                        ['Café Saint-Paul', 'commande@cafesaintpaul.fr', '01 42 72 19 44', '801 223 118 00011', "12 rue Saint-Paul\n75004 Paris", 'Hebdo'],
                    ],
                ],
                'ventes' => [
                    'name' => 'Ventes du jour',
                    'fields' => [
                        ['Réf', 'text', true, 140],
                        ['Date', 'date', false, 120],
                        ['Produit', 'link_row', false, 180, ['link' => 'produits']],
                        ['Qté', 'number', false, 80],
                        ['Montant TTC', 'number', false, 130, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['Canal', 'single_select', false, 120, ['options' => [['Boutique', 'blue'], ['B2B', 'green'], ['Click & collect', 'purple']]]],
                    ],
                    'rows' => [
                        ['V-0609-01', '2026-09-06', 'Baguette tradition', 86, 116.10, 'Boutique'],
                        ['V-0609-02', '2026-09-06', 'Croissant beurre', 54, 75.60, 'Boutique'],
                        ['V-0609-03', '2026-09-06', 'Baguette tradition', 40, 54.00, 'B2B'],
                    ],
                ],
                'planning' => [
                    'name' => 'Planning',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Poste', 'single_select', false, 140, ['options' => [['Fournil', 'brown'], ['Vitrine', 'pink'], ['Caisse', 'blue']]]],
                        ['Jour', 'date', false, 120],
                        ['Début', 'text', false, 90],
                        ['Fin', 'text', false, 90],
                        ['Présent', 'boolean', false, 90],
                    ],
                    'rows' => [
                        ['Karim B.', 'Fournil', '2026-09-06', '04:00', '12:00', true],
                        ['Léa M.', 'Vitrine', '2026-09-06', '06:30', '14:30', true],
                        ['Sofia R.', 'Caisse', '2026-09-06', '07:00', '15:00', true],
                    ],
                ],
            ],
            'views' => [
                ['table' => 'produits', 'name' => 'Par catégorie', 'type' => 'kanban', 'kanban' => 'Catégorie'],
                ['table' => 'planning', 'name' => 'Semaine', 'type' => 'calendar', 'kanban' => 'Jour'],
            ],
            'dashboard' => [
                'name' => 'Pilotage boulangerie',
                'description' => 'Ventes, stock et équipe du fournil.',
                'widgets' => [
                    ['stat', 'Lignes de vente', 'ventes', 'count'],
                    ['stat', 'CA TTC', 'ventes', 'sum', 'Montant TTC'],
                    ['chart', 'Ventes par canal', 'ventes', null, 'Canal'],
                    ['list', 'Dernières ventes', 'ventes'],
                    ['invoice_stat', 'Facturé TTC', 'ttc'],
                    ['invoice_stat', 'Impayés', 'unpaid'],
                ],
            ],
            'automations' => [
                [
                    'name' => 'Alerte stock produit',
                    'table' => 'produits',
                    'trigger' => 'row_updated',
                    'action' => 'notify',
                    'message' => 'Un produit de la boulangerie a été mis à jour — vérifier le seuil de stock.',
                ],
            ],
        ];
    }
}
