---
sessao: "ordem-2026-10-06"
titulo: "Ordem das pendências dos playbooks — §0, §1, §2, §4 e §6 do pedido"
executor: "[CL]"
data: 2026-10-06
base: bab78f4764 (origin/main, clone não-raso)
pedido: ../../PEDIDO-CL-ordem-pendencias-playbooks-2026-10-06.md
---
# _saida · ordem das pendências (2026-10-06)

Escopo desta sessão: §0 (portão), §1 (recibos que faltam), §2 (pesquisar antes de executar),
§4 (decisões pendentes do [W]) e §6 (este recibo). A §3 (executar threads) **não** foi feita
aqui: uma thread = uma sessão = um PR.

## §0 · Portão

`node scripts/qa/placar.mjs --todos` em `bab78f4764`: **rc=0, 0 ocorrências de `NÃO MEDI`**
em 46 módulos. O handoff (49) do Cowork já estava no `main` (#8822), com o `venda-menu`
corrigido (C0–C6 existem).

## Placar antes → depois (só o que mudou)

| módulo | antes | depois |
|---|---|---|
| venda-menu | entregue 9 de 21 · 1 sem recibo (C0) | **entregue 10 de 21** · 0 sem recibo |
| Fiscal | entregue 4 de 29 · 2 sem recibo (18, 19) | igual — ver abaixo |
| cobertura cumulativa | 179 de 328 (54,6%) | **180 de 328 (54,9%)** |

Os outros 44 módulos não mudaram (diff das 376 linhas do `--todos` só toca as linhas acima).

## §1 · Threads `sem recibo`

O placar mostrou **3**: `venda-menu/C0`, `Fiscal/18`, `Fiscal/19`.

| thread | veredito | o que foi feito |
|---|---|---|
| venda-menu/C0 | **entregue** pelo #8630 (merge `2d23c5a1ad`, 2026-10-05) | recibo **retroativo** escrito: `venda-menu/playbook/_saida-C0.md`. Não havia recibo apagado a restaurar (`git log --all` sobre o path: vazio). As 3 provas nasceram no próprio #8630, depois da ficha (#8629): não é padrão antigo. O teste **executou**: run 37486886677 da lane Sells, 33 passed · 152 assertions · 0 skipped. |
| Fiscal/18 | **NÃO entregue — prova errada no índice** | nenhum recibo escrito |
| Fiscal/19 | **NÃO entregue — prova errada no índice** | nenhum recibo escrito |

**Por que 18 e 19 são prova errada.** As provas do json são `RegraForm.casos.md` contém
`UC-NFRF-04` (e `ImportCsv.casos.md` contém `UC-NFIM-04`) + `TributacaoGatesContratoTest.php`
existe. As três já eram verdadeiras **antes** do conserto: os dois UCs estão escritos no `main`
como `❌ falha esperada` (failing-first). A prova casa o achado, não a correção — é o caso que o
pedido manda desconfiar. Conferido no código: `TributacaoController@destroy` (linha 249) continua
sem `can(`/`abort`. **Em curso por outras sessões:** #8828 (UC-NFRF-04) e #8827 (UC-NFIM-04),
abertos. Volta ao Cowork: a prova que separa entregue de não-entregue é o **status** do UC no
casos.md deixar de ser `falha esperada` (ex.: `nao_contem` de `falha esperada` na linha do UC),
não a presença do id. Não editei o índice (lei do espelho).

## §2 · Módulos com playbook e nenhum recibo — já entregues?

Medido em `bab78f4764`. **Nenhum dos 5 tem entrega sem recibo.**

| módulo | thread | resultado | evidência |
|---|---|---|---|
| sistema | 00 mapa Blade↔protótipo | não entregue | thread sem prova explícita; nenhum `_saida-00`; nenhum PR |
| sistema | 01 Usuários · 02 Funções · 04 Locais/Impressoras/Barcode · 05 Esquemas/Impostos/Tipos · 06 Modelos de notificação/Contas | não entregue | os 11 controllers têm **0** `Inertia::render`; nenhuma Page correspondente em `resources/js/Pages` ou `Modules/*/Resources/js/Pages` |
| sistema | 03 Comissionados | não entregue — **em curso** | `SalesCommissionAgentController` com 0 `Inertia::render`; **#8817 aberto** (`Comissionados/Index`). Sobrepõe a `comissoes/01` — mesma tela em dois playbooks |
| sistema | 07 Relatórios | não entregue | `ReportController` sem `Inertia::render('Relatorios/` (`Pages/Financeiro/Relatorios` e `Pages/Ponto/Relatorios` são de outros donos) |
| tema-escuro | 01 sonda no espelho | **já feito com recibo** (`_saida-01`, #8810) | — |
| tema-escuro | 02 sonda nas Pages de produção | não entregue | `tema-escuro-probe.mjs` só mede o espelho (`ESPELHO_PADRAO`); não há modo produção |
| tema-escuro | 03 corrigir invertidas | não entregue | depende da 02 |
| tema-escuro | 04 triagem no protótipo ([CC]) | não entregue | nenhum `_saida-04` |
| recorrente | 00 puxar 6 Pages vivas ([CC]) | não entregue | `cobranca-recorrente-page.jsx` tem as 4 rotas (`recurring`, `rb-planos`, `rb-faturas`, `rb-config`) mas o diff com as Pages vivas (o `_saida-00` com mapa rota↔Page) não existe |
| recorrente | A1 alvos dos 4 Index | não entregue | os 4 `governance/design/targets/recorrente--*.alvo.json` ausentes |
| notificacoes | 01 modelos em Inertia | não entregue | `NotificationTemplateController` 0 `Inertia::render`; nenhuma Page. Prova usa `${PAGE}` não decidido |
| acessos | 01 `/roles` em Inertia | não entregue | `RoleController` 0 `Inertia::render`; nenhuma Page. Prova usa `${ROLES_PAGE}` não decidido |

**Prova errada / fraca a voltar ao Cowork:** `notificacoes/01` e `acessos/01` têm prova com
variável não decidida (`${PAGE}`, `${ROLES_PAGE}`) — o placar não tem como fechá-las;
`sistema/00`, `recorrente/00` e `tema-escuro/04` têm só nota, sem prova explícita (fecham só
por recibo). `sistema/03` e `comissoes/01` são a mesma tela em dois índices.

## Threads `proximo` reveladas (NÃO executadas aqui)

- **venda-menu/C1** — passou de `pendente` para `proximo` com o recibo do C0. É ato no `.env` de
  produção (biz=1); fecha pelo recibo com GET comum devolvendo a Page.
- Já `proximo` antes e seguem: venda-menu 00 · Q1 · Q3; sistema 00; tema-escuro 02 · 04;
  recorrente 00. A ordem de execução é a §3 do pedido.

## §4 · Decisões que esperam o [W] (`respondida: false`)

| módulo | id | decisão |
|---|---|---|
| modulos | D1 | versão exibida: `system.<alias>_version` (P4) |
| modulos | D4 | install em fila só se houver worker em produção |
| modulos | D5 | remover chaves órfãs do `modules_statuses.json` (P8) |
| modulos-faltantes | VEST-D1 | ligar hard-block de `vestuario.etiqueta.*` |
| modulos-faltantes | VEST-D2 | prévia antes de imprimir: podar charter ou construir |
| modulos-faltantes | PERM | permissão que abre Voz do Cliente e Catálogo QR (trava 03 e 04) |
| comissoes | D-COM-1 | ADR 0151 segue proposta: playbook fica só no legado? |
| comissoes | D-COM-2 | relatório: comissão sobre venda paga ou faturada? |

## PRs

- Este PR: `_saida-C0.md` (retroativo) + este recibo. Nenhum `00-INDICE.md` tocado, nenhum
  `_saida` existente editado.
- Envio ao Cowork: **não** aberto por esta sessão; sobe pelo `pendentes-cowork.mjs --plano`
  quando a sessão da fila de merges liberar.
