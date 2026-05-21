<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ClienteAuditoriaController -- Wave F (ADR 0179) timeline LGPD Art. 18.
 *
 * Endpoints:
 *   GET /cliente/{id}/auditoria          -> timeline paginada JSON (20/pg, max 100)
 *   GET /cliente/{id}/auditoria/export   -> download CSV UTF-8 BOM (Excel BR)
 *
 * Reusa Spatie ActivityLog v4.8 (`forSubject($contact)` Spatie helper) +
 * scope explicito `where('activity_log.business_id', $bizId)` (multi-tenant Tier
 * 0 ADR 0093 IRREVOGAVEL -- defense-in-depth alem do escopo do subject).
 *
 * Schema activity_log oimpresso:
 *   - `description` (Spatie) = string PT-BR (ex "criou registro X")
 *   - `event` (col adicional 2023_02_11) = 'created'|'updated'|'deleted'|'restored'|'reverted'
 *   - `business_id` (col 2021_03_16) = scope Tier 0
 *   - `causer_kind` (col 2026_05_10 US-AUDIT-005) = 'user'|'agent'|'system'|'api'
 *   - `properties` (JSON) = { old:{}, attributes:{} } shape Spatie padrao
 *
 * App\Contact usa trait Spatie LogsActivity com config:
 *   logOnly(['name','email','mobile','contact_type','customer_group_id'])
 *   logOnlyDirty() + dontSubmitEmptyLogs() + useLogName('crm.contact')
 *   tax_number (CPF/CNPJ) PROIBIDO em properties (ADR 0127 §F1 PII LGPD).
 *
 * LGPD Art. 18: usuario pode exportar TODO o historico de modificacoes do seu
 * cadastro (direito de acesso aos dados pessoais). Permission gate aqui usa
 * apenas .view (nao .update) -- direito leitura amplo.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   Contact::where('business_id', $bizId)->findOrFail() -> 404 cross-tenant
 *   Activity::query()->where('activity_log.business_id', $bizId) -> defense
 *
 * PII LGPD:
 *   - Modelo Contact NUNCA loga tax_number em logOnly() (ADR 0127 §F1)
 *   - maskPiiValue() ainda mascara CPF/CNPJ em description/properties caso
 *     algum codigo legacy tenha logado por engano (defesa em profundidade)
 *   - CSV nunca exporta tax_number plain
 *
 * Refs:
 *   - memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md §Wave F
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - memory/decisions/0127-modules-auditoria-ui-undo.md
 *   - prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md §6
 *   - resources/js/Pages/Cliente/Index.charter.md v3 (Goals Tab Auditoria)
 */
class ClienteAuditoriaController extends Controller
{
    /** Page size default + cap maximo. */
    private const PER_PAGE_DEFAULT = 20;
    private const PER_PAGE_MAX = 100;

    /**
     * GET /cliente/{id}/auditoria
     *
     * Query: ?page=N&per_page=20 (cap 100).
     * Response 200:
     *   { data: AuditEvent[], meta: { current_page, last_page, per_page, total } }
     * Response 404: cross-tenant ou contact inexistente (nao 403 -- nao vaza).
     * Response 403: sem customer.view E sem supplier.view E sem .view_own.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact; // ja e JsonResponse 404
        }

        $permissionCheck = $this->ensureCanView();
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }

        $perPage = (int) $request->query('per_page', (string) self::PER_PAGE_DEFAULT);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));

        $businessId = (int) $request->session()->get('user.business_id');

        $paginator = Activity::query()
            ->where('activity_log.business_id', $businessId) // Tier 0 defense
            ->where('subject_type', Contact::class)
            ->where('subject_id', $contact->id)
            ->with('causer')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $paginator->getCollection()
            ->map(fn (Activity $a) => $this->humanize($a))
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /cliente/{id}/auditoria/export
     *
     * Query: ?format=csv (default csv -- xlsx/pdf futuro).
     * Response: download stream CSV UTF-8 BOM (Excel BR abre certo).
     * Cabecalho: ID;Tipo;Descricao;Causer;Data
     *
     * chunk(500) evita OOM em logs grandes (ex cliente com 5000 eventos).
     */
    public function export(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact; // 404
        }

        $permissionCheck = $this->ensureCanView();
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $contactId = (int) $contact->id;
        $filename = "auditoria-cliente-{$contactId}-" . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () use ($businessId, $contactId) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // BOM UTF-8 -- Excel BR abre acentos corretamente.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Tipo', 'Descricao', 'Causer', 'Data'], ';');

