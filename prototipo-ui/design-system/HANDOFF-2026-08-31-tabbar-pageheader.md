# HANDOFF 2026-08-31 · TabBar sem wrapper + PageHeader (contexto/frescor) · espelho DS v6

> **Para:** Claude Code em `wagnerra23/oimpresso.com@main`.
> **Ler antes:** `HANDOFF.md` §1 (regras duras), §3 (mapa DS→repo), §5 (DoD).
> **Um intent:** tirar o wrapper obrigatório da barra de sub-abas e dar ao header canon o contexto/frescor que hoje vive em arquivo de dono temporário. **Merge é ato do [W].**
> **Nada aqui é canon.** O espelho propõe; token novo, componente novo e produto são soberania [W] (§7).

---

## 1. Por que · o bloqueio

Três telas de Produto estão paradas. Motivo único: a barra de sub-abas não aceita o próprio contrato — para pendurar `data-contract` e um `aria-label` de tela, o consumidor precisa embrulhar a barra num `<div>`.

Isso é regressão, não estilo, por dois motivos que o repo já audita:

1. **O atributo deixa de descrever o elemento que nomeia.** `data-contract` num `<div>` anônimo não afirma que o *tablist* cumpre o contrato — afirma sobre um nó de layout. Auditoria por máquina lê o elemento errado.
2. **Nó extra na chain de overflow** — AP10 (`flex-1` em coluna precisa de `h-full` ou pai `flex flex-col min-h-0`). Cada wrapper decorativo é mais um lugar onde a chain quebra silenciosamente.

Regra generalizada nesta rodada em `HANDOFF.md` §1.13: **contrato mora no elemento, não num wrapper.**

Há um segundo efeito, visual: quando o recuo lateral está no wrapper, a `border-bottom` da barra para no padding — vira um risco flutuante em vez da régua que separa header de conteúdo. Com o recuo dentro do elemento, a borda sangra de ponta a ponta. Isso está quebrado hoje nas três telas que usam o padrão.

---

## 2. Auditoria do `main` (arquivo + linha, §1.2)

`main` @ `84b62eb785e8`, lido em 2026-08-31. **Linhas medidas por busca no ref, não estimadas** — a primeira versão deste arquivo trazia números de memória e errava por até 24 linhas; foram todos remedidos.

| Afirmação | Evidência (arquivo:linha) |
|---|---|
| A raiz de `PageHeaderTabs` aceita **só** `className`, sem `...rest` | `shared/PageHeaderTabs.tsx:163` |
| O `aria-label` do tablist é **cravado** em toda tela | `shared/PageHeaderTabs.tsx:202` — `aria-label="Visão da página"` |
| `icon` **já existe** por aba | `shared/PageHeaderTabs.tsx:69` — `icon?: string` |
| `count` **já existe** por aba, com outro nome | `shared/PageHeaderTabs.tsx:76` — `badge?: number \| string` |
| O padding por aba está cravado na className | `shared/PageHeaderTabs.tsx:237` — `px-3 py-1.5 …` |
| Badge inativo em `oklch` literal | `shared/PageHeaderTabs.tsx:286` |
| `shared/PageHeader.tsx` está **CONGELADO** | `shared/PageHeader.tsx:9` — `@deprecated CONGELADO em migração F4`; ratchet `pageheader-gate` falha o CI se tela nova importar |
| O header canon **já tem** o slot de marca | `Components/PageHeader/PageHeader.tsx:58` — `leading?: React.ReactNode`; render em `:123`, **dentro do `h1`** |
| O canon **já tem** onde a barra entra | `Components/PageHeader/PageHeader.tsx:66` — `subnav`; render em `:142` |
| O canon **não tem** linha de contexto acima do título | o que existe é `suffix?: string` (`:62`), sufixo cinza *inline* — não é a mesma coisa |

