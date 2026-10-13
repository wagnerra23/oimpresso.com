---
sessao: "13"
titulo: Configurações React — ligar para todas — saída (recibo)
playbook: sistema
thread: "13"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo
base: wagnerra23/oimpresso.com@main 987ff270fb
---
# _saida-13 · Locais, Impressoras e Código de barras em React para todas as empresas

O código entrou no [#9112](https://github.com/wagnerra23/oimpresso.com/pull/9112) (mergeado 2026-10-09, `0ed2e69acc`):
as três flags passaram a `true` no `fallbackDefaults` do `FeatureFlagService` (o Hostinger não tem o SDK do GrowthBook);
um OFF explícito do GrowthBook ainda devolve a Blade. Decisão D-CFG-LIGAR ([W] 2026-10-07, `_DECISOES-W-2026-10-07b.md`).
O #9112 deixou o recibo para depois do deploy; este arquivo é esse recibo. Nenhum código muda aqui.

## Pré-requisito da thread
Os drawers estão no `main`: [#8975](https://github.com/wagnerra23/oimpresso.com/pull/8975) (`c736a7032e`) e
[#8976](https://github.com/wagnerra23/oimpresso.com/pull/8976) (`522b441e0f`).

## Teste na lane
`tests/Feature/Configuracoes/ConfiguracoesCutoverTest.php`, lane `PHP / Pest (Acessos · MySQL)`, conferido pelo nome
no log do run [38384486541](https://github.com/wagnerra23/oimpresso.com/actions/runs/38384486541) (push de `d0e6c8b476`,
que contém o #9112) — 9 de 9 com ✓, 3 telas × 3 casos:
- `cutover sem GrowthBook responde React no GET comum do menu`
- `cutover conserva rollback explícito do GrowthBook para Blade`
- `cutover não concede acesso a usuário sem permissão`

O run do próprio merge (`37979175665`) foi cancelado pela fila; por isso o recibo cita o run de hoje.

## Flags em produção (Hostinger, só leitura, 2026-10-13)
| flag | antes do #9112 | depois (medido hoje, empresa 1) |
|---|---|---|
| `useV2ConfiguracoesLocais` | fora do `fallbackDefaults` → Blade | **`true`** |
| `useV2ConfiguracoesImpressoras` | idem | **`true`** |
| `useV2ConfiguracoesCodigoBarras` | idem | **`true`** |

Commit servido `1572f1376c`, que contém `0ed2e69acc`. "Antes" vem do diff do #9112, não de medição da época.

## Smoke (produção, empresa WR2 Sistemas, não a empresa 4)
| rota | status | componente servido |
|---|---|---|
| `/business-location` | 200 | `Configuracoes/Locais/Index` |
| `/printers` | 200 | `Configuracoes/Impressoras/Index` |
| `/barcodes` | 200 | `Configuracoes/CodigoBarras/Index` |

Etiquetas seguem em polegada (D-ETQ-MM); este recibo não mediu a tela de etiquetas além do componente servido.

## Prova do índice
`tests/Feature/Configuracoes/ConfiguracoesCutoverTest.php` existe desde o #9112; com este recibo a thread sai de `(sem recibo)`.
