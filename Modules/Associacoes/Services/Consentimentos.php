<?php

declare(strict_types=1);

namespace Modules\Associacoes\Services;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\ConsentimentoAssociada;

/**
 * Consentimentos LGPD da associada, por finalidade e versão (ADR 0451 D5; US-ASSOC-003).
 * Mesmo desenho do Fisio ({@see \Modules\Fisio\Services\Consentimentos}):
 *
 *  - a lista de finalidades é FECHADA e a versão vigente vem de
 *    `config('associacoes.consentimento_versoes')` — nunca do corpo do request;
 *  - só vale o aceite na versão vigente e sem `revoked_at`;
 *  - revogar preenche `revoked_at`; a linha fica como prova (Model e trigger recusam DELETE).
 *
 * O cadastro (onda 4) e o app da associada (onda 6, sem sessão de usuário) usam esta classe.
 * Por isso toda consulta filtra a empresa DA ASSOCIADA explicitamente, em vez de depender da
 * sessão (ADR 0093).
 */
class Consentimentos
{
    public const ASSOCIADA = 'associada';

    public const WHATSAPP = 'whatsapp';

    public const VISIVEL_REDE = 'visivel_rede';

    /** @return list<string> */
    public function finalidades(): array
    {
        return array_map('strval', array_keys((array) config('associacoes.consentimento_versoes', [])));
    }

    public function versaoVigente(string $finalidade): ?string
    {
        $v = config("associacoes.consentimento_versoes.{$finalidade}");

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Texto que a pessoa lê antes de aceitar, com a versão vigente.
     *
     * @return list<array{finalidade: string, versao: string, titulo: string, texto: string, obrigatorio: bool}>
     */
    public function textos(): array
    {
        $out = [];
        foreach ($this->finalidades() as $f) {
            $t = (array) config("associacoes.consentimento_textos.{$f}", []);
            $out[] = [
                'finalidade' => $f,
                'versao' => (string) $this->versaoVigente($f),
                'titulo' => (string) ($t['titulo'] ?? $f),
                'texto' => (string) ($t['texto'] ?? ''),
                'obrigatorio' => (bool) ($t['obrigatorio'] ?? false),
            ];
        }

        return $out;
    }

    public function vigente(Associada $associada, string $finalidade): bool
    {
        $versao = $this->versaoVigente($finalidade);

        return $versao !== null && $this->ativos($associada, $finalidade)->where('versao', $versao)->exists();
    }

    /** @return array<string, bool> finalidade => vigente */
    public function situacao(Associada $associada): array
    {
        $out = [];
        foreach ($this->finalidades() as $f) {
            $out[$f] = $this->vigente($associada, $f);
        }

        return $out;
    }

    /**
     * Registra o aceite na versão vigente. Já aceito nessa versão: devolve o existente, sem
     * linha nova. Finalidade fora da lista fechada é recusada.
     *
     * @return array{consentimento: ConsentimentoAssociada, criado: bool}
     */
    public function conceder(Associada $associada, string $finalidade, string $canal): array
    {
        $versao = $this->versaoVigente($finalidade);
        if ($versao === null) {
            throw new InvalidArgumentException('finalidade de consentimento desconhecida');
        }
        $existente = $this->ativos($associada, $finalidade)->where('versao', $versao)->first();
        if ($existente !== null) {
            return ['consentimento' => $existente, 'criado' => false];
        }

        $novo = ConsentimentoAssociada::withoutGlobalScopes() // SUPERADMIN: grava na empresa da PRÓPRIA associada (o app não tem sessão).
            ->create([
                'business_id' => (int) $associada->business_id,
                'associada_id' => (int) $associada->id,
                'finalidade' => $finalidade,
                'versao' => $versao,
                'concedido_em' => now(),
                'canal' => $canal,
                'revoked_at' => null,
            ]);

        return ['consentimento' => $novo, 'criado' => true];
    }

    /** Revoga todo aceite ativo da finalidade (qualquer versão). Devolve quantos revogou. */
    public function revogar(Associada $associada, string $finalidade): int
    {
        return $this->ativos($associada, $finalidade)->update(['revoked_at' => now()]);
    }

    /**
     * IDs (entre os informados) com aceite vigente da finalidade. Uma consulta para a lista
     * inteira, sem N+1.
     *
     * @param  list<int>  $associadaIds
     * @return list<int>
     */
    public function comVigente(int $businessId, array $associadaIds, string $finalidade): array
    {
        $versao = $this->versaoVigente($finalidade);
        if ($versao === null || $associadaIds === []) {
            return [];
        }

        return ConsentimentoAssociada::withoutGlobalScopes() // SUPERADMIN: filtra a empresa explicitamente (uso também fora de sessão).
            ->where('business_id', $businessId)
            ->whereIn('associada_id', $associadaIds)
            ->where('finalidade', $finalidade)
            ->where('versao', $versao)
            ->whereNull('revoked_at')
            ->pluck('associada_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return Builder<ConsentimentoAssociada> */
    private function ativos(Associada $associada, string $finalidade): Builder
    {
        return ConsentimentoAssociada::withoutGlobalScopes() // SUPERADMIN: filtra a empresa da associada explicitamente (app sem sessão).
            ->where('business_id', (int) $associada->business_id)
            ->where('associada_id', (int) $associada->id)
            ->where('finalidade', $finalidade)
            ->whereNull('revoked_at');
    }
}
