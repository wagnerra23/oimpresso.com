---
title: ADR proposta — mwart-gate.yml deve ser HARD ou manter SOFT com SLA backfill?
status: proposed (Wagner valida)
date: 2026-05-09
author: Claude (Opus 4.7) — sub-agent execução autônoma
supersedes: nenhuma
amends: ADR 0104 (Processo MWART canônico — único caminho)
related:
  - '0094'  # Constituição v2 — princípio #4 "loop fechado por métrica"
  - '0095'  # Skills tiers
  - '0107'  # Emendation 0104 visual-comparison gate F3
  - '0114'  # prototipo-ui Cowork loop formalizado
---

# ADR proposta — mwart-gate.yml deve ser HARD ou manter SOFT com SLA backfill?

**Status:** proposed (Wagner valida)
**Data:** 2026-05-09
**Autor:** Claude (Opus 4.7) — sub-agent execução autônoma do audit `04-ci-pr-audit-30d`
**Amends:** [ADR 0104](../0104-processo-mwart-canonico-unico-caminho.md) — adiciona modo de enforcement Camada 3.
**Não supersede:** ADR 0104 segue válida; este ADR só endurece o gate CI já criado lá.

---

## Contexto

**Origem do problema.** O audit CI/PRs últimos 30 dias ([`memory/audits/2026-05-pre-sales/04-ci-pr-audit-30d.md`](../../audits/2026-05-pre-sales/04-ci-pr-audit-30d.md)) detectou **bug #6**: o workflow `mwart-gate.yml` está em modo **SOFT** (`continue-on-error: true` no step `Verify RUNBOOK + SPEC presence`, conclusion `success` mesmo com violações). O gate **comenta** no PR mas não **bloqueia merge**.

**Caso paradigmático — PR #349.** "Visão Unificada Cockpit V2" (2026-05-08, Modules/Financeiro) mergeou em main apesar do mwart-gate ter comentado **"❌ Violações detectadas"**. Faltavam 4 dos 9 artefatos obrigatórios listados em [`memory/requisitos/_processo/MWART-CHECKLIST.md`](../../requisitos/_processo/MWART-CHECKLIST.md):

- `Index.charter.md` ao lado do `.tsx` (ADR 0104 §F3)
- `UnificadoControllerTest.php` (Pest GUARD ADR 0093 Tier 0)
- `financeiro-unificado-visual-comparison.md` (ADR 0107 §F1.5)
- `RUNBOOK-unificado.md` (ADR 0104 §F1)

**Custo direto da regressão silenciosa.** A ausência forçou **5 PRs follow-up** retroativos: #355 (charter), #358 (RUNBOOK), #359 (Pest GUARD + charter expandido), #361 (visual-comparison + ADR ui/0003 amends 0002). Estimativa conservadora: **~12-16h dev** desperdiçadas reconstruindo contexto que estaria fresco se feito no PR original. Padrão "feature first, test depois" se repete (ver também #330 → #338, #349 → #359 — audit §3).

**Métricas de cobertura hoje** (audit §3):

| Artefato | Cobertura | Total Pages |
|---|---|---|
| `*-visual-comparison.md` | **4** | 127 (~3%) |
| `*.charter.md` | **13** | 127 (~10%) |
| `RUNBOOK-*.md` | **22** | 127 (~17%) |

Soft mode produziu **3 anos de débito técnico em 30 dias**. O gap não é disciplinar (audit §6 confirma **zero `--no-verify` em commits últimos 30 dias** — ninguém burla manualmente), é **institucional**: o gate não obriga, então não acontece.

**Princípio Constituição v2 violado.** ADR 0094 §4 "loop fechado por métrica" exige que toda regra crítica tenha enforcement automático. Soft mode é métrica aberta — detecta mas não fecha o loop.

## Decisão proposta

**Adotar Alternativa #4 — HÍBRIDO: HARD em paths MWART, SOFT em paths satélites, com janela de 14 dias warm-up.**

Justificativa quantitativa única que decide: **127 Pages × 3% cobertura visual-comparison = 4 conformes hoje. Soft mode rodou 30 dias e produziu 1 PR não-conforme mergeado de cada 3 PRs MWART (audit §3). HARD com escopo restrito (só `resources/js/Pages/<Mod>/<Tela>.tsx`) bloqueia exatamente o vetor de 100% das regressões detectadas, sem afetar PRs de backend, infra, docs ou módulos legacy.**

Operacionalmente:

1. **HARD (bloqueia merge)** quando o PR toca `resources/js/Pages/<Mod>/<Tela>.tsx` que **NÃO seja exempto** (helpers `_*`, `App.tsx`, `Layout.tsx`, `_components/*`, `_Showcase/*` — já filtrados no detect step do workflow atual).
2. **SOFT (comenta apenas)** quando o PR toca apenas paths satélites (`_components/`, `Components/shared/`, `Layouts/`).
3. **Override** segue funcionando: `/mwart-override <razão>` em comentário de PR vira ADR per-tela `lifecycle: historical`.
4. **Warm-up de 14 dias** (2026-05-09 → 2026-05-23) — hard mode em **dry-run**: workflow bloqueia, mas required-checks no branch protection só ativa após o 14º dia. Permite o time backfillar PRs em vôo sem trauma.

## Alternativas consideradas

### 1. HARD imediato (escopo total)

- **Pró:** zero regressão silenciosa em qualquer PR que toque `Pages/`. Loop fechado total.
- **Contra:** PRs de helpers/`_components/`/`_Showcase/` ficam reféns de gate que não foi desenhado pra eles. Audit §2 mostra ≥2 PRs/mês mexem em `_components` sem tocar tela canônica.
- **Custo dev:** baixo (1 line change em `continue-on-error: false`).
- **Risco:** alto — bloqueio inesperado em PR de polimento UI gera fricção e empurra time pra `/mwart-override` casual (anti-padrão erodir governança).

### 2. SOFT + SLA backfill 7d (revoga PR após 7d sem artefatos)

