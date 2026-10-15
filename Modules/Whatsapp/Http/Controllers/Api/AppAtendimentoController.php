<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use App\Contracts\Atendimento\AtendimentoDoApp;
use App\Http\Controllers\Controller;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\User;
use App\Utils\ModuleUtil;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;

/**
 * Atendimento WhatsApp do app das lojas (tela 41, P5). Contrato:
 * memory/requisitos/AppMobile/api/tela-41-atendimento.md.
 *
 * Por cima da Caixa Unificada da web (Modules/Whatsapp, schema omnichannel ADR 0135):
 *  - Acesso: as permissões das rotas web (`whatsapp.access` para ler, `whatsapp.send` para enviar)
 *    com o módulo no plano da empresa.
 *  - Canal = fila (US-WA-069): o usuário vê os canais com acesso ATIVO em `channel_user_access`
 *    (`revoked_at` nulo), ou todos com `whatsapp.view-all-phones` — a regra da
 *    CaixaUnificadaController. O `InboxQueryService::visibleChannelIdsForUser` NÃO é usado: ele
 *    filtra `channels.is_active` (coluna que a tabela não tem) e ignora acesso revogado.
 *  - Envio: o despacho ao daemon é o `EnvioDeTextoNoCanal`, o mesmo da web (`InboxController::send`).
 *
 * LGPD (Art. 7º, I): envio ativo — fora da janela de 24 h aberta pelo cliente — só com
 * `contacts.whatsapp_consent = true`. Responder dentro da janela é permitido. Não é ajuste da empresa.
 *
 * Tier 0 (ADR 0093): business_id do usuário do token, explícito em toda consulta (sem sessão, o
 * escopo global ScopeByBusiness não filtra). Conversa de outra empresa ou de canal sem acesso → 404.
 *
 * Mora no módulo (não em app/) porque depende das entidades do Whatsapp: a seta vai módulo → núcleo
 * (DependencyDirectionTest). O Início do app pergunta a área pelo contrato AtendimentoDoApp, que este
 * controller implementa e o WhatsappServiceProvider registra.
 */
class AppAtendimentoController extends Controller implements AtendimentoDoApp
{
    private const POR_PAGINA = 30;

    private const MENSAGENS = 100;

    /** Tipos de canal que o atendimento do app mostra (WhatsApp). */
    private const TIPOS_WHATSAPP = [
        Channel::TYPE_WHATSAPP_META,
        Channel::TYPE_WHATSAPP_ZAPI,
        Channel::TYPE_WHATSAPP_BAILEYS,
        Channel::TYPE_WHATSAPP_WHATSMEOW,
    ];

