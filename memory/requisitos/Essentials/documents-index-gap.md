---
id: requisitos-essentials-documents-index-gap
tela: Essentials/Documents/Index (/essentials/document)
prototipo: prototipo-ui/cowork/essenciais-page.jsx
tela_viva: resources/js/Pages/Essentials/Documents/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Documents/Index

> **Fase 1 = PARIDADE.** `essenciais-page.jsx:1-3` declara o porte reverso do blade do main.
> Região: `Arquivos({ modo })` (`:338-400`) — **um só componente serve Documentos e Memorandos**,
> exatamente como o vivo, que alterna por aba (`Documents/Index.tsx:78`, `tab: 'documents' | 'memos'`).
> Contratos do intake: [`documentos.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/documentos.contract.json)
> e [`memorandos.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/memorandos.contract.json).
> ⚠️ **Versionamento de documento está fora de escopo por decisão pendente de [W]**
> (`LEIA-ME.md` §"Decisões pendentes" item 2: *"hoje é media única; versionar pede tabela de versões
> e regra de quem substitui"*) — não entra como gap aqui.

> ⚠️ **O contrato citado ainda NÃO é gate ativo.** Ele vive em
> `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`
> (medido com o critério do próprio gate — `git ls-files "prototipo-ui/contrato/*.contract.json"` sem o EXEMPLO, como `scripts/contrato-de-tela.mjs` faz: **28** contratos ativos, que incluem `essentials-tipos`, `essentials-licencas` e
> `essentials-metas` — nenhum dos 5 dos essenciais). Ele é **proposta de contrato**: descreve a
> copy literal pretendida e serve de âncora para esta comparação, mas **não trava merge hoje**.
> Por isso as divergências de copy abaixo saem como `Decidir.`, nunca como "quebra de gate".

| Parte | Estado no vivo | Ação |
|---|---|---|
| Unificação Documentos + Memorandos | **Paridade estrutural.** Vivo: uma tela com 2 abas — `Documents/Index.tsx:273` (`TabButton` documents) e `:276` (memos), com o alvo de rota alternando em `:105`. Protótipo: mesmo componente com `modo` (`:338`), e o `sub` do Card explica a razão do domínio: *"o memorando é um documento com tipo memos no mesmo controller"* (`:372`). | Nada — paridade. |
| Título do card | **Paridade literal nas duas abas.** Protótipo `:372`: `memo ? "Todas as notas" : "Todos os documentos"` — os dois `copy.titulo` dos contratos. Vivo: `:250` alterna `Memos`/`Documentos` no cabeçalho e `:252-256` alterna a descrição (*"Avisos e memorandos internos em texto."* / *"Arquivos compartilhados com a equipe."*). | **Decidir.** O contrato trava *"Todos os documentos"* / *"Todas as notas"*; o vivo grafa *"Documentos"* / *"Memos"*. Divergência é **só de copy**, e copy de contrato é soberania de [W] — não se troca de passagem. |
| Botão de criação | **Paridade de capacidade, copy diferente.** Contrato: `botao_novo` = "Adicionar" (protótipo `:373`). Vivo: `:259` abre o formulário na aba `documents`. | **Decidir.** Mesma classe da linha acima: conferir a copy literal contra o contrato antes de qualquer mexida na tela. |
| Colunas da tabela | **Diverge no rótulo, não na capacidade.** Vivo `:295-298`: `{memos ? 'Título' : 'Arquivo'}` · Descrição · **Enviado em** · Ações. Contrato de documentos: **Nome** · Descrição · **Data do upload** · Ações; o de memorandos: **Título** · Descrição · **Data de criação** · Ações. A 1ª coluna do vivo já alterna certo por aba (`Título` bate com memorandos); divergem `Arquivo`×`Nome` e `Enviado em`×`Data do upload`/`Data de criação`. | **Decidir.** Renomeação, não ausência. Note que o vivo usa **um rótulo só** (`Enviado em`) para as duas abas, enquanto os contratos pedem rótulos distintos — decidir se a coluna alterna com a aba. |
| Formulário de upload | **Paridade.** Vivo `:358-398`: `<Dialog>` com input de arquivo (`:373`), barra de progresso (`:376-380`), campo Descrição (`:387-392`) e botão Enviar (`:398`). Protótipo: `FormArquivo` (`:401-443`), acionado em `:374`. O estado `tipo-recusado` do contrato tem contraparte em `:384` (`uploadForm.errors.name`) e no erro de `:125`. | Nada — paridade. |
| Ação Baixar | **Paridade literal.** Vivo `:321` (`<Download/> Baixar`). Protótipo `:357`: `memo ? "Ver" : "Baixar"` — o vivo não expõe o "Ver" do memorando. | Nada na aba de documentos — paridade. |
| Ação Compartilhar + drawer | **Paridade.** Vivo `:463` (`<Share2/> Compartilhar`) e a dica em `:364` (*"Compartilhe via ícone … após o upload"*). Protótipo: botão em `:358` (sob `A.pode("compartilhar")`) e o drawer de detalhe em `:383` (com "Compartilhar" em `:385`). | Nada — paridade. |
| Estado vazio "primeira vez" | **Diverge.** Protótipo `:377-379`: `Vazio` com variante `first` × `no-results` e textos distintos — o de memorando explica o que é o artefato (*"Memorando é o recado que fica: regra de balcão, plantão de feriado, prazo do mês"*). O vivo tem estado vazio, mas único. | **Decidir.** Mesma classe do `Todo/Index`: os contratos listam `primeira` e `normal` como estados **separados**. Copy de onboarding é decisão de [W]. |
