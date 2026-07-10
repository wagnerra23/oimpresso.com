---
slug: proposta-loop-design-code-diff-first
title: "Proposta — Loop design→code otimizado: 1 SSOT git + espelhos gerados + diff-first automático"
type: proposal
module: _DesignSystem
status: proposto
date: "2026-07-10"
related_adrs: [0239-governanca-design-system-git-ssot-regressao-ia, 0300-tokens-dtcg-ssot, 0315-design-sync-claude-design-vs-cowork-charter, 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma, 0325-import-prototipo-designsync-pull-direto]
---

# Proposta — Loop design→code otimizado (diff-first)

> Origem: sessão 2026-07-10. Depois de exercitar o loop Cowork↔Code ponta-a-ponta (3 prompts, DS-sync, mirror), medi a fricção e proponho o desenho mais enxuto. **Não muda o protocolo de papéis/fases** (ADR 0114/0282) — muda o **transporte e a validação**.

## 1. Diagnóstico (medido nesta sessão, não teórico)

**A dor nº1 = PROMPT STALE (L-42).** Os 3 `PROMPT_PARA_CODE` recebidos descreviam um estado já divergente do git:
- "adicione os 56 tokens de domínio ao DS" → **já existiam** no SSOT (ADR 0310/0311). 22 valores no prompt eram snapshot velho.
- "ds-v6 aposentado/deletado, 3 mapas, raiz enxuta, L-42/43" → **nada disso no git** (mirror ainda tem `ds-v6/` inteiro; git em L-27).
- "o Cowork linka `_ds/…/cockpit_domains.css`" → **o arquivo não está no DS vivo** (companion só no git, #4097).

Cada prompt custou **validação manual** contra git + DesignSync. E há **4 armazéns autorais que driftam**:

```
git SSOT            prototipo-ui/cowork/      DS vivo (019dd02f)     ERP vivo (019dcfd3)
semantic.tokens.json   (mirror)               claude.ai/design         claude.ai/design
   │  gera                  ↑ pull manual?         ↑ push manual?          ↑ linka _ds/
   └── _generated-*.css ────┘ (drifta)             (companion ausente)     (reorg não no git)
```

**Transporte por URL que expira (~1h)**, fora do perímetro git/PR/CI.

## 2. Princípio

**1 SSOT autoral (git `semantic.tokens.json`). Todo o resto é GERADO ou ESPELHADO — uma via — com diff-first automático na entrada.** Ninguém "adiciona token" em 4 lugares; edita-se o SSOT, e a máquina propaga + detecta divergência.

## 3. O loop otimizado (4 passos, cada um mecânico)

| # | Passo | Direção | Mecanismo | Mata |
|---|---|---|---|---|
| 1 | **git → DS vivo** (deploy) | uma via (o sentido que o guard 0315 abençoa: "vitrine A PARTIR do git aprovado") | job gera `colors_and_type.css` + `cockpit_domains.css` do SSOT e PUSHA pro `019dd02f` via DesignSync (opt-in `design-sync` uma vez). DS vivo = espelho, nunca autoral | companion/token ausente do DS vivo |
| 2 | **Cowork → git mirror** (ingestão) | uma via | cron `DesignSync get_file` → atualiza `prototipo-ui/cowork/` + `memory/LICOES_CC.md`, gate `cowork-mirror-freshness` (ADR 0324) | mirror stale (ds-v6/mapas/L-43 não chegando) |
| 3 | **diff-first = ENTRADA de toda tarefa** | bidirecional | `ds-project-diff.mjs` (git canon × vivo) roda ANTES de qualquer prompt virar trabalho; só REAL delta vira ação | **PROMPT STALE (L-42) na raiz** — o prompt é auto-refutado |
| 4 | **handoff git-native** | — | o retorno é o diff + git (PROTOCOL §10.2), não `PROMPT_PARA_CODE` com URL que expira | relay frágil fora do perímetro |

**Ganho:** de "N prompts stale + validação manual + 4 stores driftando" → "1 SSOT + 3 espelhos gerados + 1 diff que decide". O humano aprova **deltas reais**, não relê estados divergentes.

## 4. Teste (o passo pivô, rodado ao vivo 2026-07-10)

`scripts/design-sync/ds-project-diff.mjs` — companion canônico (git, #4097) × inventário do DS vivo (`019dd02f`, lido via DesignSync `get_file`):

```
companion: 62 · já no vivo: 6 · AUSENTES: 56
  --origin-* 10 · --stage-* 7 · --sla-* 18 · --canal-* 12 · --kpi-feature-* 5 · --kind-* 4
  >>> AÇÃO: 56 tokens faltam no DS vivo → push cockpit_domains.css
```

**Detectou mecanicamente os 56 tokens ausentes** — exatamente o gap que eu tinha achado à mão. É a prova de que o diff-first substitui a validação manual do prompt stale. `--selftest` inclui anti-regressão do furo `[a-z0-9-]` (regex lowercase perde `--origin-CRM-bg`, pego 2× nesta sessão).

## 5. Adoção (o que falta pra ligar)

- **Passo 1 (deploy git→DS):** hoje manual (bloqueado pelo opt-in `design-sync`, correto). Automatizar = job que roda `ds-domains-companion --write` + DesignSync `write_files` sob flag de deploy. Governança: é o sentido "vitrine do git aprovado" (0315), não cria fonte divergente.
- **Passo 2 (pull):** `cowork-mirror-freshness` já existe (advisory, ADR 0324) — falta o cron de pull que aplica o delta no git.
- **Passo 3 (diff-first):** `ds-project-diff.mjs` entregue (esta proposta) + `ds-domains-companion --check` (já existe). Falta plugar como 1º passo do `aplicar-prototipo`/`mwart-comparative`.
- **Passo 4:** já é o §10.2; falta desativar o relay por URL expirável em favor do diff.

Tier 0 (toca DS/processo) → aguarda [W] pra virar ADR + ligar.
