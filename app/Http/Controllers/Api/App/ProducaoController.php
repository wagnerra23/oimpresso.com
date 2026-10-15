<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\Services\AppLojas\EtiquetaProducao;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Apontamento de produção pelo app das lojas (P8, tela 02) — com token, por cima do mesmo registro
 * do `Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController` (US-COMVIS-004): a tabela
 * `comvis_apontamentos`, um apontamento aberto por operador, sem pausa, append-only.
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-02-apontamento.md.
 *
 * Por que não chama o `ApontamentoTracker`: ele resolve a empresa pelo global scope das entidades,
 * que lê a SESSÃO — numa chamada com token a sessão não existe e o `Os::find($id)` acharia OS de
 * qualquer empresa. Aqui o business_id vem do usuário do token, explícito em toda consulta (Tier 0,
 * ADR 0093). As regras são as do tracker: duração calculada no servidor, cancelar fecha com
 * m² 0 e "[CANCELADO] <motivo>", nada é apagado nem reaberto.
 */
class ProducaoController extends Controller
{
    /** Etapas da OS em que não se aponta mais. */
    private const TERMINAIS = ['concluida', 'cancelada'];

    private const ROTULOS = [
        'arte' => 'Arte',
        'producao' => 'Produção',
        'finalizando' => 'Acabamento',
        'entrega' => 'Entrega',
        'instalacao' => 'Instalação',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ];

    public function __construct(private ModuleUtil $moduleUtil)
    {
    }

    /** GET /api/app/producao/apontamentos/em-andamento */
    public function emAndamento(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $ap = $this->aberto($bizId, (int) $user->id);

        return response()->json(['item' => $ap === null ? null : $this->item($bizId, $ap)]);
    }

    /** GET /api/app/producao/etiqueta/{codigo} — QR `oimpresso:p:<hash>:<id>` ou número digitado. */
    public function etiqueta(Request $request, string $codigo): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $codigo = trim(rawurldecode($codigo));

        $qr = EtiquetaProducao::ler($codigo, $bizId);
        if ($qr === null) {
            if (AjustesDaEmpresa::valor($bizId, 'app_apontamento_exige_qr') === true) {
                return $this->invalido(['codigo' => 'Leia o QR da etiqueta: esta empresa não aceita número digitado.']);
            }
            $os = $this->osPorNumero($bizId, $codigo);
        } else {
            // Hash de outra empresa: mesmo 404 de "não existe", sem consultar o pedido.
            $os = $qr['empresa'] ? $this->os($bizId, $qr['pedido_id']) : null;
        }

        if ($os === null) {
            return $this->naoEncontrado('Etiqueta não encontrada.');
        }

