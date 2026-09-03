---
date: "2026-09-02"
hour: "21:40 BRT"
topic: "Bundle v2 do DS bloqueado com número (get_file trunca em 256 KiB); inconsistências SINTAXE/IMPORTANT/FONTRAMP da Forja consertadas na fonte do Cowork"
authors: [C]
related_adrs: ["0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0315-design-sync-claude-design-vs-cowork-charter"]
---

# 2026-09-02 (tarde/noite) — bundle v2 do DS: bloqueado com número; inconsistências da Forja: consertadas na fonte

> Sessão `ds-bundle-v2-font-sync-ab5c59` · [C] sob autorização [W] 2026-09-02, textual **"apenas faça"**
> (opt-in de escrita do design-sync, ADR 0315). Continuação da decisão pendente nº 3 do
> [handoff 13:50](../handoffs/2026-09-02-1350-forja-onda2-replica-primeiro-no-ar.md).

## Em uma frase

O bundle v2 **não desceu** e o bloqueio agora tem número (`get_file` corta em 262.144 B com
`truncated:true`, e o `sync/` que existe é o lote de 24/08); as inconsistências cuja receita era
"na fonte" foram **aplicadas na fonte** e a lista perdeu as linhas `SINTAXE` e `IMPORTANT` e o `FONTRAMP` caiu **291 → 192**.

## Base

O worktree estava **−145 commits** de `origin/main`, com 1 commit local não-pushado de 28/08
(recibos de design-sync). Não destruí: a branch antiga segue apontando pra ele; criei
`claude/ds-bundle-v2-fonte-sync` a partir de `origin/main` fresco (0/0). Painel
(`protocolo.config.mjs`) + `--selftest` rodados antes de qualquer download, como manda a fase −1.

## (A) O bundle v2 — as duas rotas do painel, testadas

**Rota principal (pacote em partes): existe, mas é velha.** O projeto ERP tem
`sync/bundle.manifest.json` + `payload.part01..43.json`. Medido no manifesto:
`generatedAt 2026-08-24T22:49:15.818Z` · `mode snapshot` · `bundleId 5023b274…` · 255 arquivos.
O `_ds_bundle.js` que ele declara tem `sha256 9d2f6ce4…` — **o mesmo do
`mirror-snapshot/_ds_bundle.js` local**. Aplicar o pacote de hoje não traria nada.

**Rota pontual (`get_file` avulso): bloqueada, com número.** Puxei o `_ds_bundle.js` vivo do projeto
DS: `content` de **262.144 bytes = 256 KiB cravados**, envelope com **`truncated: true`**, cortado no
meio de uma string, sem o `})();` final. **Não existe `offset`/`range`** — li o schema da tool, o
`get_file` só aceita `projectId` e `path`. E a rota do pacote também não resolve *este* arquivo: o
gerador exclui `_ds/**` por desenho, e `_ds_bundle.js` em JSON dá 303.415 B, acima do teto de parte.

**O que veio de graça:** o header `@ds-bundle` está nos primeiros bytes, então sobreviveu ao corte.
Contei os dois lados: espelho **44**, vivo **57** — não 55; o número que circulava estava baixo.
Faltam 13, superset limpo: `ColumnManager · ColumnPrefs · DataGrid · Kebab · PresenterMode ·
Segmented · Timeline · Toolbar · ToolbarButton · ToolbarDivider · ToolbarSearch · ToolbarSpacer ·
Widget`. O `Segmented` da Onda 4 está entre eles.

**A rota com precedente (mais barata que regerar o pacote):** em 2026-08-18 este mesmo teto foi
vencido — [W] baixou o arquivo por fora e o repo provou ser *"continuação exata dos 259.769
caracteres do `get_file` truncado"*. Tenho a metade que falta pra repetir a prova: **os 262.144 bytes
de prefixo do vivo**. Reconstruir dos `components/*.jsx` continua fora (registrado em 2026-08-17
como *build FABRICADO — possível, não feito*).

