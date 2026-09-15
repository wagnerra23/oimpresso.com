---
sessao: "_saida-04"
thread: "04 · Modo `hidden` + SidebarReopenHandle — promover do protótipo pro shell"
dono: "[CL]"
data: 2026-09-11
prefixo_tocado: resources/js/Components/cockpit/{Sidebar.tsx,shared.ts} · resources/js/Layouts/AppShellV2.tsx · resources/css/cockpit.css
base_lida: wagnerra23/oimpresso.com@main — 844ea9b838 (rebaseado durante a execução; ver §6)
---
# _saida-04

## 1 · Feito

O shell ganhou o terceiro modo. Quatro arquivos, todos dentro do prefixo:

| arquivo | o que mudou |
|---|---|
| `Components/cockpit/shared.ts` | `SidebarMode` passa a `'expanded' \| 'rail' \| 'hidden'` |
| `Layouts/AppShellV2.tsx` | parse do `LS.SB_MODE` aceita `hidden` **na lista** (parse intacto) · `toggleSidebarHidden` · ramo `e.shiftKey` no atalho + guarda de campo de texto · `<aside>` condicional · render da alça |
| `Components/cockpit/Sidebar.tsx` | `SidebarReopenHandle` exportado, portado do **protótipo**, não do bundle do Financeiro |
| `resources/css/cockpit.css` | `.sb-reopen-handle` no bloco `Sidebar — DARK FIXO` · grid do 3º modo · mobile neutralizado |

As 3 provas do §7 do índice conferem no diff: `shared.ts` contém `| 'hidden'` ·
`AppShellV2.tsx` contém `SidebarReopenHandle` · `cockpit.css` contém `.sb-reopen-handle`.

Invariantes: `hidden` **nunca** vem do auto-rail (só entra via `chooseSidebarMode`, e o handler do
`matchMedia` já retorna quando existe escolha persistida) · a `<aside>` monta sempre no mobile ·
`SidebarMenuItemContractTest` não precisou mudar — o shape do item não foi tocado.

## 2 · A descoberta que mudou o desenho: o modo `hidden` do protótipo está QUEBRADO

A thread mandava parar e reportar se remover a `<aside>` deixasse coluna fantasma. Parei — e o
achado é maior que a pergunta previa: **a coluna fantasma existe no próprio protótipo, hoje.**

Medido em `prototipo-ui/cowork/` servido local, 1440×900, dark, duas leituras estáveis com 1,5 s de
intervalo:

| | expandido | **hidden** |
|---|---|---|
| `getComputedStyle(.app).gridTemplateColumns` | `260px 1180px` | **`0px 1440px`** |
| `.main` largura × x | 1180 × 260 | **0 × 0** |
| conteúdo no DOM | — | **2636 caracteres, invisíveis** |

Screenshot do protótipo nesse estado: **tela em branco**, só o fundo e a alça de 18 px.

São dois defeitos que se mascaram, e isolei os dois:

1. `transition: grid-template-columns .18s` **prende o valor** quando a mudança vem de `var(--sb-w)`.
   Com `transition:none` o grid resolve; com ela, fica em `260px 1180px`.
2. Mesmo com o grid resolvido, `.main` fica com largura **0** — porque a alça é `position: fixed`
   (não é grid item), então o `.main` vira o único item em fluxo e o auto-placement o joga
   justamente na faixa que o `--sb-w: 0px` zerou. Forcei `0px 1fr` à mão: `mainW: 0`. Provado.

Com a transition ativa o main fica com 260 px ("meio quebrado"); sem ela, 0 px (branco). Nenhum dos
dois é a forma pretendida.

**Como resolvi no shell, sem copiar o defeito:** a UI-0029 dá soberania ao protótipo sobre a FORMA,
e a forma pretendida é inequívoca — a sidebar some e o conteúdo ocupa a largura. O que está errado
lá é a implementação. Então aqui a coluna **sai da lista** em vez de ser zerada:

```css
.cockpit[data-sidebar="hidden"]                    { grid-template-columns: 1fr 320px; }
.cockpit[data-sidebar="hidden"][data-linked="off"] { grid-template-columns: 1fr 0; }
```

Não é invenção minha: **é o idioma que o próprio protótipo já usa** no drawer mobile
(`.app--mobile{ grid-template-columns: 1fr; }`). O modo `hidden` é que ficou com a forma errada.

> **Para as threads 01/02 — não é meu prefixo, não toquei.** O conserto do protótipo é de um lado
> só: `styles.css`, onde `.app.app--sb-hidden` precisa **remover a faixa**, não zerá-la (thread 01).
> O `app.jsx` está certo (thread 02, nada a fazer). Enquanto não for feito, o `hidden` do protótipo
> segue servindo tela em branco a quem apertar ⌘⇧\ lá.

