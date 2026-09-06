<?php

namespace App\Support\Erp\Templates;

class CoffeeShop
{
    /**
     * @return array<string, mixed>
     */
    public static function pack(): array
    {
        return [
            'slug' => 'coffee-shop',
            'name' => 'Coffee shop',
            'sector' => 'CHR',
            'audience' => 'Cafés, coffee shops et bars à café en France',
            'summary' => 'Carte, recettes, stock, services, réservations et factures (TVA 10 % conso / 20 % boutique).',
            'database' => 'Coffee shop',
            'look' => [
                'tagline' => 'Carte, service et caisse',
                'brand_color' => '#6b3f2a',
                'sidebar_color' => '#f7f1ea',
            ],
            'invoice' => [
                'legal_name' => 'Atelier Café',
                'address' => "5 rue des Martyrs\n75009 Paris",
                'siret' => '890 112 445 00019',
                'tva_number' => 'FR45890112445',
                'ape' => '5610C',
                'franchise_tva' => false,
                'default_vat' => 10,
                'payment_days' => 15,
                'prefix' => 'FA',
                'clients' => 'clients',
            ],
            'tables' => [
                'fournisseurs' => [
                    'name' => 'Fournisseurs',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Email', 'email', false, 200],
                        ['Téléphone', 'phone', false, 140],
                        ['Produit', 'text', false, 160],
                    ],
                    'rows' => [
                        ['Torréfacteur Rivoli', 'commandes@rivoli-cafe.fr', '01 42 36 18 22', 'Café grain'],
                        ['Laiterie de l’Est', 'livraison@laiterie-est.fr', '01 43 57 09 40', 'Lait / crème'],
                    ],
                ],
                'ingredients' => [
                    'name' => 'Ingrédients',
                    'fields' => [
                        ['Nom', 'text', true, 180],
                        ['Stock', 'number', false, 100, ['decimal_places' => 2]],
                        ['Seuil', 'number', false, 90, ['decimal_places' => 2]],
                        ['Unité', 'text', false, 80],
                        ['Fournisseur', 'link_row', false, 180, ['link' => 'fournisseurs']],
                    ],
                    'rows' => [
                        ['Espresso blend', 12, 4, 'kg', 'Torréfacteur Rivoli'],
                        ['Lait entier', 18, 8, 'L', 'Laiterie de l’Est'],
                        ['Croissants', 24, 12, 'pièce', 'Torréfacteur Rivoli'],
                    ],
                ],
                'carte' => [
                    'name' => 'Carte',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Famille', 'single_select', false, 130, ['options' => [['Boisson', 'brown'], ['Pâtisserie', 'pink'], ['Brunch', 'green'], ['Boutique', 'purple']]]],
                        ['Prix TTC', 'number', false, 110, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['TVA', 'single_select', false, 90, ['options' => [['10 %', 'orange'], ['20 %', 'red']]]],
                        ['Actif', 'boolean', false, 80],
                        ['Notes', 'long_text', false, 220],
                        ['Accord IA', 'ai', false, 200, ['source' => 'Notes', 'mode' => 'summarize']],
                    ],
                    'rows' => [
                        ['Flat white', 'Boisson', 4.80, '10 %', true, 'Double shot, lait entier, tasse 20 cl.'],
                        ['Filtre saison', 'Boisson', 3.50, '10 %', true, 'Éthiopie naturel, V60.'],
                        ['Cookie chocolat', 'Pâtisserie', 3.20, '10 %', true, 'Sortie 11h. Accords filtre.'],
                        ['Sac 250 g', 'Boutique', 12.00, '20 %', true, 'Même blend que le bar.'],
                    ],
                ],
                'clients' => [
                    'name' => 'Clients',
                    'fields' => [
                        ['Nom', 'text', true, 200],
                        ['Email', 'email', false, 200],
                        ['Téléphone', 'phone', false, 140],
                        ['Type', 'single_select', false, 130, ['options' => [['Régulier', 'green'], ['Entreprise', 'blue'], ['Événement', 'purple']]]],
                    ],
                    'rows' => [
                        ['Studio Northwind', 'hello@northwindlabs.io', '01 84 80 12 12', 'Entreprise'],
                        ['Atelier photo 9e', 'bookings@atelier9.fr', '06 18 44 21 09', 'Événement'],
                    ],
                ],
                'ventes' => [
                    'name' => 'Ventes',
                    'fields' => [
                        ['Réf', 'text', true, 130],
                        ['Date', 'date', false, 120],
                        ['Article', 'link_row', false, 160, ['link' => 'carte']],
                        ['Qté', 'number', false, 80],
                        ['Montant TTC', 'number', false, 130, ['decimal_places' => 2, 'suffix' => ' €']],
                        ['Service', 'single_select', false, 110, ['options' => [['Matin', 'yellow'], ['Midi', 'orange'], ['Après-midi', 'blue']]]],
                    ],
                    'rows' => [
                        ['C-0609-1', '2026-09-06', 'Flat white', 62, 297.60, 'Matin'],
                        ['C-0609-2', '2026-09-06', 'Cookie chocolat', 28, 89.60, 'Midi'],
                        ['C-0609-3', '2026-09-06', 'Sac 250 g', 6, 72.00, 'Après-midi'],
                    ],
                ],
                'equipe' => [
                    'name' => 'Équipe',
                    'fields' => [
                        ['Nom', 'text', true, 180],
                        ['Rôle', 'single_select', false, 130, ['options' => [['Barista', 'brown'], ['Salle', 'blue'], ['Manager', 'purple']]]],
                        ['Jour', 'date', false, 120],
                        ['Début', 'text', false, 80],
                        ['Fin', 'text', false, 80],
                    ],
                    'rows' => [
                        ['Nina C.', 'Barista', '2026-09-06', '07:30', '15:30'],
                        ['Hugo T.', 'Salle', '2026-09-06', '08:00', '16:00'],
                    ],
                ],
                'reservations' => [
                    'name' => 'Réservations',
                    'fields' => [
                        ['Nom', 'text', true, 180],
                        ['Client', 'link_row', false, 180, ['link' => 'clients']],
                        ['Date', 'date', false, 120],
                        ['Heure', 'text', false, 80],
                        ['Couverts', 'number', false, 90],
                        ['Statut', 'single_select', false, 120, ['options' => [['Confirmé', 'green'], ['Option', 'yellow'], ['Annulé', 'red']]]],
                    ],
                    'rows' => [
                        ['Réunion Northwind', 'Studio Northwind', '2026-09-09', '09:00', 8, 'Confirmé'],
                    ],
                ],
            ],
            'views' => [
                ['table' => 'carte', 'name' => 'Par famille', 'type' => 'kanban', 'kanban' => 'Famille'],
                ['table' => 'reservations', 'name' => 'Calendrier', 'type' => 'calendar', 'kanban' => 'Date'],
            ],
            'dashboard' => [
                'name' => 'Pilotage café',
                'description' => 'Services, carte et encaissements.',
                'widgets' => [
                    ['stat', 'Tickets', 'ventes', 'count'],
                    ['stat', 'CA TTC', 'ventes', 'sum', 'Montant TTC'],
                    ['chart', 'Ventes par service', 'ventes', null, 'Service'],
                    ['list', 'Dernières ventes', 'ventes'],
                    ['invoice_stat', 'Facturé TTC', 'ttc'],
                    ['invoice_stat', 'Impayés', 'unpaid'],
                ],
            ],
            'automations' => [
                [
                    'name' => 'Nouvelle réservation',
                    'table' => 'reservations',
                    'trigger' => 'row_created',
                    'action' => 'notify',
                    'message' => 'Une réservation a été ajoutée — prévenir la salle.',
                ],
            ],
        ];
    }
}
