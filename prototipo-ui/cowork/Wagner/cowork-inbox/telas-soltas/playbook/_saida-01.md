---
sessao: "01"
titulo: "Contratos das 7 telas soltas — NÃO entregue: a thread não fecha dentro do prefixo dela"
autor: "[CL]"
data: 2026-10-06
base: origin/main 1ab4ab51b1
thread: 01-contratos.md
veredito: "bloqueada — 0 de 7 contratos escritos. Home/Index já tem contrato; as outras 6 reprovariam um gate required porque a thread não pode pôr as âncoras no .tsx."
---

# _saida-01 · Contratos das telas soltas

## 1 · Feito

- Nenhum arquivo em `governance/design/contracts/`. O prefixo da thread ficou intocado.
- Este `_saida-01.md`.

## 2 · Não feito, e por quê (medido em `1ab4ab51b1`)

**B1 — `Home/Index` já tem contrato.** `governance/design/contracts/dashboard-visao-geral.contract.json` declara `tela: Home/Index`, `fonte: dash-legacy-page.jsx`, `alvo: resources/js/Pages/Home`. Ele passa no gate (`node scripts/contrato-de-tela.mjs --contract governance/design/contracts/dashboard-visao-geral.contract.json` → `✅ limpo`, rc=0). A tabela §1 do `00-INDICE.md` marca "contrato —" para a Visão geral; essa marcação está errada. Um `home.contract.json` seria um segundo dono do mesmo alvo.

**B2 — as outras 6 telas não têm âncora `data-contract`, e a thread não pode pô-las.** O job `Contratos de tela (fidelidade + intenção)` é required (lista `classic_protection`/`rulesets` do `required-checks-baseline.json`) e roda `--contract` em **todo** `*.contract.json` versionado. O gate exige âncora `data-contract="<id>"` no `alvo` para cada seção. `git grep 'data-contract='` nas Pages:

| tela | âncoras no alvo |
|---|---|
| `kb/Index` | 0 |
| `Documentacao/Index` | 0 (as 2 que existem são de `Documentacao/Programa.tsx`, outra tela, no mesmo diretório) |
| `User/Perfil` | 0 |
| `Suporte/Empresas` · `Suporte/Visao` | 0 |
| `Financeiro/Cobranca/Index` | 0 |

O índice põe `resources/js/Pages/` em `nao_toca` desta thread. Prova de que o contrato reprova sem a âncora: um contrato do `kb` montado com as 4 seções do alvo A1 (`header · stats · abas-mobile · tri`), rodado pelo gate a partir do scratchpad → `X seção "header" sem âncora …` × 4, `❌ 4 falha(s)`, rc=1. Mergear isso deixaria o required vermelho para todo PR que tocar o diretório de contratos.

**B3 — o alvo não carrega copy, e não existe máquina alvo→contrato.** O `.alvo.json` mede estilo, contraste, retângulo, nós e ordem de classes por seletor. Não tem texto. O contrato do gate é `id + copy literal + ordem`. A única máquina que emite esqueleto de contrato é `scripts/design/gerar-contrato.mjs`, e ela deriva de `<tela>-gap.md`, não do alvo. Do alvo dá para derivar só os ids e a ordem das seções — e os ids do A1 têm `_` (`abas_mobile`, `bloco_2`), que o `contract.schema.json` recusa (`^[a-z0-9-]+$`). Escrever a copy seria o "à mão" que a thread proíbe.

## 3 · Pedido literal ao [CL]/Cowork (colável)

```
Telas soltas, thread 01: decidir antes de reabrir.
1. Tirar "home" da thread 01 e corrigir a §1 do 00-INDICE: Home/Index já tem
   contrato (dashboard-visao-geral.contract.json, passa no gate).
2. Para as 6 restantes, escolher um caminho:
   a) a thread 01 passa a poder escrever as âncoras data-contract nas 6 Pages
      (tirar resources/js/Pages/<tela> do nao_toca, 1 PR por tela ou por par),
      com ids derivados dos nomes das seções do alvo A1 trocando "_" por "-",
      e a copy tirada do protótipo da âncora (kb-page.jsx etc.), não do alvo; ou
   b) criar antes uma máquina alvo→esqueleto de contrato (ids + ordem do
      .secoes.json) em scripts/, numa thread própria, e deixar a copy para a
      thread que puser as âncoras.
3. Nomes posicionais do A1 (bloco_N na Visão geral, Suporte·visão e Cobrança)
   precisam de nome de papel antes de virar id de contrato.
```

## 4 · Descobertas que mudam outra sessão

- `Documentacao/Index` divide o diretório com `Documentacao/Programa.tsx`, que já tem âncoras `cabecalho` e `kpis`. Um contrato com `alvo: resources/js/Pages/Documentacao` veria essas âncoras e passaria por elas sem que `Index.tsx` tivesse nada. O alvo do contrato da Documentação tem de apontar para o arquivo `Index.tsx`, não para o diretório.
- A ressalva 1 do A1 (`perfil` × `prefs`) continua aberta e decide qual protótipo vira `fonte` do contrato de `User/Perfil`.

## 5 · Prefixo tocado

Só `prototipo-ui/cowork/Wagner/cowork-inbox/telas-soltas/playbook/_saida-01.md`. Nada em `governance/design/contracts/`, nada em `resources/js/Pages/`.