Nada foi escrito no espelho por causa disso — e a guarda funcionou sozinha:
`cowork-mirror-freshness.mjs:399` recusa `truncated:true` antes de escrever (fruto do #5910).

## (B) Consertos na fonte — o que entrou e o que voltou

Escrevi no projeto Cowork ERP e desci por `get_file` → `--export-from`. **Nada transcrito**: o
conteúdo saiu do dado, por script, e o vivo pós-push bateu byte a byte com o que gerei nas duas
rodadas. Lista regenerada pela máquina: **−2 linhas** (`SINTAXE`, `IMPORTANT`) e `FONTRAMP 291 → 192`.

> ⚠️ **Não cito o total absoluto de propósito.** Ele é o denominador da Forja inteira e se moveu
> quatro vezes em um dia — 101 (cabeçalho do pedido) → 107 (com o `.css` no comando) → 123 (o #6569
> passou a cobrir ESLint `ds/*`) → 126 (quatro PRs da Forja entraram na madrugada). O que a minha
> mudança causa é o delta acima, e **esse** não se mexe. Pra o número do dia, rode o comando.

| regra | antes | depois | quem |
|---|---:|---:|---|
| `SINTAXE` | 1 | **0** | Code |
| `IMPORTANT` | 2 | **0** | Code |
| `FONTRAMP` | 291 | **192** | Code (os 99 em degrau) |
| `HEX-CSS` | 6 | 6 | devolvido, medido |
| `R1` css | 326 | 326 | devolvido, medido |
| `PALETA` | 1 | 1 | token novo = [W] |

### O `)` sobrando não era cosmético

O CSSOM mostra `[data-theme="dark"] .fj-ho-flow` com `background: "(vazio)"` — a declaração era
**descartada** por sintaxe inválida. É a única mudança nossa que move pixel: no dark o chip herdava
`--accent-soft` (`0.32/0.06`) e passa a valer o que o autor escreveu (`0.275/0.050`). Claro intacto.

### Os dois `!important` saíram com render idêntico — provado com controle negativo

A/B no render (shell real, folha real, markup copiado verbatim de `forja-integra.jsx:69` e
`forja-page.jsx:344`, regra trocada **no lugar** via `deleteRule`/`insertRule` pra preservar ordem
de origem). `.fj-int-tab` e `.fj-anexo-hint`: idênticos nos dois temas. **Controle negativo:** tirar
o `!important` do `.fj-int-tab` *sem* subir especificidade leva a cor de `--text-dim` (0.72) pra
`--text-mute` (0.58), porque `.fj-int-rota small` (0‑1‑1) vence `.fj-int-tab` (0‑1‑0). Com o bump
(0‑2‑1) fica. A sonda discrimina — não é carimbo.

### FONTRAMP: 99 mecânicos, 192 devolvidos

Varri o CSSOM: `--fs-1..9` têm 18 definições, **todas** em `:root`, valores idênticos, **zero**
override por media-query ou tema. Logo a troca dos que caem em degrau é neutra por construção, e
confirmei no render (9/9 iguais, com controle negativo `--fs-1` ≠ `--fs-2`). Os 192 restantes caem
**fora** de degrau (0,5 a 2,0px) — escolher o degrau é decisão de design, que o próprio pedido
atribui ao Cowork/[W]. Histograma devolvido no CODE_NOTES: **os 67 de `11px` + os 38 de `10px` são
105 dos 192**, ambos a 0,5px do `--fs-1`.

### HEX-CSS: o "não" é medido — e o prompt de [W] estava certo contra a minha leitura

Li a cascata do `colors_and_type.css` e **concluí errado** que `--accent-fg` era `#ffffff` nos dois
temas (o `.cockpit` define assim e não há override dark lá). O render desmentiu: `--accent-fg` vem
do **`styles.css` do shell** (`:root` = `oklch(1 0 0)` · `[data-theme="dark"]` =
`oklch(0.14 0.02 295)`), que é a última definição e vence. Trocar `#fff` por `var(--accent-fg)` dá a
mesma cor no claro e **inverte** no escuro. LC-08 evitado por medir: ler cascata não é medir cascata.

Dos 6: **2 sobre `var(--accent)`** são defeito de contraste real no dark (branco sobre roxo `0.70`)
— o token certo É o `--accent-fg`, mas aplicá-lo muda render, então é decisão [W]. **4 sobre
`--ink`/`--neg`** estão certos como estão e faltam tokens de foreground (`--ink-fg`, `--neg-fg`):
pedido registrado, não inventado.

### R1: medi e concluí que NÃO é trabalho mecânico

| | |
|---|---:|
| total `oklch()` | 326 |
| dinâmicos (`var(--ph)`) ou com alpha | 88 |
| simples | 238 (74 em regra dark · 164 neutras) |
| **com token render-neutro** | **5** |
| sem token render-neutro | 233 |

E os 5 batem por **valor**, não por **sentido** (`--kind-employee-soft` num hover de alavanca,
`--vip-soft` num banner de proposta). Trocar por coincidência numérica acopla a Forja a um token de
outro domínio — derivar do lugar errado. **Troquei zero, deliberadamente.**

A causa é estrutural: **164 literais estão em regras neutras de tema**, e dos 258 tokens de cor
medidos quase nenhum é invariante (só `--av-c*`, `--sb-*`, `--vd-ai*`, `--bubble-me*`,
`--kpi-feature-fg`). Tokenizar um literal neutro **sempre** muda um dos temas. Não falta token —
falta a regra ganhar par claro/escuro. Isso é desenho.

## Recibos operacionais

- Opt-in: o hook `block-design-sync-without-optin` barrou a 1ª tentativa por **expiração do TTL de
  15min**, não por falta de autorização. Usei `.design-sync-allow` (canal que o próprio hook
  documenta), com a autorização escrita dentro, e **removi ao fim** — nunca foi commitado.
- `--unverified --check` acusou `mexido-depois: 1` depois do 1º commit (o commit ficou mais novo que
  a verificação de 11:17). Correto. Rodei `--snapshot-from --emit-snapshot` + `--compare --check
  --ledger` (forja-page.css = SYNC) e voltou a 0. Os 257 `unchecked` são dívida pré-existente que o
  `--sla` já reporta, não desta rodada.
- `ds-guard` segue vermelho no `PALETA --dev-*(4)`. Provei ser **herdado** rodando-o no arquivo de
  `HEAD` — mensagem idêntica, delta zero.
- Transformações com **teste de identidade** (desfazer reproduz o original byte a byte) — LC-16.
- A falha do build do Vite pelo `)` sobrando é recibo da sessão de 2026-09-02 manhã, **não medição
  minha**: este worktree está sem `node_modules`, logo sem stylelint/lightningcss. O que eu medi foi
  o veredito do browser (declaração descartada).

## O que fica aberto

1. **Bundle v2** — pedido de 2026-09-01 intacto. Caminho barato com precedente: [W] baixa o
   `_ds_bundle.js` vivo e eu provo a continuidade contra os 262.144 B de prefixo que tenho.
2. **FONTRAMP 192** e **HEX-CSS 6** — decisão de design; histograma e triagem devolvidos.
3. **R1 326** — precisa de par claro/escuro por regra antes de tokenizar; não é fila de trabalho
   mecânico.
4. **PALETA `--dev-*`** — promoção a `--origin-DEV*` segue decisão [W].
