---
sessao: "03"
saida: 2026-09-08
base_lida: 99530522729d (árvore do turno; o índice nasceu em 11eff17f13db)
dono: "[CC]"
veredito: FEITA — e derruba as threads 04, 05 e 06
---
# `_saida-03` — Aferição do Fiscal

## 1 · Feito (por caminho)
Aferição read-only, sem tocar em nenhum arquivo do `main` nem do build. Quatro buscas dirigidas + 3 charters lidos (`Dfe` 2.008 B · `Eventos` 4.489 B · `Config` 4.512 B). **Nenhum PR aberto** — como manda a thread.

## 2 · `SeloProcedencia` está plugado — D-PROC **respondida pelo código**
Não é órfão. A busca anterior falhou por sintaxe: é **named import**, não default.
```
Cockpit.tsx:31   import { SeloProcedencia } from './_components/SeloProcedencia';
Cockpit.tsx:36   import { type MapaProcedencia } from './_lib/procedencia';
```
E está renderizado em **9 pontos**, um por superfície: `chave="sefaz"` (:368) · `"eventos"` (:380) · `"contabil"` (:393) · `"kpis"` e `"spark"` (:425) · `"alerts"` (:465) · `"writeoff"` (:473) · `"notas"` (:500) · `"viewCounts"` (:501).

**Consequência:** o `CU-FISC-16` ("procedência das 6 superfícies") está **implementado no cockpit com 9 chaves**. A pergunta que resta para [W] deixou de ser *"marcar na UI, flag ou Non-Goal?"* e passou a ser **"as 9 chaves em produção são as que você queria, e falta alguma superfície fora do cockpit?"** — confirmação, não decisão.

## 3 · As 4 telas "nunca lidas": **as 4 ondas já estão feitas**
Eu ia produzir 4 fichas do §13.2. Não produzi nenhuma — porque nenhuma das 4 ondas sobreviveu à medição. Ficha de trabalho que não existe é papel.

| onda de 03/09 | veredito | prova (caminho e linha) |
|---|---|---|
| **6** DF-e manifestação em lote | **FEITA** | `Dfe.tsx:381` `data-contract="lote-dfe"` `role="region"` · seleção em `:140-163` · modal `:607` · *"uma requisição por nota"* `:626` · aviso **"Manifestação é definitiva por nota — não há desfazer em lote"** `:659` · `Dfe.casos.md:238`: **UC 07..10, os primeiros verdes da tela** (2026-09-04), e o botão `disabled` com title *"Bulk manifestar (PR seguinte)"* **saiu** |
| **7** Export CSV de eventos | **FEITA e contratada** | `Eventos.charter.md` §"Contrato do export CSV (Onda 7 · 2026-09-03)": `GET /fiscal/eventos/export`, gate `fiscal.access`, `throttle:6,1`, escopo = conjunto **filtrado**, teto 10.000 linhas em lotes de 500, BOM UTF-8 + `;`, 7 colunas, `UC-FEVT-05/06/07`. O Non-Goal *"❌ Export CSV (backlog)"* foi **revogado no mesmo PR que entregou** |
| **8** Config: abas + gate de ambiente | **FEITA** | `Config.tsx:26` `type ConfigTab = 'cert' \| 'series' \| 'ambiente' \| 'sped'` — as 4 abas · gate `fiscal.config.ambiente` (`UC-FCFG-06`, `GatesPermissaoFiscalTest`) · **cerimônia**: destino digitado à mão + motivo ≥15 chars + evento (`UC-FCFG-07`, `TrocaAmbienteCerimoniaTest`, `Config.tsx:187-210`) |
| **9** SPED: competência + prévia | **FEITA** | `Sped.tsx:318-335` — régua da competência com `motivoBloqueio(selecionada)`, `data-ok`, `ItensDaRegua`, `aria-label="Régua de geração da competência {mês}"`; seleção de período em `:396-411` com `aria-pressed` |

**Bônus não pedido:** o cockpit também tem barra de seleção em lote (`Cockpit.tsx:554-557`, `fx-bulk-bar` com `role="region" aria-label="Ações em lote"`), com select-all em `:579` e por linha em `:623`.

## 4 · As duas decisões [W] que sobravam também caíram
- **D-DFE** — as duas metades estão respondidas: lote **existe** (`Dfe.tsx`, uma requisição **por nota**, não bulk agregado) e o CSV é **servidor** (`streamDownload`, headers `attachment` + `X-Robots-Tag: noindex`, espelhando o `SpedController::gerar`). O `Dfe.casos.md:189` explica por que não é bulk agregado: *"um relatório 3 de 10 falharam não diz quais 3 refazer"* — o vício do `ManifestacaoController::bulkConfirmar` do NfeBrasil, evitado de propósito.
- **D-CONFIG** — a prop **`envioDocumentos` já chega no componente** (`Config.tsx:140`), e a aba `sped` existe no `ConfigTab`. Os "2 pontos já decididos" (tela editável · séries reais) foram **reconciliados no charter em 2026-09-02**, com o motivo escrito. Resta a [W]: **a ADR desses 2 pontos**, que é papel, não tela.

## 5 · Descobertas que viram RESÍDUO (não são trabalho meu)
1. **`Dfe.charter.md:39` ainda diz `❌ Bulk manifestar — backlog`** — stale, refutado pelos UC 07..10 verdes. Pela precedência *teste verde > casos > charter > SPEC*, o charter é que se reconcilia. **1 linha**, no PR de quem tocar a tela.
2. **Defeito de produção registrado e ainda aberto** (`Dfe.casos.md:216`, BACKLOG 🧪): `ManifestacaoService::buildConfig()` e `DistribuicaoDfeService::buildConfig()` fazem `select(['name','tax_number_1','state'])` em `business`, e **a coluna `state` não existe** — 133 colunas no schema canônico, no staging CT 100 e na Hostinger, e nenhuma das 43 migrations a cria. Se isso ainda vale, **nenhuma manifestação chega à SEFAZ por caminho nenhum**. É 🔴, é fiscal, e não é onda de UI — é a coisa mais grave que apareceu nesta aferição.
3. **`Cockpit.casos.md:258`** — filtros/visões/densidade/seleção em lote seguem `[BACKLOG · ⬜ sem teste]`: a capacidade existe, o teste não. É exatamente o que a **thread 01** (rede E2E) cobre.

## 6 · Prefixo tocado
Nenhum. Só este arquivo.

## 7 · O que isto significa para o playbook
Das 6 threads emitidas hoje de manhã, **4 morreram na primeira medição** (03 se cumpriu; 04, 05 e 06 perderam a razão de existir). Sobram **01 (rede E2E)** e **02 (paginação)** — as duas que já eram as únicas com trabalho de código real. O pacote de 03/09 tinha **10 ondas: 9 estão feitas**; a única de pé é a paginação.
