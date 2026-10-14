---
sessao: "_saida-16"
thread: "16 · Logins de contatos + comissões (portal, D3) (ContactLoginController) → Inertia"
dono: "[CL]"
data: 2026-10-14
tipo: recibo
entregue_em: "PRs #9287, #9292, #9315, #9299 (mergeados em main, 13 e 14/10)"
base_lida: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# _saida-16

> Recibo escrito depois da entrega, pela sessão que fecha a contabilidade do placar, a partir do git e do CI.
> As sessões que executaram a thread em 13/10 não deixaram o `_saida`. Cada linha abaixo aponta um PR ou um run.

## Entregue: Logins em React, atrás da flag OFF
`ContactLoginController` responde Inertia `Crm/Logins/Index` quando a flag `useV2CrmLogins` está ligada para o negócio. Desligada (default),
segue a Blade. Ligar é o cutover (F5), decisão [W].

**Escopo parcial.** Migrou o **Login de contatos**. **Comissões** (`/crm/commissions`) é outra tela, sem alvo medido na A2, e ficou na Blade (charter `Crm/Logins/Index`, Non-Goals; `RUNBOOK-logins.md`). O percentual de comissão é valor: a lista nova não o mostra nem o altera.

## PRs
| fase | PR |
|---|---|
| F1 RUNBOOK + paridade | #9287 |
| F2 baseline da Blade | #9292 |
| achado: login de contato só edita login de contato do próprio negócio (Tier 0) | #9315 |
| F3 tela React + charter + casos + contrato | #9299 |

## Testes (pelo nome, no último run de main que os executou)
Lane verticais-pest.yml run 38518244616 (push em main @d68c0219b2): 372 passed · 9 skipped · 2088 assertions.

- `CrmLoginsBaselineTest`: PASS
- `CrmLoginsContratoTest`: PASS
- `CrmLoginsEscritaEscopoTest`: PASS

Não rodei Pest local (regra: CT 100 ou CI).

## Pendente
- F4: smoke no biz=1 com a flag ligada. Não feito.
- F5: ligar a flag para as empresas. Decisão [W] (handoff `2026-10-14-0300-gestao-fila-merges-13-out-3.md`, pendência 1).