            Activity::query()
                ->where('activity_log.business_id', $businessId)
                ->where('subject_type', Contact::class)
                ->where('subject_id', $contactId)
                ->with('causer')
                ->orderByDesc('id')
                ->chunk(500, function ($activities) use ($out) {
                    foreach ($activities as $a) {
                        $h = $this->humanize($a);
                        fputcsv($out, [
                            (string) $h['id'],
                            (string) $h['type'],
                            (string) $h['description'],
                            (string) ($h['causer']['name'] ?? 'Sistema'),
                            (string) $h['created_at'],
                        ], ';');
                    }
                });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Humaniza uma Activity Spatie em payload front-end-ready.
     *
     * Mapeia event (created/updated/deleted/restored/reverted) -> icon_hint
     * + descricao PT-BR humanizada.
     */
    private function humanize(Activity $a): array
    {
        $event = (string) ($a->event ?? $a->description ?? 'custom');
        $properties = is_array($a->properties)
            ? $a->properties
            : ($a->properties ? $a->properties->toArray() : []);

        $attributes = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];

        // Campo afetado: primeira chave de attributes (Spatie logOnlyDirty
        // garante so dirty fields). Em deletes attributes pode vir vazio.
        $field = ! empty($attributes) ? (string) array_key_first($attributes) : null;

        $causerName = null;
        $causerInitials = null;
        $causerId = null;
        if ($a->causer) {
            $first = (string) ($a->causer->first_name ?? '');
            $last = (string) ($a->causer->last_name ?? '');
            $causerName = trim($first . ' ' . $last) ?: ($a->causer->email ?? 'Usuario');
            $causerInitials = $this->initials($first, $last);
            $causerId = (int) $a->causer->id;
        }

