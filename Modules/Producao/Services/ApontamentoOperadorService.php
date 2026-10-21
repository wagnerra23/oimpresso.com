<?php

namespace Modules\Producao\Services;

use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Services\PresencaDoDiaService;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;

/**
 * Regra do apontamento do operador (thread 04 + E04 do playbook PCP), por cima do
 * `ApontamentoService` da 02. Os consumidores são dois: a PWA web e a API do app com token.
 *
 * - `business_id` por parâmetro em toda consulta: o app não tem sessão (ADR 0093).
 * - Etapa = linha de `pcp_programacoes`; só o responsável aponta nela (UC-PCV-34).
 * - Fila offline (UC-PCV-23 · E04 A8, [W] 2026-10-15): vale a hora do aparelho, dentro dos
 *   limites abaixo, e a mesma chave nunca grava duas vezes.
 * - Fechamento automático (UC-PCV-22 · UC-PCV-36, D-PONTO): pessoa fecha na saída do ponto;
 *   sem saída, e máquina sempre, no fim do turno. O Ponto só é lido.
 * - Correção de horário não reescreve o par (append-only, e o custo lê o par): vira evento
 *   `correcao_solicitada` para o gestor. Aplicar a correção ao custo mexe em VALOR (regra mestre).
 * - Etapa da OS por item (`os_tipo = pcp_ordem`, ADR 0436): a situação da OS anda junto
 *   (SituacaoDaOrdem) e OS não liberada, em retrabalho ou encerrada não aponta.
 */
class ApontamentoOperadorService
{
    public const FUTURO_TOLERADO_MIN = 5;
    public const PASSADO_TOLERADO_H = 72;
    public const EXCEDENTE_PENDENCIA_MIN = 30;

    /** Tipos de `pcp_eventos` gravados aqui (texto livre na tabela; semântica em memory/dominio/producao.md). */
    public const EV_ETAPA_INICIADA = 'etapa_iniciada';
    public const EV_ETAPA_CONCLUIDA = 'etapa_concluida';
    public const EV_AVISO_PROXIMO = 'aviso_proximo';
    public const EV_FECHADO_AUTO = 'fechado_automaticamente';
    public const EV_CORRECAO = 'correcao_solicitada';
    public const EV_PENDENCIA_GESTOR = 'pendencia_gestor';

    public function __construct(
        private readonly ApontamentoService $base,
        private readonly TurnoService $turno,
        private readonly PresencaDoDiaService $ponto,
    ) {}

    /** UC-PCV-19: etapas do operador no dia, na ordem programada. */
    public function minhasEtapasDeHoje(int $businessId, int $userId, CarbonInterface $dia): Collection
    {
        return Programacao::query()->where('business_id', $businessId)->where('user_id', $userId)
            ->whereIn('status', ['pendente', 'iniciada'])
            ->whereDate('inicio_previsto', $dia->toDateString())
            ->orderBy('inicio_previsto')->get();
    }

    /** E04 A1: apontamento de pessoa + máquinas iniciadas por ele, mais a linha do ponto. */
    public function rodandoAgora(int $businessId, int $userId, CarbonInterface $agora): array
    {
        $abertos = $this->base->abertos($businessId)
            ->where(fn ($q) => $q->where(fn ($p) => $p->where('pcp_apontamentos.tipo_custo', Posto::PESSOA)->where('pcp_apontamentos.user_id', $userId))
                ->orWhere(fn ($m) => $m->where('pcp_apontamentos.tipo_custo', Posto::MAQUINA)->where('pcp_apontamentos.iniciado_por', $userId)))
            ->orderBy('pcp_apontamentos.momento')->get();

        return [
            'pessoa' => $abertos->firstWhere('tipo_custo', Posto::PESSOA),
            'maquinas' => $abertos->where('tipo_custo', Posto::MAQUINA)->values(),
            'ponto' => $this->ponto->doUsuario($businessId, $userId, $agora),
        ];
    }

    /**
     * UC-PCV-20/34: o QR da OS abre a etapa do operador nela. Etapa de outro: devolve onde está
     * e com quem, sem gravar nada. Sem etapa aberta na OS: `nao_encontrado`.
     *
     * @return array{pode_apontar: bool, programacao: Programacao}
     */
    public function resolverQr(int $businessId, int $userId, string $osTipo, int $osId, ?int $programacaoId = null): array
    {
        $etapas = Programacao::query()->where('business_id', $businessId)
            ->where('os_tipo', $osTipo)->where('os_id', $osId)
            ->whereIn('status', ['pendente', 'iniciada'])
            ->when($programacaoId, fn ($q) => $q->whereKey($programacaoId))
            ->orderBy('inicio_previsto')->get();

        $minha = $etapas->firstWhere('user_id', $userId);
        $atual = $minha ?? $etapas->first() ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Etapa não encontrada.');

        return ['pode_apontar' => $minha !== null, 'programacao' => $atual];
    }

