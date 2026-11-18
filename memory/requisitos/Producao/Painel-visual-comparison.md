---
id: requisitos-producao-painel-visual-comparison
title: "Comparação design × produção — Producao/Painel/Index"
module: Producao
tela: Producao/Painel/Index
owner: W
status: rascunho
last_validated: "2026-11-18"
---

# Comparação design × produção — `Producao/Painel/Index` (Painel)

> **Âncora computada** (`node scripts/design/ancora.mjs Producao/Painel/Index`):
> `prototipo-ui/cowork/Wagner/producao-cv-page.jsx`, declarada em `related_prototype` do charter.
> No shell do protótipo a vista é a rota `pcv-painel` (`app.jsx`, linha do `ProducaoCvPage`; override em
> `governance/design/targets/roles/Producao--Painel--Index.json`).
>
> Medido em **2026-11-18** pela sonda canônica (`design-diff.mjs --probe`), a mesma nos dois lados,
> e comparado por `node scripts/design/design-diff.mjs --compare prod.json design.json --check`
> (saída 1, 1 DIVERGE(bug)). Snapshots e veredito em
> [`governance/design/targets/medidas/Producao--Painel--Index/`](../../../governance/design/targets/medidas/Producao--Painel--Index/)
> (`prod.json` sha `2acad8404774`, `design.json` sha `d1dac4e2f2a3`).
> Antes da medida rodaram `node scripts/design/protocolo.config.mjs` e `node scripts/design/design-diff.mjs --selftest` (OK).

## Como foi medido

- **Produção:** `https://oimpresso.com/producao/painel`, sessão já aberta no Chrome do [W] (só leitura; nenhum cookie ou senha manuseado).
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html`, servido pelo `servirEspelho` do `design-diff-lote.mjs` (que também serve o Design System), no mesmo Chrome, com `localStorage["oimpresso.route"] = "pcv-painel"`.
- **Tema:** claro nos dois lados. O espelho abre escuro; o tema foi trocado para claro na página antes da sonda.
- **Transporte:** o CSP da produção (`upgrade-insecure-requests`) bloqueia fetch para localhost. A sonda entrou e o JSON saiu por `postMessage` com uma janela-ponte local, que gravou em disco. O sha256 calculado na página bateu com o do arquivo gravado. Nada foi transcrito à mão.
- **Dado pessoal:** a `assinatura` de produção traz o texto do menu lateral; o nome do usuário logado foi trocado por `[usuário]` antes do commit.

## Dimensões medidas (saída do comparador)

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D2 | layout | ok | ok | IGUAL |
| D4 | tipografia | ok | ok | IGUAL |
| D6 | cor | ok | ok | IGUAL |
| D8 | kpi.tag | A | BUTTON | DIVERGE (bug) |
| D9 | cabecalho | presente | ausente | SEM-DADO |
| D9 | kpis | presente | ausente | SEM-DADO |
| D9 | producao-painel-carga | presente | ausente | SEM-DADO |
| D9 | producao-painel-eventos | presente | ausente | SEM-DADO |
| D9 | producao-painel-fila-arte | presente | ausente | SEM-DADO |
| D4 | linha da tabela | não medido | não medido | SEM-DADO |
| SHELL | shell | não medido | não medido | SEM-DADO |
| SAÚDE | tokens.prod | --accent --pos --neg --warn --text-dim --sunken --border | — | NÃO MEDI |

## Divergências lidas no DOM (fora do que o comparador mede)

Medidas pelas mesmas duas abas, por consulta ao DOM, não por screenshot.

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D8 | tag do KPI | `A` nos 3 primeiros, `DIV` no 4º (Refugo) | `BUTTON` nos 4 | **DÍVIDA A FECHAR**: a produção usa link nos KPIs que filtram e um bloco sem ação no 4º; o protótipo usa botão nos 4 (o 4º leva ao Custo). |
| D2 | botão primário do cabeçalho | ausente | "Nova OS do orçamento" (`oklch(0.55 0.15 295)`) | **A CLASSIFICAR**: a produção não tem esse botão em nenhuma tela da Produção (`grep` em `Pages/Producao` e `memory/requisitos/Producao`: zero). Pode ser capacidade que falta, não só forma. |
| D2 | abas do cabeçalho | 5: Painel, Quadro, Linha do tempo, Roteiros, Postos e cadastros | 9: as 5 mais Custo real × orçado, Instalação, Apontamento, Terminal | **A CLASSIFICAR**: as 4 vistas a mais do protótipo não existem como tela na produção (Apontamento existe, mas fora das abas). |

Vereditos pela regra do [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
(eixo FORMA: o protótipo manda, UI-0029): **DÍVIDA A FECHAR** = a produção converge; **A CLASSIFICAR** =
pode ser capacidade, não forma, e quem decide é humano; **NÃO COMPARÁVEL** = os dois lados não mostram o mesmo estado.

## O que esta medida NÃO cobre (declarado, não suposto)

- **Dados:** a empresa logada não tem OS em produção. A produção mostra só o esqueleto (cabeçalho, KPIs com zero, filtros, estados vazios); o protótipo mostra os dados de exemplo. Cartão do quadro, bloco da linha, linha de tabela e lista de etapas **não foram medidos** e o comparador sai `SEM-DADO` neles. Medir isso exige uma empresa de teste com OS (nunca a empresa 4).
- **`IGUAL` vazio:** quando a produção não tem botão primário (`primary` nulo), a D6 do comparador não compara nada e imprime `IGUAL`. O mesmo vale para a D8 com zero KPI dos dois lados. Esses `IGUAL` não são prova de igualdade.
- **Regiões de contrato (D9):** a produção tem `data-contract`; o protótipo não, então a D9 sai SEM-DADO por construção.
- **Shell:** `__SB_ROLES` não foi declarado; o menu lateral não entrou na medida.
- **`tokens.prod NÃO MEDI`:** falso-positivo conhecido do instrumento. A sonda procura os tokens em `:root` e a produção os guarda em `.cockpit` (mesmo achado da medida do Superadmin, #11051).

## Defeito do instrumento (registrado, sem conserto aqui)

No Apontamento, o botão de ler QR mede fundo `oklch(0.55 0.15 295)` na produção e `rgb(255, 255, 255)` no protótipo, e a D6 sai `IGUAL`. Em `dimCor` a matiz e a luminância só são lidas de cor em `oklch()`/`oklab()`; o fundo em `rgb()` vira nulo, as duas comparações são puladas, e sem nenhuma linha o comparador imprime `IGUAL`. É o mesmo caminho que imprime `IGUAL` quando a produção não tem botão primário. Dono: `scripts/design/design-diff.mjs`.

## Rodapé de evolução

- 2026-11-18 · [W+C] Primeira medida (auditoria da Produção, lote 5.4).
