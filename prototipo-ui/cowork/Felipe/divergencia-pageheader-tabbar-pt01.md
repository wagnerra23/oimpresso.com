# Divergência PT-01 × tela do shell — cabeçalho da página (B-04) e abas (B-05)

> Origem: pergunta da usuária em 31/08/2026 — "o `Pt01Lista.dc.html` está com PageHeader
> diferente e TabBar também (diverge da tela do Wagner que você falou que estava no shell).
> Entre mais alguns detalhes. POR QUÊ? Quem está certo aqui?"
>
> Procedência marcada em todo item: **[DS]** citado do design system · **[TELA]** decidido no
> protótipo · **[RUNTIME]** observado em execução · **[REPO]** lido do código do `main`.

---

## §1 Resposta curta

**Na estrutura, o template está certo.** `PageHeader` (slot 1) + `TabBar` em faixa própria
(slot 2) reproduz a última decisão do Wagner (14/07/2026), e o componente canon do repo concorda
com ela. A tela que aparece com placa de ícone e abas na mesma linha do título é a geração
**anterior**, congelada.

**Nas abas não há conflito de valor**: `TabBar` [DS] e `PageHeaderTabs` [REPO] descendem do mesmo
`.cli-moduletopnav-tab` do protótipo e batem número por número (§3).

O que resta são divergências numéricas medidas (§4), que pela regra do projeto se aplicam como o
DS manda e vão para a pauta — nunca "corrigidas" dentro da tela.

---

## §2 Por quê: coexistem três gerações de cabeçalho no repo

| Geração | Arquivo [REPO] | Estado | Anatomia |
|---|---|---|---|
| 1 | `resources/js/Components/shared/PageHeader.tsx` | `@deprecated` / **CONGELADO** no próprio docblock; o ratchet `pageheader-gate` reprova CI se tela nova importar | placa de ícone 40×40 `bg-primary/10` · `description` em `text-sm` · **abas dentro do slot `action`**, na mesma linha do título ([W] 2026-05-17) |
| 2 | `resources/js/Components/PageHeader/PageHeader.tsx` | **canon v3.8** (ADR 0189 / 0190) | flat · sem fundo · sem raio · **sem placa de ícone** · `border-b` em `var(--border)` · h1 22px · subtítulo tabular · zonas A / B / C |
| 3 | decisão de **14/07/2026**, visível em `Pages/Cliente/Index.tsx` L882-883 | vigente | *"ZONA C · subnav MOVIDA pra faixa própria abaixo do header — 'mesma posição do Clientes/protótipo em todas'"* — a Zona C do canon foi deliberadamente esvaziada |

Amostra de 39 `Index.tsx` lidas no `main`: **17 ainda na geração 1**, **22 migradas**. A geração 1
serve cerca de 104 telas no total — é por isso que ela continua no repo e continua aparecendo em
tela.

`Pt01Lista.dc.html` implementa a geração 3.

---

## §3 O que NÃO divergiu (abas — mesma origem, mesmos números)

`TabBar` [DS] e `PageHeaderTabs` [REPO] batem em:

- sublinhado ativo **2 px** em `var(--accent)`, deslocado **-1px** para cobrir a borda da faixa
- fundo do item ativo `color-mix(--accent-soft 50%)`
- rótulo ativo peso **600**
- pílula de contador: peso **600**, **10.5px / 1.4**, `min-width 18`, raio total
- pílula ativa em `--accent` / `--accent-fg`

As abas, portanto, não divergem em valor. Divergem em **posição** (§2) e em **mecânica** (§4).

---

## §4 Divergências reais e medidas — lista **fechada**

| Item | DS / template **[DS]** | Canon do repo **[REPO]** |
|---|---|---|
| h1 | 600 · 22px / 1.3 · `-.015em` | **700** · 22px · `tracking-tight` (−0.025em) · `leading-snug` |
| subtítulo | `stats[]` · 400 · **13px** / 1.45 · mt 4px | ReactNode livre · **12px** (`text-xs`) · mt 2px |
| caixa do header | `14px 0` — lateral vem do wrapper (14px compacto / 22px confortável) · `align-items: flex-start` | `pt-6 px-6 pb-3.5` · `min-h-[60px]` · `items-center` |
| landmark | `<header>` sem `role` | `<header role="banner">` |
| altura da aba | **36 px** · padding `0 14px` · 13px | `px-3 py-1.5 text-sm` → 14px, ~**30 px** |
| mecânica da aba | `<button>` + `onChange` (estado local) · `<nav aria-label="Sub-navegação">` · `overflow-x: auto` | `<Link href>` Inertia (URL) · `role="tablist"` + ←/→/Home/End · `maxVisible 5` + overflow `⋯ Mais` |
| pílula inativa | `var(--bg-2)` / `var(--text-dim)` | `oklch(0.32 0.01 240)` / `oklch(0.70 0.01 240)` **cravados** (hue frio 240) |
| ação primária | fora da barra de abas | `PageHeaderPrimary`, **dentro** da barra de abas |

Nota de densidade: em densidade confortável o respiro lateral de 22px do template praticamente
encosta nos 24px do canon. A diferença de respiro só aparece no compacto.

**Não auditado neste documento** (declarado para não virar omissão): sidebar, faixa de KPI,
toolbar, tabela, paginação.

---

## §5 Decisão pendente da usuária

Pela regra do projeto o DS ganha, e divergência autoriza pergunta, não ação. Nada foi alterado.

Candidatos a registro em `pauta-design-system.md`:

| # | Item | Sugestão |
|---|---|---|
| a | peso do h1 — 700 [REPO] vs 600 [DS] | P1 (aparece em toda tela) |
| b | subtítulo — 12px [REPO] vs 13px [DS] | P2 (esperar segundo caso) |
| c | altura da aba — 30 px [REPO] vs 36 px [DS] | P1 (36 px também é o alvo de toque) |
| d | `TabBar` sem `role="tablist"` e sem navegação por seta | **D** (defeito de origem do DS) |
| e | `PageHeader` do DS não emite `role="banner"` | **D** (defeito de origem do DS) |
| f | pílula inativa em hue 240 cravado no repo, fora de token | fora do DS — item de repo, não de pauta |

Pergunta aberta: abrir os cinco (a–e) ou só os que valem segundo caso?

---

## §6 Teste de aceite

- Nenhum arquivo de tela ou de DS foi modificado nesta entrega — `git status` limpo fora deste `.md`.
- Toda linha da tabela §4 tem procedência [DS] ou [REPO] declarada.
- A lista da §4 está marcada **fechada**; §3 lista o que não divergiu; §4 declara o que não foi auditado.
