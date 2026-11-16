<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Log;

/**
 * Empresa (business_id) em que o código está rodando — ADR 0453.
 *
 * Ordem de resolução: valor definido explicitamente (job/comando) → sessão web
 * (`user.business_id`) → usuário autenticado no guard em uso (API com token, onde não há sessão).
 *
 * Os global scopes leem daqui. O que fazem quando não há empresa depende de
 * `config('tenancy.escopo_sem_contexto')`: `aberto` (legado, não filtra), `log` (não filtra e
 * registra) ou `fechado` (lança ContextoEmpresaAusente). Código que lê TODAS as empresas de
 * propósito roda dentro de comoPlataforma().
 *
 * #[Scoped]: uma instância por request/job (o container zera entre eles).
 */
#[Scoped]
final class ContextoEmpresa
{
    public const ABERTO = 'aberto';

    public const LOG = 'log';

    public const FECHADO = 'fechado';

    private ?int $definido = null;

    private int $plataforma = 0;

    public function definir(int $businessId): void
    {
        $this->definido = $businessId > 0 ? $businessId : null;
    }

    public function limpar(): void
    {
        $this->definido = null;
    }

    public function atual(): ?int
    {
        if ($this->definido !== null) {
            return $this->definido;
        }

        $daSessao = (int) session('user.business_id');
        if ($daSessao > 0) {
            return $daSessao;
        }

        $doUsuario = auth()->check() ? (int) (auth()->user()->business_id ?? 0) : 0;

        return $doUsuario > 0 ? $doUsuario : null;
    }

    /** @throws ContextoEmpresaAusente */
    public function exigir(): int
    {
        return $this->atual() ?? throw new ContextoEmpresaAusente('Nenhuma empresa definida no contexto.');
    }

    /**
     * Roda $fn sem filtro de empresa, de propósito (comando/job de plataforma). Fica registrado.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function comoPlataforma(string $motivo, callable $fn): mixed
    {
        Log::info('tenancy.como_plataforma', ['motivo' => $motivo]);
        $this->plataforma++;
        try {
            return $fn();
        } finally {
            $this->plataforma--;
        }
    }

    /**
     * Empresa que um global scope de MÓDULO deve aplicar, ou null para não filtrar.
     * - `aberto`: semântica legada desses scopes — só a sessão web.
     * - `log`/`fechado`: contexto (explícito → sessão → usuário do token); sem empresa,
     *   semEmpresa() registra (log) ou lança (fechado). Dentro de comoPlataforma(): null.
     *
     * @throws ContextoEmpresaAusente
     */
    public function empresaDoEscopo(string $model): ?int
    {
        if (self::modo() === self::ABERTO) {
            $daSessao = (int) session('user.business_id');

            return $daSessao > 0 ? $daSessao : null;
        }

        if ($this->emModoPlataforma()) {
            return null;
        }

        $businessId = $this->atual();
        if ($businessId === null) {
            $this->semEmpresa($model);
        }

        return $businessId;
    }

    public function emModoPlataforma(): bool
    {
        return $this->plataforma > 0;
    }

    public static function modo(): string
    {
        $modo = (string) config('tenancy.escopo_sem_contexto', self::ABERTO);

        return in_array($modo, [self::ABERTO, self::LOG, self::FECHADO], true) ? $modo : self::ABERTO;
    }

    /**
     * Decide o que um scope faz quando a consulta não tem empresa: devolve true se pode seguir sem
     * filtro (aberto/log/plataforma) e lança no modo fechado.
     *
     * @throws ContextoEmpresaAusente
     */
    public function semEmpresa(string $model): bool
    {
        if ($this->emModoPlataforma()) {
            return true;
        }

        $modo = self::modo();
        if ($modo === self::FECHADO) {
            throw new ContextoEmpresaAusente("Consulta em {$model} sem empresa no contexto (ADR 0453).");
        }
        if ($modo === self::LOG) {
            Log::warning('tenancy.escopo_sem_contexto', [
                'model' => $model,
                'rota' => app()->runningInConsole() ? null : (request()->route()?->getName() ?? request()->path()),
                'console' => app()->runningInConsole(),
            ]);
        }

        return true;
    }
}
