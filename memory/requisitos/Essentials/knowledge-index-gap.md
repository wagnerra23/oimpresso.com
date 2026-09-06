---
id: requisitos-essentials-knowledge-index-gap
tela: Essentials/Knowledge/Index (/essentials/knowledge-base)
prototipo: prototipo-ui/cowork/essenciais-extras.jsx
tela_viva: resources/js/Pages/Essentials/Knowledge/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Knowledge/Index

> **Fase 1 = PARIDADE.** `essenciais-page.jsx:1-3` declara o porte reverso do blade
> (`knowledge_base/{index,sidebar,show,create,edit}.blade.php`). A rota `ess-kb` é despachada em
> `essenciais-page.jsx:649` para `X.BaseConhecimento`, que vive em
> `essenciais-extras.jsx:127-188`.
> Charter: [`BaseConhecimento.charter.md`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/BaseConhecimento.charter.md)
> (4 seções: Busca · Árvore · Artigo · Ações de autoria). **Não há contrato de tela** para esta —
> os 5 contratos do intake cobrem tarefas, documentos, memorandos, lembretes e mensagens.
> ⚠️ **Recorte:** a seção "Artigo" do charter pertence à rota `/essentials/knowledge-base/{id}`, que
> é **outra tela** (`ancora.mjs --list`: *"tela de detalhe bespoke … não segue um dos 5 Padrões"*).
> Aqui ela entra só onde o protótipo a renderiza **inline**.

> ⚠️ **Fonte declarada = `essenciais-extras.jsx`**, onde a tela de fato vive; o `essenciais-page.jsx:649`
> apenas despacha a rota `ess-kb` para ela, e o `bundle_source` do charter aponta o page por ser o
> arquivo de entrada do bundle. Os dois são verdade e não conflitam.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Árvore categoria → seção → artigo | **Paridade.** Vivo `Knowledge/Index.tsx:121` (categorias/`bookList`), `:161` (seções, com abrir/fechar em `:162` e `:168`) e `:192` (artigos). Protótipo: `aside.ess-kb-nav` (`:148`) com os 3 níveis em `:152-157`. | Nada — paridade. |
| Ações de autoria | **Paridade, e o vivo cobre mais nós.** Vivo: editar categoria `:135`, adicionar seção `:143`, ver seção `:176`, editar seção `:179`, remover seção `:182`, adicionar artigo `:185`, editar artigo `:198`, remover artigo `:201`. O protótipo só expõe *"Adicionar categoria"* (`:145`). | Nada — vivo à frente. |
| Busca | **Ausente.** Protótipo `:144`: `U.Busca` com placeholder *"Buscar na base · /"* (atalho de teclado), filtrando seções e artigos por título+conteúdo via `casa()` (`:139`, aplicado em `:152` e `:155`). Varredura contada no vivo: os termos `search`, `busca`, `filtro` e `Input` em `Knowledge/Index.tsx` = **0 ocorrência de cada um**. | **Decidir.** É a **1ª seção** do charter e é comportamento (filtrar) → vira pedido pela régua do playbook. O charter também lista *"busca sem resultado"* como estado coberto, que hoje não existe. A árvore já chega inteira no payload, então o filtro pode ser client-side — barato, mas é decisão de [W] porque muda a 1ª dobra da tela. |
| Leitura do artigo inline | **Diverge por desenho.** Protótipo `:160-176`: `article.ess-kb-doc` mostra título, resumo, blocos ricos (`:163-168`) e os filhos como cartões (`:170-175`) **na mesma tela**. Vivo: clicar navega para `/essentials/knowledge-base/{id}` (`:176`, `:195`) — leitura em rota própria. | **Decidir.** Layout de duas colunas × navegação por rota é decisão de forma, e o eixo FORMA tem o protótipo como soberano ([UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)). Mas a rota de detalhe **já existe e é canon** (aparece no `ancora.mjs --list` com âncora própria declarada). Fundir as duas telas é decisão de [W], não conserto. |
| Estado "sem permissão de editar" | **Divergência de mecanismo.** Protótipo: `A.pode("gerir_kb")` esconde o botão de autoria (`:145`). Vivo: os botões de autoria são renderizados na árvore sem guarda visível de permissão no `.tsx`. | **Decidir.** ⚠️ Verificar **no controller** antes de tratar como defeito: a guarda pode estar no back-end (o padrão do módulo) e a ausência no `.tsx` ser correta. O charter exige o padrão explícito — *"o que o papel não pode aparece bloqueado com motivo, nunca escondido sem explicação"* — que é diferente **dos dois** comportamentos acima. Medir o controller é pré-requisito da decisão. |