## 3 · Medições (runtime, não CI)

CI verde não prova runtime ([LC-30](../../../../memory/LICOES_CODE.md)). Montei um harness com o
`cockpit.css` **real** e a estrutura real do `.cockpit` (`aside.sb` · `div.main` · `aside.apps`),
espelhando a montagem condicional do `AppShellV2`, e medi por `getComputedStyle` /
`getBoundingClientRect` — nunca por screenshot. Controle positivo em toda bateria (`expanded` tem
de abrir com `260px`; se não abrir, a sonda está errada, não o código).

**1440×900 — o modo novo, e a ausência de regressão nos outros dois:**

| modo / linked | `grid-template-columns` | `.main` x/largura | `.apps` x/largura | `<aside>` no DOM | alça |
|---|---|---|---|---|---|
| expanded / on | `260px 860px 320px` | 260 / 860 | 1120 / 320 | sim | não |
| expanded / off | `260px 1180px 0px` | 260 / 1180 | — | sim | não |
| rail / on | `56px 1064px 320px` | 56 / 1064 | 1120 / 320 | sim | não |
| rail / off | `56px 1384px 0px` | 56 / 1384 | — | sim | não |
| **hidden / on** | **`1120px 320px`** | **0 / 1120** | 1120 / 320 | **não** | **sim** |
| **hidden / off** | **`1440px 0px`** | **0 / 1440** | — | **não** | **sim** |

`expanded` e `rail` saem com os números de sempre. **Sem coluna fantasma:** o `.main` começa em x=0
e ocupa a largura toda.

**1280 (o monitor do [W]):** `hidden` → `1280px 0px`, `.main` 0/1280. `rail` e `expanded` intactos.

**375 (mobile, `matchMedia('(max-width: 768px)')` confirmada ativa — medida num iframe de 375 px,
porque o viewport do pane não desce abaixo de ~980):** os **três** modos dão `375px`, `.main` 0/375,
`<aside>` montada (drawer `fixed`, 290 px) e a alça **não** montada. E, forçando a alça a aparecer,
o CSS a derruba: `display: none`. Mobile intocado.

**A alça, contra o protótipo (dark, 1440):**

| | protótipo | shell | |
|---|---|---|---|
| position · retângulo | `fixed` · x0 y418 18×64 | `fixed` · x0 y418 **18×64** | idêntico |
| `border-radius` · `border-left` | `0 6px 6px 0` · 0 | `0 6px 6px 0` · 0 | idêntico |
| `z-index` · `padding` · sombra | 40 · 0 · `2px 0 8px oklch(0 0 0 /.06)` | idem | idêntico |
| `color` | `oklch(0.58 0.005 90)` | `oklch(0.58 0.005 90)` (`--sb-text-dim`) | idêntico |
| `background` | `oklch(0.3 0.008 240)` (`--bg-elev`) | `oklch(0.18 0.006 240)` (`--sb-bg`) | **diverge — §4** |

Geometria 1:1. É `<button>` com `aria-label="Mostrar sidebar"`, não é grid item (`position: fixed`
confirmado) e passa no hit-test (`elementFromPoint` devolve a própria alça).

**UI-0023 conferida:** em `light` e em `dark` a alça mede **a mesma cor** — `--sb-bg` não tem par
claro. Preta nos dois modos, como a sidebar.

## 4 · Uma escolha que declaro, porque é divergência do alvo

O playbook e o pedido mandam `--sb-*` e nada de cor crua. O protótipo pinta a alça com tokens de
**tema** (`--bg-elev` / `--border` / `--text-mute`), e `--bg-elev` **não existe** neste shell. Segui
a instrução: `--sb-bg` / `--sb-border` / `--sb-text-dim`, e no hover `--sb-active` / `--sb-text-hi`
— que é, literalmente, o fallback que o próprio protótipo declara (`var(--accent-soft, var(--sb-active))`).

Consequência medida: o fundo fica **mais escuro** que no protótipo (L 0.18 contra 0.30). Defensável
— a alça é o toco da sidebar, e a UI-0023 a quer preta — mas é divergência de cor, e cor é forma.
**Se [W] quiser paridade exata, são duas linhas do `cockpit.css`**, e eu troco.

Segundo item, menor: o `title` do protótipo chega ao DOM com **duas** barras invertidas. É JSX com
atributo literal, que não passa por escape de string. Aqui saiu como expressão, então o usuário lê
uma barra só, igual ao `.sb-collapse-handle` que já existe no shell. Não repliquei o bug.

## 5 · Não feito, e por quê

