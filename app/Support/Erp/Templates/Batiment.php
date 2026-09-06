<?php

namespace App\Support\Erp\Templates;

class Batiment
{
    /**
     * @return array<string, mixed>
     */
    public static function pack(): array
    {
        return [
            'slug' => 'batiment',
            'name' => 'Bâtiment',
            'sector' => 'BTP',
            'audience' => 'Artisans et PME du bâtiment en France',
            'summary' => 'Chantiers, devis, pointages, matériaux, sous-traitance et factures à 20 % (mentions de retard).',
            'database' => 'Chantiers',
            'look' => [
                'tagline' => 'Devis, chantiers et facturation BTP',
                'brand_color' => '#c45c26',
                'sidebar_color' => '#fff4e8',
            ],
            'invoice' => [
                'legal_name' => 'Martin Bâtiment',
                'address' => "18 avenue Jean Jaurès\n93100 Montreuil",
                'siret' => '478 221 009 00027',
                'tva_number' => 'FR81478221009',
                'ape' => '4120B',
                'franchise_tva' => false,
                'default_vat' => 20,
                'payment_days' => 30,
                'prefix' => 'FA',
                'clients' => 'clients',
            ],
            'tables' => [
                'clients' => [
                    'name' => 'Clients',
                    'fields' => [
                        ['Nom', 'text', true, 220],
                        ['Type', 'single_select', false, 130, ['options' => [['Particulier', 'blue'], ['Syndic', 'purple'], ['Promoteur', 'green']]]],
                        ['Email', 'email', false, 200],
                        ['Téléphone', 'phone', false, 140],
                        ['SIRET', 'text', false, 160],
                        ['Adresse', 'long_text', false, 240],
                    ],
                    'rows' => [
                        ['SCI du Parc', 'Promoteur', 'compta@sciduparc.fr', '01 48 59 22 10', '399 100 221 00044', "2 place de la Mairie\n93100 Montreuil"],
                        ['Mme Morel', 'Particulier', 'claire.morel@mail.fr', '06 12 44 90 18', '', "7 villa des Roses\n94130 Nogent-sur-Marne"],
                    ],
                ],
                'chantiers' => [
                    'name' => 'Chantiers',
                    'fields' => [
                        ['Nom', 'text', true, 240],
                        ['Client', 'link_row', false, 180, ['link' => 'clients']],
                        ['Statut', 'single_select', false, 140, ['options' => [['Devis', 'gray'], ['En cours', 'blue'], ['Réception', 'orange'], ['Clos', 'green']]]],
                        ['Début', 'date', false, 120],
                        ['Fin prévue', 'date', false, 120],
                        ['Budget HT', 'number', false, 130, ['decimal_places' => 0, 'suffix' => ' €']],
                        ['Adresse', 'long_text', false, 220],
                        ['Notes', 'long_text', false, 240],
                        ['Synthèse IA', 'ai', false, 220, ['source' => 'Notes', 'mode' => 'summarize']],
                    ],
                    'rows' => [
                        ['Ravalement villa Morel', 'Mme Morel', 'En cours', '2026-08-18', '2026-10-02', 18400, '7 villa des Roses, Nogent', 'Échafaudage posé. Attente teinte façade.'],
                        ['R+2 SCI du Parc', 'SCI du Parc', 'Devis', '2026-10-01', '2027-03-15', 240000, '2 place de la Mairie, Montreuil', 'Lots gros œuvre + étanchéité. Relance AOR.'],
                    ],
                ],
                'devis' => [
                    'name' => 'Devis',
                    'fields' => [
                        ['Réf', 'text', true, 140],
                        ['Chantier', 'link_row', false, 200, ['link' => 'chantiers']],
                        ['Montant HT', 'number', false, 130, ['decimal_places' => 0, 'suffix' => ' €']],
                        ['Statut', 'single_select', false, 130, ['options' => [['Envoyé', 'yellow'], ['Accepté', 'green'], ['Refusé', 'red']]]],
                        ['Validité', 'date', false, 120],
                    ],
                    'rows' => [
                        ['DEV-2026-014', 'Ravalement villa Morel', 18400, 'Accepté', '2026-09-30'],
                        ['DEV-2026-021', 'R+2 SCI du Parc', 240000, 'Envoyé', '2026-10-15'],
                    ],
                ],
                'materiaux' => [
                    'name' => 'Matériaux',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Unité', 'text', false, 90],
                        ['Stock', 'number', false, 90],
                        ['Prix', 'number', false, 110, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['Chantier', 'link_row', false, 180, ['link' => 'chantiers']],
                    ],
                    'rows' => [
                        ['Enduit monocouche', 'sac 25 kg', 42, 18.90, 'Ravalement villa Morel'],
                        ['Échafaudage semaine', 'lot', 1, 480, 'Ravalement villa Morel'],
                    ],
                ],
                'pointages' => [
                    'name' => 'Pointages',
                    'fields' => [
                        ['Réf', 'text', true, 140],
                        ['Chantier', 'link_row', false, 200, ['link' => 'chantiers']],
                        ['Compagnon', 'text', false, 140],
                        ['Date', 'date', false, 120],
                        ['Heures', 'number', false, 90, ['decimal_places' => 1]],
                        ['Tâche', 'text', false, 180],
                    ],
                    'rows' => [
                        ['PT-0609-01', 'Ravalement villa Morel', 'Yann P.', '2026-09-06', 8, 'Préparation façade sud'],
                        ['PT-0609-02', 'Ravalement villa Morel', 'Ibrahim K.', '2026-09-06', 7.5, 'Gobetis'],
                    ],
                ],
                'soustraitants' => [
                    'name' => 'Sous-traitants',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Métier', 'text', false, 140],
                        ['Téléphone', 'phone', false, 140],
                        ['Décennale', 'boolean', false, 110],
                        ['Chantier', 'link_row', false, 200, ['link' => 'chantiers']],
                    ],
                    'rows' => [
                        ['Échafaudages 93', 'Montage', '01 48 32 11 90', true, 'Ravalement villa Morel'],
                    ],
                ],
            ],
            'views' => [
                ['table' => 'chantiers', 'name' => 'Kanban', 'type' => 'kanban', 'kanban' => 'Statut'],
                ['table' => 'pointages', 'name' => 'Calendrier', 'type' => 'calendar', 'kanban' => 'Date'],
            ],
            'dashboard' => [
                'name' => 'Pilotage chantiers',
                'description' => 'Avancement, heures et en-cours.',
                'widgets' => [
                    ['stat', 'Chantiers', 'chantiers', 'count'],
                    ['stat', 'Heures pointées', 'pointages', 'sum', 'Heures'],
                    ['chart', 'Chantiers par statut', 'chantiers', null, 'Statut'],
                    ['list', 'Derniers chantiers', 'chantiers'],
                    ['invoice_stat', 'Facturé TTC', 'ttc'],
                    ['invoice_stat', 'Impayés', 'unpaid'],
                ],
            ],
            'automations' => [
                [
                    'name' => 'Nouveau chantier',
                    'table' => 'chantiers',
                    'trigger' => 'row_created',
                    'action' => 'notify',
                    'message' => 'Un chantier a été créé — vérifier devis et planning.',
                ],
            ],
        ];
    }
}
