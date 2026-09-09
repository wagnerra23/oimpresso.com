---
sessao: "13"
titulo: Paridade de FORMA — Painel (Dashboard/Index.tsx)
dono: "[CL]"
base: 2b4a3ec3b48a
prefixo: resources/js/Pages/Ponto/Dashboard/Index.tsx
nao_toca: ${PAGES}/_components/** · Modules/Ponto/** · qualquer CSS · as seções que só existem na produção
depende: ds-atomos 01 (Card badge/note/flush) · ds-atomos 02 (KpiCard variant=filter)
---
# 13 · Painel — a forma do protótipo na Page real

## A · IDENTIDADE (ancoragem dupla)
- **alvo (layout, read-only):** `prototipo-ui/cowork/ponto-page.jsx` — aba **Painel**, T1 estável **959 nós** (dark, duas leituras iguais), **largura de referência 1280px**.
- **âncora (código):** `resources/js/Pages/Ponto/Dashboard/Index.tsx` — **20.313 B**, sha `19c5ad41dc7c`. Símbolos: `DashboardIndex` (:183) · `NotaFechamento` (:131) · `ApprovalRow` (:487).
- **oráculo (NÃO é leitura de abertura):** `Index.charter.md` (3.097 B) · `Index.casos.md` · `PontoDashboardContratoTest.php` (28 KB) · `prototipo-ui/contrato/ponto-painel.contract.json`.
- **NÃO ler:** `_components/{PresenceStrip,ActivityFeed,AlertInbox}.tsx` — **ficam como estão** nesta thread.
- **contrato vigente:** `ponto-painel` — trava seções e copy no CI. **Se o alvo abaixo contradiz o contrato, o contrato manda e você para.**
- **persona:** Wagner (escritório, 1440) e Larissa (balcão, 1280).

## B · NÃO INVENTAR
- **Zero CSS novo, zero utilitária de cor/espaço** onde os átomos já cobrem. Zero hex, zero `oklch()` literal.
- **Reusar os átomos que a tela já importa** — eles já têm `aria`/`data-slot`; não reimplemente nenhum.
- **Copy:** literal do protótipo, PT-BR, sentence case. Enum legal (`REP-P`, `NF-e`, `NSR`) mantém a caixa canônica.
- **Dado:** Model/Service/coluna real. Sem fonte ⇒ `—` + linha no PR. Nenhum número inventado.
- **O alvo dos átomos NÃO se repete aqui** — está em `cowork-inbox/ds-atomos/playbook/00-INDICE.md` (§Alvo). Cite por nome, não copie: regra copiada envelhece em paralelo.

## ⛔ O QUE ESTA THREAD NÃO É
Não é "deixar a tela igual ao protótipo **em inventário de seção**". A produção tem seções que o meu protótipo **não** tem, e elas **ficam** — remover é regressão, não paridade. O que se aplica é **forma**: anatomia do átomo, densidade, tipografia, tom e ordem **dentro** das seções que existem nos dois lados.

## C · ALVO MEDIDO (dark, T1 959 nós, 1280px)
Quatro seções no protótipo, nesta ordem, com `data-contract` **já casando** com o que a Page emite (4/4):

| # | `data-contract` | nós | forma |
|---:|---|---:|---|
| 1 | `painel-nota-fechamento` | 8 | `Alert` tintado 6% + borda 22% no tom (**nunca** pastel sólido) · 1 filho |
| 2 | `painel-kpis` | 57 | **6 KPI-filtros** em grid `gap:10px`, trilha ~194px · cada tile = `variant="filter"` da ds-atomos 02 · ícone por KPI |
| 3 | `painel-fila-aprovacoes` + `painel-atividade` | 91 | 2 colunas (`pt-cols-2`) · cada uma um `Card` com **badge** de contagem e `note` (ds-atomos 01) |
| 4 | `pt-legal` | 3 | linha legal com artigo **literal** (Portaria MTP 671/2021) |

**Tom dos 6 tiles** (é onde a produção mais difere): colaboradores `primary` · presentes `emerald` · atrasos `amber` · faltas `rose` · HE do mês `violet` · aprovações pendentes `primary`. Sem isso os 6 saem do mesmo tom — foi o defeito que eu mesmo tive no build.
**Cabeçalho de card:** contagem vai pro **badge** (mono, `--text-dim`), frase de apoio vai pro **`note`**. Nada disso entra no `CardTitle` — ele trunca.

## D · COMO VALIDAR
1. Contagem **e ordem** das 4 seções = alvo; os 4 `data-contract` seguem presentes.
2. `getComputedStyle` em dark bate com o alvo dos átomos (ds-atomos §Alvo) nos tiles e nos 2 cards.
3. **Guarda:** `PresenceStrip`, `ActivityFeed` e `AlertInbox` continuam montados e sem diff — a thread não os remove nem reestiliza.
4. `PontoDashboardContratoTest` verde · `contrato-de-tela` do `ponto-painel` verde.
5. KPI-filtro: `aria-pressed` reflete, `focus-visible` com anel de accent, clicar no ativo **desliga** (invariante 2).
6. Screenshot prod autenticado · dark · **1280px** · PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `${PAGES}/Dashboard/Index.tsx` — **só este**.
- **REUSAR:** `KpiGrid` (o grid é dele) · `KpiCard` com `variant="filter"` · `Card` com `badge`/`note` · `StatusBadge` · `Icon`.
- **CRIAR:** nada.
- **NÃO TOCAR:** `_components/**`, `Modules/Ponto/**`, contrato, charter, casos.
- **PASSO A PASSO:** 1) medir a Page **antes** (contagem por `data-contract`) e registrar no `_saida` · 2) tiles → `variant="filter"` + tom + ícone · 3) contagem/apoio dos 2 cards → `badge`/`note` · 4) conferir que nenhuma seção sumiu · 5) rodar contrato + Pest.
- **PARAR SE:** ds-atomos 01/02 ainda **não** estiverem no `main` (sem elas, chegar ao alvo exige CSS novo — e aí é dívida, não paridade) · o contrato `ponto-painel` contradizer o alvo · faltar fonte para algum KPI.

## PRÉ / PÓS
- **antes:** `Dashboard/Index.tsx` sem `variant="filter"` nos tiles · `_components/{PresenceStrip,ActivityFeed,AlertInbox}` **presentes** (guarda) · `ponto-painel.contract.json` **presente**.
- **depois:** 6 tiles com tom distinto e `aria-pressed` · 2 cards com badge/note · as 3 peças da guarda intactas · contrato verde.
- **quebra:** se o "antes" já não vale (alguém migrou os tiles), **não execute** — reporte e pare.

## PROVA
`${PAGES}/Dashboard/Index.tsx` contém `variant="filter"` · `_components/PresenceStrip.tsx` presente (guarda) · `PontoDashboardContratoTest` verde · `_saida-13.md` com contagem antes/depois por `data-contract`.