- **Pest.** Não rodei. Não é esquecimento de protocolo: `AppShellUsageGateTest`,
  `CockpitPatternConformanceTest` e irmãos leem o repo com `file_get_contents(base_path(...))`, e o
  checkout do container no CT 100 é **outro** (defasado, com alterações não-commitadas de
  terceiros). Rodar lá mediria uma árvore que não é a minha — a classe de erro
  [LC-08](../../../../memory/LICOES_CODE.md). O oráculo certo para este diff é o **CI**, que roda
  contra este branch; o resultado dele vai no PR.
- **Recibo `recibos/04-execucao.json`** (junit-summary, §7 do índice). Depende do run de Pest acima
  — sem ele, o recibo seria afirmação com cara de prova. A pasta `recibos/` ainda não existe em
  `main`: nenhuma thread gerou uma.
- **O comportamento React em runtime** — ⌘⇧\ de fato alternando, a `<aside>` desmontando, o clique
  na alça voltando pra `expanded`. O harness mede o **CSS** com a estrutura real; a ponte JSX→DOM
  está coberta por typecheck e leitura, não por execução. Exige a app com sessão logada
  (`oimpresso.com/home` → 302 `/login`).
- **T7** (`design-diff --compare --check`) e **screenshot prod dark 1280** — a própria thread já os
  declara fora do alcance daqui ([W2]).

## 6 · Colisão de prefixo (Lei 1 e Lei 3)

Ao abrir, dois PRs abertos tocavam meus arquivos. **O [#7203](https://github.com/wagnerra23/oimpresso.com/pull/7203)
foi mergeado no meio da execução** (11:59 UTC) — peguei ao re-medir a base antes do commit, não por
sorte: é a regra [LC-20](../../../../memory/LICOES_CODE.md) de medir a base **por arquivo** no
instante do dispatch, e 3 dos meus 4 arquivos tinham divergido.

Commitei, rebaseei sobre `main` fresco e resolvi o conflito real: o #7203 **removeu** a prop
`nomeCurto` do `SidebarFooter`; fiquei com o meu lado do bloco (a `<aside>` condicional) **menos**
essa prop — a mudança dele vence, não é minha thread. Depois do rebase re-rodei **tudo**: typecheck,
gates e a bateria inteira do harness com o `cockpit.css` pós-rebase. Os números do §3 são os de
depois.

Segue aberto o [#7030](https://github.com/wagnerra23/oimpresso.com/pull/7030) (remove o seletor de
matiz), que toca `shared.ts` e `AppShellV2.tsx`. Hunks disjuntos dos meus — ele mexe em `LS.TW_HUE`
(~6 linhas acima do `SidebarMode`) e no bloco de accent; eu, no tipo, no atalho e no render. Pela
Lei 3, a ordem de colagem é a de conclusão: **quem mergear depois rebaseia.**

Não rodei `whats-active`: o MCP do oimpresso não está conectado nesta sessão. Usei o fallback —
`gh pr list` cruzado com os 4 arquivos, mais varredura dos branches remotos com commit neles nas
últimas 48 h. Foi esse fallback que achou o #7203 já mergeado.

## 7 · Gates

| gate | resultado |
|---|---|
| `tsc --noEmit` antes × depois | **314 → 314**, zero assinatura de erro nova (normalizando linha/coluna). Os 314 são pré-existentes no `main` |
| `conformance-gate --all` | `rc=0` · `cockpit.css: cor-crua(regras de tela)=0` |
| `css-size-baseline` | `rc=1` (+56 no `cockpit.css`) → `--write` → `rc=0`. Crescimento consciente: **~31 linhas de CSS** (3 regras de grid, 2 seletores na lista mobile, os 2 blocos da alça) e **~25 de comentário**, que é onde mora a medição do §2 — pra ninguém "simplificar" o grid de volta pro `0 1fr 320px` |
| `cowork-ssot-guard` | `rc=0` |
| `prototipo-readiness` | `rc=0` |
| `--filter=Sidebar` · `=Cockpit` · `=AppShellUsageGate` | **não rodados** — §5 |

## 8 · Para o [W]

1. **A cor da alça** (§4) — `--sb-bg` por instrução, ou paridade com o protótipo?
2. **RESÍDUO-5 do índice segue aberto e não é desta thread.** O rail (56 px) do alerta de
   certificado é invenção da thread 03, não paridade; não promovi nada dele. Se o `hidden` entrar
   em prod, a pergunta continua de pé do jeito que está escrita lá.
3. **O protótipo serve tela em branco no `hidden`** (§2). Não bloqueia este PR — o shell está
   correto — mas alguém aperta ⌘⇧\ lá e acha que quebrou.
4. **R10:** aprovação sua antes do merge.
