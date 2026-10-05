---
data: 2026-10-05 15:16 BRT
autor: "[M+C]"
modulo: Manufacturing
tema: Fabricação — grade do DS nas 4 listas, Ordens (filtros/indicadores) e segurança do cadastro de receita
---

# Handoff — Fabricação: grade, Ordens e segurança da receita

## O que entrou no `main` nesta sessão

| PR | o quê | prova |
|---|---|---|
| #8628 | Insumos e Relatório na grade do DS (`shared/DataTable` `density="grid"`) + 3 ajustes na Receitas (cabeçalho 34px, linha 45px, mono 12px) | medido em prod 05/10: as 3 telas batem célula a célula com o protótipo |
| #8636 | registro da medição nas matrizes de comparação | — |
| #8651 | Ordens de produção na grade do DS; selo "Rascunho" passa a contorno (mapa `producao` do `StatusBadge`) | medido em prod 05/10: as 8 células batem; único resíduo, o selo com 12px × 11,5px |
| #8671 | "Copiar da receita" só copia receita da própria empresa (`RecipeBomService::receitaParaCopiar`) | vermelho→verde na lane MySQL (runs 37316751680 → 37319717842) |
| #8690 | filtros das Ordens nas medidas do protótipo | medido em prod: confere, menos o canto (ver #8715) |
| #8698 | "salvar receita" só grava dado da própria empresa + `store()` atômico (`variacaoDaEmpresa`, linha só da própria receita, `DB::transaction`) | vermelho→verde (runs 37343079546 → 37346546233); a etapa 1 mostrou as 3 escritas cruzadas acontecendo |
| #8707 | indicadores das Ordens com a linha de apoio e o tamanho padrão do `KpiCard` | — (medir depois do deploy) |

**Correção de afirmação feita na sessão:** dei como certo, lendo o código, que o "Copiar" antigo **mostrava** a
receita de outra empresa na tela. Medido: a página dava **404** (o `getSubUnits` busca a unidade na empresa
da sessão com `findOrFail`). O servidor lia o dado alheio, mas a tela não o mostrava — por acaso, não por regra.

## Em aberto

1. **#8715** — canto 8px nos filtros das Ordens (saiu 12px: o `rounded-lg` deste projeto vale 12) e cor do
   "Só finalizadas". Aberto; o único vermelho é o `visual-regression` (ver item 6). Depois do merge,
   medir em produção.
2. **Medir em produção os indicadores do #8707** (linha de apoio + altura igual à da Receitas).
3. **Indicadores das Ordens seguindo o filtro — aguarda aprovação [W] (regra de valor).** Hoje
   `ProductionService::summary($business_id)` ignora local e período; no protótipo os 4 números seguem os
   dois (não seguem "Só finalizadas"). O antes→depois da empresa 1 foi feito por 2 caminhos independentes
   (lista da tela nova × JSON do DataTables da tela antiga), iguais nos 4 cenários — os valores ficaram só
   na conversa, nunca no git. Ao implementar: as descrições voltam às do protótipo com filtro ("no filtro de
   local e data", "ordens do período"). Cuidado registrado: a lista tem teto de 25 ordens (`listProductions`
   `limit(25)`) e o rodapé "custo do período" soma só as listadas.
4. **Painel lateral da ordem** (`MfgProducaoDrawer`): detalhe com os ingredientes consumidos — precisa de dado
   novo do servidor.
5. **Janela "Nova receita" — aguarda o Wagner em 3 perguntas** (mensagem entregue à [M] para enviar):
   (a) Categoria e Subcategoria na janela, sabendo que no sistema são do produto; (b) a cópia leva o preço de
   venda (mexe em valor); (c) produto que já tem receita — bloquear (protótipo) ou abrir a existente (Blade).
   Contexto: hoje o botão "Nova receita" da tela nova leva a `/manufacturing/recipe/create`, que devolve só o
   miolo da janela, sem layout nem scripts — a busca de produto não funciona (medido em prod 05/10). Junto
   entra o conserto dos **grupos compartilhados na cópia** (a cópia reaproveita o `mfg_ingredient_group_id` da
   original; renomear o grupo na cópia renomeia na original).
6. **Contrato visual das telas da Fabricação.** Nenhuma tem entrada em `tests/Browser/visreg-screens.json`;
   o `visual-regression` (não required) falha de propósito em PR que mexe só nelas. Não gerar baseline no PR
   de tela (ADR 0409 · §5 2026-09-21); fazer no molde da 0409 (protótipo vivo × aplicação na mesma execução).
7. **Achados menores:** `StoreRecipeRequest` aceita `production_cost_type` só `fixed,percentage` — falta
   `per_unit`, que o cálculo e o protótipo usam. Migrar as 2 telas Blade restantes do módulo.
8. **Ledger de aprendizado:** a afirmação do "Copiar" (lida no código, desmentida pela medição) e o
   `rounded-lg` suposto como 8px são ocorrências de LC-08 (afirmar sem medir) e ainda não têm recibo no
   `memory/LICOES_CODE.md`. Registrar com o adversário (`ciclo-adversary`) antes.

## Estado no fechamento

- Servidor MCP `oimpresso` **indisponível nesta sessão** (HTTP 401 no cabeçalho de autorização), então o
  checklist MCP-first (`cycles-active`, `my-work`, `sessions-recent`, `decisions-search`) **não rodou**. O
  estado acima vem do GitHub (`gh pr view`) e das medições da sessão.
- #8427 (grade antiga das listas, pedido de decisão ao Wagner): **fechado** por ele.
- Medições e sondas da sessão ficam no scratchpad, fora do git; a prova pública está nos PRs.
