---
id: requisitos-ponto-espelho-show-gap
tela: Ponto/Espelho/Show (/ponto/espelho/{colaborador})
prototipo: prototipo-ui/cowork/ponto-page.jsx
tela_viva: resources/js/Pages/Ponto/Espelho/Show.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Ponto/Espelho/Show

> **Fase 1 = PARIDADE.** `ponto-page.jsx:1-4` declara o porte reverso. Regiões do protótipo:
> `EspelhoShow` (`:329-446`) · `GradeMes` (`:160-207`) · `DiaDrawer` (`:209-270`) ·
> `FolhaEspelho` (`:272-327`). Esta é a tela que o contrato
> [`ponto-espelho.contract.json`](../../../prototipo-ui/contrato/ponto-espelho.contract.json)
> de fato descreve: as 5 seções da `ordem` são todas daqui, e as **5 estão ancoradas por
> `data-contract` no vivo** — âncora estável, não range de linha.
> Lei do módulo que enquadra a última linha desta tabela: correção de marcação é **anulação +
> nova marcação**, nunca edição (Portaria MTP 671/2021 · `COLAR-NO-CODE-ponto-ondas.md` §0 lei 1).

| Parte | Estado no vivo | Ação |
|---|---|---|
| Dados do colaborador | **Paridade literal.** `Show.tsx:179` (`data-contract="espelho-dados-colaborador"`); os 7 rótulos de campo saem em `:187-196` (Matrícula · CPF · PIS · Escala atual · Carga diária · Admissão · Desligamento) e o 8º item de `copy` do contrato — o título *"Dados do colaborador"* — em `:181`. O comentário `:178` registra o porquê legal: *"sem matrícula/CPF/PIS o documento não serve"* à fiscalização. | Nada — paridade. |
| Totalizadores do mês (6) | **Paridade literal.** `Show.tsx:220` (`data-contract="espelho-totais"`); os 6 rótulos do contrato em `:221-226`: Trabalhado · Atraso · Falta · Hora extra · Banco hrs (+) · Banco hrs (−). O comentário `:216-217` declara que são os do Blade legado campo a campo e que "Hora extra" soma diurna+noturna. | Nada — paridade. |
| Seletor de modo (Tabela / Grade do mês) | **Paridade.** `Show.tsx:243` (`data-contract="espelho-modo-visao"`), com "Tabela" `:250` e "Grade do mês" `:258` — a copy literal do contrato. Protótipo em `ponto-page.jsx:411-412`. A grade é o `MonthHeatmap` (`:263`), contraparte do `GradeMes` do protótipo. | Nada — paridade. |
| Apuração diária (7 colunas) | **Paridade literal.** `Show.tsx:281` (`data-contract="espelho-apuracao-diaria"`); as 7 colunas do contrato em `:303-309`: Previsto · Realizado · Marcações · Atraso · HE · BH (+/−) · Estado — a mesma ordem do `ponto-page.jsx:415`. | Nada — paridade. |
| Folha de impressão | **Paridade.** `Show.tsx:389` (`data-contract="espelho-folha-impressao"`), com "Espelho de Ponto Eletrônico" `:390`, "Apuração diária" `:395`, "Totais do mês" `:396`, a linha de assinatura "Responsável RH" `:399` e a norma "Portaria MTP 671/2021 Art. 85" `:402`. Protótipo: `FolhaEspelho` (`:272-327`). O vivo ainda oferece a rota dedicada `/{id}/imprimir` (`:147-148`), que o protótipo simula com `window.print()` (`:367`). | Nada — vivo à frente na rota de impressão. |
| Navegação de mês | **Paridade de capacidade.** Vivo: "Mês anterior" `:158` + `<input type="month">` `:163-169`, com partial reload `only: ['mes','totais','linhas']` (`:117`). Protótipo: `anterior`/`proximo` derivados de `D.MESES` (`:343`). O comentário `:162` explica por que aqui o label é visual e no `Index` é `<label htmlFor>`. | Nada — paridade. ⚠️ O `_pendente_w` do contrato pergunta *"quantos meses ficam navegáveis antes de exigir o relatório"* — segue aberto, e é do contrato, não deste gap. |
| Drawer do dia — marcações com NSR/origem/REP/hash | **Ausente.** Protótipo: `DiaDrawer` (`ponto-page.jsx:209-270`), tabela de 6 colunas Hora · NSR · Origem · REP · Hash · Ação (`:245`). No vivo, `origem` só aparece como `title` de tooltip (`Show.tsx:353` e `:504`); não há drawer, nem NSR, nem hash. Varredura contada: os quatro termos `Drawer`, `Sheet`, `NSR` e `hash` em `Show.tsx` = **0 ocorrência de cada um, no arquivo inteiro** (`rg` rc=1; controle positivo: `origem` casa 3×, em `:62`, `:353` e `:504`). Os dois tooltips carregam `origem`, não NSR nem hash. | **Decidir.** É leitura de dado que o vivo já tem (`ponto_marcacoes` traz NSR e hash) e é a prova documental que a fiscalização pede. Mas abrir o drawer sem a ação de anular (linha seguinte) entrega metade — e a ação está travada. Decisão de [W]: drawer só-leitura agora, ou junto com a anulação. |
| Anular marcação (append-only) | **Ausente na UI e SEM ROTA — a capacidade existe pela metade no backend.** Varredura contada: `[Aa]nular` em `resources/js/Pages/Ponto/**` = **0 ocorrências**. `Modules/Ponto/Http/routes.php:39-41` expõe só `index`/`show`/`imprimir` (três GET) — **não existe rota de anulação**. `Modules/Ponto/Http/Requests/AnularMarcacaoRequest.php` existe e é **órfão**: `rg AnularMarcacaoRequest` no repo devolve 10 hits, **todos** em `memory/**` (SUPERFICIE, README, CHANGELOG, AUDIT) e no próprio arquivo — **zero** em controller, rota ou Page. O `MarcacaoService` tem a regra — medido com o comando ao lado: `rg -c "anula" Modules/Ponto/Services/MarcacaoService.php` = **7 linhas** (com `-i`: **12**). | **Decidir.** O botão "Anular" do `Espelho/Show` é âncora nomeada da **frente 5** do [`COLAR-NO-CODE-ponto-ondas.md`](../../../prototipo-ui/design-docs/COLAR-NO-CODE-ponto-ondas.md) §1, **travada** nas decisões [W] 1–4 — e a invariante §4.3 (*"competência fechada desabilita Anular no Espelho/Show"*) pressupõe um estado de competência que ainda não existe. Construir antes é inventar lei. |
