<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use App\User;
use Closure;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeRevisaoContador;
use Throwable;

/**
 * Revisão do contador (playbook Fiscal thread 15a · D-SUPORTE · D-CONTADOR).
 *
 * - Toda versão nova de regra (criada, editada pela tela, aplicada por CSV, aceita da Jana) gera
 *   uma revisão `pendente` com o que mudou. Quem chama não precisa lembrar: o model chama
 *   `registrar()` nos eventos dele e no `novaVersao()`.
 * - Aceitar exige a permissão PRÓPRIA `nfe.tributacao.aceitar`, checada direto no Spatie.
 * - Falta de aceite NÃO bloqueia nada: emissão e motor não consultam esta tabela.
 */
class RevisaoContadorService
{
    /** Campos de regra que entram no de → para. */
    public const CAMPOS = [
        'ncm', 'uf_origem', 'uf_destino', 'operacao_id', 'cfop', 'csosn', 'cst',
        'aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi', 'mva', 'fcp',
        'c_class_trib', 'cst_ibs', 'cst_cbs', 'aliquota_ibs', 'aliquota_cbs', 'valida_de',
    ];

    /** @var list<string> pilha de origem — `comOrigem()` empilha, o padrão é "manual" */
    private static array $origens = [];

    private static ?bool $tabela = null;

    /** Executa `$fn` marcando a origem das versões que ela criar (csv · jana · template). */
    public static function comOrigem(string $origem, Closure $fn): mixed
    {
        self::$origens[] = $origem;
        try {
            return $fn();
        } finally {
            array_pop(self::$origens);
        }
    }

    public static function esquecerTabela(): void
    {
        self::$tabela = null;
    }

    /**
     * Registra a revisão pendente de uma versão de regra.
     *
     * @param array<string, mixed>|null $antes atributos da versão anterior (null = regra nova)
     */
    public static function registrar(NfeFiscalRule $regra, ?array $antes, ?int $anteriorId = null): void
    {
        // Sem a migração 2026_10_07_000005 (schema de teste SQLite montado à mão) não há onde gravar.
        if (! (self::$tabela ??= Schema::hasTable('nfe_revisoes_contador'))) {
            return;
        }

        $diff = [];
        foreach (self::CAMPOS as $c) {
            $de   = $antes === null ? null : self::normalizar($antes[$c] ?? null);
            $para = self::normalizar($regra->getAttribute($c));
            if ($de !== $para) {
                $diff[$c] = [$de, $para];
            }
        }
        if ($diff === []) {
            return; // ex.: só valida_ate/updated_at mudaram — não há o que conferir
        }

        NfeRevisaoContador::query()->create([
            'business_id'       => (int) $regra->business_id,
            'regra_id'          => (int) $regra->id,
            'regra_anterior_id' => $anteriorId,
            'origem'            => self::$origens === [] ? 'manual' : (string) end(self::$origens),
            'autor_id'          => auth()->id(),
            'diff'              => $diff,
            'status'            => 'pendente',
        ]);
    }

    /**
     * Pode aceitar? Checa a permissão DIRETO no Spatie (`hasPermissionTo`), não por `can()`.
     *
     * POR QUÊ: o `Gate::before` do `AuthServiceProvider` devolve `true` para qualquer ability
     * quando o usuário tem o papel `Admin#{business_id}`. Com `can()`, o dono da empresa
     * "aceitaria" pelo contador — exatamente o que a D-CONTADOR proíbe. `hasPermissionTo` lê só as
     * permissões do papel/usuário e não passa pelo Gate. O `AuthServiceProvider` não foi tocado.
     */
    public static function podeAceitar(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        try {
            return $user->hasPermissionTo('nfe.tributacao.aceitar');
        } catch (Throwable) {
            return false; // permissão ainda não criada no banco = ninguém tem
        }
    }

    public function aceitar(NfeRevisaoContador $r, User $user, ?string $ip): NfeRevisaoContador
    {
        $this->exigirPendente($r);

        $r->forceFill([
            'status'             => 'aceita',
            'aceito_por_nome'    => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
            'aceito_por_email'   => $user->email,
            'aceito_por_user_id' => $user->id,
            'aceito_em'          => now(),
            'aceito_ip'          => $ip,
        ])->save();

        activity('nfe.tributacao')->causedBy($user)->performedOn($r)
            ->withProperties(['business_id' => $r->business_id, 'regra_id' => $r->regra_id])
            ->log('aceite.registrado');

        return $r;
    }

    public function pedirAjuste(NfeRevisaoContador $r, User $user, string $comentario): NfeRevisaoContador
    {
        $this->exigirPendente($r);

        $r->forceFill(['status' => 'ajuste_pedido', 'comentario' => $comentario])->save();

        activity('nfe.tributacao')->causedBy($user)->performedOn($r)
            ->withProperties(['business_id' => $r->business_id, 'regra_id' => $r->regra_id])
            ->log('aceite.ajuste_pedido');

        return $r;
    }

    private function exigirPendente(NfeRevisaoContador $r): void
    {
        if ($r->status !== 'pendente') {
            throw ValidationException::withMessages(['status' => 'Esta revisão já foi decidida.']);
        }
    }

    private static function normalizar(mixed $v): mixed
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v) && ! is_string($v)) {
            return round((float) $v, 4);
        }
        if (is_string($v) && is_numeric($v) && str_contains($v, '.')) {
            return round((float) $v, 4);
        }

        return $v === '' ? null : $v;
    }
}
