<?php

declare(strict_types=1);

namespace App\Sidebar;

/**
 * Sidebar v3 — 5 grupos canônicos + 3 topo + fallback.
 *
 * ADR 0180 (2026-05-21): substitui as 11 keys legadas do v2 por 5 keys verbo-PT-BR
 * (VENDER · OPERAR · FINANÇAS · PESSOAS · SISTEMA) + 3 fixos no topo
 * (IA · ATENDIMENTO · EQUIPE). LEGACY_GROUP_MAP cobre migração faseada.
 *
 * Wagner regra 2026-05-19: DataController declara `group`, frontend não hardcode.
 */
enum SidebarGroup: string
{
    // ── Topo (sempre visível, sem header de grupo) ──────────────────────
    case Ia          = 'ia';
    case Atendimento = 'atendimento';
    case Equipe      = 'equipe';

    // ── 5 grupos canônicos ──────────────────────────────────────────────
    case Vender   = 'vender';
    case Operar   = 'operar';
    case Financas = 'financas';
    case Pessoas  = 'pessoas';
    case Sistema  = 'sistema';

    // ── Fallback (collapse fechado por default) ─────────────────────────
    case Mais = 'mais';

    /**
     * LEGACY_GROUP_MAP — converte keys do sidebar v2 pros 5 grupos v3.
     *
     * Permite migração modulo-a-modulo: DataControllers não-migrados
     * ainda declaram `'group' => 'office'|'fin-op'|...` e caem no grupo
     * canônico v3 correto via este mapeamento.
     */
    public static function fromLegacy(string $key): self
    {
        return match ($key) {
            // v2 → v3
            'office'                                        => self::Vender,
            'oficina', 'estoque'                            => self::Operar,
            'fin', 'fin-op', 'fin-analise', 'fin-config',
            'fiscal'                                        => self::Financas,
            'rh'                                            => self::Pessoas,
            'conhecimento', 'rel'                           => self::Ia,
            'governanca', 'plataforma'                      => self::Sistema,

            // v3 pass-through (já está canônico)
            default => self::tryFrom($key) ?? self::Mais,
        };
    }

    /**
     * Grupos canônicos v3 (sem topo nem fallback) — usado em validação
     * e em testes que querem garantir cobertura dos 5.
     */
    public static function canonical(): array
    {
        return [self::Vender, self::Operar, self::Financas, self::Pessoas, self::Sistema];
    }
}