- **Pró:** velocidade preservada no calor da entrega.
- **Contra:** **enforcement humano** (Wagner cobra individualmente). Não escala em time de 5 pessoas com WIP=2 cada. Audit já mostra que o time **não** auto-corrige em <7d (PRs follow-up #355/#358/#359/#361 vieram entre 12h e 5d depois — média ~36h, mas alguns só vieram após Wagner explicitamente cobrar). Quem revoga PR após 7d? Não há automação de "rollback de feature em prod por débito de artefato".
- **Custo dev:** médio (cron job + `gh pr revert` + comm).
- **Risco:** alto — política sem dono escala mal. Mata Constituição §4.

### 3. SOFT + dashboard público (Wagner cobra individualmente)

- **Pró:** zero overhead técnico.
- **Contra:** estado atual já é isso (audit comenta no PR). Não funcionou em 30d. Wagner já é bottleneck explicitamente reconhecido em [`memory/regras-time.md`](../../regras-time.md) ("W deve evitar virar bottleneck").
- **Custo dev:** baixo.
- **Risco:** repete o padrão que gerou o problema. Status quo disfarçado.

### 4. HÍBRIDO (HARD em Pages canônicas, SOFT em paths satélites) ⭐ ESCOLHIDA

- **Pró:** bloqueia o vetor de regressão real (100% dos casos do audit foram em `Pages/<Mod>/<Tela>.tsx`, não em helpers). Preserva fluidez em refactors de `_components`. Warm-up 14d evita trauma. Override `/mwart-override` segue válido pra escapes legítimos com pegada auditável.
- **Contra:** dois modos = mais regex no detect step (já existe; só ajustar). Devs novos precisam entender exempções (mitigação: comentário no PR explicita exatamente qual artefato falta + link MWART-CHECKLIST.md).
- **Custo dev:** baixo (~1h):
  - 1 linha em `mwart-gate.yml` (`continue-on-error: true` → split por path)
  - 1 linha em branch protection (adicionar `mwart-gate / mwart-gate` em required-checks após D+14)
  - 3 linhas em `MWART-CHECKLIST.md` documentando hard mode + warm-up
- **Risco:** baixo. Reversível em <5min se gerar fricção real (revert PR → `continue-on-error: true` de volta).

### 5. Status quo (não fazer nada)

- **Pró:** zero esforço.
- **Contra:** **30 dias produziram débito de 3 anos** (cobertura 3%/10%/17%). Próximos 30 dias produzirão pior — Sprint 6 vai migrar +5 telas Cockpit (Repair Producao, Sells/create finalize, etc).
- **Custo dev:** zero hoje, **alto amanhã** (cada PR follow-up = 2-4h dev).
- **Risco:** crítico. Viola Constituição §4 explicitamente.

---

## Consequências

### Positivas (do híbrido escolhido)

- **Zero PR MWART canônico mergeia sem 9 artefatos** após D+14. Cobertura visual-comparison projetada de 3% → ~25% em 90d (extrapolando ritmo de 5-7 Pages MWART/mês × 100% conformidade).
- **Sinal claro pra time:** PR vermelho = falta artefato, não falta opinião humana. Reduz reuniões "isso precisa de charter?".
- **Wagner sai do papel de cobrador** — gate cobra. Libera ~30min/dia que hoje vão em revisão de débito retroativo.
- **Constituição §4 (loop fechado por métrica) aplicada em CI**, padrão pra outros gates SOFT atuais (`charter-gate.yml`, `visual-regression.yml`).

### Negativas + mitigações

| Negativa esperada | Mitigação |
|---|---|
| **PR de polish UI em `_components/` pode ficar bloqueado por engano se regex falhar** | Detect step já distingue `_components/` (excluído por `grep -vE '/_[A-Za-z]'` linhas 36-37 do workflow). Adicionar test runner local: `act` ou snapshot test do detect step com 10 paths conhecidos. |
| **Devs novos travam até entender 9 artefatos** | (a) Mensagem do gate cita link direto pra MWART-CHECKLIST.md + skill `mwart-process` Tier A + skill `cockpit-runbook` (já implementado nas linhas 234-240 do workflow). (b) Wagner adiciona seção "Primeiro PR MWART seu" em `memory/onboarding.md`. (c) Override `/mwart-override <razão>` segue válido pra primeiro PR — vira ADR per-tela com `lifecycle: historical`. |
| **Warm-up 14d cria janela cinza onde gate parece HARD mas não é** | Comment do gate em modo dry-run inclui linha **"⚠️ Em D+14 (2026-05-23) este gate vira REQUIRED no branch protection — backfill antes"**. Ping Wagner em D+13 pra confirmar ativação. |
| **Override casual erode governança** | Audit trimestral: se >2 overrides/cycle de 14d, abrir ADR pra revisar processo. Hoje: 0 overrides em 30d, então threshold é conservador. |

### Plano de implementação (Alternativa #4)

**Mudanças em `.github/workflows/mwart-gate.yml`** (apenas Wagner aplica — ADR proposed bloqueia auto-aplicação):

1. Renomear job: `mwart-gate (soft)` → `mwart-gate (hard for canonical Pages)`.
2. Step `Verify RUNBOOK + SPEC presence`: remover `continue-on-error: true`.
3. Adicionar step `Decide enforcement mode` antes do gate:
   ```yaml
   - name: Decide enforcement mode
     id: mode
     run: |
       # HARD se qualquer Page detectada NÃO é satélite/_components
       canonical_count=$(echo "${{ steps.detect.outputs.list }}" | grep -vE '/_components/|/_Showcase/|/Components/shared/' | wc -l)
       if [ "$canonical_count" -gt 0 ]; then
         echo "mode=hard" >> "$GITHUB_OUTPUT"
       else
         echo "mode=soft" >> "$GITHUB_OUTPUT"
       fi
   ```
4. Step `Verify RUNBOOK + SPEC presence` ganha `if: steps.mode.outputs.mode == 'hard' && ...` (mantém soft pra paths satélites).
5. Step `Comment results on PR` ganha mensagem dual (já tem 3 ramos `overrideActive`/`gateFail`/`success` — adicionar 4º "soft mode satélite").

**Branch protection (Wagner aplica em GitHub UI):**

- Settings → Branches → `main` → required status checks → adicionar `mwart-gate / mwart-gate` (apenas após D+14, 2026-05-23).
- Manter `Require branches to be up to date before merging: ON`.

**Documentação:**

- `MWART-CHECKLIST.md` linha 92: `Soft mode — não bloqueia merge` → `Hard mode em Pages canônicas (D+14 ativo) — bloqueia merge se artefato ausente. Soft em paths satélites.`
- ADR 0104: append amendment box "2026-05-23: gate Camada 3 vira HARD pra Pages canônicas — ver ADR proposta `proposta-mwart-gate-hard.md` aceita".
- `memory/regras-time.md`: nova linha em "Sempre fazer" — *"Antes de abrir PR mexendo em `Pages/<Mod>/<Tela>.tsx`, rodar `mwart-comparative` skill localmente — se faltar artefato, gate hard vai bloquear."*

**Comunicação ao time:**

- Sentinela em `cowork-inbox/` da semana 2026-05-09 com diff do plano.
- PR description template em `.github/PULL_REQUEST_TEMPLATE.md` ganha checklist 9 artefatos (auto-marca via gate).
- Wagner posta no canal interno em D+0 e D+13 ("amanhã vira hard").

**Rollback se quebrar produtividade:**

- Métrica de fricção: PRs MWART abertos/dia despencam >40% por 7 dias consecutivos OU `/mwart-override` >3 em cycle.
- Comando rollback: revert do PR que mudou `mwart-gate.yml` + remover required-check no branch protection. Tempo: <5min.
- Trigger automático: nenhum (decisão Wagner) — métrica é monitor, não ação.

---

## Métricas de validação pós-decisão

### KPI primário (decide manter/reverter em 30d)

**% PRs tocando `resources/js/Pages/<Mod>/<Tela>.tsx` canônica que mergeiam com 9/9 artefatos.**

- Hoje (estimativa pré-mudança): ~30% (1 em cada 3 PRs MWART está conforme — extrapolando audit §3).
- Meta D+30 (2026-06-08): **≥95%**.
- Reverter se: <70% após 30d **OU** time reportar fricção >40% PRs travados.

### KPIs secundários

- **Cobertura visual-comparison.md:** 3% (4/127) → meta D+90 ≥ 25% (32/127). Calculado via `find memory/requisitos -name '*-visual-comparison.md' | wc -l` rodado por cron Wagner em D+30/60/90.
- **PRs follow-up retroativos** (categoria "fix charter ausente PR #X"): hoje 5 em 30d → meta D+30 = **0**.
- **Velocidade PR/dia tocando Pages:** baseline 30d = ~7 PRs MWART. Meta D+30: ≥6 (queda <15% aceitável).
- **Overrides `/mwart-override` por cycle:** baseline 0 / 14d. Threshold alerta: >2 overrides em 14d → revisar processo.

### Sucesso vs reverter

- **Sucesso (D+30):** ≥95% conformidade + ≤15% queda velocidade + ≤2 overrides/cycle → **manter HARD, expandir pra `charter-gate.yml`**.
- **Reverter parcial (D+30):** <70% conformidade OU >40% queda velocidade → voltar `continue-on-error: true`, abrir nova ADR ajustando escopo.
- **Sucesso parcial (D+90):** ≥25% cobertura visual-comparison.md histórica → o gate funcionou pra novos, falta política de backfill (abrir ADR separada US-MWART-002 acelerada).

---

## Apêndice: estado atual do gate (5 evidências literais)

### A. Workflow `.github/workflows/mwart-gate.yml` — modo soft confirmado

Linha 1 — nome literal:
```yaml
name: MWART Gate (soft)
```

Linha 65 — `continue-on-error: true` no step de validação:
```yaml
- name: Verify RUNBOOK + SPEC presence
  id: gate
  if: steps.detect.outputs.count != '0' && steps.override.outputs.active != 'true'
  continue-on-error: true   # ← causa raiz do soft mode
  run: |
```

Linha 243 — comentário do gate confessa intenção transitória:
```javascript
`> **Soft mode** — não bloqueia merge ainda. Em fase 2 (após backfill US-MWART-002), vira hard.`,
```

### B. Artefatos required pelo gate (audit completo dos 5)

| # | Artefato | Path padrão | Verificação |
|---|---|---|---|
| 1 | RUNBOOK | `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` | `[ -f "$runbook" ]` |
| 2 | SPEC.md com US declarada | `memory/requisitos/<Mod>/SPEC.md` | `grep -qE "^### US-[A-Z]+-[0-9]+"` |
| 3 | Charter ao lado do .tsx | `${page%.tsx}.charter.md` | `[ -f "$charter_at" ]` |
| 4 | Pest test (condicional) | `Modules/<Mod>/Tests/Feature/<Tela>ControllerTest.php` | exige se Controller existe |
| 5 | visual-comparison.md (ADR 0107) | `memory/requisitos/<Mod>/<tela>-visual-comparison.md` | `status: approved` + ≥6 dimensões + sem TODO/??? |

### C. Hook local `block-mwart-violation.ps1` — Camada 2 já bloqueia

[`/.claude/hooks/block-mwart-violation.ps1`](../../../.claude/hooks/block-mwart-violation.ps1) bloqueia `Edit/Write/MultiEdit` em `Pages/<Mod>/<Tela>.tsx` em runtime se RUNBOOK ausente. Funciona — porém é **runtime do agent Claude Code**, não pega humano editando direto via Cursor/IDE. Por isso Camada 3 (CI gate) é necessária complementar.

### D. PR #349 — evidência de regressão silenciosa

PR mergeado em 2026-05-08 com gate comentando **"❌ Violações detectadas"** (audit §2). Nenhum required-check bloqueou. Mergeou via `Squash and merge` direto. 5 PRs follow-up custaram tempo medido em dezenas de horas dev.

### E. MWART-CHECKLIST.md já lista os 9 artefatos como obrigatórios

[`memory/requisitos/_processo/MWART-CHECKLIST.md`](../../requisitos/_processo/MWART-CHECKLIST.md) lista 8 artefatos obrigatórios + 1 opcional (RUNBOOK exigido apenas via gate, não always-on no checklist). Documento já existe, está alinhado, **só falta enforcement HARD**.

---

**Próximo passo:** Wagner valida + decide. Se aceito, este ADR vira `accepted` em PR separado, ID atribuído (provavelmente 0120-mwart-gate-hybrid-hard.md), e plano de implementação roda em 1h dev + 14d warm-up.

**Última atualização:** 2026-05-09 — Claude (Opus 4.7) sub-agent.
