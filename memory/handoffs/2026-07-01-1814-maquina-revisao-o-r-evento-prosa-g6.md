---
date: "2026-07-01"
time: "18:14 BRT"
slug: maquina-revisao-o-r-evento-prosa-g6
tldr: "Construída a CAMADA DE DETECÇÃO da máquina de revisão de ADR (ADR 0317) — Check O (morta-mas-canon), EVENTO-prosa (furo 0097), Check R (revisão vencida por TTL) e G6 (watchdog dos 13 crons) — 3 gatilhos evento/inconsistência/tempo + auto-canário generalizado, todos 🟡 sentinela determinística sem bloquear merge. Mais o relabel 0078 morta-por-erro→viva-parcial. 5 PRs mergeados (#3514/#3517/#3518/#3519/#3522). Falta a camada de SURFACING (M3 AdrReviewBriefLineService + quarterlyOn, Onda 3 pt.2) e a Onda 4 (triagem humana das filas que os detectores criaram)."
decided_by: [W]
prs: [3514, 3517, 3518, 3519, 3522]
related_adrs:
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0120-reverse-supersession-metadata-housekeeping
next_steps:
  - "M3 / Onda 3 pt.2: AdrReviewBriefLineService (PHP, molde do PlanHealthBriefLineService) + quarterlyOn no Kernel — pega os warns O/R/prosa do memory-health --json e põe 1 linha (teto de vazão top-3) na seção FLAGS do Daily Brief. Pré-flight: preflight-modulo no Modules/Brief ou Copiloto + o pipeline do brief."
  - "Onda 4 (aplicação/triagem — humano + adversarial 1×1, invariante Tier 0 do 0317, é o que salvou o G3 de reviver errado): Check O 5 (0008, 0010, 0079, 0136, 0190) · EVENTO-prosa 2 (0097→0091, 0185→0179 = supersedes_partially faltando) · Check R 16 proposals stale >30d. Cada um: verificar corpo+supersede real → ratificar/emendar (supersedes_partially)/aposentar."
  - "Promover Check O/R/EVENTO-prosa/crons-watchdog de advisory (ADR 0271) → required quando calibrados por soak (ADR 0275 §5). O job legado single-cron de memory-health.yml pode ser aposentado quando o G6 crons-watchdog for required."
---

# Handoff — Máquina de revisão de ADR: camada de detecção (O/R/EVENTO-prosa/G6) + 0078

## Estado MCP no momento do fechamento

Snapshot MCP-first (prova de consulta, não promessa) — sessão de governança-código, **não tocou tasks MCP** (consistente com o handoff-mãe 1316):

- **`cycles-active`** (COPI): _Nenhum cycle ATIVO_ — governança roda off-cycle.
- **`my-work` @wagner**: 30 tasks ativas, **todas de produto** (Triage/Financeiro/NFe Gold/RecurringBilling/Sells/Oficina/COPI Jana) — nenhuma desta sessão. Detecção-de-ADR não é US de produto.
- **`decisions-search "máquina de revisão de ADR"`**: **0317** é a ADR-mãe (aceita, ativa) — implementei-a, **não criei ADR nova** (append-only: 0317 já cobre o design).
- **git**: os 5 PRs em `origin/main` (`git log`): #3522 G6 · #3519 Check R · #3518 EVENTO-prosa · #3517 Check O · #3514 0078. `memory-health` exit 0 · `adr-index --check` verde · `recall-golden` consistente (7 violations, todas mortas de verdade).

## O que aconteceu

Continuação do handoff-mãe [2026-07-01-1316-maquina-revisao-adr-esquecimento-g3](2026-07-01-1316-maquina-revisao-adr-esquecimento-g3.md), que deixou como `next_steps`: G4 (Check O), 0078 (aguardava sinal Wagner), G6 + M1 EVENTO-prosa + M2 Check R + M3 BriefLineService, e recall-golden.

**Fechado nesta sessão (5 PRs):**

