---
id: requisitos-ponto-importacoes-index-gap
tela: Ponto/Importacoes/Index (/ponto/importacoes)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Importacoes/Index.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Importacoes/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/24-gap-importacoes.md
---

# GAP-SPEC — Ponto/Importacoes/Index

> **Fonte do contrato:** charter `Importacoes/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Importacoes` (`:745-886`, re-medido 2026-09-29) — **1 símbolo, 3 telas**: a lista é o
> `return` final (`:856-885`); o upload é o `if (nova)` (`:823-854`, ver `importacoes-create-gap.md`)
> — até 2026-09-28 era um Card inline na lista, e desde a thread 28 (2026-09-29) é página própria
> (`sub === "novo"`); o detalhe é o `if (sel)` (`:758-821`, ver `importacoes-show-gap.md`). Lado vivo
> medido em `Importacoes/Index.tsx` e `ImportacaoController.php` (`origin/main` e4289e688; linhas
> re-medidas em 2026-09-29). O `.tsx` vivo tinha **0** `data-contract` nessa medição; desde #8091 tem
> **1** (`Index.tsx:69`, `importacoes-historico-de-importacoes`).

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra e ação primária | **Diverge desde 2026-09-29.** Vivo: só o `PageHeaderPrimary` "Nova importação" navegando para `/ponto/importacoes/novo` (`Index.tsx:65-67`); o subtítulo "Dedup por SHA-256." saiu com o header de módulo do W9 (#8118) — `grep -n "SHA" Importacoes/Index.tsx` = 0. Protótipo: barra com a nota de duplicado por SHA-256 (`ponto-telas.jsx:859`) + "Nova importação AFD" (`:861`), que desde a thread 28 (2026-09-29) abre página própria (`sub === "novo"`, `:823-854`), não mais upload inline. | **Incorporar** a frase de dedup por SHA-256 na barra do corpo (copy do protótipo, `ponto-telas.jsx:859`). O destino do botão já é paridade: `D-PONTO-DETALHE` = ROTA PRÓPRIA, e o protótipo passou a cumpri-la na thread 28 (2026-09-29). |
| Histórico de importações | **Vivo com 8 colunas × protótipo com 9.** Vivo `Index.tsx:89-96`: Arquivo · Tipo · Tamanho · Estado · Linhas · Por · Quando · ação. Protótipo `ponto-telas.jsx:866`: + **ID** (mono), sub-linha do arquivo "N linhas com erro / sem erros" (`:871`) e "processadas de total" (`:875`). O vivo mostra `criadas/processadas` (`Index.tsx:110-112`); o payload da lista não tem total de linhas nem contagem de erro (`ImportacaoController.php:34-48`). | **Decidir** se a coluna ID e a sub-linha de erro entram. Não há `D-*` para as colunas da lista (o `D-IMP-EXTRAS` é do Show). Exigiria `linhas_erro`/total no payload da lista. |
| Data da importação | **Vivo segue o charter.** Vivo mostra data **humanizada** com a absoluta no `title` (`Index.tsx:114-116`). Protótipo mostra data absoluta mono (`ponto-telas.jsx:877`). | Nada — vivo à frente (o charter pede "quando (humanizado)"). |
| Ordenação e paginação | **Vivo à frente.** Vivo ordena por mais recente no servidor (`ImportacaoController.php:30`, `orderByDesc('created_at')`) e pagina 20 (`:31`), com partial reload `only: ['importacoes']` (`Index.tsx:144`). Protótipo pagina 20 (`ponto-telas.jsx:754`) mas **não ordena** a lista existente — defeito declarado pela própria thread 24. | Nada — vivo à frente. O protótipo corrige a ordenação. |
| Estado vazio | **Vivo à frente.** Vivo: `EmptyState` com CTA "Fazer primeira importação" (`Index.tsx:72-83`), que é o que o charter pede. Protótipo: só texto, sem CTA (`ponto-telas.jsx:867`). | Nada — vivo à frente; o CTA é catch-up do protótipo. |
| Filtro por estado e tipo | **Ausente nos dois lados.** `grep -ci` de `filtro` e de `filter` em `Importacoes/Index.tsx` = 0 e 0 (2026-09-29); o `index` do controller não lê filtro (`ImportacaoController.php:24-51`). Protótipo também não tem. | **Incorporar** — `D-IMP-FILTRO` = ENTRA ("barato, e a lista cresce todo dia"). Filtro de estado + tipo no controller e na tela, com partial reload. |