    /** UC-PCV-20/31: inicia (ou retoma) a etapa. Máquina não ocupa a pessoa. */
    public function iniciar(int $businessId, int $userId, int $programacaoId, string $chave, CarbonInterface $momento, string $origem = 'qr'): Apontamento
    {
        $momento = $this->hora($momento);
        if ($repetido = $this->repeticao($businessId, $chave, 'inicio', $momento, programacaoId: $programacaoId)) {
            return $repetido;
        }
        $this->validarMomento($momento);

        $etapa = Programacao::query()->where('business_id', $businessId)->find($programacaoId)
            ?? throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Etapa não encontrada.');
        if (in_array($etapa->status, ['concluida', 'cancelada'], true)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ENCERRADA, 'Esta etapa já foi encerrada.');
        }
        if ((int) $etapa->user_id !== $userId) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ALHEIA, 'Esta etapa está com outro responsável.',
                ['programacao_id' => $etapa->id, 'posto_id' => $etapa->posto_id, 'user_id' => $etapa->user_id]);
        }
        // UC-FLX-08 · US-PCP-030: posto com operadores cadastrados só começa com um deles.
        $posto = $etapa->posto_id !== null ? (int) $etapa->posto_id : null;
        if (! app(OperadoresDoPosto::class)->podeAtuar($businessId, $posto, $userId)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::ETAPA_ALHEIA, 'Você não está cadastrado como operador deste posto.',
                ['programacao_id' => $etapa->id, 'posto_id' => $etapa->posto_id, 'user_id' => $etapa->user_id]);
        }
        app(SituacaoDaOrdem::class)->antesDeIniciar($businessId, $etapa);

        try {
            $ap = $this->base->iniciar($businessId, $etapa->posto_id, $userId, $chave, $etapa->os_tipo, (int) $etapa->os_id,
                $origem, null, Carbon::instance($momento), programacaoId: $etapa->id);
        } catch (DomainException $e) {
            if (! $this->base->pessoaAberto($businessId, $userId)) {
                // Única outra recusa do base: origem `manual` sem justificativa.
                throw new ApontamentoRecusado(ApontamentoRecusado::JUSTIFICATIVA_OBRIGATORIA, $e->getMessage());
            }
            $aberto = $this->rodandoAgora($businessId, $userId, $momento)['pessoa'];
            throw new ApontamentoRecusado(ApontamentoRecusado::PESSOA_OCUPADA, $e->getMessage(), ['apontamento_id' => $aberto?->id]);
        }

        if ($etapa->status === 'pendente') {
            $etapa->forceFill(['status' => 'iniciada'])->save();
        }
        $this->evento($businessId, self::EV_ETAPA_INICIADA, $ap, $userId);
        app(SituacaoDaOrdem::class)->aoIniciar($businessId, $etapa, $userId);

        return $ap;
    }

    /**
     * E04 A4 · UC-PCV-33: motivos de pausa ATIVOS do cadastro do posto, na ordem do gestor.
     * É a lista que o `pausar` aceita (mesmo filtro do `motivoDoPosto`). Com `$postoId`, só os
     * daquele posto; posto de outra empresa devolve vazio. Consumidores: PWA web e API do app.
     *
     * @return Collection<int, array{id:int, posto_id:int, nome:string}>
     */
    public function motivosDePausa(int $businessId, ?int $postoId = null): Collection
    {
        if (! Schema::hasTable('pcp_motivos')) {
            return collect();
        }

        return DB::table('pcp_motivos')->where('business_id', $businessId)->where('tipo', 'pausa')->where('ativo', true)
            ->when($postoId !== null, fn ($q) => $q->where('posto_id', $postoId))
            ->orderBy('posto_id')->orderBy('ordem')->orderBy('nome')->get(['id', 'posto_id', 'nome'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'posto_id' => (int) $m->posto_id, 'nome' => (string) $m->nome])
            ->values();
    }

    /** E04 A4: pausa com motivo do cadastro do posto (o cadastro e a FK são da thread 03). */
    public function pausar(int $businessId, int $userId, int $inicioId, int $motivoId, string $chave, CarbonInterface $momento): Apontamento
    {
        if ($motivoId <= 0) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MOTIVO_OBRIGATORIO, 'Escolha o motivo da pausa.');
        }

        $pausa = $this->encerrar('pausa', $businessId, $userId, $inicioId, $chave, $momento, $motivoId);
        if ($pausa->wasRecentlyCreated && $pausa->tipo === 'pausa') {
            app(SituacaoDaOrdem::class)->aoPausar($businessId, $pausa, $userId);
        }

        return $pausa;
    }

    /** UC-PCV-21: conclui a etapa, passa a OS adiante e avisa o próximo responsável. */
    /**
     * `$impressao` (US-COMVIS-004 · UC-FLX-09): o que o operador informou ao concluir — `m2_impresso`
     * e `tinta_ml_c|m|y|k`, cada um opcional. Vai na linha `fim`; valor inválido recusa e nada grava.
     */
    public function concluir(int $businessId, int $userId, int $inicioId, string $chave, CarbonInterface $momento, array $impressao = []): Apontamento
    {
        $fim = $this->encerrar('fim', $businessId, $userId, $inicioId, $chave, $momento, null, $this->impressao($impressao));
        if (! $fim->wasRecentlyCreated || $fim->tipo !== 'fim' || ! $fim->programacao_id) {
            return $fim;
        }

        $etapa = Programacao::query()->where('business_id', $businessId)->find($fim->programacao_id);
        $etapa?->forceFill(['status' => 'concluida'])->save();
        $proxima = $etapa ? Programacao::query()->where('business_id', $businessId)
            ->where('os_tipo', $etapa->os_tipo)->where('os_id', $etapa->os_id)
            ->where('status', 'pendente')->where('id', '!=', $etapa->id)
            ->orderBy('inicio_previsto')->first() : null;

        $this->evento($businessId, self::EV_ETAPA_CONCLUIDA, $fim, $userId);
        if ($etapa) {
            app(SituacaoDaOrdem::class)->aoConcluirEtapa($businessId, $etapa, $userId);
        }
        if ($proxima?->user_id) {
            $this->evento($businessId, self::EV_AVISO_PROXIMO, $fim, (int) $proxima->user_id, ['programacao_id' => $proxima->id]);
        }

        return $fim;
    }

    /**
     * UC-PCV-22/36: fecha o que ficou aberto. Pessoa na saída do ponto (se houver e for antes do
     * fim do turno), senão no fim do turno; máquina no fim do turno. Avisa o PCP e abre pendência
     * se as horas de pessoa passarem da presença em mais de 30 min. Devolve quantos fechou.
     */
    public function fecharAbertosNoFimDoTurno(int $businessId, CarbonInterface $agora): int
    {
        $fechados = 0;
        $pessoas = [];
        foreach ($this->base->abertos($businessId)->orderBy('pcp_apontamentos.momento')->get() as $inicio) {
            $fimTurno = $this->turno->fimDoTurno($businessId, $inicio->momento);
            if ($agora->lt($fimTurno)) {
                continue;
            }
            [$quando, $origem] = [$fimTurno, 'turno'];
            if ($inicio->tipo_custo === Posto::PESSOA && $inicio->user_id) {
                $saida = $this->ponto->doUsuario($businessId, (int) $inicio->user_id, $inicio->momento)['saida'] ?? null;
                if ($saida && $saida->gt($inicio->momento) && $saida->lt($fimTurno)) {
                    [$quando, $origem] = [Carbon::instance($saida), 'ponto'];
                }
                $pessoas[$inicio->user_id.'|'.$inicio->momento->toDateString()] = [(int) $inicio->user_id, $inicio->momento];
            }
            $fim = $this->base->fechar($businessId, $inicio->id, (int) $inicio->iniciado_por,
                "auto-{$inicio->id}", $origem, Carbon::instance($quando));
            $this->evento($businessId, self::EV_FECHADO_AUTO, $fim, null, ['origem' => $origem]);
            $fechados++;
        }
        foreach ($pessoas as [$userId, $dia]) {
            $this->conferirPresenca($businessId, $userId, $dia);
        }

        return $fechados;
    }

    /** UC-PCV-22: correção com justificativa. O par original fica; o gestor aplica. */
    public function lancarCorrecao(int $businessId, int $userId, int $inicioId, CarbonInterface $momento, string $justificativa, string $chave): Evento
    {
        if (trim($justificativa) === '') {
            throw new ApontamentoRecusado(ApontamentoRecusado::JUSTIFICATIVA_OBRIGATORIA, 'A correção exige justificativa.');
        }
        $inicio = $this->doOperador($businessId, $userId, $inicioId);
        $dados = ['inicio_id' => $inicio->id, 'momento' => $this->hora($momento)->toIso8601String(), 'justificativa' => $justificativa, 'chave' => $chave];

        // Reenvio da fila: a mesma chave devolve o pedido já gravado; corpo diferente é conflito.
        $existente = Evento::query()->where('business_id', $businessId)->where('tipo', self::EV_CORRECAO)
            ->where('user_id', $userId)->where('dados->chave', $chave)->first();
        if ($existente) {
            $igual = array_intersect_key((array) $existente->dados, $dados) == $dados;

            return $igual ? $existente : throw new ApontamentoRecusado(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO, 'Esta chave já foi usada em outra correção.');
        }

        return $this->evento($businessId, self::EV_CORRECAO, $inicio, $userId, $dados);
    }

    private function encerrar(string $tipo, int $businessId, int $userId, int $inicioId, string $chave, CarbonInterface $momento, ?int $motivoId, array $impressao = []): Apontamento
    {
        $momento = $this->hora($momento);
        if ($repetido = $this->repeticao($businessId, $chave, $tipo, $momento, inicioId: $inicioId)) {
            return $repetido;
        }
        $this->validarMomento($momento);
        $inicio = $this->doOperador($businessId, $userId, $inicioId);
        if ($momento->lt($inicio->momento)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MOMENTO_INVALIDO, 'A hora é anterior ao início.');
        }
        if ($tipo === 'pausa' && ! $this->motivoDoPosto($businessId, (int) $inicio->posto_id, (int) $motivoId)) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MOTIVO_OBRIGATORIO, 'Escolha um motivo de pausa deste posto.');
        }

        $fechamento = Apontamento::query()->where('business_id', $businessId)->where('inicio_id', $inicio->id)->first();
        if ($fechamento) {
            // Fila offline chegou depois do fechamento automático: a hora real vira correção para o gestor.
            if (in_array($fechamento->origem, ['turno', 'ponto'], true) && $momento->lt($fechamento->momento)) {
                $this->lancarCorrecao($businessId, $userId, $inicio->id, $momento, 'fila offline', $chave);

                return $fechamento;
            }
            throw new ApontamentoRecusado(ApontamentoRecusado::APONTAMENTO_ENCERRADO, 'Este apontamento já foi encerrado.');
        }

        return $tipo === 'pausa'
            ? $this->base->pausar($businessId, $inicio->id, $userId, $chave, $motivoId, 'qr', Carbon::instance($momento))
            : $this->base->fechar($businessId, $inicio->id, $userId, $chave, 'qr', Carbon::instance($momento), $impressao);
    }

    /**
     * Normaliza m² e tinta: número com ponto (a tela troca a vírgula), zero ou mais, sem campo vazio.
     * m² até 3 casas; tinta em ml até 2. Ausente ou vazio fica fora (coluna NULL = não informado).
     *
     * @return array<string, string>
     */
    private function impressao(array $dados): array
    {
        $campos = ['m2_impresso' => 3, 'tinta_ml_c' => 2, 'tinta_ml_m' => 2, 'tinta_ml_y' => 2, 'tinta_ml_k' => 2];
        $saida = [];
        foreach ($campos as $campo => $casas) {
            $v = trim((string) ($dados[$campo] ?? ''));
            if ($v === '') {
                continue;
            }
            if (! preg_match('/^\d{1,7}(\.\d{1,'.$casas.'})?$/', $v)) {
                $nome = $campo === 'm2_impresso' ? 'm² impresso' : 'tinta '.strtoupper(substr($campo, -1));
                throw new ApontamentoRecusado(ApontamentoRecusado::QUANTIDADE_INVALIDA, "Valor inválido em {$nome}: use número com até {$casas} casas (ex.: 12.5).", ['campo' => $campo]);
            }
            $saida[$campo] = $v;
        }

        return $saida;
    }

    /** E04 A4: o motivo vem do cadastro do posto (`pcp_motivos`, thread 03). Antes da 03, só a presença vale. */
    private function motivoDoPosto(int $businessId, int $postoId, int $motivoId): bool
    {
        if (! Schema::hasTable('pcp_motivos')) {
            return $motivoId > 0;
        }

        return DB::table('pcp_motivos')->where('business_id', $businessId)->where('posto_id', $postoId)
            ->where('tipo', 'pausa')->where('ativo', true)->where('id', $motivoId)->exists();
    }

    /** O início tem de ser da empresa e do operador; de outro operador é o mesmo 404. */
    private function doOperador(int $businessId, int $userId, int $inicioId): Apontamento
    {
        $inicio = Apontamento::query()->where('business_id', $businessId)->where('tipo', 'inicio')->find($inicioId);
        $dono = $inicio && ($inicio->tipo_custo === Posto::PESSOA ? (int) $inicio->user_id : (int) $inicio->iniciado_por) === $userId;

        return $dono ? $inicio : throw new ApontamentoRecusado(ApontamentoRecusado::NAO_ENCONTRADO, 'Apontamento não encontrado.');
    }

    /** Mesma chave e mesmo corpo devolve a linha gravada; corpo diferente é conflito. */
    private function repeticao(int $businessId, string $chave, string $tipo, CarbonInterface $momento, ?int $programacaoId = null, ?int $inicioId = null): ?Apontamento
    {
        $linha = Apontamento::query()->where('business_id', $businessId)->where('chave_idempotencia', $chave)->first();
        if (! $linha) {
            return null;
        }
        $igual = $linha->tipo === $tipo && $linha->momento->format('Y-m-d H:i:s') === $momento->format('Y-m-d H:i:s')
            && ($programacaoId === null || (int) $linha->programacao_id === $programacaoId)
            && ($inicioId === null || (int) $linha->inicio_id === $inicioId);

        return $igual ? $linha : throw new ApontamentoRecusado(ApontamentoRecusado::IDEMPOTENCIA_CONFLITO, 'Esta chave já foi usada em outro apontamento.');
    }

    /** Hora do aparelho no fuso do servidor, sem fração: a coluna guarda segundos e o reenvio compara igual. */
    private function hora(CarbonInterface $momento): Carbon
    {
        return Carbon::instance($momento)->setTimezone(config('app.timezone'))->startOfSecond();
    }

    private function validarMomento(CarbonInterface $momento): void
    {
        $agora = Carbon::now();
        if ($momento->gt($agora->copy()->addMinutes(self::FUTURO_TOLERADO_MIN))) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MOMENTO_INVALIDO, 'Hora do aparelho errada; acerte o relógio.');
        }
        if ($momento->lt($agora->copy()->subHours(self::PASSADO_TOLERADO_H))) {
            throw new ApontamentoRecusado(ApontamentoRecusado::MOMENTO_INVALIDO, 'Envio vencido; peça a correção ao gestor.');
        }
    }

    /** D-PONTO: horas de pessoa no dia ≤ presença; excedente > 30 min vira pendência do gestor. */
    private function conferirPresenca(int $businessId, int $userId, CarbonInterface $dia): void
    {
        $presenca = $this->ponto->doUsuario($businessId, $userId, $dia)['presenca_minutos'] ?? null;
        if ($presenca === null) {
            return;
        }
        $pares = DB::table('pcp_apontamentos as i')
            ->join('pcp_apontamentos as f', fn ($j) => $j->on('f.inicio_id', '=', 'i.id')->on('f.business_id', '=', 'i.business_id'))
            ->where('i.business_id', $businessId)->where('i.tipo', 'inicio')->where('i.tipo_custo', Posto::PESSOA)
            ->where('i.user_id', $userId)->whereDate('i.momento', $dia->toDateString())
            ->get(['i.momento as de', 'f.momento as ate']);
        $apontado = (int) $pares->sum(fn ($p) => intdiv(max(0, strtotime($p->ate) - strtotime($p->de)), 60));

        if ($apontado > $presenca + self::EXCEDENTE_PENDENCIA_MIN) {
            Evento::query()->create(['business_id' => $businessId, 'tipo' => self::EV_PENDENCIA_GESTOR, 'user_id' => $userId,
                'dados' => ['dia' => $dia->toDateString(), 'apontado_minutos' => $apontado, 'presenca_minutos' => $presenca]]);
        }
    }

    private function evento(int $businessId, string $tipo, Apontamento $ap, ?int $userId, array $dados = []): Evento
    {
        return Evento::query()->create(['business_id' => $businessId, 'tipo' => $tipo, 'os_tipo' => $ap->os_tipo,
            'os_id' => $ap->os_id, 'posto_id' => $ap->posto_id, 'user_id' => $userId,
            'dados' => $dados + ['apontamento_id' => $ap->id, 'programacao_id' => $ap->programacao_id]]);
    }
}
