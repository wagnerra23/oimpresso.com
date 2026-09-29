---
id: requisitos-ponto-intercorrencias-index-gap
tela: Ponto/Intercorrencias/Index (/ponto/intercorrencias)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Intercorrencias/Index.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Ponto/Intercorrencias/Index

> **Origem:** thread `20-gap-intercorrencias.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-PONTO-DETALHE** (detalhe e edição são ROTA PRÓPRIA; o protótipo se
> ajusta, R2) e **D-INTERC-ACOES** (Non-Goal ratificado: a lista não edita nem submete).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24), símbolo
> `Intercorrencias` (`:203-332`). As faixas da thread (`:168-288`) são de 14/09 e não valem mais.
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Intercorrencias/Index.tsx` @ `e4289e688`
> (248 linhas) e `IntercorrenciaController.php`. Toda linha abaixo saiu de `grep -n`.
> **Re-medido em 2026-09-29:** protótipo após o handoff 43 (thread 28 — Show, Create e Edit viraram
> endereço próprio; símbolo `Intercorrencias` em `ponto-telas.jsx:209-350`, rotas lidas pelo shell em
> `ponto-page.jsx:450-463`) e vivo `Index.tsx` com 243 linhas; as citações de linha abaixo são desta data.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra superior e ação primária | **Paridade de capacidade, copy diferente.** Vivo: `PageHeaderPrimary label="Nova"` no cabeçalho, navegando para `/ponto/intercorrencias/create` (`Index.tsx:105`). Protótipo: `Nova intercorrência` na barra (`ponto-telas.jsx:320`), que desde 2026-09-29 (thread 28) navega para o endereço `pt-intercorrencias-create` (`onNovo` → `irInterc("create")`, `ponto-page.jsx:510`), e o contador "N de M registros" (`ponto-telas.jsx:319`). O charter manda o botão para a rota (`Index.charter.md:34`). Fato datado: quando este gap foi medido, em 2026-09-28, o botão do protótipo abria o form na mesma tela. | Nada — destino corrigido no protótipo pela thread 28 (D-PONTO-DETALHE, R2). A copy "Nova" × "Nova intercorrência" é da passada de FORMA. |
| Filtros por estado e tipo | **Paridade.** Vivo: `PageFilters` com chips e reset, partial reload `only: ['intercorrencias','filtros']` (`Index.tsx:108-136`, reload em `:78`). Protótipo: 2 selects + `Limpar` (`ponto-telas.jsx:310-318`). Goal do charter (`Index.charter.md:31`). | Nada. A região que a thread declarava "a nascer" já existe nos dois lados. |
| Lista de intercorrências | **Diverge em colunas.** Vivo: Código, Colaborador, Tipo, Data, Estado (com badge de urgente dentro), **Criada** humanizado, ação (`Index.tsx:167-173`, badge em `:191-193`, Criada em `:196`). Protótipo: Código, Colaborador, Tipo, Data, Estado, **Prioridade** em coluna própria, Ação (`ponto-telas.jsx:324`); sem Criada. O charter lista Criada e põe o urgente na coluna Estado (`Index.charter.md:32`). | Protótipo corrige: ganha a coluna Criada e leva a prioridade para dentro de Estado, como o charter e o vivo. O `data-contract="intercorrencias-intercorrencias"` já está no card dos dois lados em 2026-09-29 (`ponto-telas.jsx:323`, `Index.tsx:138`). |
| Ação por linha | **Paridade.** Vivo: só `Ver`, link para `/ponto/intercorrencias/{id}` (`Index.tsx:197-203`). Protótipo: só `Ver` (`ponto-telas.jsx:338-341`), coluna de 88px (`:324`), depois do D-INTERC-ACOES. | Nada. O Pest GUARD do Non-Goal ratificado (R1) existe desde 2026-09-28 ([#8093](https://github.com/wagnerra23/oimpresso.com/pull/8093)): `UC-INTIDX-04` em `Modules/Ponto/Tests/Feature/IntercorrenciaContratoTest.php:324`. |
| Linha clicável | **Protótipo à frente.** Protótipo: `tr.hit` abre o detalhe e a célula de ação faz `stopPropagation` (`ponto-telas.jsx:331`, `:338`). Vivo: a linha não navega; só o link `Ver` (`Index.tsx:197-203`). | Não medido se vale a pena: linha clicável e link na mesma linha criam dois alvos para o mesmo destino. Fica para a passada de FORMA. |
| Detalhe da intercorrência | **Paridade de rota desde 2026-09-29 (thread 28, handoff 43).** Protótipo: o detalhe é endereço próprio `pt-intercorrencias-<uuid>` (`ponto-telas.jsx:205`), página com Dados, Justificativa, Rastreio e ações `Editar`, `Submeter`, `Cancelar` (`:258-304`); `grep -c "Drawer"` no arquivo = 0. Vivo: página `Show` na rota própria (`resources/js/Pages/Ponto/Intercorrencias/Show.tsx`, 190 linhas). O `window.confirm` segue no protótipo (`ponto-telas.jsx:269`, `:272`), e o vivo também confirma com `confirm` nativo (`Show.tsx:54`, `:62`). Fato datado: quando este gap foi medido, em 2026-09-28, o protótipo abria um `Drawer` de 620px. | Trocar o `confirm` nativo pelo diálogo do DS nos dois lados (`ponto-telas.jsx:269`, `:272` · `Show.tsx:54`, `:62`). A rota já é paridade (D-PONTO-DETALHE, R2); o `confirm` é a metade que esta linha mandava tirar junto com o drawer, e a regra é a mesma da ata de 2026-09-14 (D-ESC-DESTROY: *`window.confirm` não era pergunta — usa o dialog do DS*) e do lote de aprovações (#8079). |
| Form embutido de nova e edição | **Paridade de rota desde 2026-09-29 (thread 28, handoff 43).** Protótipo: `FormIntercorrencia` em página própria, endereços `pt-intercorrencias-create` e `pt-intercorrencias-<uuid>-edit` (`ponto-telas.jsx:206-207`, página em `:237-248`). Vivo: rotas `Create.tsx` e `Edit.tsx` separadas. Fato datado: quando este gap foi medido, em 2026-09-28, o form era um `Card` acima da lista. | Nada — cumprido no protótipo pela thread 28 (D-PONTO-DETALHE, R2). O conteúdo do form é medido em `intercorrencias-create-gap.md`. |
| Paginação | **Paridade.** Vivo: 25 por página no servidor (`IntercorrenciaController.php:44`), navegação com partial reload (`Index.tsx:210-231`). Protótipo: `usePagina(lista.length, 25)` (`ponto-telas.jsx:216`). Charter: 25/página (`Index.charter.md:30`). | Nada. |
| Estado vazio | **Paridade.** Vivo: `EmptyState` com "Sem intercorrências" + criar, e "Nenhum resultado" + `Limpar filtros` (`Index.tsx:140-161`). Protótipo: `Vazio` `first` + `Criar primeira` e `filtered` + `Limpar filtros` (`ponto-telas.jsx:325-327`). | Nada. A thread pedia os dois estados; os dois lados já têm. |
