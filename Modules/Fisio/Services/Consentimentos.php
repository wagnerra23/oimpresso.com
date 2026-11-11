<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Modules\Fisio\Entities\Consent;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Study;

/**
 * Consentimentos do paciente, separados e versionados (LGPD; ADR 0437 §7; etapa 02 T-07b).
 *
 * Cada finalidade é independente: `teleatendimento` (tratamento à distância — sem ele o portal
 * não mostra o programa), `whatsapp` (lembretes e mensagens), `camera` (exercício acompanhado
 * pela câmera, F2), `imagem`, `marketing` e `treino_ia`. A lista FECHADA e a versão vigente
 * vêm de `config('fisio.consent_versions')`; a versão nunca vem do corpo do request.
 *
 * `pesquisa` é à parte (etapa 07 P-02): um consentimento POR ESTUDO, com `study_id` e a versão
 * do TCLE aprovada pelo CEP daquele estudo. Não está na lista da config, então as rotas
 * genéricas do portal não o concedem nem o revogam.
 *
 * Consentimento só vale na versão vigente e sem `revoked_at`. Revogar preenche `revoked_at`;
 * a linha fica como prova (o model recusa DELETE). Sem sessão de usuário (portal público), as
 * consultas filtram o business do paciente explicitamente (ADR 0093).
 */
class Consentimentos
{
    public const TELEATENDIMENTO = 'teleatendimento';

    public const PESQUISA = 'pesquisa';

    /** @return list<string> finalidades aceitas (lista fechada da config) */
    public function finalidades(): array
    {
        return array_map('strval', array_keys((array) config('fisio.consent_versions', [])));
    }

    public function versaoVigente(string $finalidade): ?string
    {
        $v = config("fisio.consent_versions.{$finalidade}");

        return is_string($v) && $v !== '' ? $v : null;
    }

    /** O paciente tem consentimento ativo na versão vigente desta finalidade? */
    public function vigente(Patient $paciente, string $finalidade): bool
    {
        $versao = $this->versaoVigente($finalidade);

        return $versao !== null && $this->ativos($paciente, $finalidade)->where('version', $versao)->exists();
    }

    /** @return array<string, bool> finalidade => vigente */
    public function situacao(Patient $paciente): array
    {
        $out = [];
        foreach ($this->finalidades() as $f) {
            $out[$f] = $this->vigente($paciente, $f);
        }

        return $out;
    }

    /**
     * Registra o aceite na versão vigente. Já aceito na versão vigente: devolve o existente
     * (`criado = false`), sem linha nova.
     *
     * @return array{consentimento: Consent, criado: bool}
     */
    public function conceder(Patient $paciente, string $finalidade, ?string $ip, string $canal): array
    {
        $versao = (string) $this->versaoVigente($finalidade);
        $existente = $this->ativos($paciente, $finalidade)->where('version', $versao)->first();
        if ($existente !== null) {
            return ['consentimento' => $existente, 'criado' => false];
        }

        $novo = Consent::withoutGlobalScopes() // SUPERADMIN: grava no business do PRÓPRIO paciente (portal público, sem sessão).
            ->create([
                'business_id' => (int) $paciente->business_id,
                'patient_id' => (int) $paciente->id,
                'purpose' => $finalidade,
                'version' => $versao,
                'granted_at' => now(),
                'revoked_at' => null,
                'ip' => $ip,
                'channel' => $canal,
            ]);

        return ['consentimento' => $novo, 'criado' => true];
    }

    /** Revoga todo consentimento ativo da finalidade (qualquer versão). Devolve quantos revogou. */
    public function revogar(Patient $paciente, string $finalidade): int
    {
        return $this->ativos($paciente, $finalidade)->update(['revoked_at' => now()]);
    }

    /**
     * Aceite do TCLE de um estudo, na versão aprovada pelo CEP. Estudo de outra empresa ou fora
     * de `ativo` é recusado: o paciente só participa de estudo da própria clínica.
     *
     * @return array{consentimento: Consent, criado: bool}
     */
    public function concederPesquisa(Patient $paciente, Study $estudo, ?string $ip, string $canal): array
    {
        if ((int) $estudo->business_id !== (int) $paciente->business_id) {
            throw new \InvalidArgumentException('estudo de outra empresa');
        }
        if ($estudo->status !== Study::STATUS_ATIVO) {
            throw new \InvalidArgumentException('estudo fora de andamento');
        }

        $versao = (string) $estudo->tcle_version;
        $existente = $this->ativosPesquisa($paciente, $estudo)->where('version', $versao)->first();
        if ($existente !== null) {
            return ['consentimento' => $existente, 'criado' => false];
        }

        $novo = Consent::withoutGlobalScopes() // SUPERADMIN: grava no business do PRÓPRIO paciente (sem sessão no portal).
            ->create([
                'business_id' => (int) $paciente->business_id,
                'patient_id' => (int) $paciente->id,
                'purpose' => self::PESQUISA,
                'study_id' => (int) $estudo->id,
                'version' => $versao,
                'granted_at' => now(),
                'revoked_at' => null,
                'ip' => $ip,
                'channel' => $canal,
            ]);

        return ['consentimento' => $novo, 'criado' => true];
    }

    /** O paciente aceitou o TCLE na versão vigente deste estudo, sem revogar? */
    public function pesquisaVigente(Patient $paciente, Study $estudo): bool
    {
        return (int) $estudo->business_id === (int) $paciente->business_id
            && $this->ativosPesquisa($paciente, $estudo)->where('version', (string) $estudo->tcle_version)->exists();
    }

    /** Revoga o consentimento de pesquisa deste estudo (qualquer versão). Devolve quantos revogou. */
    public function revogarPesquisa(Patient $paciente, Study $estudo): int
    {
        return $this->ativosPesquisa($paciente, $estudo)->update(['revoked_at' => now()]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Consent> */
    private function ativosPesquisa(Patient $paciente, Study $estudo)
    {
        return $this->ativos($paciente, self::PESQUISA)->where('study_id', (int) $estudo->id);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Consent> */
    private function ativos(Patient $paciente, string $finalidade)
    {
        return Consent::withoutGlobalScopes() // SUPERADMIN: portal público sem sessão; filtra pelo business do paciente.
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', (int) $paciente->id)
            ->where('purpose', $finalidade)
            ->whereNull('revoked_at');
    }
}