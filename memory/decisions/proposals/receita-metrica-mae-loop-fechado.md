---
title: Receita é a métrica-mãe — Loop Fechado Anti-Drift obrigatório
status: proposed
date: 2026-05-31
author: Wagner (dono/operador)
proposto_por: Claude (sessão frosty-greider-83ab2f)
relates: [0094, 0105, 0091, 0226, 0022, 0026, 0070]
implements: "Constituição v2 — Princípio duro 4 (Loop fechado por métrica)"
---

# ADR (proposta) — Receita é a métrica-mãe + Loop Fechado Anti-Drift obrigatório

> **Ratificar pra virar canon** (próximo número livre ~0241). Implementa um princípio que a Constituição v2 já declara mas nunca foi aplicado à métrica que mais importa.

## Status
**Proposed** — Wagner aprovou a construção do loop completo em 2026-05-31 ("Sim — o loop completo"). Aguarda ratificação como ADR canon.

## Contexto

Auditoria do norte (2026-05-31, sessão de gerência):

- **O destino está certo** (R$5M ARR — [ADR 0022](../0022-meta-5mi-ano-financeira.md); posicionamento ERP vertical + IA — [ADR 0026](../0026-posicionamento-erp-grafico-com-ia.md)), **mas a navegação diária não aponta pra ele.**
- **Drift 104/104:** os 104 commits/PRs dos últimos 7 dias NÃO tocaram nenhuma task do cycle ativo (CYCLE-07 "Fundações pós-4.8"). Pior: ao fechar o CYCLE-07, **0 tasks rolaram** — o cycle não tinha NENHUMA task rastreada. O trabalho acontecia inteiramente fora do sistema de medição.
- **A saúde do sistema é medida em engenharia, não em negócio:** module grades, DS conformance, tsc verde, Pest, `jana:health-check` (5 checks: multi_tenant, brief_uptime, custo_brain_b, pii_leak, profile_distiller). **Nenhuma métrica de receita** (MRR, pagantes, migrações, conversão).
- **O princípio `cliente-como-sinal` ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) está sendo violado na prática:** com 1 cliente pagante real, construímos 4 verticais + DS v3→v4 + deprecações sem sinal de cliente pagante.
- **A Constituição v2 ([ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md)) já manda "Princípio duro 4 — Loop fechado por métrica".** Declaramos o princípio e nunca o implementamos pra receita.

**O detector existia e não bastou:** o próprio Daily Brief ([ADR 0091](../0091-daily-brief.md)) já emite "⚠️ Cycle drift detectado" (`BriefFetchTool::renderCycleDriftAlert`). Mesmo assim o sistema derivou por semanas. **Aviso passivo não é mecanismo.** Trocar o cycle uma vez (CYCLE-08 Receita, feito 2026-05-31) muda o destino — mas sem um loop que MEÇA, MOSTRE e FORCE correção, a recalibração tem prazo de validade.

Reforço de urgência (pesquisa de mercado 2026-05-31, 28 streams): a **Reforma Tributária 2026 (IBS/CBS/NFS-e nacional)** vai forçar a base legada Delphi/Firebird a trocar de sistema de qualquer jeito; o **canal contábil** (Omie dá ERP grátis a 28k contadores) aspira o lead da PME antes do oimpresso aparecer. A janela pra monetizar a carteira morna é AGORA — o que torna "receita = métrica-mãe" não opcional.

## Decisão

**1. Receita é a métrica-mãe do oimpresso.** Enquanto o negócio estiver em estágio de monetização (< ~30 clientes pagantes), a saúde do sistema é medida primeiro por **MRR + clientes pagantes + migrações + movimento de pipeline** — não por qualidade de engenharia. Engenharia é meio, não fim.

**2. `cliente-como-sinal` passa a ser enforced, não só declarado.** Só entra trabalho com sinal de cliente pagante (paga + reporta, OU métrica detecta drift, OU Wagner sinaliza comercial direto — [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)). Exceção exige justificativa de sinal explícita (ex.: ComVis V1 destrava ~30 gráficas da carteira; boletos multi-banco = cliente real Martinho/Larissa).

**3. Loop Fechado Anti-Drift obrigatório (5 camadas)** — implementa o Princípio duro 4 pra receita:

| # | Camada | O que faz | Status |
|---|---|---|---|
| 1 | **Sensor** (`oimpresso:revenue-pulse`) | mede MRR, pagantes ativos, novos 7d, estágios do pipeline | 📦 pronto (SQL abaixo) — deploy Wagner |
| 2 | **Brief mostra** | Daily Brief abre com `## RECEITA` + flag de drift comercial | 📦 pronto (patches abaixo) — deploy servidor MCP |
| 3 | **Hook que FORÇA** | SessionStart reabre o frame de receita + exige ação comercial do dia | ✅ construído (`receita-loop-check.ps1`, registrado em settings.json) |
| 4 | **Ritual semanal** | sexta: atualiza placar + cycle-goals-track + 3 perguntas | 📋 disciplina (skill futura opcional) |
| 5 | **ADR canon** | torna tudo Tier 0 durável, pra não re-litigar | 📄 este documento |

## Implementação pronta pra deploy

