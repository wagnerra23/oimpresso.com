---
sessao: "17"
titulo: "Bens — formulário (create/edit/show) — saída da thread"
autor: "[CL]"
data: 2026-09-30
base: wagnerra23/oimpresso.com@main 0d65559c4 (merge do #8258)
thread: 17-bens-form.md
veredito: "entregue — edit do bem em drawer React; create já era drawer (#7832); show resolvido por remoção (#7904). Smoke de edição em produção NÃO feito: a empresa do smoke não tem bem."
---

# _saida-17 · Bens — formulário

## O que a thread pedia × o que o `main` já tinha

| parte | situação em 2026-09-30 |
|---|---|
| **create** | já era drawer React (`_shared/CadastroBemDrawer`, #7832). O `create()` Blade só respondia sob `ajax()` |
| **show** | resolvido por **remoção** na thread 15 (#7904: as rotas `show` apontavam view inexistente) |
| **edit** | ainda Blade → **entregue aqui** ([#8258](https://github.com/wagnerra23/oimpresso.com/pull/8258)) |

## Decisões [W] desta passada
- **`_shared/` autorizado** (2026-09-30): o índice marca `_shared` como `nao_toca`, mas o único jeito de editar sem duplicar o formulário é o `CadastroBemDrawer` ganhar modo editar. [W] autorizou.
- A dependência `16` do índice ficou obsoleta: com a decisão da `_saida-16b`, a 16 passa a depender do drawer de detalhe do bem e da 18. Executada a pedido do [W], ciente do placar.

## O que mudou (#8258)
- `AssetController::create/edit` deixam de devolver os fragmentos de modal Blade e passam a devolver a Page `Patrimonio/Bens` por um `renderBens()` único, extraído do `index`. O ramo `if (ajax())` saiu **inteiro**: o cliente Inertia manda `X-Requested-With` em toda visita, então ele não pode conviver com a tela React.
- `create` → drawer de cadastro aberto (`abrir_cadastro`). `edit` → bem na prop `edicao`, **escopado por business** (id de outra empresa = 404).
- `CadastroBemDrawer` em modo editar: o mesmo formulário posta no `update()` existente (`_method=put`).
- **Garantias:** o drawer mostra a mais recente e reenvia as outras intactas — o `AssetService::atualizar` apaga toda garantia que não vier no envio. Esvaziar o período remove só a mais recente.
- Lista: botão **Editar** volta à linha (`asset.update`).
- Charter: Non-Goal de edição **revogado e datado**; casos: UC-BENS-09; `[BACKLOG]` de edição removido.
- `asset.create` / `asset.edit` Blade: **0 chamadores** (`git grep` em `*.php`).

## Correção Tier 0 achada no caminho (PR próprio, #8253)
`AssetService::atualizar` atualizava `asset_warranties` por id vindo do request, sem amarrar ao bem — a tabela não tem `business_id`. Quem editasse o próprio bem com o id de uma garantia de outra empresa a sobrescrevia. Corrigido antes do drawer (que reusaria a escrita). UC-BENS-08 ficou **vermelho** sem a correção (run 36720116026, `1 failed, 116 passed`) e verde com ela.

## REGRA MESTRE (valor/estoque) — dupla prova
1. **vitest** `tests/js/patrimonio-cadastro-bem.test.tsx` (UC-BENS-09): as strings exatas do envio. 16/16; `tests/js` inteiro 471/471.
2. **Pest** `BensContratoTest.php` (UC-BENS-09): as mesmas strings no `update()` real, lendo o banco — valor, quantidade, código inalterado, depreciação vazia continua `NULL` (é o `ConvertEmptyStringsToNull` global que segura isso: o MySQL do projeto não é strict) e as duas garantias preservadas.

Tabela antes→depois apresentada no corpo do #8258; **merge feito pelo [W]** (sem auto-merge).

## Recibo do Pest sobre o código final
A lane `assetmanagement-pest` só dispara em `opened/reopened/ready_for_review`: ela rodou no **1º** commit do PR (120 passed, 480 assertions) e **não** no 2º (`ab2ea5ee2`, a correção de PHPStan que mudou a leitura das garantias no `edit()`).
- O `workflow_dispatch` no `main` (run 36726761945) saiu **verde sem rodar teste**: em dispatch o `paths-filter` olha só o último commit, que não tocava o módulo. **Não conta como prova** (§5 2026-09-04).
- Rodado no **CT 100** num worktree temporário de `origin/main @ 0d65559c4`, com o mesmo stub de manifest do Vite da lane: **13 passed (147 assertions)** no `BensContratoTest`, incluindo os 3 UC-BENS-09 e o UC-BENS-08. Controle positivo: o `AssetController` carregado vinha do worktree (com `vendor` em symlink ele vinha do checkout antigo — 1ª tentativa descartada). Worktree removido; checkout do container intacto.

## Smoke em produção (após o deploy do merge, run 36726576991)
| o quê | resultado |
|---|---|
| `GET /asset/assets/create` | 200 · tela de Bens com o drawer **"Adicionar recurso"** aberto e botão "Cadastrar bem" (screenshot) |
| `GET /asset/assets/999999999/edit` | **404** (antes: página vazia fora de ajax) |
| drawer de **edição** com um bem real | ❌ **não medido** — a empresa do smoke (WR2 Sistemas) tem **zero bens**; criar um só para testar seria gravar em empresa real. Nada foi enviado |

## Resíduos declarados
- O teste do `edit()` usa um bem **sem** garantia: a conversão de datas/meses de cada garantia (`buildEdicaoPayload`) não é exercida com linha. Cobrir quando houver fixture com garantia no UC-BENS-09.
- `meses` da garantia é derivado de `diffInMonths(início, fim)`; salvar sem mexer regrava `fim = início + meses`, o mesmo que o Blade fazia. Garantia gravada com fim fora da grade de meses teria o fim ajustado.
- Editar a **quantidade** abaixo do alocado não é barrado — o Blade também não barrava, e a trava de saldo é da alocação (thread 02). Não inventei regra.
