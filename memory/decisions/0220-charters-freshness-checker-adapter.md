---
slug: 0220-charters-freshness-checker-adapter
number: 220
title: "ChartersFreshnessChecker — adapter pattern do charter:audit existente"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-28"
module: governance
tags: [governance, charter, freshness]
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0216-governance-drift-framework-driftchecker-plugavel
pii: false
---

## Contexto

ADR 0216 PR1 entregou 4 dos 5 checkers Top 5 priorizados. Faltou **#5 ChartersFreshnessChecker** — pulado deliberadamente porque `charter:audit` (407 linhas) + `charter:health` (63 linhas) JÁ existiam em `Modules/Governance/Console/Commands/`. Implementar novo scan duplicaria lógica.

Princípio Constituição v2 **"Charter > Spec"** ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3, [ADR 0101](0101-page-charter-contract-vivo.md)) demanda monitoring contínuo de charters fresh — sem isso, contract vivo das telas vira documentação morta.

Hoje a defesa é:
- `charter:audit --json` — detecta 4 categorias: stale, invalid_frontmatter, missing_sections, tsx_without_charter
- `charter:health --notify` — cron 06:30 BRT, loga ALERT estruturado

Faltam:
- Integração no framework `governance:drift` channel Centrifugo (Brief Jana ingere)
- Persistência em `mcp_alertas_eventos` para tracking histórico (séries temporais drift)
- Filtros uniformes (--tag, --cadence) consistentes com outros checkers
- Severity/enforcement explícitos (charter:health retorna só exit 0/1 binário)

## Decisão

Implementar `Modules\Governance\Services\Checkers\ChartersFreshnessChecker` (`name='charters_freshness'`) **como adapter** sobre `charter:audit` existente — não duplicar lógica sofisticada.

### Adapter mechanics

```php
public function check(array $opts = []): DriftCheckResult
{
    Artisan::call('charter:audit', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);
    // converte 4 arrays → DriftFinding[] com severity por categoria
}
```

### Categoria → severity mapping

| Categoria | Severity | Rationale |
|---|---|---|
| `stale` (last_validated > TTL) | **medium** | Documentação defasada — risco moderado decisões erradas |
| `invalid_frontmatter` (8 campos obrigatórios) | **medium** | Parser quebra; charter-fetch tool MCP retorna inválido |
| `missing_sections` (8 seções obrigatórias) | **low** | Charter compila, faltam apenas headers — qualidade baixa, não bloqueante |
| `tsx_without_charter` (gap cobertura) | **low** | Advisory — Tier C low-traffic aceitável sem charter |

### Convenções canon (consistentes ADR 0216)

- Severity baseline: `medium`
- Enforcement: `warn` (não bloqueia merge)
- Cadence: `daily`
- Tags: `['tier_2', 'compliance', 'charter', 'ui_governance']`
- Persistência: `mcp_alertas_eventos` tipo `drift_charters_freshness`
- Centrifugo: channel canônico `governance:drift`

### Back-compat preservado

- `charter:audit` e `charter:health` continuam funcionando independentemente
- Cron 06:30 BRT (`charter:health --notify`) NÃO removido — defesa em profundidade
- ADR 0216 §D10 canary 7d: `governance:audit --check=charters_freshness` roda em paralelo com `charter:health` por 7 dias antes de qualquer cleanup

## Não-goals

- ❌ **Não duplica lógica CharterAuditCommand** — adapter delega via Artisan::call
- ❌ **Não substitui `charter:audit` standalone** — humano continua usando interativo
- ❌ **Não modifica formato charter** (`.charter.md`) — só lê via comando existente
- ❌ **Não emite RemediationProposal** — humano revalida charter, não auto-PR
- ❌ **Não cobre charters Blade Tier C** — `charter:audit` já tem feature flag p/ future Tier C

## Plano implementação

✅ **Já implementado neste PR**:
- `Modules\Governance\Services\Checkers\ChartersFreshnessChecker` (~220 linhas — adapter + 4 categoria→finding mappers)
- Registrado em `config/governance.php > drift_checkers[]`
- Esta ADR

⏳ **Pest test (próximo commit)**:
- `Modules/Governance/Tests/Feature/ChartersFreshnessCheckerTest.php` (mock `charter:audit` retorna fixture JSON, valida findings agregados)

## Consequências

✅ **Boas:**
- 5/5 Top 5 ADR 0216 fechado (ComposerAudit + MultiTenant + AdrLinks + Charters + RoutesZombie)
- Brief Jana 06h ingere drift charters via Centrifugo `governance:drift` — Wagner vê numa narrativa unificada
- Persistência `mcp_alertas_eventos` permite séries temporais (gráfico "charters stale ao longo do tempo")
- Reuso ~407 linhas de lógica testada (`CharterAuditCommand`) sem cópia
- Performance: 1 invocação Artisan::call por audit; output JSON parseável
- Mesmo padrão Severity 5-níveis + Enforcement 3-níveis aplicado consistentemente

⚠️ **Tradeoffs:**
- 1 nível indireto extra vs CharterAuditCommand direto — overhead ~10-50ms por execução (aceitável)
- Mensagem do finding depende do schema JSON output do `charter:audit` — se schema mudar, adapter quebra. Mitigação: campo `evidence.category` documenta source array, e `?? 'unknown'` fallback evita crash
- 2 cron daily (`charter:health` 06:30 + `governance:audit` 06:35) competem por DB connection — aceitável durante canary 7d, depois remover `charter:health` se quiser unificação

## Validação

- ⏳ Smoke `php artisan governance:audit --check=charters_freshness --json` retorna estrutura findings esperada (resultados reais do repo)
- ⏳ Pest test fixture: mock `charter:audit` JSON com 4 categorias populadas → adapter retorna DriftFinding[] correto
- ⏳ Pest framework 18/18 verde (não regredir)
- ⏳ `governance:audit --all` agora tem 5 checkers

## Notas

- ADR 0220 numeração reservada desde ADR 0216 §Plano implementação — esperada pelo handoff PR #1875
- Sprint 2 followup: criar `charter:write` command pra auto-gerar charter draft a partir de tsx (skill Tier A já existe — wrapper futuro)
- Sprint 2 ADR 0223 futura: AST scan completo charters body (sections aninhadas, links broken para outros charters, anti-padrões Mission/Goals)
- Wagner pediu "continuar" sessão 2026-05-28 — esta ADR fecha Top 5 conforme prometido
