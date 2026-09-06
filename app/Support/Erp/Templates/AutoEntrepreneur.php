<?php

namespace App\Support\Erp\Templates;

class AutoEntrepreneur
{
    /**
     * @return array<string, mixed>
     */
    public static function pack(): array
    {
        return [
            'slug' => 'auto-entrepreneur',
            'name' => 'Auto-entrepreneur',
            'sector' => 'Indépendant',
            'audience' => 'Micro-entreprises et auto-entrepreneurs en France',
            'summary' => 'Clients, missions, temps, notes de frais, URSSAF et factures en franchise de TVA (art. 293 B).',
            'database' => 'Activité',
            'look' => [
                'tagline' => 'Missions, frais et factures 293 B',
                'brand_color' => '#275d9f',
                'sidebar_color' => '#f4f7fb',
            ],
            'invoice' => [
                'legal_name' => 'Alex Rivera — micro-entreprise',
                'address' => "14 rue de la Folie-Méricourt\n75011 Paris",
                'siret' => '889 334 120 00012',
                'tva_number' => '',
                'ape' => '6201Z',
                'franchise_tva' => true,
                'default_vat' => 0,
                'payment_days' => 30,
                'prefix' => 'FA',
                'clients' => 'clients',
            ],
            'tables' => [
                'clients' => [
                    'name' => 'Clients',
                    'fields' => [
                        ['Nom', 'text', true, 220],
                        ['Email', 'email', false, 200],
                        ['SIRET', 'text', false, 160],
                        ['Adresse', 'long_text', false, 240],
                        ['Taux jour', 'number', false, 110, ['decimal_places' => 0, 'suffix' => ' €']],
                    ],
                    'rows' => [
                        ['Northwind Labs', 'ava@northwindlabs.io', '812 900 441 00028', "44 rue de Turbigo\n75003 Paris", 550],
                        ['Lumen Studio', 'maya@lumen.studio', '447 112 009 00016', "11 rue Lucien Sampaix\n75010 Paris", 480],
                    ],
                ],
                'prestations' => [
                    'name' => 'Prestations',
                    'fields' => [
                        ['Nom', 'text', true, 240],
                        ['Client', 'link_row', false, 180, ['link' => 'clients']],
                        ['Statut', 'single_select', false, 130, ['options' => [['Devis', 'gray'], ['En cours', 'blue'], ['Livrée', 'green'], ['Facturée', 'purple']]]],
                        ['Début', 'date', false, 120],
                        ['Fin', 'date', false, 120],
                        ['Jours', 'number', false, 90, ['decimal_places' => 1]],
                        ['Notes', 'long_text', false, 240],
                        ['Compte-rendu IA', 'ai', false, 220, ['source' => 'Notes', 'mode' => 'summarize']],
                    ],
                    'rows' => [
                        ['Refonte tunnel devis', 'Northwind Labs', 'En cours', '2026-09-01', '2026-09-18', 8, 'Parcours devis + signature. Recette le 18.'],
                        ['Identité saison 2', 'Lumen Studio', 'Livrée', '2026-08-12', '2026-08-28', 5, 'Livrables Figma remis. Attente facture.'],
                    ],
                ],
                'temps' => [
                    'name' => 'Temps',
                    'fields' => [
                        ['Réf', 'text', true, 130],
                        ['Prestation', 'link_row', false, 200, ['link' => 'prestations']],
                        ['Date', 'date', false, 120],
                        ['Heures', 'number', false, 90, ['decimal_places' => 1]],
                        ['Détail', 'text', false, 220],
                    ],
                    'rows' => [
                        ['T-0609-1', 'Refonte tunnel devis', '2026-09-06', 6.5, 'Parcours mobile + mentions'],
                        ['T-0509-1', 'Refonte tunnel devis', '2026-09-05', 7, 'Atelier recueil'],
                    ],
                ],
                'frais' => [
                    'name' => 'Notes de frais',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Date', 'date', false, 120],
                        ['Montant', 'number', false, 110, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['Catégorie', 'single_select', false, 140, ['options' => [['Transport', 'blue'], ['Repas', 'orange'], ['Matériel', 'purple'], ['Abonnement', 'green']]]],
                        ['Client', 'link_row', false, 160, ['link' => 'clients']],
                        ['Remboursé', 'boolean', false, 110],
                    ],
                    'rows' => [
                        ['Navigo septembre', '2026-09-01', 86.40, 'Transport', 'Northwind Labs', false],
                        ['Déjeuner atelier', '2026-09-05', 18.50, 'Repas', 'Northwind Labs', false],
                    ],
                ],
                'urssaf' => [
                    'name' => 'Déclarations URSSAF',
                    'fields' => [
                        ['Période', 'text', true, 140],
                        ['CA déclaré', 'number', false, 130, ['decimal_places' => 0, 'suffix' => ' €']],
                        ['Cotisations', 'number', false, 130, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['Statut', 'single_select', false, 120, ['options' => [['À faire', 'yellow'], ['Déclaré', 'green'], ['Payé', 'blue']]]],
                        ['Échéance', 'date', false, 120],
                    ],
                    'rows' => [
                        ['2026-T2', 12400, 2728.00, 'Payé', '2026-07-31'],
                        ['2026-T3', 0, 0, 'À faire', '2026-10-31'],
                    ],
                ],
            ],
            'views' => [
                ['table' => 'prestations', 'name' => 'Kanban', 'type' => 'kanban', 'kanban' => 'Statut'],
                ['table' => 'temps', 'name' => 'Calendrier', 'type' => 'calendar', 'kanban' => 'Date'],
            ],
            'dashboard' => [
                'name' => 'Pilotage activité',
                'description' => 'Missions, temps et déclarations.',
                'widgets' => [
                    ['stat', 'Prestations', 'prestations', 'count'],
                    ['stat', 'Heures', 'temps', 'sum', 'Heures'],
                    ['chart', 'Prestations', 'prestations', null, 'Statut'],
                    ['list', 'Missions', 'prestations'],
                    ['invoice_stat', 'Facturé', 'ttc'],
                    ['invoice_stat', 'Impayés', 'unpaid'],
                ],
            ],
            'automations' => [
                [
                    'name' => 'Prestation livrée',
                    'table' => 'prestations',
                    'trigger' => 'row_updated',
                    'action' => 'notify',
                    'message' => 'Une prestation a changé — penser à émettre la facture (franchise 293 B).',
                ],
            ],
        ];
    }
}
