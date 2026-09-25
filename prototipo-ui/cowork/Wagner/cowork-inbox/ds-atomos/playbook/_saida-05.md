---
sessao: "_saida-05"
thread: "05 · shared/StatusBadge.tsx — kinds sla · frescor · atendimento + rel/tone"
dono: "[CL]"
data: 2026-09-25
prefixo_tocado: resources/js/Components/shared/StatusBadge.tsx · tests/js/statusbadge-kinds.test.tsx
base_lida: wagnerra23/oimpresso.com@main 45a687387ee2 (a thread foi escrita em 07036a68a049; o StatusBadge.tsx tinha os mesmos 15.840 B na abertura — sem remedição necessária)
---
# _saida-05

## 1 · Feito

- `shared/StatusBadge.tsx`: 3 kinds novos **no fim** do `mappings` — `sla` (4 chaves),
  `frescor` (4), `atendimento` (5) = **13 chaves**, com chave e rótulo literais do `MAP` do
  StatusBadge em `prototipo-ui/design-system/_ds_bundle.js` (conferido linha a linha no turno).
- Props novas, opcionais, nos dois ramos (mapeado e fallback):
  - `rel?: string` → rótulo + `·` (`aria-hidden`) + sufixo. Sem `rel`, nenhum nó a mais.
  - `tone?: Variant` → troca a variante. Tipado pelo `Variant` derivado do `badgeVariants`.
    Com `tone`, a `className` de cor da entrada sai junto (senão o token do mapa venceria por
    cima do tom pedido). Consequência declarada: `tone` num valor com `animate-pulse`
    (urgente, Risco Crítico) tira o pulso. Nenhum consumidor passa `tone` hoje.
- Cor: forma AP7 — `variant: 'outline'` + `bg-[var(--sla-*-soft)]`/`text-[var(--sla-*)]` e
  `bg-[var(--canal-*-tint)]`/`text-[var(--canal-*-fg)]` + `border-transparent`; `frescor` usa as
  variantes soft `success`/`warning`/`danger`. WhatsApp usa o verde do SLA, como o DS.
- **0 token novo · 0 diff em `ui/badge.tsx` e em `resources/css/**`.**

## 2 · Os tokens existem — medido em runtime, não pelo nome

A thread mandava confirmar antes de usar (lápide §5 2026-09-25, `--primary` × `--color-primary`).
- **Nome:** o espelho do DS chama os tokens de `--color-sla-*` e `--color-canal-*-soft`. **Esses
  nomes não existem no app.** Os reais, usados aqui, são `--sla-{fresh,aging,late,expired}[-soft]`
  e `--canal-{email,ig,fb,ml}-{tint,fg}`, saída de `tokens/_generated-cockpit-{light,dark}.css`.
- **`getComputedStyle(el).getPropertyValue('--x')`**, browser, CSS real carregado: os 16 tokens
  **não-vazios** em `.cockpit` light e em `.cockpit[data-theme="dark"]`; **vazios** fora do
  `.cockpit` (controle negativo — a sonda discrimina). `--color-primary` também saiu vazio nessa
  sonda: ela só carregava os `_generated-*`, e isso é esperado.
- **Cor renderizada** (build Inertia local, `inertia-*.css` + `AppShellV2-*.css`, markup real do
  componente via `renderToStaticMarkup`, DOM estável em 2 leituras):

  | pílula | light `color` | dark `color` |
  |---|---|---|
  | Atrasado (`late`) | `oklch(0.45 0.16 30)` | `oklch(0.84 0.16 30)` |
  | Vencido (`expired`) | `oklch(0.46 0.18 25)` | `oklch(0.84 0.16 25)` |
  | E-mail / Instagram / Facebook / Mercado Livre | hue 280 / 30 / 250 / 95 | idem |

  O dot sai com a cor do texto (`bg-current`). O Tailwind v4 gerou `.text-[var(--sla-expired)]{color:…}`
  (cor, não tamanho) e o `tailwind-merge` descarta o `text-foreground`/`border-border` do `outline`.

## 3 · Achados para decisão — não consertados (fora do prefixo)

