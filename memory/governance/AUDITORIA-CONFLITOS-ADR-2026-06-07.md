---
id: governance-auditoria-conflitos-adr-2026-06-07
---

# Auditoria Profunda de Conflitos — ADRs (2026-06-07)

> Busca profunda pedida por Wagner ("uma busca profunda sobre conflitos", foco ADR).
> 4 agentes paralelos varreram 260 arquivos em `memory/decisions/` vs `main` @ 561ff8be3.
> Disco = fonte primária: **260 arquivos · 245 números únicos · máx ADR 0255**.

---

## 🔴 P0 — ADRs ATIVAS que se contradizem (o pior: 2 decisões vivas opostas)

| # | Conflito | A diz | B diz | Vigente | Conserto |
|---|---|---|---|---|---|
| C1 | **0028 vs 0180** | nº duplicado → renomear | nº duplicado → aceitar drift | 0180 | ADR 0257 deve `supersedes: [0028]` |
| C2 | **0091 vs 0097/0226** | Brief = Sonnet | Brief = gpt-4o-mini | 0097/0226 | marcar 0091 superseded_parcial; corpo de 0091 mente |
| C3 | **0182 vs 0235** | PageHeader hue-per-grupo | roxo universal 295 | 0235 | 0182 corpo ainda manda hue (só comentário YAML rebaixa — inválido) |
| C4 | **0136 vs 0178** | Sells toggle Lista/Grade | Sells tabs unificadas | 0178 | 0136 segue `ativo` — marcar substituido |
| C5 | **0153/0154/0155** | module-grade v1 / v2 / v3 | 3 versões ativas | 0155 | cadeia lifecycle suja — marcar 0153/0154 substituido |

## 🟠 P1 — Integridade supersede/lifecycle quebrada (15 issues)

- **0035** superseded por 0048 mas frontmatter `aceito/ativo` (+ índice diz vigente).
- **0073, 0075** — frontmatter `substituido` MAS `_INDEX-LIFECYCLE` diz `A` (ativo) — inversão.
- **0136** superseded por 0178 (declarado) mas não marcado.
- **0078** `superseded_by: [0079]` incompleto — falta 0094.
- **0009 / 0038 / 0039 / 0041 / 0047** — índice diz S/D, frontmatter `aceito/ativo`.
- **0039** "superseded por DESIGN.md AppShellV2" — superseder é um doc, não uma ADR (modelo inconsistente).

## 🟡 P2 — Colisões de número (13; 2 silenciosas)

- 13 números colididos; **0236 (×3) e 0246 (×2) NÃO registrados** em `_INDEX-LIFECYCLE` → `AdrNumberCollisionTest` falharia. (PR #2381 registra.)
- **Nenhuma** colisão é "dois canons vivos brigando" (todas tratam temas distintos; 0170 são amendments).
- **0126-mcp-jira** tem `id: 0125` no frontmatter (≠ nome do arquivo) — drift interno.

## 🟡 P3 — Os índices de ADR mentem (a pergunta original do Wagner)

| Índice | Mente em | Veredito |
|---|---|---|
| `decisions/README.md` | parou no ADR 0023, diz "Ponto WR2" | **fóssil** — aposentar como índice |
| `_INDEX-LIFECYCLE.md` | `total:119` (real 245); lookup só até 0164; faltam 0236/0246 | **fonte oficial mas defasada** |
| `INDEX_TEMATICO.md` | cobre até 0247 (faltam 0248-0255); diz "0193 não existe" (existe) | parcial |
| `INDEX.md` | "~220 ADRs" (real 245) | só navegação |
| **disco** | — | **única fonte sem mentira** |

**Ressurreição:** `copiloto:seed-adrs` (cron diário) re-indexa ADRs no **banco** (Meilisearch/`jana_memoria_facts`) — é o índice que "volta". MAS pipeline separado (git→DB), **não conflita** com markdown nem sobrescreve índice manual.

## ✅ Verificado OK (sem conflito)
Broadcaster (Reverb→Centrifugo, supersede correto), deploy runtime (0060/0062), sidebar light/dark (UI-0009/0014), multi-tenant, MWART, stack AI (0035/0252 complementares), DS v4/v5/v6 (aditivos).

---

## Caminho de conserto (encaixa no que já construímos)

A maioria é **metadata** → resolvível pela exceção de normalização da **ADR 0257** (PR #2387: label `adr-metadata-normalization`):
1. **P1 (supersede/lifecycle drift)** → normalização de frontmatter (marcar 0035/0136/0073/0075/0009/0038/0041/0047 corretos) — via label 0257. 1 PR.
2. **P0 C1** → ADR 0257 ganha `supersedes: [0028]`.
3. **P0 C2/C3** → corpo das ADRs mente (0091 modelo, 0182 hue) — append-only proíbe editar corpo; conserto = nota de errata OU ADR superseder. Decisão Wagner.
4. **P2 silenciosas** → PR #2381 já registra 0236/0246.
5. **P3 índices** → aposentar README como índice; regenerar _INDEX-LIFECYCLE/INDEX_TEMATICO (gerador determinístico) cobrindo 0001-0255; ou novo `memory-health` Check F (índice vs disco).

**Prevenção:** `memory-health` (ADR 0256) ganha checks novos — supersede-integrity + índice-vs-disco — pra isso nunca mais acumular silenciosamente.

## Refs
- 4 sub-relatórios: colisões · supersede-integrity · contradições-ativas · índices-vs-disco (2026-06-07)
- Encaixa: ADR 0256 (survival) · ADR 0257 (status/lifecycle/kind + normalização) · PR #2381 (colisões) · PR #2386 (sentinela)