1. **[#3514] 0078 morta-por-erro → viva-parcial.** Wagner confirmou 0094 como herdeiro. A aposta "constituição = 1 frase" cedeu (0079→0094), mas a meta-skill `meta-skill-roi-erp-autonomo` sobrevive → supersede PARCIAL. 0078 `superseded/substituido`→`aceito/ativo`, `superseded_by []`; 0094 `supersedes:[0079]` + `supersedes_partially:[0078]`. Refina a **ADR 0120** (que documentou full) sob a **0317** (semântica `supersedes_partially` "não rebaixa o alvo"), posterior à 0120. Migração legacy do frontmatter (`number 0078→78`, `decided_at` quotado) sob label `adr-legacy-schema-migration` (exceção 0297, corpo byte-idêntico).

2. **[#3517] Check O — morta-mas-canon** (memory-health.mjs, classe INCONSISTÊNCIA). ADR morta ainda citada como canon numa fonte-de-verdade VIVA (primer + BRIEFING + SPEC). Calibrado de 11 falsos/dia (o cético) → **5** via corpus curado + só-corpo + negação-de-contexto + `aceito`≠morta. 🟡 sentinela, ratchet `.checkO`.

3. **[#3518] EVENTO-prosa** (adr-index-generate.mjs). Furo 0097: supersede/amends declarado no título/status-note mas SEM o número no campo. Canal `proseWarn` separado do `supWarn` (gate duro) → NUNCA bloqueia. Refinado de 64 falsos (corpo inteiro) → **2** (0097→0091 o alvo, 0185→0179) via título+status-note, por-cláusula, guardas passiva/negação.

4. **[#3519] Check R — revisão vencida por TTL** (memory-health.mjs, classe TEMPO). `decided_at + TTL(kind)` vencido → 🟡. De `decided_at` IMUTÁVEL, nunca git-mtime. TTL: proposto/rascunho 30d · errata/feature-wish 180d · decisão 270d · meta/historical ∞ · morta isenta. **16** flags (todas proposto/rascunho >30d). Tier 90d "toca-dependência-externa" DEFERIDO (falta sinal). Ratchet `.checkR`.

5. **[#3522] G6 — watchdog dos 13 crons** (`cron-watchdog.mjs` + job no umbrella). Generaliza o auto-canário single-cron. Descobre dinamicamente todo workflow com `schedule:`, checa idade da última run agendada por cadência (semanal 10d/mensal 35d/diário 3d). 🔴 morto, 🟡 bootstrap, fail-open. **Testado local + CI** contra runs reais: 13/13 vivos.

**recall-golden**: já fechado pelo #3511 (sessão-mãe) — reverifiquei: 7 violations, todas `superseded/substituido`. Consistente.

## Persistência

- **git canon:** 5 PRs em main (webhook→MCP ~2min).
- **Este handoff** + índice `08-handoff.md` + session log `2026-07-01-maquina-revisao-o-r-evento-prosa-g6.md`.
- ADR 0317 é a documentação viva do design (append-only, não editada).

## Próximos passos pra retomar

`/continuar` → foco **M3 (AdrReviewBriefLineService)** — última peça da máquina (surfacing no brief). Depois **Onda 4** (triagem das filas, item a item, humano+adversarial). Ver `next_steps` no frontmatter.

## Lições catalogadas

- **Precisão de sentinela é iterativa e empírica**: Check O 11→9→8→5, EVENTO-prosa 64→4→2 (por-cláusula + passiva), Check R validado 1×1. Rodar → medir → filtrar > adivinhar o filtro. O ADR 0317 já avisava "calibrar o baseline é o custo real".
- **`status: aceito` nunca é "morta"** mesmo com `lifecycle: arquivado` — falso-positivo por construção (aceito+arquivado ≠ substituído). Corolário pro Check O.
- **execSync no Windows usa cmd.exe** (não sh) → aspas simples de `--jq` quebram. Filtrar JSON em JS = cross-platform + testável local. (cron-watchdog).
- **Job novo em workflow já registrado ≠ workflow novo** → não dispara Check G/M (gates-registry/ceiling). Caminho barato pra adicionar gate advisory.
- **Detector 🟡 (warn) não entra no canal do gate duro** — proseWarn separado do supWarn preserva o exit 0; regex em prosa PT-BR não pode bloquear ("não substitui X" casaria).
