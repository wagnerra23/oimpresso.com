---
id: requisitos-ponto-importacoes-index-gap
tela: Ponto/Importacoes/Index (/ponto/importacoes)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Importacoes/Index.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Importacoes/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/24-gap-importacoes.md
---

# GAP-SPEC — Ponto/Importacoes/Index

> **Fonte do contrato:** charter `Importacoes/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Importacoes` (`:716-846`) — **1 símbolo, 3 telas**: a lista é o `return` final
> (`:791-845`); o upload é o Card inline (`:799-823`, ver `importacoes-create-gap.md`); o detalhe é
> o `if (sel)` (`:726-789`, ver `importacoes-show-gap.md`). Lado vivo medido em
> `Importacoes/Index.tsx` e `ImportacaoController.php` (`origin/main` e4289e688). O `.tsx` vivo
> tem **0** `data-contract`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra e ação primária | **Paridade de conteúdo.** Vivo: subtítulo "Dedup por SHA-256." (`Index.tsx:66`) + `PageHeaderPrimary` "Nova importação" navegando para `/ponto/importacoes/novo` (`:70`). Protótipo: nota de duplicado por SHA-256 + botão que abre o upload **inline** (`:793-797`). | Nada na lista. A diferença de destino (rota × inline) é `D-PONTO-DETALHE` = ROTA PRÓPRIA: o vivo está certo e quem muda é o protótipo (R2). |
| Histórico de importações | **Vivo com 8 colunas × protótipo com 9.** Vivo `Index.tsx:93-102`: Arquivo · Tipo · Tamanho · Estado · Linhas · Por · Quando · ação. Protótipo `:826`: + **ID** (mono), sub-linha do arquivo "N linhas com erro / sem erros" (`:831`) e "processadas de total" (`:835`). O vivo mostra `criadas/processadas` (`:115-117`); o payload da lista não tem total de linhas nem contagem de erro (`ImportacaoController.php:34-48`). | **Decidir** se a coluna ID e a sub-linha de erro entram. Não há `D-*` para as colunas da lista (o `D-IMP-EXTRAS` é do Show). Exigiria `linhas_erro`/total no payload da lista. |
| Data da importação | **Vivo segue o charter.** Vivo mostra data **humanizada** com a absoluta no `title` (`Index.tsx:119-121`). Protótipo mostra data absoluta mono (`:837`). | Nada — vivo à frente (o charter pede "quando (humanizado)"). |
| Ordenação e paginação | **Vivo à frente.** Vivo ordena por mais recente no servidor (`ImportacaoController.php:30`, `orderByDesc('created_at')`) e pagina 20 (`:31`), com partial reload `only: ['importacoes']` (`Index.tsx:149`). Protótipo pagina 20 (`:723`) mas **não ordena** a lista existente — defeito declarado pela própria thread 24. | Nada — vivo à frente. O protótipo corrige a ordenação. |
| Estado vazio | **Vivo à frente.** Vivo: `EmptyState` com CTA "Fazer primeira importação" (`Index.tsx:76-88`), que é o que o charter pede. Protótipo: só texto, sem CTA (`:827`). | Nada — vivo à frente; o CTA é catch-up do protótipo. |
| Filtro por estado e tipo | **Ausente nos dois lados.** `grep -ci` de `filtro` e de `filter` em `Importacoes/Index.tsx` = 0 e 0; o `index` do controller não lê filtro (`ImportacaoController.php:24-51`). Protótipo também não tem. | **Incorporar** — `D-IMP-FILTRO` = ENTRA ("barato, e a lista cresce todo dia"). Filtro de estado + tipo no controller e na tela, com partial reload. |
