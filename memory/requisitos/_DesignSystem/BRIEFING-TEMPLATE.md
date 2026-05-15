# BRIEFING — `<NomeModulo>` (template canônico)

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva atualizada por PR mergeado relevante
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md) — regra Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B) — atualiza este BRIEFING ao terminar PR que toque `Modules/<X>/` + `resources/js/Pages/<X>/`
> **Origem:** Wagner 2026-05-15 — "manter atualizado o briefing acho isso super necessário" + "ja era para ser assim sempre"

---

## Como usar este template

Copie pra `memory/requisitos/<Modulo>/BRIEFING.md` e preencha. Cada seção tem 1-3 frases ou 1 tabela compacta. **Total: 1 página de scroll** (~150 linhas max).

Atualização: depois de PR mergeado relevante, skill `brief-update` (ou manual) revisita cada seção e atualiza datas/scores/gaps. Append-only nas tabelas de histórico, sobrescreve seções de estado-atual.

---

## 1. O que é

**URL principal:** `https://oimpresso.com/<caminho>`
**Backend:** `Modules/<Nome>/`
**Frontend:** `resources/js/Pages/<Nome>/`

1-2 frases: o que o módulo faz e qual problema resolve.

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | ?% | YYYY-MM-DD |
| Capterra score vs top-mercado | ?/N | YYYY-MM-DD (ref [COMPARATIVO]) |
| Diferencial competitivo | ?% | YYYY-MM-DD |
| Cobertura SPEC formal (done/spec'ado) | ?% | YYYY-MM-DD |
| Documentação canon (SPEC + AUDIT-LOG + CAPTERRA) | ?% | YYYY-MM-DD |
| Deploy/ops (prod biz=N) | ?% | YYYY-MM-DD |

## 3. Capacidades hoje

Bullets curtos por camada — render no máximo 7 bullets:

- **Canais**: …
- **Atendimento**: …
- **Conteúdo**: …
- **Automação**: …
- **CRM/360**: …
- **Real-time**: …
- **Métricas**: …

## 4. Diferenciais únicos (não-replicáveis BSPs)

Lista numerada — top 5-10:

1. **<Nome diferencial>** — 1 frase técnica + concorrente que tinha (ou ✗ ninguém faz)
2. …

## 5. Gaps remanescentes (próxima onda)

Tabela compacta — top 5 itens:

| # | PR alvo | Esforço IA-pair | Score impact |
|---|---|---|---|
| 1 | Multi-phone UI completa | 3h | +1.5pp |
| … |

## 6. Bloqueadores manuais Wagner

- Deploy/SSH/canary que Claude não pode fazer (ex: re-pareamento WA, Tailscale)
- Curate de heurísticas / approvals
- Confirmação cliente piloto pré-cutover

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| Chatwoot OSS | ERP nativo, LID, anti-ban | Telegram/FB/Insta |
| Take Blip (R$1.500/mês) | 15× preço, ERP, Onboarding 5min | CTWA, Catalog, BSP oficial |
| Bling/Tiny/Omie | 5 anos à frente em inbox | — |

## 8. Risks ativos

Bullets — top 3-5 riscos que afetam módulo:

- 🟡 Risco 1 (impacto + mitigation)
- 🔴 Risco 2 (impacto + mitigation)
- …

## 9. Métricas-chave (last 7d)

Curto — KPIs operacionais reais (puxar via dashboards `/atendimento/metricas` etc):

- Volume: N msgs/dia
- Custo: R$ N/dia (HSM + Whisper)
- Deflection bot: N%
- Tempo médio 1ª resposta: Nmin

## 10. Cliente piloto / canary

- **Atual:** `<Nome biz=N>` — desde YYYY-MM-DD
- **Próximo canary:** `<Nome biz=N>` — quando `<condição>`

## 11. ADRs centrais do módulo

- [ADR XXXX](../decisions/XXXX-slug.md) — decisão arquitetural mãe
- [ADR YYYY](../decisions/YYYY-slug.md) — decisão complementar
- …

## 12. Sessões e handoffs relevantes (últimos 30d)

Histórico append-only — link de até 5 entradas mais recentes:

- [YYYY-MM-DD-HHMM-handoff-slug](../../handoffs/...) — 1 frase resumo
- …

---

## 13. Último update

**Atualizado:** YYYY-MM-DD HH:MM BRT pelo PR #NNN
**Próximo update esperado:** quando próximo PR relevante mergear (auto-trigger `brief-update` skill)
**Mantenedor:** Claude (auto) + Wagner (review)