    public const ATALHOS = ['orcamento', 'status_pedido', 'link_arte'];

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** A regra da área `atendimento` e das rotas de leitura (permissão + módulo no plano). */
    public function podeVerAtendimento(User $user): bool
    {
        if (! class_exists(Conversation::class)) {
            return false;
        }
        if ($user->can('superadmin')) {
            return true;
        }

        return $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'whatsapp_module')
            && $user->can('whatsapp.access');
    }

    /** Área `atendimento` em `areas` (§6): acesso + canal WhatsApp ligado. */
    public function areaVisivel(User $user): bool
    {
        return $this->podeVerAtendimento($user) && $this->canalLigado($user) !== null;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerAtendimento($user)) {
            return $this->semPermissao('Seu usuário não tem acesso ao atendimento.');
        }
        $bizId = (int) $user->business_id;
        $canal = $this->canalLigado($user);
        $pagina = max(1, (int) $request->query('pagina', 1));

        if ($canal === null) {
            return response()->json([
                'canal' => ['ligado' => false, 'numero' => null],
                'conversas' => [], 'pagina' => $pagina, 'tem_mais' => false,
            ]);
        }

        $linhas = $this->conversasVisiveis($user)
            ->whereIn('cv.status', [Conversation::STATUS_OPEN, Conversation::STATUS_AWAITING_HUMAN])
            ->orderByDesc('cv.last_message_at')
            ->orderByDesc('cv.id')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([
                'cv.id', 'cv.contact_id', 'cv.contact_name', 'cv.customer_external_id', 'cv.unread_count',
                'cv.last_message_preview', 'cv.last_message_at', 'cv.last_inbound_at',
                'c.name as contato_nome', 'c.whatsapp_consent',
            ]);

        return response()->json([
            'canal' => ['ligado' => true, 'numero' => $canal->display_identifier],
            'conversas' => $linhas->take(self::POR_PAGINA)->map(fn ($l) => $this->item($l))->values(),
            'pagina' => $pagina,
            'tem_mais' => $linhas->count() > self::POR_PAGINA,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeVerAtendimento($user)) {
            return $this->semPermissao('Seu usuário não tem acesso ao atendimento.');
        }
        $l = $this->linhaConversa($user, $id);
        if ($l === null) {
            return $this->naoEncontrada();
        }

        // Notas internas são da equipe: nunca vão ao app (mesma regra do cliente da conversa).
        $mensagens = Message::query()
            ->where('business_id', (int) $user->business_id)
            ->where('conversation_id', $id)
            ->where('is_internal_note', false)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::MENSAGENS)
            ->get()
            ->reverse()
            ->map(fn (Message $m) => $this->mensagem($m))
            ->values();

        return response()->json(['conversa' => $this->item($l), 'mensagens' => $mensagens]);
    }

    /** POST /api/app/atendimento/{id}/mensagens — texto livre. `Idempotency-Key` obrigatória. */
    public function enviar(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeEnviar($user)) {
            return $this->semPermissao('Seu usuário não pode enviar mensagens.');
        }
        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'texto' => ['required', 'string', 'max:4096'],
            'modelo' => ['nullable', 'prohibited'],
            'anexo' => ['nullable', 'prohibited'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'texto.required' => 'Escreva a mensagem.',
            'texto.max' => 'A mensagem passou de 4.096 caracteres.',
            'modelo.prohibited' => 'Envio por modelo aprovado ainda não está no app.',
            'anexo.prohibited' => 'Anexo ainda não está no app.',
        ]);
        if ($v->fails()) {
            return $this->invalido($v);
        }
        $texto = trim((string) $request->input('texto'));

        return $this->enviarTexto($user, $id, $texto, 'atendimento_mensagem', $chave, hash('sha256', $texto));
    }

    /**
     * POST /api/app/atendimento/{id}/atalho — o ERP monta o texto (valor e link vêm dele; o app nunca
     * digita valor). O registro tem de ser do CLIENTE da conversa e da empresa do token: o atalho não
     * serve para mandar a um cliente o pedido de outro.
     */
    public function atalho(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->podeEnviar($user)) {
            return $this->semPermissao('Seu usuário não pode enviar mensagens.');
        }
        $bizId = (int) $user->business_id;
        if (AjustesDaEmpresa::valor($bizId, 'app_atendimento_atalhos') !== true) {
            return $this->semPermissao('Os atalhos do atendimento estão desligados nas configurações da empresa.');
        }
        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'in:' . implode(',', self::ATALHOS)],
            'ref_id' => ['required', 'integer', 'min:1'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'tipo.required' => 'Escolha o atalho.',
            'tipo.in' => 'Atalho não aceito: use orçamento, status do pedido ou link da arte.',
            'ref_id.required' => 'Escolha o registro.',
            'ref_id.integer' => 'Registro inválido.',
            'ref_id.min' => 'Registro inválido.',
        ]);
        if ($v->fails()) {
            return $this->invalido($v);
        }

        $l = $this->linhaConversa($user, $id);
        if ($l === null) {
            return $this->naoEncontrada();
        }
        if ($l->contact_id === null) {
            return response()->json(['erro' => 'validacao', 'campos' => [
                'ref_id' => 'Vincule a conversa a um cliente antes de usar o atalho.',
            ]], 422);
        }

        $tipo = (string) $request->input('tipo');
        $texto = $this->textoDoAtalho($bizId, (int) $l->contact_id, $tipo, (int) $request->input('ref_id'));
        if ($texto === null) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Registro não encontrado para este cliente.'], 404);
        }

        return $this->enviarTexto($user, $id, $texto, 'atendimento_atalho', $chave,
            hash('sha256', $tipo . '|' . (int) $request->input('ref_id')));
    }

    /**
     * Gates de canal, janela e consentimento → reserva da Idempotency-Key → Message `queued` →
     * despacho pelo Service da web. A mensagem real sai do servidor, então a reserva é gravada ANTES
     * (sem transação em volta do HTTP): a mesma chave nunca envia duas vezes.
     */
    private function enviarTexto(User $user, int $id, string $texto, string $rota, string $chave, string $hash): JsonResponse
    {
        $bizId = (int) $user->business_id;
        $conversa = $this->conversaVisivel($user, $id);
        if ($conversa === null) {
            return $this->naoEncontrada();
        }
        $canal = $conversa->channel_id === null ? null : Channel::query()
            ->where('business_id', $bizId)->where('id', $conversa->channel_id)->first();
        if ($canal === null || $canal->status !== 'active') {
            return response()->json(['erro' => 'sem_configuracao', 'mensagem' => 'O canal do WhatsApp está desligado.'], 503);
        }

        $janelaAberta = $this->janelaAte($conversa->last_inbound_at) !== null;
        if (! $janelaAberta) {
            $consentimento = $conversa->contact_id === null ? null : DB::table('contacts')
                ->where('business_id', $bizId)->where('id', $conversa->contact_id)->value('whatsapp_consent');
            if ((int) $consentimento !== 1) {
                return response()->json([
                    'erro' => 'sem_consentimento',
                    'mensagem' => 'Este cliente não autorizou mensagens no WhatsApp. Só dá para responder quando ele escrever.',
                ], 422);
            }
            if ($canal->type === Channel::TYPE_WHATSAPP_META) {
                return response()->json([
                    'erro' => 'janela_fechada',
                    'mensagem' => 'Passaram 24 h desde a última mensagem do cliente. Use um modelo aprovado pela web.',
                ], 422);
            }
        }

        $ja = DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', (int) $user->id)
            ->where('rota', $rota)->where('chave', $chave)->first();
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }
        try {
            $reservaId = DB::table('app_idempotencia')->insertGetId([
                'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => $rota, 'chave' => $chave,
                'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $ja = DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', (int) $user->id)
                ->where('rota', $rota)->where('chave', $chave)->first();

            return $ja !== null ? $this->repetida($ja, $hash) : $this->emAndamento();
        }

        // Sem o prefixo `*Nome:*` da web: o app grava `sender_user_id`, que é o que a métrica de
        // atendente usa quando o prefixo falta (InboxController::maybeAutoPrefixSenderName).
        $mensagem = Message::query()->create([
            'business_id' => $bizId,
            'conversation_id' => $conversa->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'provider' => $canal->type,
            'type' => 'text',
            'body' => $texto,
            'status' => Message::STATUS_QUEUED,
            'sender_user_id' => (int) $user->id,
            'sender_kind' => 'human',
            'is_internal_note' => false,
        ]);
        $conversa->forceFill(['last_outbound_at' => now(), 'last_message_at' => now()])->save();

        $r = app(EnvioDeTextoNoCanal::class)->despachar($mensagem, $conversa, $canal, $texto);
        $mensagem->refresh();

        if ($r['ok']) {
            $codigo = 201;
            $corpo = ['mensagem' => $this->mensagem($mensagem)];
        } elseif ($r['motivo'] === 'tipo_nao_suportado') {
            $codigo = 422;
            $corpo = ['erro' => 'bloqueado', 'mensagem' => 'Este canal ainda não envia pelo app. Responda pela web.'];
        } else {
            $codigo = 502;
            $corpo = ['erro' => 'falha_envio', 'mensagem' => 'O WhatsApp não aceitou a mensagem agora. Tente de novo.'];
        }
        DB::table('app_idempotencia')->where('id', $reservaId)->update([
            'resposta' => json_encode(['codigo' => $codigo, 'corpo' => $corpo]), 'updated_at' => now(),
        ]);

        return response()->json($corpo, $codigo);
    }

    private function textoDoAtalho(int $bizId, int $contatoId, string $tipo, int $refId): ?string
    {
        if ($tipo === 'orcamento') {
            $t = DB::table('transactions')->where('business_id', $bizId)->where('id', $refId)
                ->where('contact_id', $contatoId)->where('type', 'sell')
                ->where('status', 'draft')->where('sub_status', 'quotation')
                ->first(['invoice_no', 'final_total']);

            return $t === null ? null : sprintf('Orçamento #%s: total R$ %s.',
                $t->invoice_no, number_format((float) $t->final_total, 2, ',', '.'));
        }

        if ($tipo === 'status_pedido') {
            $t = DB::table('transactions as t')
                ->leftJoin('sale_process_stages as sps', 't.current_stage_id', '=', 'sps.id')
                ->where('t.business_id', $bizId)->where('t.id', $refId)->where('t.contact_id', $contatoId)
                ->where('t.type', 'sell')->where('t.status', 'final')
                ->first(['t.invoice_no', 'sps.name as etapa']);
            if ($t === null) {
                return null;
            }

            return $t->etapa === null
                ? sprintf('Seu pedido #%s está registrado.', $t->invoice_no)
                : sprintf('Seu pedido #%s está em %s.', $t->invoice_no, $t->etapa);
        }

        // link_arte: arte da ordem de produção da Comunicação Visual (US-COMVIS-NEW-004).
        if (! Schema::hasTable('cv_ordens_producao')) {
            return null;
        }
        $op = DB::table('cv_ordens_producao')->where('business_id', $bizId)->where('id', $refId)
            ->where('contato_id', $contatoId)->whereNotNull('arte_url')->first(['codigo', 'arte_url']);

        return $op === null ? null : sprintf('Arte do pedido %s: %s', $op->codigo, $op->arte_url);
    }

    /** 1º canal WhatsApp ativo que o usuário enxerga (o número que o app mostra). */
    private function canalLigado(User $user): ?Channel
    {
        return Channel::query()
            ->where('business_id', (int) $user->business_id)
            ->whereIn('type', self::TIPOS_WHATSAPP)
            ->where('status', 'active')
            ->whereIn('id', $this->idsDeCanaisVisiveis($user))
            ->orderBy('id')
            ->first();
    }

    /** Subconsulta dos canais visíveis (para `whereIn`). */
    private function idsDeCanaisVisiveis(User $user): \Illuminate\Database\Query\Builder
    {
        $bizId = (int) $user->business_id;
        if ($user->can('whatsapp.view-all-phones')) {
            return DB::table('channels')->where('business_id', $bizId)->select('id');
        }

        return DB::table('channel_user_access')->where('business_id', $bizId)
            ->where('user_id', (int) $user->id)->whereNull('revoked_at')->select('channel_id');
    }

    private function conversasVisiveis(User $user): \Illuminate\Database\Query\Builder
    {
        $bizId = (int) $user->business_id;

        return DB::table('conversations as cv')
            ->join('channels as ch', function ($j) use ($bizId) {
                $j->on('ch.id', '=', 'cv.channel_id')->where('ch.business_id', '=', $bizId);
            })
            ->leftJoin('contacts as c', function ($j) use ($bizId) {
                $j->on('c.id', '=', 'cv.contact_id')->where('c.business_id', '=', $bizId);
            })
            ->where('cv.business_id', $bizId)
            ->whereIn('ch.type', self::TIPOS_WHATSAPP)
            ->whereIn('cv.channel_id', $this->idsDeCanaisVisiveis($user));
    }

    private function linhaConversa(User $user, int $id): ?object
    {
        return $this->conversasVisiveis($user)->where('cv.id', $id)->first([
            'cv.id', 'cv.contact_id', 'cv.contact_name', 'cv.customer_external_id', 'cv.unread_count',
            'cv.last_message_preview', 'cv.last_message_at', 'cv.last_inbound_at',
            'c.name as contato_nome', 'c.whatsapp_consent',
        ]);
    }

    private function conversaVisivel(User $user, int $id): ?Conversation
    {
        return $this->linhaConversa($user, $id) === null ? null : Conversation::query()
            ->where('business_id', (int) $user->business_id)->where('id', $id)->first();
    }

    /** @return array<string, mixed> */
    private function item(object $l): array
    {
        return [
            'id' => (int) $l->id,
            'contato' => [
                'id' => $l->contact_id === null ? null : (int) $l->contact_id,
                'nome' => (string) ($l->contato_nome ?? $l->contact_name ?? $l->customer_external_id),
            ],
            'ultima' => $l->last_message_preview,
            'quando' => $this->iso($l->last_message_at),
            'nao_lidas' => (int) $l->unread_count,
            'consentimento' => (int) ($l->whatsapp_consent ?? 0) === 1,
            'janela_aberta_ate' => $this->janelaAte($l->last_inbound_at),
        ];
    }

    /** @return array<string, mixed> */
    private function mensagem(Message $m): array
    {
        $de = $m->direction === Message::DIRECTION_INBOUND ? 'cliente'
            : ($m->sender_kind === 'human' ? 'eu' : 'sistema');
        $midia = in_array($m->type, Message::MEDIA_TYPES, true)
            ? ['tipo' => $m->type, 'nome' => $m->media_filename]
            : null;

        return [
            'id' => (int) $m->id,
            'de' => $de,
            'texto' => $m->body,
            'midia' => $midia,
            'quando' => $this->iso($m->created_at),
            'status' => $m->status,
        ];
    }

    /** Fim da janela de 24 h aberta pela última mensagem do cliente; `null` se já fechou. */
    private function janelaAte(mixed $ultimaDoCliente): ?string
    {
        if ($ultimaDoCliente === null) {
            return null;
        }
        $fim = \Illuminate\Support\Carbon::parse($ultimaDoCliente)->addHours(24);

        return $fim->isFuture() ? $this->iso($fim) : null;
    }

    private function iso(mixed $quando): ?string
    {
        if ($quando === null) {
            return null;
        }
        $c = $quando instanceof CarbonInterface ? $quando : \Illuminate\Support\Carbon::parse($quando);

        return $c->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function podeEnviar(User $user): bool
    {
        return $this->podeVerAtendimento($user) && ($user->can('superadmin') || $user->can('whatsapp.send'));
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return response()->json([
                'erro' => 'idempotencia_conflito',
                'mensagem' => 'Esta chave já foi usada para outra mensagem. Gere uma chave nova.',
            ], 422);
        }
        if ($ja->resposta === null) {
            return $this->emAndamento();
        }
        $r = json_decode((string) $ja->resposta, true);

        return response()->json($r['corpo'], (int) $r['codigo'] === 201 ? 200 : (int) $r['codigo']);
    }

    private function emAndamento(): JsonResponse
    {
        return response()->json(['erro' => 'em_andamento', 'mensagem' => 'Esta mensagem ainda está sendo enviada.'], 409);
    }

    private function invalido(\Illuminate\Validation\Validator $v): JsonResponse
    {
        return response()->json([
            'erro' => 'validacao',
            'campos' => collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all(),
        ], 422);
    }

    private function naoEncontrada(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Conversa não encontrada.'], 404);
    }

    private function semPermissao(string $mensagem): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => $mensagem], 403);
    }
}
