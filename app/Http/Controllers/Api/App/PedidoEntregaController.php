<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Arquivos\AnexosDoApp;
use App\Domain\Fsm\Exceptions\InvalidActionForCurrentStageException;
use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Policies\StageActionPolicy;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Http\Controllers\Controller;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\Transaction;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/app/pedidos/{id}/entrega — protocolo de entrega do pedido pelo app (P3 · tela 22).
 * Contrato: memory/requisitos/AppMobile/api/tela-22-entrega.md.
 *
 * Numa transação de banco só: reserva a Idempotency-Key, trava a venda, confere a etapa, grava o
 * protocolo `ENT-NNNN` (sequencial por business, em `app_entregas`), anexa a assinatura em
 * `arquivos` (pelo contrato AnexosDoApp — o núcleo não importa o módulo Arquivos) e executa a ação
 * `entregar` da FSM pelo ExecuteStageActionService. Qualquer falha desfaz tudo; só o arquivo da
 * assinatura já escrito no disco fica órfão (o registro em `arquivos` volta junto).
 *
 * Ajustes da empresa (conferidos aqui, não só no app):
 *  - `app_entrega_exige_assinatura` true → sem `assinatura_png` = 422 campos.assinatura_png.
 *  - `app_entrega_exige_localizacao` true → `lat`/`lng` nulos = 422 campos.lat.
 *
 * `entregar` não mexe em valor nem estoque no processo `venda_com_producao`
 * (FsmProcessoVendaComProducaoSeeder: invoiced → delivered e, por compatibilidade, paid → delivered —
 * ADR 0434; sem side effect). Se o cadastro do business
 * tiver posto side effect ou evento na ação, ela fica só na web (regra mestre).
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em toda consulta; pedido de outro business ou
 * fora do "só os próprios" → 404, sem dizer que existe.
 */
class PedidoEntregaController extends Controller
{
    private const ROTA = 'pedidos.entrega';

    private const ACAO = 'entregar';

    /** Tamanho máximo da assinatura depois de decodificada (contrato: ≤ 200 KB). */
    private const ASSINATURA_MAX_BYTES = 200 * 1024;

    public function registrar(Request $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $pedidos = app(PedidosController::class);
        if ($user === null || ! $pedidos->podeVerVendas($user)) {
            return $this->erro(403, 'sem_permissao', 'Seu usuário não tem acesso aos pedidos.');
        }
        $bizId = (int) $user->business_id;

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'etapa_esperada' => ['required', 'string', 'max:80'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.linha_id' => ['required', 'integer'],
            'itens.*.conferido' => ['required', 'boolean'],
            'recebido_por' => ['required', 'string', 'min:2', 'max:120'],
            'documento' => ['nullable', 'string', 'max:40'],
            'assinatura_png' => ['nullable', 'string'],
            'sem_assinatura_motivo' => ['nullable', 'string', 'min:5', 'max:200'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'precisao_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'etapa_esperada.required' => 'Informe a etapa que o app mostrava.',
            'recebido_por.required' => 'Informe quem recebeu.',
            'recebido_por.min' => 'Informe o nome de quem recebeu.',
            'recebido_por.max' => 'O nome tem no máximo 120 caracteres.',
            'documento.max' => 'O documento tem no máximo 40 caracteres.',
            'sem_assinatura_motivo.min' => 'O motivo precisa ter de 5 a 200 caracteres.',
            'sem_assinatura_motivo.max' => 'O motivo precisa ter de 5 a 200 caracteres.',
            'lat.numeric' => 'Localização inválida.', 'lat.between' => 'Localização inválida.',
            'lng.numeric' => 'Localização inválida.', 'lng.between' => 'Localização inválida.',
            'precisao_m.numeric' => 'Precisão inválida.', 'precisao_m.min' => 'Precisão inválida.',
            'precisao_m.max' => 'Precisão inválida.',
            'assinatura_png.string' => 'A assinatura precisa ser uma imagem PNG de até 200 KB.',
        ]);
        if ($v->fails()) {
            return $this->validacao(collect($v->errors()->toArray())
                ->mapWithKeys(fn ($m, $k) => str_starts_with((string) $k, 'itens')
                    ? ['itens' => 'Confira os itens do pedido.']
                    : [$k => $m[0]])
                ->all());
        }
        $d = $v->validated();

        // Visível ao usuário (mesmas regras da lista): senão 404, inclusive outro business.
        if (! $pedidos->baseVisivel($user)->where('t.id', $id)->exists()) {
            return $this->naoEncontrado();
        }

        $esperada = (string) $d['etapa_esperada'];
        $recebidoPor = trim((string) $d['recebido_por']);
        $documento = $this->ouNulo($d['documento'] ?? null);
        $motivo = $this->ouNulo($d['sem_assinatura_motivo'] ?? null);
        $assinaturaTexto = $this->ouNulo($d['assinatura_png'] ?? null);
        $lat = isset($d['lat']) ? (float) $d['lat'] : null;
        $lng = isset($d['lng']) ? (float) $d['lng'] : null;
        $precisao = isset($d['precisao_m']) ? (float) $d['precisao_m'] : null;
        $itens = collect($d['itens'])->map(fn ($i) => [
            'linha_id' => (int) $i['linha_id'], 'conferido' => (bool) $i['conferido'],
        ])->sortBy('linha_id')->values()->all();

        $hash = hash('sha256', (string) json_encode([
            $id, $esperada, $itens, $recebidoPor, $documento, $assinaturaTexto !== null ? hash('sha256', $assinaturaTexto) : null,
            $motivo, $lat, $lng, $precisao,
        ]));
        $ja = $this->idempotencia($bizId, (int) $user->id, $chave);
        if ($ja !== null) {
            return $this->repetida($ja, $hash);
        }

        // Regras do contrato e dos ajustes da empresa (o app só esconde; quem recusa é a rota).
        $exigeAssinatura = AjustesDaEmpresa::valor($bizId, 'app_entrega_exige_assinatura') === true;
        $exigeLocalizacao = AjustesDaEmpresa::valor($bizId, 'app_entrega_exige_localizacao') === true;

        $assinatura = null;
        if ($assinaturaTexto !== null) {
            $assinatura = $this->decodificarPng($assinaturaTexto);
            if ($assinatura === null) {
                return $this->validacao(['assinatura_png' => 'A assinatura precisa ser uma imagem PNG de até 200 KB.']);
            }
            $motivo = null;
        } elseif ($exigeAssinatura) {
            return $this->validacao(['assinatura_png' => 'Esta empresa exige a assinatura de quem recebe.']);
        } elseif ($motivo === null) {
            return $this->validacao(['assinatura_png' => 'Colha a assinatura ou informe o motivo de não ter.']);
        }

        if (($lat === null) !== ($lng === null)) {
            return $this->validacao([$lat === null ? 'lat' : 'lng' => 'Envie latitude e longitude juntas.']);
        }
        if ($exigeLocalizacao && $lat === null) {
            return $this->validacao(['lat' => 'Esta empresa exige a localização do celular na entrega.']);
        }

        $linhas = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $bizId)
            ->where('tsl.transaction_id', $id)
            ->whereNull('tsl.parent_sell_line_id')
            ->pluck('tsl.id')->map(fn ($x) => (int) $x)->sort()->values()->all();
        $conferidas = collect($itens)->where('conferido', true)->pluck('linha_id')->unique()->sort()->values()->all();
        if ($linhas === [] || $conferidas !== $linhas) {
            return $this->validacao(['itens' => 'Confira todos os itens do pedido antes de entregar.']);
        }

