---
id: requisitos-ponto-intercorrencias-index-gap
tela: Ponto/Intercorrencias/Index (/ponto/intercorrencias)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Intercorrencias/Index.tsx
gerado_em: 2026-09-28
---

# GAP-SPEC — Ponto/Intercorrencias/Index

> **Origem:** thread `20-gap-intercorrencias.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-PONTO-DETALHE** (detalhe e edição são ROTA PRÓPRIA; o protótipo se
> ajusta, R2) e **D-INTERC-ACOES** (Non-Goal ratificado: a lista não edita nem submete).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24), símbolo
> `Intercorrencias` (`:203-332`). As faixas da thread (`:168-288`) são de 14/09 e não valem mais.
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Intercorrencias/Index.tsx` @ `e4289e688`
> (248 linhas) e `IntercorrenciaController.php`. Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra superior e ação primária | **Paridade de capacidade, copy diferente.** Vivo: `PageHeaderPrimary label="Nova"` no cabeçalho, navegando para `/ponto/intercorrencias/create` (`Index.tsx:109`). Protótipo: `Nova intercorrência` na barra, abrindo o form na mesma tela (`ponto-telas.jsx:295`) e o contador "N de M registros" (`:293`). O charter manda o botão para a rota (`Index.charter.md:34`). | Protótipo corrige o destino (D-PONTO-DETALHE, R2): o botão vai para a rota `create`, não abre form embutido. A copy "Nova" × "Nova intercorrência" é da passada de FORMA. |
| Filtros por estado e tipo | **Paridade.** Vivo: `PageFilters` com chips e reset, partial reload `only: ['intercorrencias','filtros']` (`Index.tsx:113-141`, reload em `:77`). Protótipo: 2 selects + `Limpar` (`ponto-telas.jsx:283-292`). Goal do charter (`Index.charter.md:31`). | Nada. A região que a thread declarava "a nascer" já existe nos dois lados. |
| Lista de intercorrências | **Diverge em colunas.** Vivo: Código, Colaborador, Tipo, Data, Estado (com badge de urgente dentro), **Criada** humanizado, ação (`Index.tsx:170-178`, badge em `:195-197`, Criada em `:201`). Protótipo: Código, Colaborador, Tipo, Data, Estado, **Prioridade** em coluna própria, Ação (`ponto-telas.jsx:304`); sem Criada. O charter lista Criada e põe o urgente na coluna Estado (`Index.charter.md:32`). | Protótipo corrige: ganha a coluna Criada e leva a prioridade para dentro de Estado, como o charter e o vivo. A thread 17 grava `data-contract="intercorrencias-intercorrencias"` no card. |
| Ação por linha | **Paridade.** Vivo: só `Ver`, link para `/ponto/intercorrencias/{id}` (`Index.tsx:203-207`). Protótipo: só `Ver` (`ponto-telas.jsx:318-322`), coluna de 88px (`:304`), depois do D-INTERC-ACOES. | Nada. Falta o Pest GUARD do Non-Goal ratificado (R1) — dono é a thread 27, não este gap. |
| Linha clicável | **Protótipo à frente.** Protótipo: `tr.hit` abre o detalhe e a célula de ação faz `stopPropagation` (`ponto-telas.jsx:311`, `:318`). Vivo: a linha não navega; só o link `Ver` (`Index.tsx:203-207`). | Não medido se vale a pena: linha clicável e link na mesma linha criam dois alvos para o mesmo destino. Fica para a passada de FORMA. |
| Detalhe da intercorrência | **Protótipo diverge da decisão.** Protótipo: `Drawer` de 620px com Dados, Justificativa, Rastreio e ações `Editar`, `Submeter`, `Cancelar` (`ponto-telas.jsx:230-279`). Vivo: página `Show` na rota própria (`resources/js/Pages/Ponto/Intercorrencias/Show.tsx`, 176 linhas). | **Nada — feito no protótipo pela thread 28 (handoff (43), 2026-09-29).** Era: protótipo corrige (D-PONTO-DETALHE, R2): o drawer vira rota `Show`. O `window.confirm` do drawer (`ponto-telas.jsx:270`, `:273`) sai junto. |
| Form embutido de nova e edição | **Protótipo diverge da decisão.** Protótipo: `Card` com `FormIntercorrencia` acima da lista (`ponto-telas.jsx:298-301`). Vivo: rotas `Create.tsx` e `Edit.tsx` separadas. | **Nada — feito no protótipo pela thread 28 (handoff (43), 2026-09-29).** Era: protótipo corrige (D-PONTO-DETALHE, R2). O conteúdo do form é medido em `intercorrencias-create-gap.md`. |
| Paginação | **Paridade.** Vivo: 25 por página no servidor (`IntercorrenciaController.php:40`), navegação com partial reload (`Index.tsx:215-233`). Protótipo: `usePagina(lista.length, 25)` (`ponto-telas.jsx:211`). Charter: 25/página (`Index.charter.md:30`). | Nada. |
| Estado vazio | **Paridade.** Vivo: `EmptyState` com "Sem intercorrências" + criar, e "Nenhum resultado" + `Limpar filtros` (`Index.tsx:146-166`). Protótipo: `Vazio` `first` + `Criar primeira` e `filtered` + `Limpar filtros` (`ponto-telas.jsx:305-307`). | Nada. A thread pedia os dois estados; os dois lados já têm. |