**Correção de rota do próprio espelho.** A versão anterior deste handoff mandava estender `shared/PageHeader.tsx` e criar um `glyph` em caixa 34×34. As duas coisas estavam erradas: aquele arquivo está congelado (`:9`), e a caixa 40×40 `bg-primary/10` que eu ia replicar (`shared/PageHeader.tsx:60-66`) é justamente o que o canon v3.8 aposentou ao virar flat. O espelho foi corrigido — a prop agora se chama `leading` e renderiza inline no `h1`, igual ao canon. **Não há nada a criar para a marca: é reuso.**

### Não verificado — confirmar antes de agir

**Que `leading` cubra 100% do que o `cli-pagehead` fazia.** Nunca vi esse arquivo: ele não está no espelho e não aparece no `main` por busca de código nem de path. A equivalência acima é inferida da descrição "glyph/contexto/frescor", não medida. Se o glyph do `cli-pagehead` for uma caixa com fundo (e não um dot/ícone na linha de base), então ou ele diverge do canon e deve ceder, ou há um requisito que o canon não cobre — e aí é decisão [W], não do Code.

---

## 3. O que fazer · A) `PageHeaderTabs.tsx` — estender

Extensão de API pura. Todo default é o comportamento atual: nenhuma tela existente muda de pixel.

```ts
interface Props extends React.HTMLAttributes<HTMLDivElement> {
  // … props atuais, inalteradas …
  /** Sobrepõe o "Visão da página" cravado no tablist. */
  ariaLabel?: string;
  /** Recuo lateral no próprio tablist (mantém a border-bottom sangrando). */
  inset?: number | string;
  /** Barra inerte: aria-disabled + sem clique + opacidade reduzida. */
  off?: boolean;
  /** Altura/tipografia: sm | md (default) | lg. */
  size?: 'sm' | 'md' | 'lg';
  /** Padding horizontal por aba (default 12 = px-3 atual). */
  pad?: number;
}
```

1. `...rest` espalhado na raiz (`:163`) — passa a aceitar `data-contract`, `data-*`, `aria-*`, `id`, `role`.
2. `className` continua **somado** via `cn()` (já está certo, não mexer).
3. `aria-label` do tablist (`:202`) → `ariaLabel ?? 'Visão da página'`.
4. `inset` → `paddingInline` no tablist; **não** criar wrapper.
5. `off` → `aria-disabled` na raiz + `tabIndex={-1}` e `pointer-events-none` nos ghosts.
6. `size`/`pad` → substituem o `px-3 py-1.5` cravado em `:237`, com `md` = valores atuais.

**Não criar** `icon`/`count`: `ghost.icon` (`:69`) e `ghost.badge` (`:76`) já cobrem.

**Observação, não pedido:** as cores do badge inativo estão literais em `:286` — `oklch(0.32 0.01 240)` / `oklch(0.70 0.01 240)`. Os do estado ativo já são token (`--accent`/`--accent-fg`). Se houver token de superfície equivalente, vale trocar no mesmo PR; se não houver, é decisão de token = [W], não do Code.

## 4. O que fazer · B) `Components/PageHeader/PageHeader.tsx` — 1 reuso + 2 slots

**`leading` — reusar, zero código.** Já existe (`:58`, render `:123`). Migrar os consumidores. Ver a ressalva "não verificado" no §2 antes de declarar paridade com o `cli-pagehead`.

**`context` — criar.** Linha de contexto acima do título (módulo/pai), mono, uppercase, `text-muted-foreground`, ~11px. Não confundir com `suffix` (`:62`), que é sufixo *inline* depois do título. É elemento visual novo no header canon → **exige ADR proposta antes do `.tsx`** (§7). Não numerar a ADR: propor.

**`freshness` — criar (barato).** Nó à direita do título, na mesma linha. Não precisa de componente: `StatusBadge kind="frescor"` já existe (`shared/StatusBadge.tsx`, valores `recente|fresc|frio|distante` + sufixo `rel`). O slot só posiciona.

Ambos opt-in: sem a prop, o header renderiza byte-idêntico ao de hoje.

