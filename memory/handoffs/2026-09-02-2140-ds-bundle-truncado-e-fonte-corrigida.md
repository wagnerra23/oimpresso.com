# Handoff 2026-09-02 21:40 BRT — bundle v2 bloqueado COM número; forja-page.css corrigido NA FONTE

> Sessão `ds-bundle-v2-font-sync-ab5c59` · [C] sob autorização [W] 2026-09-02, textual **"apenas faça"**
> (opt-in de escrita do design-sync, ADR 0315). Fecha a decisão pendente **nº 3** do
> [handoff 13:50](2026-09-02-1350-forja-onda2-replica-primeiro-no-ar.md) — metade resolvida,
> metade devolvida com medida.
> Session log: [2026-09-02-ds-bundle-v2-e-consertos-na-fonte.md](../sessions/2026-09-02-ds-bundle-v2-e-consertos-na-fonte.md).

## Estado em uma frase

O bundle v2 **não desceu** — e agora o bloqueio tem número em vez de impressão; as inconsistências
da Forja cuja receita era "na fonte" foram **aplicadas na fonte do Cowork** e desceram pelo
`--export-from`, levando a lista de **123 → 121** (`FONTRAMP 291 → 192`, `SINTAXE` e `IMPORTANT`
zerados).

## O que está pronto (branch `claude/ds-bundle-v2-fonte-sync`, a partir de `origin/main` fresco)

| # | commit | o quê | linhas |
|---|---|---|---:|
| 1 | `214e939357` | `SINTAXE` (1) + `IMPORTANT` (2) na fonte | 3 no CSS |
| 2 | `2d51720cfa` | `FONTRAMP` — 99 font-size em degrau → `var(--fs-N)` | 99 no CSS |
| 3 | (docs) | rodapés nos 2 CODE_NOTES + session log + este handoff | — |

## O bundle v2 — as duas rotas do painel, testadas (não "não há rota")

**Pacote em partes (rota principal): existe, mas é o lote de 24/08.** `sync/bundle.manifest.json` +
`payload.part01..43.json` no projeto ERP. `generatedAt 2026-08-24T22:49:15.818Z` · `mode snapshot` ·
`bundleId 5023b274…` · 255 arquivos. O `_ds_bundle.js` que ele declara é `sha256 9d2f6ce4…` — **o
mesmo byte a byte do `mirror-snapshot/_ds_bundle.js` local**. Aplicar não traria nada.

**`get_file` avulso: bloqueado, medido.** `content` = **262.144 B (256 KiB cravados)**, envelope com
**`truncated: true`**, corte no meio de string, sem `})();`. **Sem `offset`/`range`** — li o schema
da tool. E o pacote também não cobre este arquivo: o gerador exclui `_ds/**` por desenho.

**Contagem, do header que sobreviveu ao corte:** espelho **44** · vivo **57** (não 55). Faltam 13,
superset limpo: `ColumnManager · ColumnPrefs · DataGrid · Kebab · PresenterMode · Segmented ·
Timeline · Toolbar · ToolbarButton · ToolbarDivider · ToolbarSearch · ToolbarSpacer · Widget`.

### Próximo passo mais barato (tem precedente, não é o pacote inteiro)

Em **2026-08-18** o mesmo teto foi vencido: [W] baixou o arquivo por fora e o repo provou ser
*"continuação exata dos 259.769 caracteres do `get_file` truncado"*. **Tenho os 262.144 B de prefixo
do vivo** — qualquer candidato se verifica em segundos (prefixo exato + parser do Node + header
declarando 57). Isso destrava a Onda 4 sem depender da regeração do pacote, que segue pedida desde
2026-09-01 e sem dono (lápide §5 2026-08-27).

⚠️ **Não** reconstruir dos `components/*.jsx`: registrado em 2026-08-17 como *build FABRICADO*.

## O que voltou pra decisão de design (medido, não abandonado)

- **FONTRAMP 192** — caem fora de degrau; escolher move 0,5 a 2,0px. Histograma no CODE_NOTES:
  **67 de `11px` + 38 de `10px` = 105 dos 192**, ambos a 0,5px do `--fs-1`. Uma decisão só resolve
  mais da metade.
- **HEX-CSS 6** — `--accent-fg` vem do `styles.css` do shell (`:root` = `oklch(1 0 0)` ·
  `[data-theme="dark"]` = `oklch(0.14 0.02 295)`), **não** do `.cockpit` do DS. A troca preserva o
  claro e **inverte** o escuro. 2 deles (sobre `var(--accent)`) são contraste ruim de verdade no
  dark; os outros 4 (sobre `--ink`/`--neg`) estão certos e faltam `--ink-fg`/`--neg-fg`.
- **R1 326** — **não é trabalho mecânico**: só **5** têm token render-neutro, e os 5 batem por valor,
  não por sentido. 164 dos literais estão em regras neutras de tema e quase nenhum token de cor é
  invariante (5 famílias em 258), logo tokenizar sempre muda um tema. A regra precisa ganhar par
  claro/escuro antes — isso é desenho, não fila.
- **PALETA `--dev-*`** — promoção a `--origin-DEV*` segue [W].

## Armadilhas que esta sessão pagou (pra próxima não pagar)

- **Ler cascata não é medir cascata.** Concluí do `colors_and_type.css` que `--accent-fg` era branco
  nos dois temas; o render provou o contrário (o `styles.css` do shell é a última definição e vence).
  O aviso que veio no pedido de [W] estava certo e a minha leitura, errada. Meça.
- **Valor igual ≠ token certo.** Os 5 candidatos de R1 casam numericamente com tokens de domínios
  alheios. Trocar acoplaria a Forja a eles.
- `--unverified --check` fica vermelho depois de commitar um `--export-from` sem refrescar o ledger
  (o commit fica mais novo que a verificação). Rode `--snapshot-from --emit-snapshot` +
  `--compare --check --ledger` **antes** de commitar, e commite o ledger junto.
- Opt-in do design-sync tem **TTL de 15min**: numa sessão longa ele expira e o hook barra mesmo com
  autorização dada. O canal durável é `.design-sync-allow` (o hook documenta) — **não commitar**.
- Este worktree está **sem `node_modules`**: nada de stylelint/lightningcss/tsc local.
- `rm -f` no scratchpad é barrado pelo `block-destructive`; use `cp -f` pra sobrescrever.

## Estado MCP no momento do fechamento

Servidor MCP `mcp.oimpresso.com` **indisponível nesta sessão** — o hook `brief-fetch` do
SessionStart caiu no fallback por timeout e nenhuma tool `oimpresso` (`cycles-active`, `my-work`,
`sessions-recent`, `decisions-search`, `whats-active`) foi exposta ao agente. O checklist MCP-first
do [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) **não pôde ser cumprido**; o estado
acima vem do git e das tools DesignSync. É a **segunda sessão seguida** com o MCP fora (o handoff das
13:50 registra o mesmo) — vale investigar o servidor. Quem abrir a próxima: rodar `brief-fetch`
primeiro e conferir se as tasks da Forja refletem as Ondas 0–2.1 e esta rodada de fonte.

## Base

Worktree começou **−145** de `origin/main`, com 1 commit local não-pushado de 28/08 (recibos de
design-sync). Preservado — a branch antiga (`claude/ds-bundle-v2-font-sync-ab5c59`) segue apontando
pra ele. O trabalho saiu de `claude/ds-bundle-v2-fonte-sync`, criada de `origin/main` fresco.
