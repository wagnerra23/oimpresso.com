---
date: "2026-07-15"
time: "00:30 BRT"
slug: consolidacao-ds-tabnav-dark
tldr: "Consolidação DS: barra-de-abas de topo unificada num componente único (PageHeaderTabs) fiel ao protótipo cliente .cli-moduletopnav; 34 telas migradas + cadastro em faixa própria com contadores. 5 PRs mergeados. 3 chips spawnados (dark fix, Fiscal/Governanca, ADR+máquinas de governança)."
prs: [4279, 4280, 4281, 4282, 4283]
decided_by: [W]
next_steps:
  - "Acompanhar os 3 chips: task_b2b0f4ee (dark fix, já abriu PR #4285), task_50050ee0 (Fiscal/Governanca), task_23fd0d05 (ADR + máquinas gate C/detector/spec-fidelidade)"
  - "Ondas futuras do detector: status-badge (11, hand-rolam pill) + combobox (5, hand-rolam dropdown)"
---

# Handoff — Consolidação DS: barra-de-abas canônica + acabamento dark

## Estado MCP no momento do fechamento
- `cycles-active`: **nenhum cycle ATIVO em COPI**.
- PRs desta sessão: **#4279, #4280, #4281, #4282, #4283 — todos MERGED** (confirmado via `gh pr view`).
- Chips (sessões limpas iniciadas por [W], rodando independentes): `task_b2b0f4ee` (dark fix — **já abriu PR #4285**), `task_50050ee0` (Fiscal/Governanca), `task_23fd0d05` (ADR+máquinas).

## O que aconteceu
[W] começou querendo "aplicar o financeiro unificado / a interface bonita" — descobri que **já estava aplicado e vivo** (ADR 0313, tela `Financeiro/Unificado` madura). O foco então virou **fidelidade ao protótipo** e **consolidação de componentes**:

1. **Auditoria de header** — 2 `PageHeader` (canon v3.8 vs `@deprecated`) + **8 componentes de barra-de-abas** divergentes + 15 tablists manuais. [W]: *"deveria ser tudo a mesma classe no sistema inteiro"*.
2. **Componente único** — `PageHeaderTabs` virou o canônico fiel ao `.cli-moduletopnav` do protótipo (`prototipo-ui/cowork/clientes-page.css`): pill roxo suave `oklch(0.55 0.15 295/0.1)`, ícone roxo, underline `0.55` **reto na base** (`-mb-px`, sem `rounded-md`), texto branco/600, **badge de contagem** opt-in (`PageHeaderGhost.badge`).
3. **34 telas** no componente: Financeiro (17) · Ponto (15) · Jana herdaram de graça (via adaptadores `*SubNav`); **Cadastro** (`Cliente/Index`) migrado da barra própria inline (`SLOT2_TABS` em 2 `<nav>`) pro componente em **faixa própria** + contadores (decisão [W]: trazer contadores de volta + faixa própria).
4. **[W] pegou 2 bugs no olho** que máquina não pegou: (a) pill arredondado vs reto; (b) dark mode — divisão do header e contorno dos KPI com cor **hardcoded light** (`oklch(0.93 0.004 90)`) que não adapta ao dark.
5. **Distribuído em 3 chips** (sessões limpas).

## Artefatos gerados (todos MERGED)
- **#4279** — `Financeiro` navheader 100% canon v3.8 (4 telas migradas do `shared/PageHeader` @deprecated + limpa `FinSubNav` órfão + ratchet 101→97).
- **#4280** — aba ativa cor `text-foreground` + underline `0.55` (fim dos 2 roxos).
- **#4281** — `PageHeaderTabs` canônico: pill + ícone roxo + badge (`.cli-moduletopnav`).
- **#4282** — slim **reto na base** (`border-radius:0` + `-mb-px`).
- **#4283** — cadastro migra pra barra canônica em faixa própria + contadores.

## Persistência
- **git canon:** os 5 PRs em `origin/main`; este handoff no branch `claude/handoff-ds-tabnav`.
- **MCP:** propaga via webhook GitHub→MCP ~2min após push.
- **Chips:** estado vivo nas 3 sessões independentes (git dos PRs que elas abrirem).

## Próximos passos pra retomar
Os 3 chips estão rodando. Retomar = acompanhar os PRs deles (#4285 do dark fix já aberto). Ordem de dependência: o **gate C** (borda/sombra inline TSX, chip 3) nasce advisory+baseline; o **dark fix** (chip 1) baixa o baseline ao remover os hardcodes.

## Lições catalogadas
- **Máquinas não pegam borda/sombra hardcoded em `style={{}}` inline no TSX** — os gates de cor (`conformance-gate.mjs`, stylelint) só olham CSS. Ponto cego que deixou o dark quebrar sem alarme. → gate C (chip 3).
- **Posição/composição na tela (inline vs faixa própria) fica no olho** — nenhuma máquina julga "essa barra devia estar abaixo do título". [W] pegou 2/5 bugs de dimensões que máquina não cobre; ~3/5 (cor/radius/dark) viram máquina.
- **Grade de cobertura de máquina:** conformidade estrutural ~72→80 com os chips; fidelidade de design+composição+gosto ~5 (fica no olho). Teto realista de automação ~75-80%.
- **Errei 2x o alvo** por assumir em vez de checar o protótipo: (a) usei a tela viva de Cliente como "molde fiel" (ela diverge — inline em vez de faixa própria); (b) disse "inline" sem ler o `.jsx` (o protótipo é faixa própria após `</header>`). Lição: **âncora = o protótipo lido, não a tela viva nem o chute**.
- **Todas as máquinas de DS são determinísticas** (regex/AST/pixel-diff/assert) — IA está no *fazer*, não no *julgar*. Separação proposital.

## Pointers detalhados (on-demand)
- Componente canônico: `resources/js/Components/shared/PageHeaderTabs.tsx`
- Protótipo-âncora: `prototipo-ui/cowork/clientes-page.css` (`.cli-moduletopnav*`)
- Cadastro migrado: `resources/js/Pages/Cliente/Index.tsx` (`SLOT2_TABS`→`contactGhosts`)
- Prompts dos chips: spawn_task `task_b2b0f4ee` / `task_50050ee0` / `task_23fd0d05`
