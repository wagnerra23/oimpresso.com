<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Exceptions\ContingenciaException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;

/**
 * US-NFE-006 / ADR TECH-0002 — ativação e desativação de contingência POR TENANT.
 *
 * A ADR REJEITOU auto-ativação, com razão declarada: "pode ativar em falsa-detecção
 * (rede do servidor caiu, não SEFAZ)". Logo este serviço só é chamado por ato humano.
 * Quem observa a SEFAZ (SefazHealthCheckJob) pode SUGERIR — nunca chamar `ativar()`.
 *
 * `$businessId` é sempre EXPLÍCITO, nunca lido de `session()`: este serviço é chamável
 * de job/command, onde não há sessão (ADR 0093 — multi-tenant Tier 0).
 */
class ContingenciaService
{
    /**
     * Liga a contingência do tenant.
     *
     * Re-ativar um tenant JÁ em contingência NÃO reseta `contingencia_ativada_em`.
     * Isso é deliberado: a ADR TECH-0002 lista como risco operacional o tenant esquecer
     * a contingência ligada, e mitiga com o banner "ATIVA há N dias". Se cada clique
     * reiniciasse o relógio, o banner nunca envelheceria e a mitigação viraria enfeite.
     *
     * US-NFE-071: `$modo` escolhe EPEC, SVC-AN ou SVC-RS para a NF-e (ModoContingencia).
     * SVC só é aceito se for a SEFAZ Virtual da UF do emitente — a outra recusaria as notas,
     * e descobrir isso na primeira emissão é pior que recusar a ativação.
     *
     * @param  string  $motivo  Justificativa declarada — exigência de auditoria fiscal.
     */
    public function ativar(
        int $businessId,
        string $motivo,
        ?User $causer = null,
        string $modo = ModoContingencia::EPEC,
    ): NfeBusinessConfig {
        $config = $this->configDo($businessId);

        $motivo = trim($motivo);

        if ($motivo === '') {
            // O fiscal pergunta POR QUE a nota saiu com tpEmis != 1. "Porque alguém clicou"
            // não é resposta — por isso o motivo é barreira, não campo opcional.
            throw ContingenciaException::motivoObrigatorio();
        }

        if (! in_array($modo, ModoContingencia::todos(), true)) {
            throw ContingenciaException::modoInvalido($modo);
        }

        if (ModoContingencia::ehSvc($modo)) {
            $uf = $this->ufDoEmitente($businessId);
            $daUf = ModoContingencia::svcDaUf($uf);
            if ($daUf !== $modo) {
                throw ContingenciaException::svcForaDaUf($modo, $uf, $daUf);
            }
        }

        $jaEstava = (bool) $config->em_contingencia;

        $config->update([
            'em_contingencia' => true,
            'contingencia_motivo' => $motivo,
            'contingencia_modo' => $modo,
            'contingencia_ativada_em' => $jaEstava
                ? $config->contingencia_ativada_em   // preserva o relógio original
                : now(),
        ]);

        activity('nfe.contingencia')
            ->causedBy($causer)
            ->performedOn($config)
            ->withProperties([
                'business_id' => $businessId,
                'motivo' => $motivo,
                'modo' => $modo,
                'ja_estava_ativa' => $jaEstava,
            ])
            ->log('contingencia.ativada');

        return $config->refresh();
    }

    /**
     * Desliga a contingência. Não mexe em emissão nenhuma: as notas já gravadas com
     * status `contingencia` continuam aguardando o RetentarContingenciaJob. Desativar
     * é dizer "as PRÓXIMAS saem normais", nunca "as anteriores foram transmitidas".
     */
    public function desativar(int $businessId, ?User $causer = null): NfeBusinessConfig
    {
        $config = $this->configDo($businessId);

        $config->update([
            'em_contingencia' => false,
            'contingencia_ativada_em' => null,
            'contingencia_motivo' => null,
            'contingencia_modo' => null,
        ]);

        activity('nfe.contingencia')
            ->causedBy($causer)
            ->performedOn($config)
            ->withProperties(['business_id' => $businessId])
            ->log('contingencia.desativada');

        return $config->refresh();
    }

    public function estaAtiva(int $businessId): bool
    {
        return (bool) $this->configDo($businessId)->em_contingencia;
    }

    /**
     * UF do emitente — mesma fonte de `NfeService::resolverUF` (o primeiro local da empresa),
     * para a checagem da ativação olhar a UF que a emissão vai usar. Sem UF válida devolve
     * null e o SVC é recusado: não se escolhe SEFAZ Virtual às cegas.
     */
    private function ufDoEmitente(int $businessId): ?string
    {
        $state = (string) (DB::table('business_locations')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->value('state') ?? '');

        return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
    }

    /**
     * Busca a config SEM depender do global scope de sessão — o serviço roda em job/command
     * também. `withoutGlobalScopes` seria a saída errada aqui: filtramos explicitamente
     * pelo business_id recebido, que é mais restrito, não menos.
     */
    private function configDo(int $businessId): NfeBusinessConfig
    {
        $config = NfeBusinessConfig::query()
            ->withoutGlobalScopes() // SUPERADMIN: serviço roda fora de sessão (job/command); o filtro explícito abaixo é o escopo.
            ->where('business_id', $businessId)
            ->first();

        if (! $config) {
            throw ContingenciaException::semConfigFiscal($businessId);
        }

        return $config;
    }
}
