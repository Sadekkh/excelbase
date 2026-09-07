<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Row;
use App\Support\Erp\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $settings = InvoiceSetting::for($workspace);

        return response()->json([
            'kind' => 'invoices',
            'can_manage' => \App\Support\Access::canEditData($request->user(), $workspace),
            'settings' => $this->settingsPayload($settings),
            'clients' => $this->clients($settings),
            'invoices' => $workspace->invoices()->limit(80)->get()->map(
                fn ($invoice) => InvoiceService::payload($invoice, $settings)
            )->values(),
        ]);
    }

    public function store(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanEdit($workspace);
        $data = $this->validated($request);
        $invoice = InvoiceService::save($workspace, $data);
        if ($request->boolean('issue')) {
            $invoice = InvoiceService::issue($invoice);
        }

        return response()->json([
            'ok' => true,
            'status' => $invoice->number ? 'Facture '.$invoice->number.' émise.' : 'Brouillon enregistré.',
            'invoice' => InvoiceService::payload($invoice, InvoiceSetting::for($workspace)),
        ], 201);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $workspace = $this->sessionWorkspace($request);
        abort_unless((int) $invoice->workspace_id === (int) $workspace->id, 404);
        $this->assertCanEdit($workspace);
        $action = $request->string('action')->toString();
        $invoice = match ($action) {
            'issue' => InvoiceService::issue($invoice),
            'pay' => InvoiceService::markPaid($invoice),
            'cancel' => InvoiceService::cancel($invoice),
            default => InvoiceService::save($workspace, $this->validated($request), $invoice),
        };

        return response()->json([
            'ok' => true,
            'status' => 'Facture mise à jour.',
            'invoice' => InvoiceService::payload($invoice, InvoiceSetting::for($workspace)),
        ]);
    }

    public function settings(Request $request)
    {
        $workspace = $this->sessionWorkspace($request);
        $this->assertCanBuild($workspace);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:400'],
            'siret' => ['nullable', 'string', 'max:32'],
            'tva_number' => ['nullable', 'string', 'max:32'],
            'ape' => ['nullable', 'string', 'max:16'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'franchise_tva' => ['nullable', 'boolean'],
            'default_vat' => ['nullable', 'numeric'],
            'payment_days' => ['nullable', 'integer', 'min:0', 'max:120'],
            'number_prefix' => ['nullable', 'string', 'max:8'],
            'template_title' => ['nullable', 'string', 'max:80'],
            'template_accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'template_intro' => ['nullable', 'string', 'max:800'],
            'template_footer' => ['nullable', 'string', 'max:800'],
            'template_legal' => ['nullable', 'string', 'max:1200'],
            'template_show_logo' => ['nullable', 'boolean'],
            'template_show_due_date' => ['nullable', 'boolean'],
        ]);
        $settings = InvoiceSetting::for($workspace);
        $settings->fill(collect($data)->except([
            'default_vat', 'franchise_tva',
            'template_title', 'template_accent', 'template_intro', 'template_footer',
            'template_legal', 'template_show_logo', 'template_show_due_date',
        ])->all());
        if ($request->exists('franchise_tva')) {
            $settings->franchise_tva = $request->boolean('franchise_tva');
        }
        if (isset($data['default_vat'])) {
            $settings->default_vat = (int) round(((float) $data['default_vat']) * 100);
        }
        $current = $settings->resolvedTemplate();
        $settings->template = [
            'title' => $data['template_title'] ?? $current['title'],
            'accent' => $data['template_accent'] ?? $current['accent'],
            'intro' => $data['template_intro'] ?? $current['intro'],
            'footer' => $data['template_footer'] ?? $current['footer'],
            'legal' => $data['template_legal'] ?? $current['legal'],
            'show_logo' => $request->exists('template_show_logo')
                ? $request->boolean('template_show_logo')
                : $current['show_logo'],
            'show_due_date' => $request->exists('template_show_due_date')
                ? $request->boolean('template_show_due_date')
                : $current['show_due_date'],
        ];
        $settings->save();

        return response()->json(['ok' => true, 'status' => 'Paramètres de facturation enregistrés.', 'settings' => $this->settingsPayload($settings)]);
    }

    public function print(Invoice $invoice)
    {
        $workspace = $this->workspaceForUser($invoice->workspace_id);
        $settings = InvoiceSetting::for($workspace);

        $invoice->load('workspace');

        return response()
            ->view('invoices.print', [
                'invoice' => $invoice,
                'settings' => $settings,
            ])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['nullable', 'in:facture,avoir,devis'],
            'client_name' => ['required', 'string', 'max:160'],
            'client_address' => ['nullable', 'string', 'max:400'],
            'client_email' => ['nullable', 'email'],
            'client_siret' => ['nullable', 'string', 'max:32'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_row_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:240'],
            'lines.*.qty' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.vat' => ['nullable', 'numeric'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(InvoiceSetting $settings): array
    {
        return [
            'legal_name' => $settings->legal_name,
            'address' => $settings->address,
            'siret' => $settings->siret,
            'tva_number' => $settings->tva_number,
            'ape' => $settings->ape,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'franchise_tva' => (bool) $settings->franchise_tva,
            'default_vat' => $settings->default_vat / 100,
            'payment_days' => $settings->payment_days,
            'number_prefix' => $settings->number_prefix,
            'next_number' => $settings->next_number,
            'client_table_id' => $settings->client_table_id,
            'vat_rate' => $settings->vatRatePercent(),
            'template' => $settings->resolvedTemplate(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: ?string, address: ?string, siret: ?string}>
     */
    private function clients(InvoiceSetting $settings): array
    {
        if (! $settings->client_table_id) {
            return [];
        }
        $table = \App\Models\Table::query()->with(['fields', 'rows'])->find($settings->client_table_id);
        if (! $table) {
            return [];
        }
        $name = $table->primaryField();
        $email = $table->fields->firstWhere('type', 'email');
        $siret = $table->fields->first(fn ($field) => str_contains(mb_strtolower($field->name), 'siret'));
        $address = $table->fields->first(fn ($field) => str_contains(mb_strtolower($field->name), 'adresse'));

        return $table->rows->map(function (Row $row) use ($table, $name, $email, $siret, $address) {
            $row->setRelation('table', $table);

            return [
                'id' => $row->id,
                'name' => $name ? (string) $row->value($name) : '#'.$row->id,
                'email' => $email ? (string) $row->value($email) : null,
                'address' => $address ? (string) $row->value($address) : null,
                'siret' => $siret ? (string) $row->value($siret) : null,
            ];
        })->values()->all();
    }

    private function sessionWorkspace(Request $request)
    {
        $id = $request->session()->get('workspace_id') ?: $request->user()->workspaces()->value('workspaces.id');
        abort_unless($id, 404);

        return $this->workspaceForUser($id);
    }
}
