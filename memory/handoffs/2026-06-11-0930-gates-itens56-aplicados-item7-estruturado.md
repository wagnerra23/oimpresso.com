---
date: "2026-06-11"
time: "09:30 BRT"
slug: gates-itens56-aplicados-item7-estruturado
topic: "Itens 5/6 da onda 2 (ADR 0271) verificados JÁ APLICADOS em main (14 required Variante B + enforce_admins=true) + item 7 estruturado em 3 passos sem deadlock + stash@{0} resolvido"
tldr: "Verificação live ~09:15 BRT: main JÁ está na Variante B — 14 required (entram multi-tenant, financeiro-pest, pii, append-only, no-mock, phpstan, ADR 0216 scan; sai module-grades-gate) + enforce_admins=TRUE (admin não fura mais) + strict=false. Itens 5/6: nada restava a executar. Item 7 (fusão 4 gates cor→1) estruturado em 3 PRs sem janela de deadlock — executar ≥2026-06-18. Stash@{0}: 1 linha resgatada (_INDEX-SECRETS superadmin WR2), resto obsoleto/duplicata PR #2441 → droppable."
decided_by: [W]
cycle: CYCLE-08
prs: [2531, 2532]
next_steps:
  - "Monitorar ~1 semana os 14 required em PRs reais (atenção: financeiro-pest skip-as-pass e custo fixo ~2-3min do ADR 0216 PR scan). Qualquer 'Expected — waiting' travado: rollback PATCH no corpo deste handoff"
  - "≥2026-06-18: executar item 7 pela sequência de 3 passos abaixo (P1 PR gate unificado em paralelo → P2 PATCH swap dos 2 required → P3 PR deleta os 4 antigos)"
  - "Wagner: `git stash drop stash@{0}` no repo principal (caracterização completa abaixo — resgate da única linha útil feito neste PR; resto obsoleto/duplicata)"
  - "Wagner: concluir rotação do superadmin WR2 + cadastrar no Vaultwarden (linha resgatada no _INDEX-SECRETS está 🟡 desde 2026-06-08)"
related_adrs: ["0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"]
---

# Handoff 2026-06-11 09:30 — Itens 5/6 já aplicados + item 7 estruturado (ADR 0271)

> Sessão [CC] desktop. Wagner apontou o handoff `2026-06-11-1145-gates-onda2-adr0271` com "se possível fazer isso. estruture". Pré-flight cumprido: `brief-fetch` primeiro + `list_sessions` (só esta sessão rodando; a sessão "Wave 2 handoff preparation" que fez a onda 2 encerrou ~09:16 BRT — regra anti-colisão de sessões paralelas).

## Descoberta central: itens 5/6 JÁ ESTAVAM aplicados

Entre o handoff anterior e ~09:15 BRT, os 3 primeiros next_steps foram executados (Wagner e/ou a sessão paralela):

| Next_step do handoff 11:45 | Estado verificado live |
|---|---|
| Merge PR #2531 (onda 2: −5 workflows + required-readiness) | ✅ mergeado (`453d8a75c`) |
| Follow-up B: `governance-drift.yml` always-run no PR | ✅ mergeado como **PR #2532** (`ccc1f8158`) — destravou a Variante B |
| Itens 5/6: PATCH `required_status_checks` | ✅ **aplicado na Variante B completa** (14 contexts, ver abaixo) |

**Esta sessão NÃO mutou a branch protection** — verificou, documentou e estruturou o que falta.

## Estado verificado live (`gh api`, 2026-06-11 ~09:15 BRT)

- `enforce_admins`: **true** ← além do escopo dos itens 5/6; resolve a decisão pendente "admin fura os gates" (nota local Claude `decisao-pendente-enforcement-gates`). Admin NÃO fura mais — PRs `--admin` deixam de funcionar como bypass.
- `strict`: false (preservado — repo com 150+ worktrees, sem exigir branch up-to-date)
- `required_approving_review_count`: 0
- `required_status_checks` (14 — **é exatamente a Variante B** do handoff 11:45; `module-grades-gate` FORA = item 6 ✓):

```
ADR frontmatter
Append-only canon (ADRs, handoffs, Constituição)
Casos-coverage · ratchet (trio + rastreabilidade)
Conformance · cor-crua ratchet vs baseline
Dominio-dict · ratchet (enum ⇔ dicionário)
Frontend / Vite build
No hardcode business_id (Tier 0)
No-mock-in-prod · ratchet
PHP / Pest (Financeiro · MySQL)
PHP / Pest (Unit)
PHPStan / Larastan · ratchet vs baseline
PII scan (CPF/CNPJ literal)
UI Lint · ratchet vs baseline
ADR 0216 PR scan (governance:audit --diff-only)
```

**Prova de não-deadlock:** o PR #2532 rodou TODOS os 14 contexts com `pass` (job names exatos conferidos, incluindo `ADR 0216 PR scan` em 50s pós always-run). Nenhum "Expected — waiting" possível com a lista atual.

**Novo snapshot de rollback** = a lista de 14 acima (rollback profundo = os 8 pré-flip, preservados no handoff 11:45).