1. **No dark, Atrasado e Vencido quase empatam:** mesma luminosidade e croma (`0.84 0.16`),
   só 5° de matiz (30 × 25). Os valores diferem, o teste 1b passa, mas a leitura "cor diferente"
   que o [W] pediu é fraca no dark. Mudar o token é `resources/css/**` + Style Dictionary =
   token do DS = soberania [W].
2. **Fora do `.cockpit` os kinds `sla`/`atendimento` saem sem cor** (fundo transparente, texto
   padrão — medido). `frescor` e todos os kinds antigos seguem com cor. Hoje não há consumidor
   dos kinds novos; quem adotar numa tela fora do AppShellV2 precisa saber disso. Não dupliquei
   token.
3. **`governance/design/component-registry.json:814-815` fica desatualizado** com este PR: diz
   `"kind=frescor/tipo/sla/atendimento": "FALTA — estender (0 ocorrências)"`. Agora 3 dos 4
   existem (`tipo` segue fora por decisão). O arquivo não está no prefixo da thread — fica para
   quem for dono dele.

## 4 · Guarda — kinds antigos sem diff

- **Medição única, mais forte que "1 valor por kind":** o `StatusBadge.tsx` do `HEAD` e o novo
  renderizaram lado a lado **todas** as chaves antigas (22 kinds · 88 chaves + um valor fora do
  mapa) × 3 variações de prop (`{}`, `label`, `className`) = **330 renders, 0 diferentes**.
  Controle positivo: com `rel="CTRL"` no novo, **330 de 330** diferem — o comparador discrimina.
  (Script descartável; fora do commit.)
- **Guarda permanente** no teste: um valor por kind antigo segue com dot + texto, sem sufixo e
  sem token de `sla`/`canal` (22 casos).
- Consumidores: 18 arquivos `.tsx` importam `shared/StatusBadge`. Nenhum usa os kinds novos nem
  `rel`/`tone` — pela medição acima, nenhum muda de byte.

## 5 · Validação (bloco E da thread)

| # | item | resultado |
|---|---|---|
| 1 | 13 chaves: rótulo literal + classe/variant | ✅ 13 casos |
| 1b | Atrasado ≠ Vencido | ✅ tokens distintos + valores distintos light/dark (jsdom); cor computada medida no browser (§2) |
| 1c | 4 canais distintos entre si | ✅ `fg` e `tint`, light e dark |
| 2 | kinds antigos sem diff | ✅ 330/330 idênticos + guarda permanente |
| 3 | `rel` após o rótulo; sem `rel`, texto idêntico | ✅ inclusive no fallback |
| 4 | `tone` troca a variante; tom fora do DS não compila | ✅ — ⚠️ `tests/` **não está** no `include` do `tsconfig.json`, então o `npm run typecheck` nunca checaria o `@ts-expect-error`. Rodei `tsc` escopado no arquivo: limpo; com `tone="info"` acusa *"Unused '@ts-expect-error'"* (morde) |
| 5 | ausências: `fiscal:`, `tipo-pj`, `canal-email-bg`; `ui/badge.tsx` e `resources/css/**` sem diff | ✅ |
| 6 | bite-test: classe de `expired` ← a de `late` | ✅ **2 vermelhos** (`sla.expired` e o 1b); restaurado com hash conferido, 46 verdes |
| 7 | `npm run test -- statusbadge-kinds` | ✅ **46 passed** |

Outros: `component-registry-check.test.mjs` ✅ · `--check --strict` ✅ (69 entradas, íntegro) ·
`--roles` exit 0 (3 independentes no papel `status-badge` — `Cliente/Pills`,
`ServiceOrderStatusBadge`, `VehicleStatusBadge` — **herdados**, não tocados) ·
`ds-canon-color-guard` ✅ · `reuse:gate` ✅ · `components:check` ✅ · eslint no componente ✅.

## 6 · Placar

**entregue 13 de 13 chaves + 2 de 2 props · ausentes `fiscal` e `tipo` por decisão D-SB-KINDS**
(fonte única `FiscalStatusBadge`; `tipo` exigiria token que não existe).

## 7 · Não medido

- T7 (`design-diff --compare --check` em prod): não se aplica sem consumidor — nenhuma Page usa
  os kinds novos. A adoção é outra onda.
- A prova `execucao` do placar depende do avaliador de recibo, fora do repo (ADR 0397).