        DB::beginTransaction();
        try {
            try {
                $reservaId = DB::table('app_idempotencia')->insertGetId([
                    'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => self::ROTA, 'chave' => $chave,
                    'hash_corpo' => $hash, 'transaction_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Outra requisição com a mesma chave ganhou a corrida (o INSERT esperou o lock).
                DB::rollBack();
                $ja = $this->idempotencia($bizId, (int) $user->id, $chave);

                return $ja !== null ? $this->repetida($ja, $hash) : $this->emAndamento();
            }

            $venda = Transaction::query()->where('business_id', $bizId)->whereKey($id)->lockForUpdate()->first();
            $atual = $venda?->current_stage_id === null ? null : DB::table('sale_process_stages')
                ->where('id', (int) $venda->current_stage_id)->value('key');
            if ($venda === null || $atual !== $esperada) {
                DB::rollBack();

                return $this->etapaMudou();
            }

            $acao = SaleStageAction::query()
                ->where('stage_id', (int) $venda->current_stage_id)
                ->where('key', self::ACAO)
                ->first();
            if ($acao === null) {
                // A etapa atual não é a de Entrega.
                DB::rollBack();

                return $this->etapaMudou();
            }
            if (! empty($acao->side_effect_class) || ! empty($acao->event_class)
                || ! app(StageActionPolicy::class)->canExecute($user, $venda, self::ACAO)) {
                DB::rollBack();

                return $this->erro(403, 'sem_permissao', 'Seu usuário não pode registrar a entrega deste pedido.');
            }

            // Numeração ENT-NNNN por business: a linha do business serializa as entregas simultâneas.
            DB::table('business')->where('id', $bizId)->lockForUpdate()->value('id');
            $numero = (int) DB::table('app_entregas')->where('business_id', $bizId)->max('numero') + 1;
            $protocolo = 'ENT-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
            $registradoEm = now();

            $arquivoId = null;
            if ($assinatura !== null) {
                $arquivoId = $this->anexarAssinatura($bizId, $id, $protocolo, $assinatura);
                if ($arquivoId === null) {
                    DB::rollBack();

                    return $this->erro(503, 'sem_configuracao', 'A assinatura não pode ser guardada agora. Tente mais tarde.');
                }
            }

            DB::table('app_entregas')->insert([
                'business_id' => $bizId, 'transaction_id' => $id, 'numero' => $numero, 'protocolo' => $protocolo,
                'recebido_por' => $recebidoPor, 'documento' => $documento, 'com_assinatura' => $arquivoId !== null,
                'assinatura_arquivo_id' => $arquivoId, 'sem_assinatura_motivo' => $motivo,
                'lat' => $lat, 'lng' => $lng, 'precisao_m' => $precisao, 'itens' => json_encode($itens),
                'user_id' => (int) $user->id, 'registrado_em' => $registradoEm,
                'created_at' => $registradoEm, 'updated_at' => $registradoEm,
            ]);

            // O protocolo vai para a trilha (sale_stage_history.payload_snapshot), como o payload da web.
            app(ExecuteStageActionService::class)->execute($venda, self::ACAO, $user, [
                'origem' => 'app', 'protocolo' => $protocolo,
            ]);

            $resposta = [
                'protocolo' => $protocolo,
                'registrado_em' => $registradoEm->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'recebido_por' => $recebidoPor,
                'com_assinatura' => $arquivoId !== null,
                'pedido' => $pedidos->show($request, $id)->getData(true),
            ];
            DB::table('app_idempotencia')->where('id', $reservaId)->update([
                'resposta' => json_encode($resposta),
                'updated_at' => now(),
            ]);
            DB::commit();

            return response()->json($resposta, 201);
        } catch (UnauthorizedActionException) {
            DB::rollBack();

            return $this->erro(403, 'sem_permissao', 'Seu usuário não pode registrar a entrega deste pedido.');
        } catch (InvalidActionForCurrentStageException) {
            DB::rollBack();

            return $this->etapaMudou();
        } catch (\DomainException $e) {
            DB::rollBack();

            return $this->erro(422, 'bloqueado', 'O ERP não permitiu registrar a entrega deste pedido.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API app pedidos.entrega: ' . $e->getMessage(), ['business_id' => $bizId, 'transaction_id' => $id]);

            return $this->erro(500, 'falha', 'Não foi possível registrar a entrega.');
        }
    }

    /** PNG decodificado do data URL, ou null se não for PNG válido de até 200 KB. */
    private function decodificarPng(string $texto): ?string
    {
        $base64 = preg_replace('#^data:image/png;base64,#', '', $texto, 1, $trocas);
        if ($trocas !== 1 || $base64 === null) {
            return null;
        }
        // Mais de ~4/3 do limite já não cabe: recusa antes de decodificar.
        if (strlen($base64) > (int) ceil(self::ASSINATURA_MAX_BYTES * 4 / 3) + 4) {
            return null;
        }
        $bin = base64_decode($base64, true);
        if ($bin === false || strlen($bin) > self::ASSINATURA_MAX_BYTES
            || ! str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return $bin;
    }

    private function anexarAssinatura(int $bizId, int $vendaId, string $protocolo, string $png): ?int
    {
        $caminho = tempnam(sys_get_temp_dir(), 'ent');
        if ($caminho === false) {
            return null;
        }
        try {
            file_put_contents($caminho, $png);
            $arquivo = new UploadedFile($caminho, 'assinatura-' . $protocolo . '.png', 'image/png', null, true);

            return app(AnexosDoApp::class)->anexar($bizId, Transaction::class, $vendaId, $arquivo);
        } finally {
            @unlink($caminho);
        }
    }

    private function ouNulo(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        return $t !== '' ? $t : null;
    }

    private function idempotencia(int $bizId, int $userId, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', self::ROTA)->where('chave', $chave)->first();
    }

    private function repetida(object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return $this->erro(422, 'idempotencia_conflito', 'Esta chave já foi usada para outra entrega. Gere uma chave nova.');
        }
        if ($ja->resposta === null) {
            return $this->emAndamento();
        }

        return response()->json(json_decode((string) $ja->resposta, true), 201);
    }

    /** @param array<string, string> $campos */
    private function validacao(array $campos): JsonResponse
    {
        return response()->json(['erro' => 'validacao', 'mensagem' => 'Confira os campos.', 'campos' => $campos], 422);
    }

    private function emAndamento(): JsonResponse
    {
        return $this->erro(409, 'em_andamento', 'Esta entrega ainda está sendo registrada. Aguarde.');
    }

    private function etapaMudou(): JsonResponse
    {
        return $this->erro(409, 'etapa_mudou', 'O pedido mudou de etapa. Atualize a tela.');
    }

    private function naoEncontrado(): JsonResponse
    {
        return $this->erro(404, 'nao_encontrado', 'Pedido não encontrado.');
    }

    private function erro(int $status, string $erro, string $mensagem): JsonResponse
    {
        return response()->json(['erro' => $erro, 'mensagem' => $mensagem], $status);
    }
}
