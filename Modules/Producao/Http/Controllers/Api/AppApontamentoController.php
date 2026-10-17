<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\Services\AppLojas\EtiquetaProducao;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;

/**
 * Apontamento de produção pelo app das lojas (P8, tela 02), com token.
 *
 * A regra é toda do `Modules\Producao\Services\ApontamentoOperadorService` (ADR 0431: o PCP é a
 * camada única de apontamento; decisão [W] 2026-10-15). Esta camada só:
 *   - autentica e confere permissão (módulo Produção no pacote + `producao.access`);
 *   - lê o QR da etiqueta (`EtiquetaProducao`) e confere o ajuste `app_apontamento_exige_qr`;
 *   - passa o `business_id` do usuário do token ao Service — nunca a sessão, que não existe aqui;
 *   - traduz `ApontamentoRecusado->codigo` em HTTP `{erro, mensagem}`.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md.
 *
 * Mora no módulo (não em app/Http/Controllers/Api/App/) porque depende do Modules\Producao:
 * a seta vai módulo → núcleo (DependencyDirectionTest), como o AppAtendimentoController do Whatsapp.
 */
class AppApontamentoController extends Controller
{
    /** Tipo de OS que o QR atual aponta (formato sem `os_tipo`; a thread 05 decide o formato com tipo). */
    public const OS_TIPO = 'comvis_os';

    /** codigo da recusa → [status HTTP, erro, campo de validação] */
    private const RECUSAS = [
        ApontamentoRecusado::NAO_ENCONTRADO => [404, 'nao_encontrado', null],
        ApontamentoRecusado::ETAPA_ALHEIA => [409, 'etapa_alheia', null],
        ApontamentoRecusado::PESSOA_OCUPADA => [409, 'em_andamento', null],
        ApontamentoRecusado::OS_NAO_LIBERADA => [409, 'os_nao_liberada', null],
        ApontamentoRecusado::ETAPA_ENCERRADA => [422, 'bloqueado', null],
        ApontamentoRecusado::APONTAMENTO_ENCERRADO => [422, 'bloqueado', null],
        ApontamentoRecusado::IDEMPOTENCIA_CONFLITO => [422, 'idempotencia_conflito', null],
        ApontamentoRecusado::MOMENTO_INVALIDO => [422, 'validacao', 'momento'],
        ApontamentoRecusado::MOTIVO_OBRIGATORIO => [422, 'validacao', 'motivo_id'],
        ApontamentoRecusado::JUSTIFICATIVA_OBRIGATORIA => [422, 'validacao', 'justificativa'],
    ];

    public function __construct(
        private ModuleUtil $moduleUtil,
        private ApontamentoOperadorService $apontamento,
    ) {
    }

    /** GET /api/app/producao/apontamentos/em-andamento — E04 A1: pessoa + máquinas + linha do ponto. */
    public function emAndamento(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $r = $this->apontamento->rodandoAgora($bizId, (int) $user->id, now());
        $ponto = $r['ponto'] ?? null;
        // A pausa sai daqui (botão do apontamento aberto): os motivos do posto vão junto, uma leitura só.
        $motivos = ($r['pessoa'] || $r['maquinas']->isNotEmpty())
            ? $this->apontamento->motivosDePausa($bizId)->groupBy('posto_id')
            : collect();

        return response()->json([
            'pessoa' => $r['pessoa'] ? $this->aberto($bizId, $r['pessoa'], $motivos) : null,
            'maquinas' => $r['maquinas']->map(fn (Apontamento $a) => $this->aberto($bizId, $a, $motivos))->values(),
            'ponto' => $ponto === null ? null : [
                'entrada' => $ponto['entrada']?->toIso8601String(),
                'saida' => $ponto['saida']?->toIso8601String(),
                'presenca_minutos' => $ponto['presenca_minutos'],
            ],
        ]);
    }

    /**
     * GET /api/app/producao/motivos-pausa?posto_id= — motivos de pausa ativos (UC-PCV-33), na ordem do
     * gestor. É a lista que o `pausar` aceita; o app guarda por posto para pausar sem internet.
     * Posto de outra empresa ou inexistente: lista vazia (nada a mostrar, nada revelado).
     */
    public function motivosPausa(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }

        $v = Validator::make($request->query(), ['posto_id' => ['nullable', 'integer', 'min:1']],
            ['posto_id.integer' => 'O posto é um número.']);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $postoId = $request->query('posto_id');

