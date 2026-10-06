# Três incoerências dentro do próprio Design System

**De:** [M+C] · **Data:** 2026-10-06 · **Projeto DS:** `019dd02f` · **Origem:** comparação das 5 abas da Fabricação

A [M] pediu que telas irmãs não tenham a mesma peça de jeitos diferentes. Ao medir as 5 abas da
Fabricação (produção e protótipo, mesma sonda, estilo computado), três das diferenças **não eram da
tela**: vinham de componentes do DS que se contradizem. A produção já foi corrigida; o DS precisa
acompanhar, senão a próxima comparação com o protótipo acusa a produção.

Regra que a [M] deu, textual: *"a decisão do navegador não tem que se sobrepor à do DS"*.

---

## 1. `DataGrid` usa a caixa do navegador; o DS tem o `Checkbox`

**Onde:** `components/DataGrid/DataGrid.jsx`, linha do `const chk = { accentColor: 'var(--accent)', width: 13, height: 13 }`.

**O que acontece:** a coluna de seleção de linha (e o "selecionar todas" do cabeçalho) usa
`<input type="checkbox">` nativo, 13px, só com a cor trocada. Todo o resto do DS usa o
`components/Checkbox`: 16px, canto `--radius-sm`, borda 1,5px, fundo `--accent` quando marcado.

**Efeito medido:**
- a caixinha da tabela de Receitas fica diferente das caixinhas de filtro ("Só finalizadas") das outras abas;
- a margem padrão que o navegador põe na caixa nativa (3px 3px 3px 4px) sobe o cabeçalho da tabela
  para **34px**. O cabeçalho do `DataGrid` sem seleção mede **27px** (`padding 7px 10px` + linha 12px +
  borda 1px). Por isso a tabela de Receitas tinha cabeçalho mais alto que Insumos, Ordens e Relatório.

**Pedido:** o `DataGrid` usar o `Checkbox` do DS na coluna de seleção, mantendo o cabeçalho em 27px
(na produção: margem vertical negativa de 2px, para os 16px ocuparem a altura de uma linha de texto).

**Produção:** PR `fix/datatable-caixinha-do-ds` — `shared/DataTable` passou a usar o `Checkbox`.

## 2. `Checkbox` não tem o estado "parcial"

**Onde:** `components/Checkbox/Checkbox.jsx` — só `checked` true/false.

**Por que importa:** o "selecionar todas" do cabeçalho precisa de três estados: nenhuma, todas e
**algumas** linhas marcadas. A caixa nativa desenhava o tracinho sozinha (`indeterminate`); ao trocar
para o `Checkbox`, o estado parcial some ou aparece como ✓ — dizendo que tudo está marcado quando não está.

**Pedido:** o `Checkbox` ganhar o estado parcial (sugestão: `checked="indeterminate"` → tracinho no
lugar do ✓, mesmo fundo `--accent`).

**Produção:** já desenha o tracinho (`Components/ui/checkbox.tsx`, mesmo PR).

## 3. `DatePicker` tem 36px; `Input`, `select` e `SearchInput` têm 34px

**Onde:** `components/DatePicker/DatePicker.jsx` (`height: 36`) contra o `controlStyle` do
`components/Input/Input.jsx` (`padding 7px 10px` + `13px/1.4` = 34px) e o `SearchInput`.

**Efeito medido:** na barra de filtros das Ordens, "Local" (select, 34) e as datas (36) ficam lado a
lado com 2px de diferença de altura.

**Pedido:** o DS fixar **uma** altura de controle de formulário. A produção alinhou as datas em
**34**, a altura da maioria dos controles (`Manufacturing/_lib/filtros.ts`). Se o DS escolher 36 para
todos, a produção acompanha.

---

## O que NÃO é pedido aqui (já está certo no protótipo, a produção é que estava errada)

- Relatório com campos de data, rótulos DE/ATÉ e "Só finalizadas" diferentes da aba Ordens;
- Configurações com caixas de marcação no lugar dos **interruptores** do protótipo;
- busca de Insumos sem a lupa e a altura do `SearchInput` de Receitas;
- subtítulo do cabeçalho de Receitas quebrando em 2 linhas (teto de 60ch que o protótipo já não tem).

Tudo isso foi corrigido na produção (PRs da Fabricação de 2026-10-06), copiando o protótipo.
