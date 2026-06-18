<?php

declare(strict_types=1);

namespace App\Support\Errors;

/**
 * Severity — a régua S0–S3 do Plano Sustentável de Erros (Fase 1 · E-1).
 *
 * Princípio: só o **S0** interrompe 1 humano (o "cano do alerta"). O resto vai
 * pra log/dashboard (OTel + mcp_audit_log que já existem). Crawl, não boil-the-ocean.
 *
 * Fonte da régua: Mapa de Severidade do Oimpresso (rascunho [W]).
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
enum Severity: string
{
    /** Crítico — interrompe humano. Pagamento fora · conciliação · cross-tenant · ERP/auth/DB fora · backup destrutivo. */
    case S0 = 'S0';

    /** Alto — o dono precisa ver. Emissão fiscal falhou · Baileys off · OS travada · boleto falhou p/ 1. */
    case S1 = 'S1';

    /** Médio — recuperável, só dashboard. Erro-rate subindo · cert a vencer · webhook atrasado · gate vermelho. */
    case S2 = 'S2';

    /** Baixo/ruído — esperado/tratado. ValidationException, exceções conhecidas. **Default**. */
    case S3 = 'S3';

    /**
     * SLA-alvo (minutos) pra primeira resposta humana. 0 = sem SLA (só dashboard/log).
     * Informativo nesta fase — o enforcement de SLA é Fase 2/3.
     */
    public function slaMinutes(): int
    {
        return match ($this) {
            self::S0 => 15,
            self::S1 => 240,
            self::S2 => 1440,
            self::S3 => 0,
        };
    }

    /** Só o S0 interrompe 1 humano. Todas as outras vão pra log/dashboard sem alertar. */
    public function interrompeHumano(): bool
    {
        return $this === self::S0;
    }
}
