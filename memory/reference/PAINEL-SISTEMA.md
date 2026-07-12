---
name: PAINEL-SISTEMA — índice gerado do estado do sistema oimpresso
description: MATRIZ gerada por scripts/governance/system-map.mjs. NÃO editar à mão (regenera). Índice que aponta pros donos canônicos + fatos deriváveis + frescor real.
type: reference
authority: generated
lifecycle: ativo
---

# 🗺️ PAINEL-SISTEMA — estado do oimpresso

> ⚙️ **Gerado por máquina** (`system-map.mjs`) em **2026-07-12**. NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/system-map.mjs`. Este é um **índice que aponta pros donos canônicos**, não uma cópia deles.
> Views humanas (mapa 🗺️ / guia 🧭 em claude.ai) derivam DESTES dados.

## Módulos & verticais

> Status/narrativa vivem no BRIEFING de cada módulo (curado). Aqui: existência + **último toque real** (git). Data absoluta (determinística — sem churn diário); a leitura de "está velho?" é do olho: um BRIEFING de meses atrás é candidato a re-destilar.

| Módulo | BRIEFING | Último toque |
|---|---|---|
| Admin | [BRIEFING](../requisitos/Admin/BRIEFING.md) | 2026-07-02 |
| ADS | [BRIEFING](../requisitos/ADS/BRIEFING.md) | 2026-06-08 |
| Arquivos | [BRIEFING](../requisitos/Arquivos/BRIEFING.md) | 2026-06-08 |
| AssetManagement | [BRIEFING](../requisitos/AssetManagement/BRIEFING.md) | 2026-06-08 |
| Auditoria | [BRIEFING](../requisitos/Auditoria/BRIEFING.md) | 2026-06-08 |
| Brief | [BRIEFING](../requisitos/Brief/BRIEFING.md) | 2026-06-08 |
| Cms | [BRIEFING](../requisitos/Cms/BRIEFING.md) | 2026-07-02 |
| Compras | [BRIEFING](../requisitos/Compras/BRIEFING.md) | 2026-07-03 |
| ComunicacaoVisual | [BRIEFING](../requisitos/ComunicacaoVisual/BRIEFING.md) | 2026-06-15 |
| Connector | [BRIEFING](../requisitos/Connector/BRIEFING.md) | 2026-06-08 |
| ConsultaOs | [BRIEFING](../requisitos/ConsultaOs/BRIEFING.md) | 2026-06-15 |
| Crm | [BRIEFING](../requisitos/Crm/BRIEFING.md) | 2026-07-02 |
| Essentials | [BRIEFING](../requisitos/Essentials/BRIEFING.md) | 2026-06-13 |
| Financeiro | [BRIEFING](../requisitos/Financeiro/BRIEFING.md) | 2026-07-06 |
| Fiscal | [BRIEFING](../requisitos/Fiscal/BRIEFING.md) | 2026-07-03 |
| Governance | [BRIEFING](../requisitos/Governance/BRIEFING.md) | 2026-07-09 |
| Jana | [BRIEFING](../requisitos/Jana/BRIEFING.md) | 2026-07-10 |
| KB | [BRIEFING](../requisitos/KB/BRIEFING.md) | 2026-06-13 |
| Manufacturing | [BRIEFING](../requisitos/Manufacturing/BRIEFING.md) | 2026-06-08 |
| NfeBrasil | [BRIEFING](../requisitos/NfeBrasil/BRIEFING.md) | 2026-07-02 |
| NFSe | [BRIEFING](../requisitos/NFSe/BRIEFING.md) | 2026-06-08 |
| Officeimpresso | [BRIEFING](../requisitos/Officeimpresso/BRIEFING.md) | 2026-06-15 |
| OficinaAuto | [BRIEFING](../requisitos/OficinaAuto/BRIEFING.md) | 2026-07-10 |
| PaymentGateway | [BRIEFING](../requisitos/PaymentGateway/BRIEFING.md) | 2026-07-02 |
| Ponto | [BRIEFING](../requisitos/Ponto/BRIEFING.md) | 2026-07-02 |
| ProductCatalogue | [BRIEFING](../requisitos/ProductCatalogue/BRIEFING.md) | 2026-06-08 |
| ProjectMgmt | [BRIEFING](../requisitos/ProjectMgmt/BRIEFING.md) | 2026-06-13 |
| RecurringBilling | [BRIEFING](../requisitos/RecurringBilling/BRIEFING.md) | 2026-07-10 |
| Repair | [BRIEFING](../requisitos/Repair/BRIEFING.md) | 2026-07-02 |
| Spreadsheet | [BRIEFING](../requisitos/Spreadsheet/BRIEFING.md) | 2026-06-08 |
| SRS | [BRIEFING](../requisitos/SRS/BRIEFING.md) | 2026-06-21 |
| Superadmin | [BRIEFING](../requisitos/Superadmin/BRIEFING.md) | 2026-06-08 |
| TeamMcp | [BRIEFING](../requisitos/TeamMcp/BRIEFING.md) | 2026-07-02 |
| Vestuario | [BRIEFING](../requisitos/Vestuario/BRIEFING.md) | 2026-06-08 |
| Whatsapp | [BRIEFING](../requisitos/Whatsapp/BRIEFING.md) | 2026-07-02 |
| Woocommerce | [BRIEFING](../requisitos/Woocommerce/BRIEFING.md) | 2026-06-08 |

## Programa SDD (governança)

- Scorecard: **10/13** métricas medidas · floor full-suite = **291**.
- Fonte viva: `governance/sdd-scorecard.json` (gerado por `sdd-scorecard.mjs`). Avaliação adversarial: `/sdd-avaliar`.
- Roadmap dono: [`memory/requisitos/_Governanca/roadmap/_ROADMAP.md`](../requisitos/_Governanca/roadmap/_ROADMAP.md).

## Auditorias & Gates

> Fontes versionadas (offline, sem `gh api`): censo [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o que **existe**) + [`required-checks-baseline.json`](../../governance/required-checks-baseline.json) (o que **bloqueia**, congelado). Anti-demoção invisível: `protection-drift.mjs` (GT-G4). As catracas mordem: `gate-selftest` (GT-G6). Censo cobrado por `memory-health` Check G/M.

### Bloqueiam merge — 25 required (enforcement: everyone)
> Congelados no baseline (captura 2026-06-20). Divergência do vivo é sinalizada pelo `protection-drift`, não reconciliada aqui.

- ADR 0216 PR scan (governance:audit --diff-only)
- ADR frontmatter
- Ancora de design nao-shell (F2/F6 required)
- Append-only canon (ADRs, handoffs, Constituição)
- Casos-coverage · ratchet (trio + rastreabilidade)
- DS gate
- Dominio-dict · ratchet (enum ⇔ dicionário)
- Frontend / Vite build
- No hardcode business_id (Tier 0)
- No-mock-in-prod · ratchet
- PHP / Pest (Financeiro · MySQL)
- PHP / Pest (NfeBrasil · MySQL)
- PHP / Pest (Unit)
- PHPStan / Larastan · ratchet vs baseline
- PII scan (CPF/CNPJ literal)
- SDD scorecard ratchet (métrica armada não regride · GT-G3)
- Secret scan (gitleaks · só linhas novas do PR)
- Tier-0 guards (WithoutGlobalScopes + BusinessId)
- anchor entry/covers gate
- anchor-lint ADR 0273
- charter status:live precisa de sinal de prod
- doneness-lint ADR 0302
- gate selftest (as catracas mordem · GT-G6)
- visual-regression
- Governance Gate (índice + memory-health + meta-teste)

### Censo — 99 workflows por classe

> Lista completa + propósito de cada um: [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o dono). Aqui: contagem + exemplos.

| Classe | Qtd | Exemplos |
|---|---|---|
| gate (bloqueia/valida PR) | 74 | a11y-axe-gate, a11y-gate, adr-index-gate, adr-lint, … |
| meta (testa os gates) | 5 | block-brl-values-selftest, gate-selftest, guards-meta-gate, protection-drift, … |
| automacao (cron/dispatch) | 18 | agent-pr-outcomes, briefing-code-staleness, composer-lock-sync, design-return-gate, … |
| deploy (entrega) | 2 | deploy, quick-sync |

## Decisões (ADRs)

- **342** ADRs no total. Índice gerado: [`_INDEX-GENERATED.md`](../decisions/_INDEX-GENERATED.md) · lifecycle: [`_INDEX-LIFECYCLE.md`](../decisions/_INDEX-LIFECYCLE.md).
- Por status: aceito: 308 · superseded: 12 · deprecated: 12 · proposto: 4 · sem-status: 4 · rascunho: 1 · recusado: 1.
- **3** reversões de rota (ADR com `supersedes:`).

## Ideias avaliadas e ABANDONADAS (§5 — não re-propor)

> Dono canônico: [`memory/proibicoes.md §5`](../proibicoes.md). 18 entradas.

- ~~2026-06-05 — Roadmap/plano de evolução PARALELO a canon existente~~
- ~~2026-06-05 — Teste que deriva do CÓDIGO (tautológico) em vez do contrato~~
- ~~2026-06-09 — Domínio de "locação" na Oficina (alucinação herdada do legado)~~
- ~~2026-06-29 — Migrar `<select>`→Radix `<Select>` mapeando opções data-driven SEM filtrar valor vazio~~
- ~~2026-06-30 — Guarda de âncora de design por NOME/PASTA (denylist OU allowlist) em vez de proveniência por charter~~
- ~~2026-07-01 — Gate de CI "charter foi tocado no diff" (charter-sync-gate) pra forçar sync código↔charter~~
- ~~2026-07-01 — Re-promover `foundation-ratchet` a required (roadmap Pfr) DEPOIS da 0314 tê-lo demovido~~
- ~~2026-07-09 — Workflow render-diff prod×proto em CI (RE-PROPOSTO e re-morto — a lápide canônica é a ADR 0290)~~
- ~~2026-07-09 — Sentinela anti-"hard-fail fantasma" em job advisory (premissa falsa)~~
- ~~2026-07-09 — Guard de bundle Cowork exigindo `@scope`/`@layer` (100% falso-positivo)~~
- ~~2026-07-09 — Dobrar casos+contrato numa catraca de cobertura nova (duplica o `casos-gate` required)~~
- ~~2026-07-09 — Frescor por `verificado_em` vs git-mtime (duplica o `briefing-code-staleness`)~~
- ~~2026-07-09 — Chokepoint de guard em comando fantasma (`flag:set`) que o fluxo real não atravessa~~
- ~~2026-07-09 — Consolidar os dois §5 (código e design) num ponteiro único~~
- ~~2026-07-09 — Claims de superioridade "acima do mercado" REFUTADAS (lápides de humildade — não re-alegar sem re-verificar)~~
- ~~2026-07-10 — Grade de réguas por decomposição em slices, sem teste de integração (fabrica "0 acima" falso)~~
- ~~2026-07-10 — Remover a redeclaração de tokens de domínio nos bundles `.fin-cowork`/`.sells-cowork` (parece "não-redeclarar", reintroduz bug de PORTAL)~~
- ~~2026-07-12 — Normalização MECÂNICA em massa de arquivos LEGADOS de `memory/` (backfill de frontmatter)~~

## Tier 0 gaps (esperam decisão/desbloqueio)

- ⛔ 2026-05-28 — Token Hostinger API inacessível ao agente autônomo

## Rastro

- **240** handoffs · **431** session logs. Índice: [`memory/08-handoff.md`](../08-handoff.md).
- Sessions recentes:
  - `2026-07-12-sdd-avaliacao-adversarial-processo`
  - `2026-07-12-cobertura-charters-100-por-cento`
  - `2026-07-11-pt-atipicas-retriage-pt07`
  - `2026-07-11-ds-sync-loop-grade-adversarial`
  - `2026-07-11-design-coverage-adversario-e-problema-real`
  - `2026-07-11-como-integrar-m1-detector-drift-ui-m2-verdade-visual`

---
_Gerado por `scripts/governance/system-map.mjs` · 2026-07-12 · deriva das fontes canônicas, não as substitui._
