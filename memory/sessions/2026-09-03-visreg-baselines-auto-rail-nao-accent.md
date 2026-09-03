---
tipo: session
data: "2026-09-03"
titulo: "Baselines defasadas de novo: a causa era o auto-rail ≤1280, não o accent do dark"
autor: "[C]"
modulo: Governance
tags: [visual-regression, baseline, gate, ci, medicao, auto-rail]
---

# As baselines do `CoreScreens` defasaram de novo — e o culpado apontado não era o culpado

**Pedido [W]:** medir a defasagem das baselines de `tests/.pest/snapshots/Browser/CoreScreens/`, tratada como dívida **sem dono**, com a hipótese de que a causa era o accent do dark ([#6581](https://github.com/wagnerra23/oimpresso.com/pull/6581)) e o escopo era `financeiro-unificado`.

**Resultado:** nada foi regravado — não era preciso. A dívida **já tinha dono e já estava paga** quando a medição chegou. E a causa medida **não é** o accent: é o auto-rail `≤1280`. Este log é a instância medida do que o [session log de 2026-09-02](2026-09-02-visreg-zona-cinza-baseline-de-codigo-velho.md) §8 deixou em aberto.

## 1. A dívida tinha dono — a medição envelheceu em voo

[PR #6613](https://github.com/wagnerra23/oimpresso.com/pull/6613) (`test(visreg): baselines regeneradas (modo update) [CC]`, commit `0684d2ff7a`):

| evento | quando (UTC) |
|---|---|
| #6613 **aberto** | 2026-09-03T10:00:14Z |
| run `33742330927` (o que originou o pedido) **começou** | 2026-09-03T10:04:29Z |
| #6613 **mergeado** por `wagnerra23` | 2026-09-03T10:53:16Z |

O PR do rebake já estava aberto **4 minutos antes** de a evidência ser coletada. A leitura "sem dono" foi honesta no instante em que foi feita; envelheceu sozinha em 49 minutos. Regenerou **29 dos 91** `.snap`.

## 2. O Δ refuta o accent em 26 dos 29

Medido com [`scripts/tests/snap-diff.mjs`](../../scripts/tests/snap-diff.mjs), pai do commit × commit (`0684d2ff7a^` × `0684d2ff7a`):

| classe | n | %px | Δmax | assinatura |
|---|---|---|---|---|
| `ComprasFlow` compact/desktop | 6 | 20,1 – 57,1% | 255 | conteúdo |
| `ComprasFlow` **wide** | 2 | 0,0003 – 0,0014% | 3 | rasterização |
| `FinanceiroFlow` compact/desktop | 8 | 27,5 – 66,1% | 127 / 255 | conteúdo |
| `SellsCreateFlow` compact/desktop | 8 | 20,7 – 36,2% | 255 | conteúdo |
| `SellsCreateFlow` **wide** | 1 | 0,0017% | 26 | ruído |
| `IsolatedStates` dark (clientes · financeiro · sells) | 3 | 0,22 – 3,0% | 50 / 53 / 245 | localizado |
| `PixelBaseline` `superadmin_Negocios` | 1 | 0,0004% | 1 | rasterização |

**O discriminador é o viewport, não a cor.** Compact (1024×768) e desktop (1280×800) repintam 20–66% da tela; **wide (1440×900) fica em ~0%**. O corte é exatamente `≤1280`.

## 3. A causa, medida — não deduzida

`git log e1fd7b9855..0684d2ff7a -- resources/css/** resources/js/Layouts/** …` devolve **4** commits de Fundação. O que casa com a assinatura:

[**#6578**](https://github.com/wagnerra23/oimpresso.com/pull/6578) — `feat(shell): sidebar vira rail automaticamente em viewport estreita (<=1280)` (commit `4f02400ec5`, [ADR UI-0030](../requisitos/_DesignSystem/adr/ui/0030-sidebar-auto-rail-responsivo.md)), mergeado **2026-09-02T22:22:13Z** — **1h38 depois** do rebake anterior `e1fd7b9855` (20:44:07Z). Tocou `AppShellV2.tsx` e `Components/cockpit/shared.ts`, e regenerou **0** `.snap`.

O #6581 responde **só** pelos 3 estados dark, com Δ pequeno (0,22–3,0%). O enquadramento do pedido estava invertido: o grosso é **layout em light e dark abaixo de 1280**, não cor em dark. E o escopo nunca foi só `financeiro-unificado` — são 5 classes de teste.

## 4. Os dois causadores mergearam vermelhos, e nada os impediu

| PR | head | `visual-regression` | `.snap` regenerados |
|---|---|---|---|
| #6578 | `dbce7ed173` | **failure** (2026-09-02 20:50→21:06Z) | 0 |
| #6581 | `fabd417c1c` | **failure** (2026-09-03 01:10→01:22Z) | 0 |

Gate conferido na **união** `classic_protection` (44) + `rulesets` (1) = **45** contexts: `visual-regression` **não está** na união. Controle positivo: `ESLint · ratchet vs baseline` está.

## 5. O rebake foi provado, não só aplicado

- Run [`33741938255`](https://github.com/wagnerra23/oimpresso.com/actions/runs/33741938255) na branch `vrt/baselines-33741109517`: **success**, 14min27s — o modo update abre PR e o gate re-roda contra as baselines novas (anti-#3297).
- Depois do merge (10:53:16Z), **7 runs com o gate pesado de fato executado** — separados de skip-as-pass por duração (13–17 min contra 0–2 min) — em 7 branches independentes, **todos `success`**: `33753117343` · `33752160392` · `33751977490` · `33751727601` · `33751326058` · `33750974927` · `33750747837`. Zero `failure` na janela.

**Sobre a [§5 2026-08-26](../proibicoes.md)** (não regravar divergência determinística que volta idêntica): a regra **não se aplicava** aqui, e agora está provado — a divergência **não voltou**. Era drift monotônico (código novo, foto velha), o caso que o rebake corrige. A contagem de escritores de fixture não foi feita porque o Δ medido mais os 7 verdes já respondem o que ela serviria para responder.

## 6. O que fica em aberto

- **PR [#6615](https://github.com/wagnerra23/oimpresso.com/pull/6615)** segue `BLOCKED` com head `fce4bbd327`, cuja base **não contém** `0684d2ff7a`. Não precisa de baseline nova — precisa de `gh pr update-branch`. Não foi tocado nesta sessão: é PR de outra frente.
- **Decisão [W]** (a mesma que o log de 09-02 §8 deixou aberta, agora com uma instância medida): PR de Fundação mergeia vermelho sem regenerar baseline, e a conta cai em quem não a causou. Aconteceu **duas vezes em 26 horas**. O modo update existe e funciona; falta ser acionado por quem muda a Fundação, **no mesmo PR**. Repromover o gate a required é flip de branch protection — soberania [W] (R10).
- 3 dos 29 arquivos foram regravados com Δmax ≤3 (rasterização pura). O modo update reescreve o que difere em **byte**, não só em conteúdo. Não é defeito; é ruído absorvido junto.

## 7. Erro meu, registrado

Meu primeiro sweep dos 91 arquivos devolveu **91/91 `IDENTICO`** — e estava errado. Eu comparava o artifact `pixel-snapshots` contra `git show <SHA-do-run>:<path>`, quando o SHA do run carrega a **baseline velha**, não `origin/main`. A sonda respondia *"o artifact bate com a árvore do run?"* (sim, trivialmente) e não *"a baseline defasou?"*.

O que denunciou foi um controle que eu já tinha rodado antes: o `cmp` de bytes dizia **DIFEREM** (215.604 × 210.628) enquanto o sweep dizia idêntico. Duas medições em contradição, e a errada era a minha.

É [LC-08](../LICOES_CODE.md) na forma canônica — medir a partir da fonte errada. Foi pego **antes** de virar afirmação publicada, pelo controle. Fica registrado aqui; o incremento do ledger não entrou neste PR para não misturar intents (`commit-discipline`).

## 8. Recibos

Toda medição deste log é reprodutível:

- `git diff-tree --no-commit-id --name-only -r -z 0684d2ff7a` → os 29 paths
- `node scripts/tests/snap-diff.mjs <a.snap> <b.snap>` → px · Δmax · células
- `gh api repos/wagnerra23/oimpresso.com/commits/<head>/check-runs --paginate` → conclusão por PR
- `gh api repos/.../branches/main/protection` ∪ `gh api repos/.../rules/branches/main` → os 45 contexts