        return response()->json([
            'pedido' => $this->pedido($bizId, $os),
            'etapa' => $this->etapa((string) $os->status_etapa),
            'pode_apontar' => $this->podeApontar($user) && ! in_array($os->status_etapa, self::TERMINAIS, true),
        ]);
    }

    /** POST /api/app/producao/apontamentos/iniciar `{ pedido_id, etapa, codigo? }` + Idempotency-Key */
    public function iniciar(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user) || ! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;
        $exigeQr = AjustesDaEmpresa::valor($bizId, 'app_apontamento_exige_qr') === true;

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'pedido_id' => ['required', 'integer', 'min:1'],
            'etapa' => ['required', 'string', 'in:' . implode(',', array_keys(self::ROTULOS))],
            'codigo' => [$exigeQr ? 'required' : 'nullable', 'string', 'max:120'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'pedido_id.required' => 'Informe o pedido.',
            'etapa.required' => 'Informe a etapa que você está vendo.',
            'etapa.in' => 'Etapa desconhecida.',
            'codigo.required' => 'Leia o QR da etiqueta: esta empresa não aceita número digitado.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $d = $v->validated();
        $pedidoId = (int) $d['pedido_id'];

        if ($exigeQr) {
            $qr = EtiquetaProducao::ler((string) $d['codigo'], $bizId);
            if ($qr === null) {
                return $this->invalido(['codigo' => 'Leia o QR da etiqueta: esta empresa não aceita número digitado.']);
            }
            if (! $qr['empresa'] || $qr['pedido_id'] !== $pedidoId) {
                return $this->naoEncontrado('Pedido não encontrado.');
            }
        }

        $hash = hash('sha256', (string) json_encode([$pedidoId, $d['etapa']]));

        return $this->idempotente($bizId, (int) $user->id, 'producao.apontamento.iniciar', $chave, $hash, function () use ($bizId, $user, $pedidoId, $d) {
            $os = $this->os($bizId, $pedidoId, true);
            if ($os === null) {
                return $this->naoEncontrado('Pedido não encontrado.');
            }
            if (in_array($os->status_etapa, self::TERMINAIS, true)) {
                return response()->json(['erro' => 'bloqueado', 'mensagem' => 'Este pedido já foi ' . mb_strtolower(self::ROTULOS[$os->status_etapa]) . ': não recebe apontamento.'], 422);
            }
            if ($os->status_etapa !== $d['etapa']) {
                return response()->json([
                    'erro' => 'etapa_mudou',
                    'mensagem' => 'O pedido mudou de etapa. Confira antes de apontar.',
                    'etapa' => $this->etapa((string) $os->status_etapa),
                ], 409);
            }
            $aberto = $this->aberto($bizId, (int) $user->id, true);
            if ($aberto !== null) {
                return response()->json([
                    'erro' => 'em_andamento',
                    'mensagem' => 'Você já tem um apontamento aberto. Finalize ou cancele antes de iniciar outro.',
                    'item' => $this->item($bizId, $aberto),
                ], 409);
            }

            $agora = now();
            $id = (int) DB::table('comvis_apontamentos')->insertGetId([
                'business_id' => $bizId,
                'os_id' => (int) $os->id,
                'operador_id' => (int) $user->id,
                'iniciado_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            return response()->json(['id' => $id, 'iniciado_em' => $agora->toIso8601String()], 201);
        });
    }

    /** POST /api/app/producao/apontamentos/{id}/finalizar `{ m2_produzido?, observacoes? }` + Idempotency-Key */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user) || ! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            // Texto com ponto e até 3 casas, como a coluna: nunca float locale-ambíguo.
            'm2_produzido' => ['nullable', 'string', 'regex:/^\d{1,7}(\.\d{1,3})?$/'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'm2_produzido.regex' => 'Use número com ponto e até 3 casas (ex.: 12.500).',
            'observacoes.max' => 'Observação com no máximo 500 caracteres.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $d = $v->validated();
        $m2 = isset($d['m2_produzido']) ? (string) $d['m2_produzido'] : null;
        $obs = isset($d['observacoes']) ? trim((string) $d['observacoes']) : null;
        $hash = hash('sha256', (string) json_encode([$id, $m2, $obs]));

        return $this->idempotente($bizId, (int) $user->id, 'producao.apontamento.finalizar', $chave, $hash, function () use ($bizId, $user, $id, $m2, $obs) {
            $ap = $this->doOperador($bizId, (int) $user->id, $id);
            if ($ap === null) {
                return $this->naoEncontrado('Apontamento não encontrado.');
            }
            if ($ap->finalizado_em !== null) {
                return $this->jaFechado();
            }

            $agora = now();
            $segundos = max(0, $agora->getTimestamp() - strtotime((string) $ap->iniciado_em));
            $drift = null;
            if ($m2 !== null && $ap->m2_orcado !== null && (float) $ap->m2_orcado > 0) {
                $drift = round((((float) $m2 - (float) $ap->m2_orcado) / (float) $ap->m2_orcado) * 100, 2);
            }
            DB::table('comvis_apontamentos')->where('business_id', $bizId)->where('id', $id)->whereNull('finalizado_em')
                ->update([
                    'finalizado_em' => $agora,
                    'duracao_segundos' => $segundos,
                    'm2_produzido' => $m2,
                    'drift_percent' => $drift,
                    'observacoes' => $obs === '' ? null : $obs,
                    'updated_at' => $agora,
                ]);

            Log::info('comvis.apontamento.finalizado', [
                'business_id' => $bizId, 'apontamento_id' => $id, 'os_id' => (int) $ap->os_id,
                'operador_id' => (int) $user->id, 'duracao_segundos' => $segundos, 'origem' => 'app',
            ]);

            return response()->json(['id' => $id, 'minutos' => intdiv($segundos, 60)]);
        });
    }

    /** POST /api/app/producao/apontamentos/{id}/cancelar `{ motivo }` + Idempotency-Key — não conta tempo. */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user) || ! $this->podeApontar($user)) {
            return $this->semPermissao();
        }
        $bizId = (int) $user->business_id;

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'motivo.required' => 'Diga por que está cancelando.',
            'motivo.min' => 'O motivo precisa de pelo menos 5 letras.',
            'motivo.max' => 'Motivo com no máximo 500 caracteres.',
        ]);
        if ($v->fails()) {
            return $this->invalido(collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all());
        }
        $motivo = trim((string) $v->validated()['motivo']);
        $hash = hash('sha256', (string) json_encode([$id, $motivo]));

        return $this->idempotente($bizId, (int) $user->id, 'producao.apontamento.cancelar', $chave, $hash, function () use ($bizId, $user, $id, $motivo) {
            $ap = $this->doOperador($bizId, (int) $user->id, $id);
            if ($ap === null) {
                return $this->naoEncontrado('Apontamento não encontrado.');
            }
            if ($ap->finalizado_em !== null) {
                return $this->jaFechado();
            }

            $agora = now();
            // Mesmo fechamento do ApontamentoTracker::cancelar: m² 0, sem drift, motivo registrado.
            DB::table('comvis_apontamentos')->where('business_id', $bizId)->where('id', $id)->whereNull('finalizado_em')
                ->update([
                    'finalizado_em' => $agora,
                    'duracao_segundos' => max(0, $agora->getTimestamp() - strtotime((string) $ap->iniciado_em)),
                    'm2_produzido' => 0,
                    'drift_percent' => null,
                    'observacoes' => '[CANCELADO] ' . $motivo,
                    'updated_at' => $agora,
                ]);

            return response()->json(['id' => $id, 'cancelado' => true]);
        });
    }

    // ------------------------------------------------------------------

    /**
     * Executa a escrita uma vez por Idempotency-Key (tabela `app_idempotencia`, a da venda §2.2).
     * A reserva vai na MESMA transação da escrita: se a escrita recusa (4xx), a reserva some junto e
     * a mesma chave pode tentar de novo; repetida depois do sucesso, devolve a mesma resposta (200).
     */
    private function idempotente(int $bizId, int $userId, string $rota, string $chave, string $hash, \Closure $escrita): JsonResponse
    {
        $ja = $this->reserva($bizId, $userId, $rota, $chave);
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }

        DB::beginTransaction();
        try {
            try {
                $reservaId = DB::table('app_idempotencia')->insertGetId([
                    'business_id' => $bizId, 'user_id' => $userId, 'rota' => $rota, 'chave' => $chave,
                    'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                DB::rollBack();
                $ja = $this->reserva($bizId, $userId, $rota, $chave);

                return $ja !== null ? $this->repetida($ja, $hash) : $this->processando();
            }

            /** @var JsonResponse $resposta */
            $resposta = $escrita();
            if ($resposta->getStatusCode() >= 300) {
                DB::rollBack();

                return $resposta;
            }
            DB::table('app_idempotencia')->where('id', $reservaId)->update([
                'resposta' => $resposta->getContent(), 'updated_at' => now(),
            ]);
            DB::commit();

            return $resposta;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API app producao ' . $rota . ': ' . $e->getMessage(), ['business_id' => $bizId]);

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível registrar o apontamento.'], 500);
        }
    }

    private function reserva(int $bizId, int $userId, string $rota, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', $rota)->where('chave', $chave)->first();
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return response()->json([
                'erro' => 'idempotencia_conflito',
                'mensagem' => 'Esta chave já foi usada para outro apontamento. Gere uma chave nova.',
            ], 422);
        }
        if ($ja->resposta === null) {
            return $this->processando();
        }

        return response()->json(json_decode((string) $ja->resposta, true), 200);
    }

    /** OS de comunicação visual da empresa (não apagada). `$trava`: lock para iniciar. */
    private function os(int $bizId, int $id, bool $trava = false): ?object
    {
        $q = DB::table('comvis_os')->where('business_id', $bizId)->where('id', $id)->whereNull('deleted_at');

        return ($trava ? $q->lockForUpdate() : $q)->first();
    }

    /** Número digitado: o id ou o número da OS (OS-2026-00012), sempre na empresa de quem lê. */
    private function osPorNumero(int $bizId, string $codigo): ?object
    {
        $q = DB::table('comvis_os')->where('business_id', $bizId)->whereNull('deleted_at');
        if (preg_match('/^[1-9][0-9]{0,18}$/', $codigo)) {
            return $q->where('id', (int) $codigo)->first();
        }
        if ($codigo === '' || mb_strlen($codigo) > 20) {
            return null;
        }

        return $q->where('numero', mb_strtoupper($codigo))->first();
    }

    private function aberto(int $bizId, int $userId, bool $trava = false): ?object
    {
        $q = DB::table('comvis_apontamentos')->where('business_id', $bizId)->where('operador_id', $userId)
            ->whereNull('finalizado_em')->orderBy('id');

        return ($trava ? $q->lockForUpdate() : $q)->first();
    }

    /** Só o próprio operador fecha o seu apontamento; o de outro operador é 404, como o de outra empresa. */
    private function doOperador(int $bizId, int $userId, int $id): ?object
    {
        return DB::table('comvis_apontamentos')->where('business_id', $bizId)->where('id', $id)
            ->where('operador_id', $userId)->lockForUpdate()->first();
    }

    private function item(int $bizId, object $ap): array
    {
        $os = $this->os($bizId, (int) $ap->os_id);

        return [
            'id' => (int) $ap->id,
            'pedido' => $os === null ? null : $this->pedido($bizId, $os),
            'etapa' => $os === null ? null : $this->etapa((string) $os->status_etapa),
            'iniciado_em' => \Illuminate\Support\Carbon::parse((string) $ap->iniciado_em)->toIso8601String(),
        ];
    }

    /** @return array{id:int, numero:string, resumo:?string, cliente:?string} */
    private function pedido(int $bizId, object $os): array
    {
        $resumo = null;
        $cliente = null;
        if ($os->orcamento_id !== null) {
            $descricoes = DB::table('comvis_orcamento_itens')->where('business_id', $bizId)
                ->where('orcamento_id', (int) $os->orcamento_id)->orderBy('ordem')->orderBy('id')
                ->limit(3)->pluck('descricao')->filter()->all();
            $resumo = $descricoes === [] ? null : implode(' + ', $descricoes);
            $cliente = DB::table('comvis_orcamentos as o')
                ->join('contacts as c', fn ($j) => $j->on('c.id', '=', 'o.contato_id')->on('c.business_id', '=', 'o.business_id'))
                ->where('o.business_id', $bizId)->where('o.id', (int) $os->orcamento_id)->value('c.name');
        }

        return ['id' => (int) $os->id, 'numero' => (string) $os->numero, 'resumo' => $resumo, 'cliente' => $cliente];
    }

    /** @return array{chave:string, rotulo:string} */
    private function etapa(string $chave): array
    {
        return ['chave' => $chave, 'rotulo' => self::ROTULOS[$chave] ?? $chave];
    }

    private function podeVer(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->can('superadmin')) {
            return (bool) $this->moduleUtil->isModuleInstalled('ComunicacaoVisual');
        }

        return (bool) $this->moduleUtil->hasThePermissionInSubscription((int) $user->business_id, 'comunicacao_visual_module', 'superadmin_package')
            && $user->can('comvis.os.view');
    }

    private function podeApontar(User $user): bool
    {
        return $user->can('superadmin') || $user->can('comvis.apontamento.create');
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não pode apontar produção.'], 403);
    }

    private function naoEncontrado(string $mensagem): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => $mensagem], 404);
    }

    private function jaFechado(): JsonResponse
    {
        return response()->json(['erro' => 'bloqueado', 'mensagem' => 'Este apontamento já foi encerrado e não muda mais.'], 422);
    }

    private function processando(): JsonResponse
    {
        return response()->json(['erro' => 'em_andamento', 'mensagem' => 'Este apontamento ainda está sendo registrado. Tente de novo.'], 409);
    }

    /** @param array<string,string> $campos */
    private function invalido(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'mensagem' => 'Confira os campos.', 'campos' => $campos], 422);
    }
}
