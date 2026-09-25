<?php

namespace Modules\Ponto\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Competencia;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Entities\Intercorrencia;

/**
 * Fechamento da competência (ADR 0413).
 *
 * - `preChecagem()` só LÊ: conta o que já existe em apuração, intercorrências, cadastro e
 *   importações. Não recalcula nada — reapurar é `ReapurarDiaJob`, fora daqui.
 * - `fechar()` grava UMA linha em `ponto_competencias` (W1) com quem, quando e quais bloqueios
 *   foram aceitos (D2 + W3). Não toca `ponto_marcacoes` (append-only, Portaria MTP 671/2021),
 *   nem apuração, nem banco de horas. "Reabrir" não existe (D1).
 * - Não gera nem condiciona AFD/AEJ (D4 + W3): o arquivo fiscal vive em Relatórios.
 *
 * Queries com `business_id` explícito: o service roda igual em request e em CLI.
 */
class FechamentoService
{
    /**
     * Bloqueios da pré-checagem. `grave` = impede fechar sem aceite explícito.
     *
     * @return array<int, array{id:string, grave:bool, n:int}>
     */
    public function preChecagem(int $businessId, CarbonImmutable $competencia): array
    {
        [$ini, $fim] = $this->intervalo($competencia);

        $apuracao = DB::table('ponto_apuracao_dia')
            ->where('business_id', $businessId)
            ->whereBetween('data', [$ini, $fim]);

        return [
            // Dia em DIVERGENCIA não consolida (marcação ímpar, falta sem justificativa…).
            ['id' => 'divergencia', 'grave' => true,
                'n' => (clone $apuracao)->where('estado', ApuracaoDia::ESTADO_DIVERGENCIA)->count()],
            // Intercorrência não decidida: a correção ficaria fora do mês.
            ['id' => 'intercorrencia', 'grave' => true,
                'n' => DB::table('ponto_intercorrencias')->where('business_id', $businessId)
                    ->whereNull('deleted_at')
                    ->whereIn('estado', [Intercorrencia::ESTADO_RASCUNHO, Intercorrencia::ESTADO_PENDENTE])
                    ->whereBetween('data', [$ini, $fim])->count()],
            // Violação já APURADA de Art. 66 (interjornada 11h) ou Art. 71 (intrajornada) da CLT.
            ['id' => 'clt', 'grave' => true,
                'n' => (clone $apuracao)->where(function ($q) {
                    $q->where('interjornada_violacao_minutos', '>', 0)
                      ->orWhere('intrajornada_violacao_minutos', '>', 0);
                })->count()],
            // Sem PIS a linha do AFD é rejeitada (Portaria MTP 671/2021 Anexo I).
            ['id' => 'sem_pis', 'grave' => false,
                'n' => DB::table('ponto_colaborador_config')->where('business_id', $businessId)
                    ->where('controla_ponto', true)
                    ->where(fn ($q) => $q->whereNull('desligamento')->orWhere('desligamento', '>=', $ini))
                    ->where(fn ($q) => $q->whereNull('pis')->orWhere('pis', ''))->count()],
            // Importação ainda rodando: fechar agora pode deixar marcação de fora.
            ['id' => 'importacao', 'grave' => false,
                'n' => DB::table('ponto_importacoes')->where('business_id', $businessId)
                    ->whereIn('estado', [Importacao::ESTADO_PENDENTE, Importacao::ESTADO_PROCESSANDO])
                    ->count()],
        ];
    }

    /**
     * Fecha a competência. Com bloqueio grave aberto, só fecha com `$aceitarBloqueios`,
     * e os bloqueios abertos ficam registrados na linha (ADR 0413 D2 + W3).
     */
    public function fechar(int $businessId, CarbonImmutable $competencia, int $usuarioId, bool $aceitarBloqueios): Competencia
    {
        $mes = $competencia->startOfMonth()->toDateString();

        if (DB::table('ponto_competencias')->where('business_id', $businessId)->where('competencia', $mes)->exists()) {
            throw new DomainException('Competência já fechada — reabrir não existe na v1 (ADR 0413 D1).');
        }

        $abertos = array_values(array_filter($this->preChecagem($businessId, $competencia), fn ($b) => $b['n'] > 0));
        $graves = array_filter($abertos, fn ($b) => $b['grave']);

        if ($graves && ! $aceitarBloqueios) {
            throw new DomainException('Há bloqueios graves na pré-checagem: resolva-os ou feche aceitando-os.');
        }

        return Competencia::forceCreate([
            'business_id'       => $businessId,
            'competencia'       => $mes,
            'fechada_por'       => $usuarioId,
            'fechada_em'        => now(),
            'bloqueios_aceitos' => $abertos ?: null,
        ]);
    }

    /** @return array{0:string,1:string} */
    private function intervalo(CarbonImmutable $competencia): array
    {
        return [$competencia->startOfMonth()->toDateString(), $competencia->endOfMonth()->toDateString()];
    }
}
