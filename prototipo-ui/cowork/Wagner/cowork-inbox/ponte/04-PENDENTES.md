---
sessao: "04"
titulo: Pendentes — reescrito após a medição
autor: "[CC]"
atualizado: 2026-08-23 (revisão 2 — pós-aferição do [CL])
regra: derivada de 01 + 02 + 06. Se divergir, o número medido manda — nunca a hipótese.
---

# Pendentes

## 🔴 Topo — responde a contradição "nada em produção" vs "54 live"

| # | Item | Dono | Por que é o topo |
|---|---|---|---|
| **R.03** | As 48 com sinal, por via (`prod-flags` / `route-hits` / `smoke:` datado) | [CL] | `route-hits.json` expirou em 25/07 — diz se `live` é fato ou herança |
| **DEF** | **"Em produção" é deploy ou é a Larissa usando?** | **[W]** | se for adoção, nenhum instrumento do repo mede isso hoje |
| **R.01** | Lista nominal das 25 não-prontas + qual das 4 exigências falta | [CL] | é a fila de trabalho real, e ninguém a tem escrita |
| **R.02** | Veredito dos 5 testes órfãos da allowlist — **skip como categoria própria** | [CL] | pode haver vermelho parado há meses |

## 🔧 Instrumentos a criar (cegueira, não trava)

| # | Instrumento | Mata | Gate |
|---|---|---|---|
| C.01 | `uc-lane-coverage.mjs` | o 11-de-37: UC citando teste fora da lane | advisory 1 sem → required |
| C.02 | `catraca-selftest.mjs` | catraca que nunca se viu reprovar | semanal |
| C.03 | `t0-mutation-check.mjs` | `MultiTenantIsolationTest` tautológico | advisory 2 sem → required |
| C.04 | `uc-id-lint.mjs` | id de UC inválido (meu erro de hoje) | **required desde já** |
| C.05 | `staleness-alarm.mjs` | `route-hits.json` envelhecendo calado | cron diário |
| **V.01–V.06** | `ponte-handoff-lint.mjs` **(feito e rodando)** | handoff quebrado antes do PR | obrigatório antes de colar |

## ⛔ Decisão [W]

| # | Pendência | Destrava |
|---|---|---|
| **DEF** | definição de "em produção" | o que os instrumentos devem medir |
| 3.25 | `inventario-migracao`: `Pages/Stocks/` ou `Pages/Inventario/` | allowlist do guard · S1 |
| 5.17 | Paginação 6/página vs 20 | Negocios **e** Assinaturas |
| 5.04 | Schema do `financeiro-unificado.intent.json` | Financeiro/Unificado |
| 9.12 | Meta de cobertura: 100% ou só onde há cliente | 38 telas (Repair, Essentials, Forja) |
| 9.09 | Duplicatas `kb/Index` vs `v2` · `Sells/Create` vs `CreateV3` | 4 trios ambíguos |
| 3.01–3.24 | 24 vereditos do intake | S8.06 |
| 5.10–5.16 | 7 pendências de copy dentro dos contratos | copy travada |
| — | **por que a allowlist do `ponto-pest.yml` existe** (custo de CI? teste instável escondido?) | C.01 mede, você decide |

## ⬜ Meus — sem bloqueio

| # | Tarefa | Sessão |
|---|---|---|
| 1.01–1.10 | Build Grade Matrix + validar 3 contratos | S1 |
| 2.01–2.16 | 13 charters/casos órfãos do Ponto | S2 |
| 3.23 | Inventariar as 12 subpastas do intake | S3 |
| 4.01–4.09 | Diff do resíduo (🔴/🟢/🟠) | S4 |
| 5.02→5.03 | Ler charter de 33 KB → casos da CaixaUnificada | S5 |
| 5.05–5.09 | Triar 14 seções recortadas | S5 |
| 9.10 | Charter para `Estoque/Movimentacao` (casos órfão) | S9 |
| H.01 | Confirmar caminho de `COWORK-ESTRUTURA-E-TELAS.md` | qualquer |

## ✅ Fechado nesta sessão

- `ponte-handoff-lint.mjs` — 6 checks + controle negativo, **39 reprovações reais**
- 37 ids `UC-PTPAINEL` → `UC-PAINEL` em 4 pedidos (divergiam do `casos.md` corrigido)
- Nota de handoff em 2 pedidos que citavam arquivo invisível ao [CL]
- `05-DIAGNOSTICO` marcado como superado

## ❌ Cancelado

| Item | Motivo |
|---|---|
| 7.08 "flip para required" | **já há 46 contexts required** — a premissa era falsa |
| `prototipo-ui/PRODUCAO.md` | `charter-live-signal.mjs` já é o dono e é required |
