# Jana — plano de conformidade ao DS (medido por 3 agentes em 2026-08-26)

> Fonte: auditoria paralela das 4 telas do modulo, cada agente numa area isolada.
> Cada agente validou a sonda com CONTROLE POSITIVO antes de afirmar ausencia
> (regex de cor casou noutros arquivos do repo; o ESLint `ds/*` foi provado
> disparando em `Financeiro/ProvaViva.tsx`). Os zeros abaixo sao medidos, nao presumidos.

## Retrato

| tela | cor crua | style inline | componentes reinventados |
|---|---:|---:|---:|
| Pro | **7** | 13 | — |
| Chat | 0 | 3 | **16** |
| Index (+6 _components) | 0 | 5 (sem canon) | **11** |
| Memoria | 0 | **0** | 5 |

**Diagnostico:** a Jana esta certa em TOKEN e errada em COMPONENTE. As telas nao inventam
cor — reinventam widgets que ja existem, varias vezes no mesmo arquivo que os importa.
O `Pro` e a excecao invertida: unico com paleta crua propria, porque foi traduzido de um
HTML em vez de montado com o DS.

**Por que o CI nao pega:** o bloco `ds/*` do `eslint.config.js` cobre `no-native-radio`,
`no-native-checkbox`, `no-native-select` — nao ha regra para `<input>`/`<textarea>`/`<label>`
nativos, nem para markup que duplique `Badge`/`Button`/`EmptyState`. Os defeitos sao reais
e invisiveis ao gate.

---

## ONDA 1 — Pro: as 7 cores cruas (maior impacto visual)

Os tokens equivalentes JA EXISTEM em `resources/css/tokens/_generated-cockpit-dark.css`:

| constante em `Pro.tsx` | valor hardcoded | token canon | delta |
|---|---|---|---|
| `NUM_POS` L51 | `oklch(0.74 0.13 150)` | `--pos` | `0.74 0.14 150` — quase exato |
| `BUB_THEM` L49 | `oklch(0.30 0.012 285)` | `--bubble-them` | `0.32 0.008 282` |
| `PROOF_INK` L47 | `oklch(0.96 0.01 90)` | `--text` (dark) | `0.94 0.005 90` |
| `PROOF_BG` L45 | `oklch(0.22 0.01 285)` | `--bg-2` (dark) | `0.23 0.006 240` |
| `PROOF_MUTE` L48 | `oklch(0.68 0.01 285)` | `--text-mute` (dark) | `0.58 0.005 90` |
| `BUB_JANA` L50 | `oklch(0.31 0.02 295)` | `--accent-soft` (dark) | `0.32 0.06 295` |
| `PROOF_OVERLAY` L46 | gradiente com `oklch(0.4 0.12 295 / .35)` | `--color-primary` + `color-mix` | — |

**Mecanismo:** o card de prova e um bloco DARK dentro de tela que pode estar clara. Em vez de
escolher cor na mao, o wrapper declara `data-theme="dark"` (`Hooks/useTheme.ts:98` — o dark
ativa por `.dark` OU `[data-theme]`), e os tokens semanticos resolvem sozinhos para os valores
escuros. Precedente: `Produto/Unificado` changelog 2026-08-24 — *"os literais crus saem num
lugar so; todo token de origem ja tem par de tema"*.

Fecha tambem os 13 `style={{}}`, que viram classes `bg-[color:var(--token)]`.

## ONDA 2 — Chat: 16 componentes reinventados

| # | arquivo:linha | markup cru | canon |
|---|---|---|---|
| 1 | `Chat.tsx:197` | `<span>` pilula | `Badge` — **ja importado L31, usado 5 linhas abaixo** |
| 2 | `Chat.tsx:212, 584, 626, 694` | 4 pilulas | `Badge` |
| 3 | `Chat.tsx:595-601` | `<input type="search">` | `ui/input` + `ui/input-group` |
| 4 | `Chat.tsx:605-617` | `<div role="tablist">` + 2 tabs | `ui/segmented` (doc: "2-3 opcoes") |
| 5 | `Chat.tsx:553, 573, 585, 588` | 4 botoes de icone | `Button variant="ghost" size="icon"` |
| 6 | `Chat.tsx:635, 646` | 2 empty states | `shared/EmptyState` |
| 7 | `Chat.tsx:389` | `style={{color:'var(--text-mute)'}}` | `className="text-muted-foreground"` |

Classes cruas de palette a eliminar junto: `bg-emerald-100` / `bg-amber-100` / `bg-rose-100`
(L94-96) — cobertas por `success`/`warning`/`destructive` do `badge.tsx`.

## ONDA 3 — Index: 11 componentes reinventados

| # | arquivo:linha | markup cru | canon |
|---|---|---|---|
| 1 | `JanaCockpit.tsx:444, 537, 544, 550` | 4 `<button>` | `Button` — **ja importado L67** |
| 2 | `Index.tsx:399-417` | empty state 19 linhas | `shared/EmptyState` (canon faz em 6) |
| 3 | `JanaCockpit.tsx:858` | empty state | `shared/EmptyState` |
| 4 | `JanaCockpit.tsx:193, 441` | 2 pilulas | `Badge` — unico canon que o arquivo NAO importa |
| 5 | `JanaMetaDrawer.tsx:75` · `JanaCockpitSkeleton.tsx:37` | 2 cards a mao | `ui/card` |

**NAO mexer:** os 5 `style={{width:...%}}` sao geometria de barra proporcional e **nao existe
`Progress` no canon** (verificado, exit 1). E os `<button>` envolvendo `<Card>` tem justificativa
escrita no codigo: o `Card` do DS nao implementa `asChild`.

## ONDA 4 — Memoria: 5 componentes reinventados

| # | linha | markup cru | canon |
|---|---|---|---|
| 1 | 366-388 | 2 empty states (23 linhas) | `shared/EmptyState` (tem `variant="search"` pro caso filtrado) |
| 2 | 197-203 | `<textarea>` | `ui/textarea` |
| 3 | 208-215 · 329-335 | 2 `<input>` | `ui/input` (+ `input-group` pro de busca) |
| 4 | 206 | `<label>` | `ui/label` |

Arbitrarios fora de escala: `min-h-[80px]` L198, `min-w-[220px]` L327.

---

## DECISOES QUE SAO DE [W], NAO MEDICAO

1. **`Memoria.tsx:65`** usa `secondary` (fill solido, grupo de acao) para a categoria
   *preferencia*. O `badge.tsx` expoe exatamente 5 variantes soft e a tela ja usa 4 — a unica
   livre e `success`. Ou aceita tom positivo em "preferencia", ou junta com `neutral` e perde a
   distincao. Um segundo neutro soft nao existe no DS (criar token novo e soberania [W]).
2. **`governance/ds-ledger.json:92`** marca a Jana como `tokens: "no"`, contra **0 cor crua**
   medida em 3 das 4 telas. Ou o ledger usa outro criterio, ou esta errado — e ele alimenta
   decisao de governanca.
3. **`_components/AssistantUiChat.tsx`**: 460 LOC, **0 imports canon**, 1 `<table>` cru
   (`shared/DataTable` existe). Boa parte e thread/composer delegado a lib `assistant-ui`.
   Reescrever ou declarar excecao e decisao de escopo.