## Item 7 estruturado — fusão 4 gates de cor → 1 (executar ≥2026-06-18)

Inventário confirmado no main atual:

| Gate | Workflow | Job name | Required? |
|---|---|---|---|
| Conformance cor-crua | `conformance-gate.yml` | `Conformance · cor-crua ratchet vs baseline` | ✅ |
| UI Lint | `ui-lint.yml` | `UI Lint · ratchet vs baseline` | ✅ |
| Stylelint | `stylelint-gate.yml` | `Stylelint · ratchet vs baseline` | ❌ |
| Accent canon | `ui-architecture-gate.yml` (roda `tests/Feature/Architecture/CockpitAccentCanonTest.php`) | parte do job `UI architecture (...)` | ❌ (só a parte accent migra) |

**Sequência de 3 passos** — substitui o "coordenar rename + PATCH no MESMO momento" do handoff anterior por passos individualmente seguros (deadlock impossível em qualquer ponto):

1. **P1 — PR aditivo:** cria `color-canon-gate.yml` (job sugerido: `Cor canon · ratchet unificado vs baseline`) consolidando os 4 scanners + **1 baseline unificada**, `pull_request: {}` always-run (lição required-readiness da onda 2 — NUNCA `paths:` em job candidato a required). **MANTÉM os 4 antigos rodando em paralelo** (dupla execução temporária). Critério de paridade antes de P2: mesmo veredito que os antigos em ≥3 PRs reais + 1 PR de teste com violação proposital (cor crua nova) que ambos pegam.
2. **P2 — PATCH swap** (só após P1 mergeado + paridade provada). Troca os 2 required de cor pelo unificado (12 mantidos + 1 novo = 13):

```bash
gh api -X PATCH repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks \
  -F strict=false \
  -f "contexts[]=ADR frontmatter" \
  -f "contexts[]=Append-only canon (ADRs, handoffs, Constituição)" \
  -f "contexts[]=Casos-coverage · ratchet (trio + rastreabilidade)" \
  -f "contexts[]=Dominio-dict · ratchet (enum ⇔ dicionário)" \
  -f "contexts[]=Frontend / Vite build" \
  -f "contexts[]=No hardcode business_id (Tier 0)" \
  -f "contexts[]=No-mock-in-prod · ratchet" \
  -f "contexts[]=PHP / Pest (Financeiro · MySQL)" \
  -f "contexts[]=PHP / Pest (Unit)" \
  -f "contexts[]=PHPStan / Larastan · ratchet vs baseline" \
  -f "contexts[]=PII scan (CPF/CNPJ literal)" \
  -f "contexts[]=ADR 0216 PR scan (governance:audit --diff-only)" \
  -f "contexts[]=Cor canon · ratchet unificado vs baseline"
```

3. **P3 — PR subtrativo:** deleta `conformance-gate.yml` + `ui-lint.yml` + `stylelint-gate.yml` + remove o `CockpitAccentCanonTest` da lista do `ui-architecture-gate.yml` (o teste em si CONTINUA na suíte Pest) + remove as baselines antigas. Anti-drift ADR 0270: propagar nos docs que citam os 4 gates.

**Rollback de qualquer passo:** re-PATCH pra lista atual de 14 (seção anterior). Gates required: 14 → 13 ao final (53 → 50 workflows-gate no total).

## Stash@{0} `charter-gate-wip-frosty-pre-onda2` — caracterizado, droppable

| Conteúdo | Veredito |
|---|---|
| `charter-validate.ps1/.sh` + `charter-first/SKILL.md` + `charter-gate.yml` (125 linhas) + `tests/Charter/baseline.json` (+1010) + `package.json` (5 scripts `charter:*` → `scripts/charter/*.mjs`) | **OBSOLETO** — premissa morta: o WIP transformava o hook em "advisory pra sempre" apoiado no Charter Gate em CI como trava, mas a onda 2 DELETOU `charter-gate.yml` (régua de tela é do `casos-gate`, ADR 0264; enforcement runtime = hook) |
| `BusinessLocationController.php` (+58) + `lang/{en,pt}/business.php` | **DUPLICATA** do PR #2441 aberto (`fix/business-location-invoice-refs` contém exatamente esses arquivos + teste) |
| `memory/_INDEX-SECRETS.md` (+1 linha: superadmin UltimatePOS "WR2" 🟡 rotacionando 2026-06-08, falta Vault) | **RESGATADO neste PR** — única informação que se perderia |

Conclusão: após merge deste PR, `git stash drop stash@{0}` perde zero informação. Drop é destrutivo → Wagner executa.

## Estado MCP no momento do fechamento

- Brief #203 · CYCLE-08 "Receita — Onda A" · 17d restantes · HITL 6 pendentes Wagner · Brain B 0/50 · flags 🟢🟢🟢
- `list_sessions`: só esta rodando (anti-colisão ok) · inbox sem itens conflitantes
- ⚠️ Aviso de cycle-drift do brief (124/124 commits 7d fora do CYCLE-08) segue de pé — fora do escopo desta sessão, fica pro Wagner avaliar rollover.
