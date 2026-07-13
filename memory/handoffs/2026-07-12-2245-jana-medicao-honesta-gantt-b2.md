---
date: "2026-07-12"
time: "22:45 BRT"
slug: jana-medicao-honesta-gantt-b2
tldr: "Verificação-de-máquina provou que o SPEC subcontava a maturidade da Jana (91%→real ~97%). Reconciliei 5 US com evidência, fechei gap Tier 0 (tag business_id no Langfuse) e construí+verifiquei em prod o Roadmap Gantt drag-drop reschedule (B2). 6 PRs mergeados; Jana 71→73."
prs: [4133, 4144, 4145, 4147, 4159, 4186]
decided_by: [W]
related_adrs: [0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio, 0101-tests-business-id-1-nunca-cliente]
next_steps: ["Rodar régua reguas-do-sistema (chip task_d3584787) pra nota atualizada do IA-OS", "Onda 6 Jana segue bloqueada por ADR 0105 — NÃO reabrir"]
---

# Handoff 2026-07-12 22:45 — Medição honesta Jana + Roadmap Gantt B2

## Estado MCP no momento do fechamento
- `cycles-active` (COPI): **nenhum cycle ATIVO**.
- `brief-fetch`: indisponível na sessão (curl exit 28 no SessionStart — operei via git/máquina).
- Handoffs irmãos hoje (outras sessões): `2026-07-12-2200-matriz-onboarding-...`, `-1250-cobertura-charters-100`. Meu tema (medição Jana + Gantt) é novo, sem duplicação.

## O que aconteceu
Wagner: "quero medir", "meu foco é máquina". Verifiquei o IA-OS/Jana **na máquina** (docker CT 100 + ClickHouse + git), não no doc. Achado central: o SPEC **subcontava** a maturidade da Jana — dizia 91%, real **~97%** — porque 2 US prontas (Langfuse, time-decay) estavam marcadas incompletas. Reconciliei tudo com evidência e construí o único gap real (Gantt drag-drop).

## Artefatos (6 PRs, todos merged)
| PR | Entrega |
|----|---------|
| #4133 | fecha drift Langfuse US-117 + abre US-132 + reconcilia 2 âncoras mortas (.ps1→.mjs) |
| #4144 | Ondas 4-5: US-108/110/109 → done (evidência de máquina) |
| #4145 | US-132 — tag `business_id` no trace Langfuse (Tier 0 multi-tenant) + 2 Pest |
| #4147 | US-111 escopo reconciliado (charter existe, lib SVAR pronta) |
| #4159 | US-111 **B2** — drag-drop reschedule do prazo (endpoint + wiring + 3 Pest) |
| #4186 | US-111 → done (gate visual R7/R1 fechado em prod) |

Deploy Hostinger `@f8d68b232d`. Module Grades: **Jana 71→73**.

## Persistência
- **git:** 6 PRs no main. **MCP:** webhook propaga handoff/session em ~2min. **BRIEFING:** Jana não re-destilado nesta sessão (só reconciliação de status + 1 feature; ver session log).

## Próximos passos pra retomar
- Chip `task_d3584787` (skill `reguas-do-sistema`) já rodando em sessão separada → traz a nota atualizada do IA-OS + gaps ranqueados.
- **Onda 6 da Jana permanece bloqueada por [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)** (cliente como sinal) — NÃO reabrir sem sinal externo.

## ⚠️ Caveat — colisão de número US-COPI-132
O handoff [2026-07-10-1443](2026-07-10-1443-reguas-integracao-atrofia-inteligencia-cycle-bi.md) do CYCLE-BI-01 planejou **"US-COPI-132 = descongelar Jana-BI"**, mas esse número nunca foi materializado no SPEC. Quando criei minha task via `tasks-create` em 07-12, o contador deu **132 pra "tag business_id no Langfuse"** (a que está no SPEC hoje). **Quem retomar o CYCLE-BI-01 precisa de um número NOVO pro descongelar Jana-BI** (o 132 está ocupado). Não é bug — é o número que ficou tomado.

## Lições catalogadas
1. **Verificação-de-máquina > status: do doc** — o SPEC subcontava maturidade; provas dinâmicas (docker ps, ClickHouse, grep de método wired) corrigiram.
2. **Gate visual R1 quase pegou falso-done** — automação de browser NÃO dirige o drag do SVAR Gantt (viewport 696×333, chart 220px, barras off-screen). Só o drag real do Wagner + delta objetivo (Duration 6→8) fechou. Nunca declarar "funciona" sem ver.
3. **Anchor-lint (reincidi 3×):** "mé-**todo**" dispara placeholder (substring "todo"); token em backtick com "/" (URL/nome-página/fórmula) vira path morto. No anchor: só path real do arquivo em backtick.

## Pointers detalhados
- Session log: [2026-07-12-jana-medicao-honesta-ondas-4-5-gantt-b2.md](../sessions/2026-07-12-jana-medicao-honesta-ondas-4-5-gantt-b2.md)