## 5. O que fazer · C) consumidores

Remover o `<div>` de padding em volta da barra nas telas do padrão PT-01 e passar `inset`. No espelho isso já foi feito nos três templates — use-os como referência visual:

- `templates/pt-01-lista/Pt01Lista.dc.html` (`inset="var(--d-cpad-x,14px)"`)
- `templates/clientes-crm/ClientesCrm.dc.html` (`inset="26px"`)
- `templates/financeiro/Financeiro.dc.html` (`inset="26px"`)

---

## 6. Fora de escopo (não faça neste PR)

- Migrar telas do `shared/PageHeader.tsx` congelado — é PR por tela, com aprovação visual.
- Tocar `SubNav.tsx` / `PageHeaderModuleNav.tsx`.
- Unificar os cinco campos de busca (dívida conhecida, `NOTAS_INTERNAS.md` P3.1).
- Os seis mini-DS (`AcessosDS`/`PBUI`/`ModuloPadrao`/`HrmUI`/`CatchupUI`/`PontoUI`) — **não existem com esses nomes no `main`**; busca por código e por path deu zero. A triagem está bloqueada no mapeamento nome → pasta, que é do [W].

---

## 7. Gates (§5 — rodar, não confiar na memória)

```bash
node prototipo-ui/ds-guard.mjs resources/js/Components/shared/PageHeaderTabs.tsx resources/js/Components/PageHeader/PageHeader.tsx
npm run typecheck && npm run lint:baseline:check && npm run stylelint:baseline:check
npm run conformance:check && npm run pt:conformance:check
npm run a11y:check          # tablist: role, aria-selected, setas/Home/End, aria-disabled do `off`
npm run visreg:pixel        # CRÍTICO: prova que nenhuma tela existente mudou de pixel
npm run ds:canon:check      # roxo 295 não regrediu
```

`visreg:pixel` é o gate que importa aqui: a tese inteira é "extensão de API, defaults preservam o atual". Se ele acusar diferença fora das telas que perderam o wrapper, **pare e reporte** — não reescreva baseline (§1.14).

---

## 8. Bloco para `prototipo-ui/COWORK_NOTES.md`

```md
## HANDOFF 2026-08-31 · TabBar/PageHeader · espelho DS v6
- fonte visual: templates/{pt-01-lista,clientes-crm,financeiro}/*.dc.html + components/{TabBar,PageHeader}/*.html
- tokens: colors_and_type.css + cockpit_domains.css (sem token novo nesta rodada)
- alvo: Components/shared/PageHeaderTabs.tsx · Components/PageHeader/PageHeader.tsx
- reusar: leading (PageHeader canon), ghost.icon, ghost.badge, StatusBadge kind=frescor
- estender: PageHeaderTabs (...rest, ariaLabel, inset, off, size, pad)
- criar: PageHeader.context (ADR proposta), PageHeader.freshness
- auditoria do main (linhas medidas no ref 84b62eb785e8, não de memória):
  PageHeaderTabs.tsx:163 raiz sem ...rest · :202 aria-label cravado
  · :69 icon + :76 badge já existem · :237 padding cravado · :286 oklch literal
  · shared/PageHeader.tsx:9 @deprecated CONGELADO
  · Components/PageHeader/PageHeader.tsx:58 leading já existe (render :123)
- NÃO verificado: paridade leading × cli-pagehead (arquivo não acessível ao espelho)
- gates: ds-guard · typecheck · conformance · pt:conformance · a11y · visreg:pixel · ds:canon
- pendências pra [W]: (1) ADR proposta p/ `context` no header canon
  (2) mapeamento nome→pasta dos 6 mini-DS — bloqueia a triagem inteira
  (3) badge inativo do PageHeaderTabs:262 em oklch literal — existe token de superfície?
```

**Pronto quando:** §7 todo verde, bloco acima commitado, PR ≤300 linhas, 1 intent, conventional commit com `Refs:`.