### Camada 1 — Sensor `oimpresso:revenue-pulse`
Schema confirmado (exploração 2026-05-31): `subscriptions` (business_id, package_id, package_price, status, start_date, end_date) + `packages` (price, interval, interval_count). Cobrança de tenant é **real e funcional** (listeners `OnCobrancaPagaUpdateSubscription` / `OnCobrancaVencidaBloqueia`, ADR 0170). SQL canônico:

```sql
-- MRR + pagantes ativos
SELECT
  COUNT(DISTINCT s.business_id) AS pagantes,
  COALESCE(SUM(CASE
    WHEN p.`interval`='months' THEN s.package_price / NULLIF(p.interval_count,0)
    WHEN p.`interval`='years'  THEN s.package_price / NULLIF(p.interval_count*12,0)
    WHEN p.`interval`='days'   THEN s.package_price / NULLIF(p.interval_count/30.0,0)
  END),0) AS mrr_brl
FROM subscriptions s
JOIN packages p ON p.id = s.package_id
WHERE s.status='approved'
  AND DATE(s.start_date) <= CURDATE()
  AND DATE(s.end_date)   >= CURDATE();

-- Novos pagantes 7d (movimento — alimenta o flag de drift comercial)
SELECT COUNT(*) AS novos_7d
FROM subscriptions
WHERE status='approved' AND created_at >= NOW() - INTERVAL 7 DAY;
```

Comando artisan (esqueleto — `app/Console/Commands/` ou `Modules/Superadmin/Console/`):
```php
// php artisan oimpresso:revenue-pulse  → imprime MRR, pagantes, novos 7d, pipeline
// Cross-tenant explícito (platform query, business_id=null) — ver skill multi-tenant-patterns.
// Lê pipeline do placar markdown OU futura tabela mcp_pipeline_stages.
```

### Camada 2 — Brief mostra `## RECEITA` (4 toques, servidor MCP — ADR 0226 gerador v2)
1. **Stored procedure** (`refresh_brief_inputs_cache`, migration `2026_05_07_120000`): add coluna `receita_json` em `mcp_brief_inputs_cache` populada com o SQL acima.
2. **`Modules/Brief/Services/BriefGeneratorService.php`** (system prompt ~L140-219): add seção `## RECEITA` no TOPO (MRR, pagantes, novos 7d, % dos goals do cycle Receita).
3. **`Modules/Brief/Services/BriefValidator.php`** (`REQUIRED_HEADERS` ~L23-31): add `'## RECEITA'` na ordem (após ESTADO MACRO ou no topo).
4. **`Modules/Brief/Mcp/Tools/BriefFetchTool.php`** (`renderCycleDriftAlert` ~L101-162): estender pra **drift comercial** — se `novos_7d=0` E `cycle Receita >X% decorrido` → "⚠️ Drift comercial: 0 clientes novos em 7d, cycle Receita N% decorrido. Próxima ação comercial?".

### Camada 3 — Hook forçador ✅ JÁ CONSTRUÍDO
`.claude/hooks/receita-loop-check.ps1` (SessionStart, modelo `brief-fetch-curl.ps1`). Chama `cycles-active`; se o cycle ativo é de Receita, injeta o frame + exige a ação comercial do dia + a regra `cliente-como-sinal`. Registrado em `.claude/settings.json` SessionStart. Falha graciosa (nunca bloqueia).

### Camada 4 — Ritual semanal
Sexta: `cycle-goals-track cycle:CYCLE-08` + atualizar placar [`_pipeline-migracao-legacy.md`](../../clientes/_pipeline-migracao-legacy.md) + 3 perguntas (quantos clientes toquei? quanto MRR? o que trava?). Opcional virar skill Tier B `revenue-weekly-review` ou `/schedule` sexta 17h.

## Consequências

**Positivas:** o sistema se auto-corrige — toda sessão reabre o frame de receita; o brief mostra MRR no topo; drift comercial vira alerta ativo, não silêncio. A recalibração de cycle (CYCLE-08) deixa de ter prazo de validade. `cliente-como-sinal` vira enforced.

**Custos/riscos:** (a) o hook adiciona 1 chamada MCP/sessão (cache, ~silencioso fora de cycle Receita); (b) o sensor + brief precisam de deploy no servidor MCP (CT 100/Hostinger) — não é mudança local; (c) risco de "alerta-fadiga" se o forçador for ignorado — mitigado por ser SessionStart (1×/sessão) e condicional ao cycle Receita.

## Métricas de sucesso (como sabemos que o loop funciona)
- **Drift comercial → 0:** semanas com `novos_7d=0` durante cycle Receita caem a zero (ou viram decisão explícita "essa semana é produto com sinal X").
- **% commits alinhados ao cycle Receita sobe** (inverte o 104/104).
- **MRR no brief é visível e cresce** vs o target do CYCLE-08 (R$2.000).

## Refs
- [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) Princípio duro 4 · [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) cliente-como-sinal · [ADR 0091](../0091-daily-brief.md) Daily Brief · [ADR 0226] gerador brief v2 · [ADR 0022](../0022-meta-5mi-ano-financeira.md) meta R$5M
- [Plano de Crescimento 2026-05-31](../../sessions/2026-05-31-plano-crescimento-oimpresso.md) · [Placar pipeline](../../clientes/_pipeline-migracao-legacy.md) · CYCLE-08 Receita (MCP)
