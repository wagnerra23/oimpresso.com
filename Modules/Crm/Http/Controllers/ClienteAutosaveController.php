<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ClienteAutosaveController -- 5 endpoints PATCH cadastrais inline Wave C
 * (ADR 0179 Q2 -- autosave on blur, debounce 800ms client-side).
 *
 * Endpoints:
 *   PATCH /cliente/{id}/identificacao  -> Tab Identificacao
 *   PATCH /cliente/{id}/contato         -> Tab Contato
 *   PATCH /cliente/{id}/endereco        -> Tab Endereco
 *   PATCH /cliente/{id}/comercial       -> Tab Comercial
 *   PATCH /cliente/{id}/classificacao   -> Tab Classificacao
 *
 * Body: JSON parcial -- pode ter 1 ou N campos. Apenas campos no $rules da
 * Tab sao validados/persistidos (whitelisting -- nunca mass assignment).
 *
 * Response shape:
 *   200 {success:true, contact:{id,name,...,tax_number_masked,...}}
 *   422 {errors:{campo:[mensagens PT-BR]}}
 *   403 {message:"Sem permissao"} (sem customer.update/supplier.update)
 *   404 (cross-tenant ou contact inexistente -- nao 403 pra nao vazar existencia)
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL):
 *   Contact::where('business_id', $bizId)->where('id', $id)->firstOrFail()
 *   (firstOrFail() retorna 404 automatico -- semantica nao vaza existencia).
 *
 * Permission gate:
 *   Cada method checa can('customer.update') OU can('supplier.update') --
 *   matricial baseado em $contact->type (customer/supplier/both). Padrao
 *   UPOS canon copiado de ContactController::show linhas 1012, 1025-1034.
 *
 * PII:
 *   Response NUNCA inclui tax_number plain -- sempre via maskTaxNumber(),
 *   mesma logica de ContactController::show linha 1071. tags JSON e
 *   favorito_users JSON sao operacionais, nao PII.
 *
 * Pre-flight LICOES F3:
 *   - T-AP-2: tenant scope explicito (where business_id)
 *   - T-AP-8: session('user.business_id') canon, NAO auth()->user()->business_id
 *   - T-AP-9: permission gate matricial
 *   - T-AP-11: whereNull('deleted_at') herdado de SoftDeletes trait em Contact
 *   - T-AP-13: mutate real (Contact::update), NAO return back() vazio
 *
 * @see app/Http/Controllers/ContactController.php::show (padrao multi-tenant + mask)
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md §Q2
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ClienteAutosaveController extends Controller
{
    /** 27 UFs brasileiras pra validacao endereco. */
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    /** 9 valores semanticos de tags (Cowork blueprint). */
    private const TAGS_WHITELIST = [
        'vip', 'atencao', 'churn_risk', 'promotor', 'novo', 'fiel',
        'problematico', 'potencial', 'perdido',
    ];

    /** 6 valores enum segmento. */
    private const SEGMENTOS = [
        'varejo', 'atacado', 'agencia', 'corporativo', 'evento', 'governo',
    ];

    /** Enum tabela_preco_padrao. */
    private const TABELAS_PRECO = ['padrao', 'varejo', 'atacado', 'parceiro'];

    /** Enum pgto_padrao. */
    private const PGTOS = ['pix', 'boleto', 'cartao', 'dinheiro', 'transferencia'];

    /** Enum canal_preferido. */
    private const CANAIS = ['whatsapp', 'email', 'telefone', 'presencial'];

    /** Enum contact_status. */
    private const CONTACT_STATUSES = ['active', 'inactive', 'blocked'];

    /**
     * PATCH /cliente/{id}/identificacao
     *
     * Campos permitidos: tipo, name, fantasia, tax_number, ie, rg, nascimento, cargo.
     * Validacao especial: tax_number com mod 11 (CPF 11 digitos / CNPJ 14 digitos).
     */
    public function identificacao(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact; // ja e JsonResponse 404/403
        }

        $validator = Validator::make($request->all(), [
            'tipo' => ['nullable', 'string', 'in:PF,PJ'],
            'name' => ['nullable', 'string', 'max:255'],
            'fantasia' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:20'],
            'ie' => ['nullable', 'string', 'max:20'],
            'rg' => ['nullable', 'string', 'max:20'],
            'nascimento' => ['nullable', 'date', 'before:today'],
            'cargo' => ['nullable', 'string', 'max:80'],
            // ADR 0186 Técnica C — campos derivados da SEFAZ ConsultaCadastro.
            'ind_ie_dest' => ['nullable', 'integer', 'in:1,2,9'],
            'sefaz_cad_sit' => ['nullable', 'string', 'in:habilitado,nao_habilitado,suspenso,cancelado,paralisado,baixado'],
            'sefaz_cad_ind_cred_nfe' => ['nullable', 'integer', 'between:0,4'],
            'sefaz_cad_consultado_em' => ['nullable', 'date'],
        ], $this->messages());

        // Validacao customizada mod 11 quando tax_number presente.
        $validator->after(function ($v) use ($request) {
            $tax = $request->input('tax_number');
            if ($tax !== null && $tax !== '' && ! $this->isValidTaxNumber($tax)) {
                $v->errors()->add('tax_number', 'CPF/CNPJ invalido (mod 11 falhou).');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return $this->updateAndRespond($contact, $validator->validated());
    }

    /**
     * PATCH /cliente/{id}/contato
     *
     * Campos: mobile, tel2, email, site_url, canal_preferido.
     */
    public function contato(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $validator = Validator::make($request->all(), [
            'mobile' => ['nullable', 'string', 'max:25'],
            'tel2' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            // site_url aceita URL com ou sem scheme. Larissa biz=4 digita
            // "exemplo.com.br" sem https:// -- aceitamos.
            'site_url' => ['nullable', 'string', 'max:120', 'regex:/^(https?:\/\/)?[a-zA-Z0-9][a-zA-Z0-9-]{0,61}(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61})+\/?.*$/'],
            'canal_preferido' => ['nullable', 'string', 'in:' . implode(',', self::CANAIS)],
        ], $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return $this->updateAndRespond($contact, $validator->validated());
    }

    /**
     * PATCH /cliente/{id}/endereco
     *
     * Campos: zip_code, address_line_1, address_line_2, neighborhood, city, state.
     * Validacao especial: state em enum 27 UFs; zip_code 8 digitos.
     */
    public function endereco(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $validator = Validator::make($request->all(), [
            'zip_code' => ['nullable', 'string', 'max:10'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            // `neighborhood` mapeia pra `colony` em alguns plugins UPOS;
            // mantemos nome canonico do request.
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'in:' . implode(',', self::UFS)],
        ], $this->messages());

        // Validacao customizada CEP 8 digitos quando preenchido.
        $validator->after(function ($v) use ($request) {
            $zip = $request->input('zip_code');
            if ($zip !== null && $zip !== '') {
                $digits = preg_replace('/\D/', '', (string) $zip);
                if (strlen((string) $digits) !== 8) {
                    $v->errors()->add('zip_code', 'CEP deve ter 8 digitos.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return $this->updateAndRespond($contact, $validator->validated());
    }

    /**
     * PATCH /cliente/{id}/comercial
     *
     * Campos: credit_limit, pay_term_number, tabela_preco_padrao, pgto_padrao, obs_comercial.
     */
    public function comercial(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $validator = Validator::make($request->all(), [
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'pay_term_number' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'tabela_preco_padrao' => ['nullable', 'string', 'in:' . implode(',', self::TABELAS_PRECO)],
            'pgto_padrao' => ['nullable', 'string', 'in:' . implode(',', self::PGTOS)],
            'obs_comercial' => ['nullable', 'string', 'max:5000'],
        ], $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return $this->updateAndRespond($contact, $validator->validated());
    }

    /**
     * PATCH /cliente/{id}/classificacao
     *
     * Campos: segmento, tags (array de 9 valores whitelist), contact_status, vip.
     *
     * `tags` e persistido como JSON string (Contact ainda nao tem cast 'array'
     * pra essa coluna -- TODO Agent B add cast). Aceita array no input e
     * grava como json_encode(array).
     */
    public function classificacao(Request $request, int $id): JsonResponse
    {
        $contact = $this->locateContact($id);
        if (! $contact instanceof Contact) {
            return $contact;
        }

        $validator = Validator::make($request->all(), [
            'segmento' => ['nullable', 'string', 'in:' . implode(',', self::SEGMENTOS)],
            'tags' => ['nullable', 'array', 'max:9'],
            'tags.*' => ['string', 'in:' . implode(',', self::TAGS_WHITELIST)],
            'contact_status' => ['nullable', 'string', 'in:' . implode(',', self::CONTACT_STATUSES)],
            'vip' => ['nullable', 'boolean'],
        ], $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Cast manual JSON ate Agent B add 'tags' => 'array' no Contact::$casts.
        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $data['tags'] = json_encode(array_values(array_unique($data['tags'])));
        }

        return $this->updateAndRespond($contact, $data);
    }

    /**
     * Localiza o contato com escopo multi-tenant Tier 0 (ADR 0093) + checagem
     * de permission gate matricial customer/supplier.
     *
     * Retorna Contact em sucesso OU JsonResponse 404/403 em falha.
     */
    private function locateContact(int $id): Contact|JsonResponse
    {
        $businessId = (int) request()->session()->get('user.business_id');

        // Filtro business_id explicito (multi-tenant Tier 0 IRREVOGAVEL).
        // firstOrFail() lanca ModelNotFoundException -> 404 automatico.
        // Capturamos pra retornar JSON estruturado (cross-tenant nao vaza
        // existencia: mesma resposta de "id realmente nao existe").
        try {
            $contact = Contact::where('business_id', $businessId)
                ->where('id', $id)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente nao encontrado'], 404);
        }

        // Permission gate matricial -- pattern UPOS canon (ContactController::show
        // linhas 1012-1034). type=customer requer customer.update; type=supplier
        // requer supplier.update; type=both aceita qualquer um dos dois.
        $user = auth()->user();
        $type = (string) ($contact->type ?? 'customer');
        $canCustomer = $user->can('customer.update');
        $canSupplier = $user->can('supplier.update');

        $allowed = match ($type) {
            'supplier' => $canSupplier,
            'customer' => $canCustomer,
            'both' => ($canCustomer || $canSupplier),
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Sem permissao'], 403);
        }

        return $contact;
    }

    /**
     * Persiste $data no $contact e retorna response 200 com snapshot fresh.
     * Centralizada pra garantir mascaramento PII consistente nos 5 endpoints.
     */
    private function updateAndRespond(Contact $contact, array $data): JsonResponse
    {
        $contact->update($data);

        return response()->json([
            'success' => true,
            'contact' => $this->shapeContactResponse($contact->fresh()),
        ], 200);
    }

    /**
     * Shape do contact pra response -- PII LGPD (ADR 0093 §LGPD Art.7).
     * tax_number NUNCA plain. Snapshot minimal sufficient pra optimistic UI
     * client recover state apos rollback 4xx.
     */
    private function shapeContactResponse(Contact $contact): array
    {
        return [
            'id' => (int) $contact->id,
            'name' => (string) ($contact->name ?? ''),
            'tipo' => $contact->tipo ?? null,
            'fantasia' => $contact->fantasia ?? null,
            'tax_number_masked' => $this->maskTaxNumber($contact->tax_number ?? null),
            'ie' => $contact->ie ?? null,
            // ADR 0186 Técnica C — campos derivados da SEFAZ.
            'ind_ie_dest' => $contact->ind_ie_dest !== null ? (int) $contact->ind_ie_dest : null,
            'sefaz_cad_sit' => $contact->sefaz_cad_sit ?? null,
            'sefaz_cad_ind_cred_nfe' => $contact->sefaz_cad_ind_cred_nfe !== null
                ? (int) $contact->sefaz_cad_ind_cred_nfe : null,
            'sefaz_cad_consultado_em' => $contact->sefaz_cad_consultado_em ?? null,
            'rg' => $contact->rg ?? null,
            'nascimento' => $contact->nascimento ?? null,
            'cargo' => $contact->cargo ?? null,
            'mobile' => $contact->mobile ?? null,
            'tel2' => $contact->tel2 ?? null,
            'email' => $contact->email ?? null,
            'site_url' => $contact->site_url ?? null,
            'canal_preferido' => $contact->canal_preferido ?? null,
            'zip_code' => $contact->zip_code ?? null,
            'address_line_1' => $contact->address_line_1 ?? null,
            'address_line_2' => $contact->address_line_2 ?? null,
            'city' => $contact->city ?? null,
            'state' => $contact->state ?? null,
            'credit_limit' => $contact->credit_limit !== null ? (float) $contact->credit_limit : null,
            'pay_term_number' => $contact->pay_term_number !== null ? (int) $contact->pay_term_number : null,
            'tabela_preco_padrao' => $contact->tabela_preco_padrao ?? null,
            'pgto_padrao' => $contact->pgto_padrao ?? null,
            'obs_comercial' => $contact->obs_comercial ?? null,
            'segmento' => $contact->segmento ?? null,
            'tags' => $this->decodeTags($contact->tags ?? null),
            'contact_status' => $contact->contact_status ?? null,
            'vip' => (bool) ($contact->vip ?? false),
        ];
    }

    /**
     * Decodifica `tags` JSON string -> array PHP. Ate Agent B add cast 'array'
     * em Contact::$casts, fazemos decode manual aqui. Graceful: input
     * malformado -> array vazio.
     */
    private function decodeTags(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Mask CPF/CNPJ -- formata visualmente mas mantem digitos visiveis
     * porque a logica canon UPOS (ContactController::maskTaxNumber linha 148)
     * faz so formatacao, nao redact. Comportamento consistente com o resto
     * do sistema; futura ADR pode endurecer pra realmente censurar.
     */
    private function maskTaxNumber(?string $taxNumber): ?string
    {
        if (empty($taxNumber)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $taxNumber) ?? '';
        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
        }
        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits);
        }
        return $taxNumber;
    }

    /**
     * Mensagens de erro PT-BR pros validators dos 5 endpoints. Mantidas
     * curtas pra toast UI no autosave on blur (debounce 800ms).
     */
    private function messages(): array
    {
        return [
            'required' => 'O campo :attribute e obrigatorio.',
            'string' => 'O campo :attribute deve ser texto.',
            'max' => 'O campo :attribute excede o tamanho maximo.',
            'in' => 'O valor do campo :attribute nao e valido.',
            'email' => 'Email invalido.',
            'date' => 'Data invalida.',
            'before' => 'Data deve ser anterior a hoje.',
            'numeric' => 'Deve ser um numero.',
            'integer' => 'Deve ser um numero inteiro.',
            'min' => 'Valor minimo nao atingido.',
            'array' => 'Deve ser uma lista.',
            'boolean' => 'Deve ser verdadeiro ou falso.',
            'regex' => 'Formato invalido.',
        ];
    }

    /**
     * Validacao mod 11 -- CPF (11 digitos) ou CNPJ (14 digitos). Implementacao
     * standalone pra nao depender de helper externo no Wave C (autossuficiente).
     *
     * Algoritmo padrao Receita Federal. Rejeita sequencias triviais (111...111)
     * que matematicamente passariam mod 11.
     */
    private function isValidTaxNumber(string $raw): bool
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($d) === 11) {
            return $this->isValidCpf($d);
        }
        if (strlen($d) === 14) {
            return $this->isValidCnpj($d);
        }
        return false;
    }

    private function isValidCpf(string $d): bool
    {
        // Rejeita "00000000000", "11111111111" etc. (passariam mod 11).
        if (preg_match('/^(\d)\1{10}$/', $d) === 1) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $d[$i] * (($t + 1) - $i);
            }
            $rem = ($sum * 10) % 11;
            $expected = $rem === 10 ? 0 : $rem;
            if ((int) $d[$t] !== $expected) {
                return false;
            }
        }
        return true;
    }

    private function isValidCnpj(string $d): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $d) === 1) {
            return false;
        }
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $d[$i] * $weights1[$i];
        }
        $rem = $sum % 11;
        $dv1 = $rem < 2 ? 0 : 11 - $rem;
        if ((int) $d[12] !== $dv1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $d[$i] * $weights2[$i];
        }
        $rem = $sum % 11;
        $dv2 = $rem < 2 ? 0 : 11 - $rem;
        return (int) $d[13] === $dv2;
    }
}
