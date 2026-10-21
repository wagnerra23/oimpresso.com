<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Modules\Fisio\Entities\Consent;
use Modules\Fisio\Entities\Patient;

/**
 * Consentimentos do paciente, separados e versionados (LGPD; ADR 0437 §7; etapa 02 T-07b).
 *
 * Cada finalidade é independente: `teleatendimento` (tratamento à distância — sem ele o portal
 * não mostra o programa), `whatsapp` (lembretes e mensagens), `camera` (exercício acompanhado
 * pela câmera, F2), `imagem`, `marketing` e `pesquisa_ia`. A lista FECHADA e a versão vigente
 * vêm de `config('fisio.consent_versions')`; a versão nunca vem do corpo do request.
 *
 * Consentimento só vale na versão vigente e sem `revoked_at`. Revogar preenche `revoked_at`;
 * a linha fica como prova (o model recusa DELETE). Sem sessão de usuário (portal público), as
 * consultas filtram o business do paciente explicitamente (ADR 0093).
 */
class Consentimentos
{
    public const TELEATENDIMENTO = 'teleatendimento';

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