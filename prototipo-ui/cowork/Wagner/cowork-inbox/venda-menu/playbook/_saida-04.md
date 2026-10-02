---
sessao: "04"
titulo: "Descontos → Discount/Index + FormRequest + ver × editar (D1) — saída da thread"
autor: "[CL]"
criado: 2026-10-02
base: 07e7853257
thread: 01-telas-legadas.md §04
veredito: "entregue em 2 PRs (backend → tela); prova da thread (Inertia::render('Discount/) no PR 2; nenhum PARAR SE disparou; A2 (validar no servidor) fica para [W]."
---

# _saída 04 · Descontos

## O que saiu

| PR | conteúdo |
|---|---|
| **#8514** (backend) | `discount.view` × `discount.manage` (D1) + migration que concede as duas a quem tinha `discount.access` (papel **e** usuário direto, idempotente) · `SalvarDescontoRequest` (autoriza `manage`, monta a mesma linha, **não valida**) · tela de papéis, `PermissionCatalog`, menu lateral, Blade da lista e botão de desconto do cliente seguem as permissões novas · `DescontosContratoTest` UC-DSC-01..07 |
| **PR 2** (tela, empilhado no #8514) | `DiscountController@index` ganha ramo `X-Inertia` → `Inertia::render('Discount/Index')` **antes** do `ajax()` · `resources/js/Pages/Discount/Index.{tsx,charter.md,casos.md}` · `memory/requisitos/Sells/RUNBOOK-discount.md` · contrato de tela + stub e2e · UC-DSC-08 |

## Decisões aplicadas

- **D1 ([W] 2026-10-02): ver × editar.** Nomes `discount.view` / `discount.manage`, no padrão do precedente `commission_agent.view|manage` (#6072). `manage` também vê a lista. Sem `manage` a tela mostra criar/editar/excluir/desativar/reativar **desabilitados com o motivo** (aviso no topo + `title` nos botões).
- **Origem do acesso no backfill:** `discount.access`, não `brand.*`. Medido no código: os 8 métodos do controller checavam `discount.access`; a Blade checava `brand.view`/`brand.create` só para mostrar botão — sem `discount.access` o servidor dava 403 de qualquer jeito.
- O aviso "Uma permissão só" do protótipo deixou de ser verdade e virou o aviso "Ver × editar" (seção `aviso` do alvo medido na A1).

## Achados novos (medidos nesta thread)

1. **O enunciado supunha `num_uf` no valor — o controller nunca usou.** `discount_amount` e `priority` são gravados crus. O FormRequest mantém cru. A tela React manda o valor normalizado com ponto decimal (`parseDecimalPtBR`), nunca texto pt-BR.
2. **A2 estava parcialmente errado:** nome vazio **não** grava. O `ConvertEmptyStringsToNull` vira `''` em `null`, `discounts.name` é `NOT NULL`, o INSERT falha e o controller responde `success:false`. Prioridade não numérica e datas ausentes seguem passando — endurecer é decisão [W] (mudaria o que é aceito: PARAR SE).
3. **Editar um desconto e salvar sem mexer devolve o mesmo número**: a tela formata o valor com até 4 casas (a coluna é `decimal(22,4)`), não 2.

## Provas

- `DescontosContratoTest` (lane `sells-pest.yml`, MySQL): mesma linha antes/depois do FormRequest por dois caminhos (cópia congelada do código antigo + valores à mão) · ver × editar (403 em toda escrita, linha intacta) · migration · [T0] 98 × 99 · render com `X-Inertia` **e** `X-Requested-With`.
- `contrato-de-tela` limpo (cabecalho · aviso · lista) · `ds-guard` limpo · `tsc` sem erro no arquivo novo · `eslint` limpo · `casos-coverage-guard` sem violação nova.

## Fora desta thread (e por quê)

- **Validação no servidor (A2)** — decisão [W]; muda o que é aceito.
- **Charter/casos do espelho** (`cowork-inbox/venda-menu/Descontos.*`) não foram editados: o Code não edita o espelho. O texto ajustado à D1 está em `resources/js/Pages/Discount/Index.{charter,casos}.md`.
- **Smoke no browser e cutover F5** — humano, depois do merge.

## PARAR SE

Nenhum disparou: sem rota nova (só o resource `/discount` + `activate` + `mass-deactivate`), o FormRequest não muda o que é aceito, e nada de cálculo/aplicação de desconto na venda foi tocado.