        return response()->json([
            'motivos' => $this->apontamento->motivosDePausa((int) $user->business_id, $postoId === null ? null : (int) $postoId),
        ]);
    }

    /** GET /api/app/producao/etiqueta/{codigo} — QR `oimpresso:p:<hash>:<os_tipo>:<os_id>` (ou o antigo sem tipo) ou número digitado. */
    public function etiqueta(Request $request, string $codigo): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $osId = $this->osDoCodigo($bizId, trim(rawurldecode($codigo)), true);
        if ($osId instanceof JsonResponse) {
            return $osId;
        }

        return $this->recusavel(function () use ($bizId, $user, $osId) {
            $r = $this->apontamento->resolverQr($bizId, (int) $user->id, self::OS_TIPO, $osId);

            return response()->json([
                'pedido' => $this->pedido($bizId, $osId),
                'etapa' => $this->etapa($bizId, $r['programacao']),
                'pode_apontar' => $r['pode_apontar'],
            ]);
        });
    }

    /** POST /api/app/producao/apontamentos/iniciar `{ programacao_id, momento?, codigo? }` + Idempotency-Key */
    public function iniciar(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $exigeQr = AjustesDaEmpresa::valor($bizId, 'app_apontamento_exige_qr') === true;

        $v = $this->validar($request, [
            'programacao_id' => ['required', 'integer', 'min:1'],
            'codigo' => [$exigeQr ? 'required' : 'nullable', 'string', 'max:120'],
        ], [
            'programacao_id.required' => 'Informe a etapa.',
            'codigo.required' => 'Leia o QR da etiqueta: esta empresa não aceita número digitado.',
        ]);
        if ($v instanceof JsonResponse) {
            return $v;
        }
        $programacaoId = (int) $v['programacao_id'];

        return $this->recusavel(function () use ($bizId, $user, $exigeQr, $v, $programacaoId) {
            if ($exigeQr) {
                // O QR tem de ser desta empresa e da OS desta etapa; senão, o mesmo 404 de "não existe".
                $osId = $this->osDoCodigo($bizId, (string) $v['codigo'], false);
                if ($osId instanceof JsonResponse) {
                    return $osId;
                }
                $this->apontamento->resolverQr($bizId, (int) $user->id, self::OS_TIPO, $osId, $programacaoId);
            }

            // Origem `qr` também para o número digitado: `manual` é o lançamento do gestor, com justificativa.
            $ap = $this->apontamento->iniciar($bizId, (int) $user->id, $programacaoId, $v['chave'], $v['momento'], 'qr');

            return response()->json(
                ['id' => (int) $ap->id, 'programacao_id' => (int) $ap->programacao_id, 'iniciado_em' => $ap->momento->toIso8601String()],
                $ap->wasRecentlyCreated ? 201 : 200
            );
        });
    }

    /** POST /api/app/producao/apontamentos/{id}/pausar `{ motivo_id, momento? }` + Idempotency-Key */
    public function pausar(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $v = $this->validar($request, ['motivo_id' => ['required', 'integer', 'min:1']], [
            'motivo_id.required' => 'Escolha o motivo da pausa.',
        ]);
        if ($v instanceof JsonResponse) {
            return $v;
        }

        return $this->recusavel(function () use ($bizId, $user, $id, $v) {
            $p = $this->apontamento->pausar($bizId, (int) $user->id, $id, (int) $v['motivo_id'], $v['chave'], $v['momento']);

            return response()->json($this->encerramento($bizId, $id, $p), $p->wasRecentlyCreated ? 201 : 200);
        });
    }

    /** POST /api/app/producao/apontamentos/{id}/concluir `{ momento? }` + Idempotency-Key */
    public function concluir(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $v = $this->validar($request, [], []);
        if ($v instanceof JsonResponse) {
            return $v;
        }

        return $this->recusavel(function () use ($bizId, $user, $id, $v) {
            $f = $this->apontamento->concluir($bizId, (int) $user->id, $id, $v['chave'], $v['momento']);

            return response()->json($this->encerramento($bizId, $id, $f), $f->wasRecentlyCreated ? 201 : 200);
        });
    }

    /** POST /api/app/producao/apontamentos/{id}/corrigir `{ momento, justificativa }` + Idempotency-Key — append-only. */
    public function corrigir(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $v = $this->validar($request, [
            'momento' => ['required', 'string', 'max:40'],
            'justificativa' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'momento.required' => 'Informe a hora certa.',
            'justificativa.required' => 'Diga por que o horário está errado.',
            'justificativa.min' => 'A justificativa precisa de pelo menos 5 letras.',
        ]);
        if ($v instanceof JsonResponse) {
            return $v;
        }
        $justificativa = trim((string) $v['justificativa']);

        return $this->recusavel(function () use ($bizId, $user, $id, $v, $justificativa) {
            // Reenvio da fila com a mesma chave e o mesmo corpo: o Service devolve o pedido já gravado.
            $e = $this->apontamento->lancarCorrecao($bizId, (int) $user->id, $id, $v['momento'], $justificativa, $v['chave']);

            return response()->json(['id' => (int) $e->id, 'apontamento_id' => $id, 'situacao' => 'aguardando_gestor'],
                $e->wasRecentlyCreated ? 201 : 200);
        });
    }

    // ------------------------------------------------------------------

    /**
     * Idempotency-Key (≤ 64, a `chave_idempotencia` do PCP) + `momento` do toque (fila offline,
     * decisão [W] 2026-10-15). Sem `momento`, vale a hora do servidor.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function validar(Request $request, array $regras, array $mensagens): array|JsonResponse
    {
        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), $regras + [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'momento' => ['nullable', 'string', 'max:40'],
        ], $mensagens + [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'idempotency_key.max' => 'A Idempotency-Key tem no máximo 64 caracteres.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $d = $v->validated();

        $momento = now();
        if (! empty($d['momento'])) {
            // ISO 8601 com fuso, como o app guarda na fila (ex.: 2026-10-15T14:03:00-03:00).
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', (string) $d['momento'])) {
                return $this->invalido(['momento' => 'Use data e hora ISO 8601 com fuso (ex.: 2026-10-15T14:03:00-03:00).']);
            }
            $momento = Carbon::parse((string) $d['momento']);
        }
        $d['momento'] = $momento;
        $d['chave'] = $chave;

        return $d;
    }

    /** Traduz `ApontamentoRecusado` para HTTP; erro inesperado vira 500 sem detalhe. */
    private function recusavel(\Closure $f): JsonResponse
    {
        try {
            return $f();
        } catch (ApontamentoRecusado $e) {
            [$status, $erro, $campo] = self::RECUSAS[$e->codigo] ?? [422, 'bloqueado', null];
            if ($campo !== null) {
                return $this->invalido([$campo => $e->getMessage()]);
            }
            $corpo = ['erro' => $erro, 'mensagem' => $e->getMessage()];
            if ($e->codigo === ApontamentoRecusado::ETAPA_ALHEIA) {
                $corpo['etapa'] = $this->etapaAlheia($e->contexto);
            }
            if ($e->codigo === ApontamentoRecusado::PESSOA_OCUPADA && ! empty($e->contexto['apontamento_id'])) {
                $corpo['apontamento_id'] = (int) $e->contexto['apontamento_id'];
            }

            return response()->json($corpo, $status);
        } catch (\Throwable $e) {
            Log::error('API app producao: ' . $e->getMessage());

            return $this->erro(500, 'falha', 'Não foi possível registrar o apontamento.');
        }
    }

    /**
     * Código da etiqueta → id da OS na empresa de quem lê. QR de outra empresa: 404 sem consultar.
     * Número digitado (o id ou o número `OS-2026-00012`) só quando a empresa não exige o QR.
     */
    private function osDoCodigo(int $bizId, string $codigo, bool $aceitaNumero): int|JsonResponse
    {
        $qr = EtiquetaProducao::ler($codigo, $bizId);
        if ($qr !== null) {
            // Outra empresa, ou OS de outro tipo (o app só aponta OS de comunicação visual): 404 sem consultar.
            return $qr['empresa'] && $qr['os_tipo'] === self::OS_TIPO ? $qr['pedido_id'] : $this->erro(404, 'nao_encontrado', 'Etiqueta não encontrada.');
        }
        if (! $aceitaNumero || AjustesDaEmpresa::valor($bizId, 'app_apontamento_exige_qr') === true) {
            return $this->invalido(['codigo' => 'Leia o QR da etiqueta: esta empresa não aceita número digitado.']);
        }

        $q = DB::table('comvis_os')->where('business_id', $bizId)->whereNull('deleted_at');
        if (preg_match('/^[1-9][0-9]{0,18}$/', $codigo)) {
            $id = $q->where('id', (int) $codigo)->value('id');
        } else {
            $id = ($codigo === '' || mb_strlen($codigo) > 20) ? null : $q->where('numero', mb_strtoupper($codigo))->value('id');
        }

        return $id === null ? $this->erro(404, 'nao_encontrado', 'Etiqueta não encontrada.') : (int) $id;
    }

    /** @return array{id:int, numero:?string, resumo:?string, cliente:?string} */
    private function pedido(int $bizId, int $osId): array
    {
        $os = DB::table('comvis_os')->where('business_id', $bizId)->where('id', $osId)->whereNull('deleted_at')
            ->first(['id', 'numero', 'orcamento_id']);
        $resumo = null;
        $cliente = null;
        if ($os !== null && $os->orcamento_id !== null) {
            $descricoes = DB::table('comvis_orcamento_itens')->where('business_id', $bizId)
                ->where('orcamento_id', (int) $os->orcamento_id)->orderBy('ordem')->orderBy('id')
                ->limit(3)->pluck('descricao')->filter()->all();
            $resumo = $descricoes === [] ? null : implode(' + ', $descricoes);
            $cliente = DB::table('comvis_orcamentos as o')
                ->join('contacts as c', fn ($j) => $j->on('c.id', '=', 'o.contato_id')->on('c.business_id', '=', 'o.business_id'))
                ->where('o.business_id', $bizId)->where('o.id', (int) $os->orcamento_id)->value('c.name');
        }

        return ['id' => $osId, 'numero' => $os?->numero, 'resumo' => $resumo, 'cliente' => $cliente];
    }

    /** Etapa = linha de `pcp_programacoes` (OS × posto × responsável). */
    private function etapa(int $bizId, Programacao $p): array
    {
        $posto = DB::table('pcp_postos')->where('business_id', $bizId)->where('id', (int) $p->posto_id)->first(['id', 'nome', 'tipo_custo']);

        return [
            'programacao_id' => (int) $p->id,
            'status' => (string) $p->status,
            'posto' => $posto === null ? null : ['id' => (int) $posto->id, 'nome' => (string) $posto->nome, 'tipo' => (string) $posto->tipo_custo],
            'inicio_previsto' => $p->inicio_previsto?->toIso8601String(),
            'responsavel' => $this->pessoa($bizId, $p->user_id),
        ];
    }

    /** Etapa alheia: onde está e com quem, a partir do contexto da recusa (nada foi gravado). */
    private function etapaAlheia(array $ctx): ?array
    {
        $bizId = (int) request()->user()->business_id;
        $p = Programacao::query()->where('business_id', $bizId)->find((int) ($ctx['programacao_id'] ?? 0));

        return $p === null ? null : $this->etapa($bizId, $p);
    }

    private function pessoa(int $bizId, ?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }
        $u = DB::table('users')->where('business_id', $bizId)->where('id', $userId)->first(['id', 'first_name', 'last_name']);

        return $u === null ? null : ['id' => (int) $u->id, 'nome' => trim($u->first_name . ' ' . ($u->last_name ?? ''))];
    }

    /** @param Collection $motivos motivos de pausa ativos agrupados por posto_id */
    private function aberto(int $bizId, Apontamento $a, Collection $motivos): array
    {
        $p = $a->programacao_id ? Programacao::query()->where('business_id', $bizId)->find((int) $a->programacao_id) : null;

        return [
            'id' => (int) $a->id,
            'tipo' => (string) $a->tipo_custo,
            'pedido' => $a->os_id ? $this->pedido($bizId, (int) $a->os_id) : null,
            'etapa' => $p === null ? null : $this->etapa($bizId, $p),
            'iniciado_em' => $a->momento->toIso8601String(),
            'motivos_pausa' => $motivos->get((int) $a->posto_id, collect())
                ->map(fn (array $m) => ['id' => $m['id'], 'nome' => $m['nome']])->values(),
        ];
    }

    /** Pausa ou fim: minutos = momento do encerramento − momento do início (o tempo que vale é o do servidor). */
    private function encerramento(int $bizId, int $inicioId, Apontamento $fim): array
    {
        $inicio = Apontamento::query()->where('business_id', $bizId)->find((int) ($fim->inicio_id ?? $inicioId));
        $minutos = $inicio === null ? null : intdiv(max(0, $fim->momento->getTimestamp() - $inicio->momento->getTimestamp()), 60);

        return ['id' => $inicioId, 'encerrado_por' => (string) $fim->tipo, 'origem' => (string) $fim->origem,
            'encerrado_em' => $fim->momento->toIso8601String(), 'minutos' => $minutos];
    }

    private function podeApontar(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->can('superadmin')) {
            return (bool) $this->moduleUtil->isModuleInstalled('Producao');
        }

        return (bool) $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'producao_module', 'superadmin_package')
            && $user->can('producao.access');
    }

    private function semPermissao(): JsonResponse
    {
        return $this->erro(403, 'sem_permissao', 'Seu usuário não pode apontar produção.');
    }

    private function erro(int $status, string $erro, string $mensagem): JsonResponse
    {
        return response()->json(['erro' => $erro, 'mensagem' => $mensagem], $status);
    }

    /** @param array<string,string> $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'mensagem' => 'Confira os campos.', 'campos' => $campos], 422);
    }
}