        return [
            'id' => (int) $a->id,
            'type' => $this->normalizeType($event),
            'description' => $this->descricaoHumanizada($event, $field, $old, $attributes, (string) ($a->description ?? '')),
            'field' => $field,
            'old_value' => $this->maskPiiValue($field !== null ? ($old[$field] ?? null) : null),
            'new_value' => $this->maskPiiValue($field !== null ? ($attributes[$field] ?? null) : null),
            'causer' => $causerName ? [
                'id' => $causerId,
                'name' => $causerName,
                'avatar_initials' => $causerInitials,
            ] : null,
            'created_at' => $a->created_at?->toIso8601String() ?? '',
            'created_at_human' => $a->created_at?->diffForHumans() ?? '',
            'icon_hint' => $this->iconHint($event, $field),
        ];
    }

    /**
     * Normaliza event em 1 dos 6 tipos canon front-end.
     */
    private function normalizeType(string $event): string
    {
        return match ($event) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'restored' => 'restored',
            'reverted' => 'restored', // alias visual
            default => 'custom',
        };
    }

    /**
     * Descricao PT-BR humanizada. Inclui campo modificado quando disponivel.
     */
    private function descricaoHumanizada(string $event, ?string $field, array $old, array $attributes, string $fallback): string
    {
        $fieldLabel = $field !== null ? $this->labelDoCampo($field) : null;

        if ($event === 'created') {
            return 'Cliente cadastrado';
        }
        if ($event === 'deleted') {
            return 'Cliente excluido (soft delete)';
        }
        if ($event === 'restored' || $event === 'reverted') {
            return 'Cliente restaurado';
        }
        if ($event === 'updated' && $field !== null) {
            $oldVal = $this->maskPiiValue($old[$field] ?? null);
            $newVal = $this->maskPiiValue($attributes[$field] ?? null);
            if ($oldVal !== null && $newVal !== null && $oldVal !== '' && $newVal !== '') {
                return ucfirst($fieldLabel ?? $field) . " alterado: {$oldVal} -> {$newVal}";
            }
            if ($newVal !== null && $newVal !== '') {
                return ucfirst($fieldLabel ?? $field) . " definido: {$newVal}";
            }
            return ucfirst($fieldLabel ?? $field) . ' atualizado';
        }
        if ($event === 'updated') {
            return 'Dados atualizados';
        }

        return $fallback !== '' ? $fallback : ucfirst($event);
    }

    /**
     * Labels PT-BR pros campos do Contact que aparecem em activity_log.
     * Cobertura: campos do logOnly() em App\Contact + campos novos Wave B/C.
     */
    private function labelDoCampo(string $field): string
    {
        return match ($field) {
            'name' => 'nome',
            'email' => 'email',
            'mobile' => 'telefone',
            'tel2' => 'telefone secundario',
            'contact_type' => 'tipo de contato',
            'customer_group_id' => 'grupo de clientes',
            'fantasia' => 'nome fantasia',
            'tipo' => 'tipo (PF/PJ)',
            'ie' => 'inscricao estadual',
            'rg' => 'RG',
            'nascimento' => 'data de nascimento',
            'cargo' => 'cargo',
            'site_url' => 'site',
            'canal_preferido' => 'canal preferido',
            'zip_code' => 'CEP',
            'address_line_1' => 'endereco',
            'address_line_2' => 'complemento',
            'neighborhood' => 'bairro',
            'city' => 'cidade',
            'state' => 'UF',
            'credit_limit' => 'limite de credito',
            'pay_term_number' => 'prazo de pagamento',
            'tabela_preco_padrao' => 'tabela de preco',
            'pgto_padrao' => 'forma de pagamento',
            'obs_comercial' => 'observacao comercial',
            'segmento' => 'segmento',
            'tags' => 'tags',
            'contact_status' => 'status',
            'vip' => 'VIP',
            default => $field,
        };
    }

    /**
     * Icon hint pro frontend escolher Lucide icon.
     * Mapeamento: created->plus, deleted->trash, restored->shield, updated->edit,
     * tags->tag (semantic), custom->eye.
     */
    private function iconHint(string $event, ?string $field): string
    {
        if ($event === 'created') {
            return 'plus';
        }
        if ($event === 'deleted') {
            return 'trash';
        }
        if ($event === 'restored' || $event === 'reverted') {
            return 'shield';
        }
        if ($event === 'updated' && $field === 'tags') {
            return 'tag';
        }
        if ($event === 'updated') {
            return 'edit';
        }
        return 'eye';
    }

    /**
     * Mascaramento defesa-em-profundidade pra CPF/CNPJ que possam ter vazado
     * pra properties por engano de codigo legacy. logOnly() do Contact NAO
     * inclui tax_number (ADR 0127 §F1), mas se algum dia incluir, este metodo
     * captura no display layer.
     */
    private function maskPiiValue(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }
        if (is_array($val)) {
            $val = json_encode($val, JSON_UNESCAPED_UNICODE);
        }
        if (! is_string($val)) {
            $val = is_scalar($val) ? (string) $val : '';
        }
        if ($val === '') {
            return '';
        }

        // CPF: 999.999.999-99 ou 99999999999 -> ***.***.***-XX
        // CNPJ: 99.999.999/9999-99 ou 99999999999999 -> **.***.***/****-XX
        $val = (string) preg_replace_callback(
            '/(\d{3})\.?(\d{3})\.?(\d{3})-?(\d{2})/',
            fn ($m) => '***.***.***-' . $m[4],
            $val
        );
        $val = (string) preg_replace_callback(
            '/(\d{2})\.?(\d{3})\.?(\d{3})\/?(\d{4})-?(\d{2})/',
            fn ($m) => '**.***.***/****-' . $m[5],
            $val
        );

        return $val;
    }

    /**
     * Iniciais 2-letras do causer (avatar UI).
     */
    private function initials(?string $first, ?string $last): string
    {
        $f = $first !== null && $first !== '' ? mb_strtoupper(mb_substr($first, 0, 1)) : '';
        $l = $last !== null && $last !== '' ? mb_strtoupper(mb_substr($last, 0, 1)) : '';
        $out = $f . $l;
        return $out !== '' ? $out : '?';
    }

    /**
     * Localiza Contact com escopo multi-tenant Tier 0 ADR 0093. Retorna Contact
     * em sucesso OU JsonResponse 404 em cross-tenant/inexistente.
     */
    private function locateContact(int $id): Contact|JsonResponse
    {
        $businessId = (int) request()->session()->get('user.business_id');

        try {
            return Contact::where('business_id', $businessId)
                ->where('id', $id)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente nao encontrado'], 404);
        }
    }

    /**
     * Permission gate -- LGPD Art. 18 leitura ampla: customer.view OU
     * supplier.view OU equivalente .view_own. NAO filtra ainda por
     * customer/supplier-only type aqui (qualquer .view qualifica) porque a
     * fonte de verdade pra cross-tenant ja foi locateContact().
     *
     * Retorna null em sucesso, JsonResponse 403 em falha.
     */
    private function ensureCanView(): ?JsonResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return response()->json(['message' => 'Nao autenticado'], 401);
        }

        $can = $user->can('customer.view')
            || $user->can('customer.view_own')
            || $user->can('supplier.view')
            || $user->can('supplier.view_own');

        if (! $can) {
            return response()->json(['message' => 'Sem permissao'], 403);
        }

        return null;
    }
}
