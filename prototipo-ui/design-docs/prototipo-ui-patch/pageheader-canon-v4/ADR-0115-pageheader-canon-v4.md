# ADR-0115 — PageHeader canon v4.1

> ## ⚠️ REVOGADO em 2026-05-24 — **NÃO ABRIR PR**
>
> ADR construída sobre premissas erradas (tokens inventados, hue per-módulo no header, tabs no zone-C). Canon real está em ADR UI-0013 + PT-01 Lista no repo `main`. Ver REVOGAÇÃO no SPEC.md irmão.

---

**Status:** Proposto (PR de [W] aprovador) · **Data:** 2026-05-24 · **Origem:** Sessão Cowork [CC]

## Contexto

`.os-page-h` em `resources/css/styles.css` (linhas 2102–2121) cresceu organicamente sem spec formal. Cada módulo (`Compras`, `Vendas`, `Oficina`, `Clientes`…) interpretou o padrão de forma levemente diferente. Sintomas observados:

- Título e ações desalinhados verticalmente entre telas (`align-items: flex-start` em uma, `center` em outra)
- Tabs vivem em `.os-toolbar` separado abaixo do header em algumas telas, mas em outras como pílulas dentro do header
- Sem hue contextual por módulo — toda primary action usa `--accent` genérico
- Sem skeleton, sem print stylesheet em uniformidade, sem container-query (header quebra quando drawer abre)
- Cobertura WCAG inconsistente (skip-link e `aria-current` ausentes em algumas)

## Decisão

Adotar **PageHeader canon v4.1** como padrão obrigatório pra todas as telas Index/List/Show, conforme spec executável em `prototipo-ui-patch/pageheader-canon-v4/SPEC.md`. Resumo das decisões duras:

1. **Geometria grid 3 colunas** `minmax(180px, 1fr) minmax(0, auto) auto` — L tem floor anti-colapso, C respira no centro, R cola na direita.
2. **Tabs vivem na zona C do header** (não mais em `.os-toolbar`). Toolbar fica só pra search + filtros avançados.
3. **Hue por módulo** via `data-group="..."` mapeado em `--origin-*-fg` (5 existentes + 3 novos: `CAD/FIS/SIS`).
4. **Density** segue o canon do projeto: `compact / default / comfy` (não `cozy/comfortable`).
5. **Container query** `@container page-shell (max-width: 900px)` faz stack-mode quando sidebar + drawer espremem o main abaixo de 900px. `container-type: inline-size` mora no `.main-body` do AppShellV2 (não no header).
6. **Tokens consolidados** em `resources/css/tokens/page-header.css` (geometria density-aware + escala de easing + z-index scale).
7. **Acessibilidade não negociável:** `role="banner"`, `aria-current`, skip-link, focus rings com offset, `prefers-reduced-motion` + `prefers-contrast: more`, RTL, touch targets 44px em mobile.
8. **Dark mode é primário** — toda regra tem par light/dark via `[data-theme="dark"]`.

## Paleta de hues canonicalizada

| Code | Módulo | Hue (light) | Hue (dark) | Justificativa |
|---|---|---|---|---|
| MFG | Produção | `oklch(0.40 0.10 30)` | `oklch(0.78 0.10 30)` | existente |
| OS | Oficina | `oklch(0.40 0.10 60)` | `oklch(0.78 0.10 60)` | existente |
| **CAD** | **Cadastro** | `oklch(0.40 0.10 180)` | `oklch(0.80 0.10 180)` | **novo · cyan-teal** |
| FIN | Finanças | `oklch(0.36 0.10 145)` | `oklch(0.76 0.10 145)` | existente |
| CRM | Comercial | `oklch(0.40 0.10 220)` | `oklch(0.78 0.10 220)` | existente |
| PNT | Pessoas | `oklch(0.40 0.10 295)` | `oklch(0.78 0.10 295)` | existente |
| **FIS** | **Fiscal** | `oklch(0.42 0.12 340)` | `oklch(0.82 0.11 340)` | **novo · rose** |
| **SIS** | **Sistema** | `oklch(0.50 0.01 80)` | `oklch(0.68 0.005 90)` | **novo · neutro** |

**Mínima distância angular:** 30° (MFG↔OS, cluster warm intencional). **2ª mínima:** 35° (CAD↔FIN, cyan vs verde — perceptualmente distintos por família). Zero trios em cluster.

## Migration path

| Onda | Módulo | Telas | Status |
|---|---|---|---|
| 1 (piloto) | Cadastro | `Clientes`, `Fornecedores`, `Representantes` | **EM ANDAMENTO** (`clientes-page.jsx` patcheado em sessão Cowork 2026-05-24) |
| 2 | Comercial | `Pedidos`, `Orçamentos`, `Vendas/Index` | pendente |
| 3 | Financeiro | `A Receber`, `A Pagar`, `Boletos`, `Caixa` | pendente |
| 4 | Oficina + Produção | `OS/Index`, `OS/Detail`, `Estoque`, `Mecânica` | pendente |
| 5 | Pessoas + Fiscal + Sistema | `Funcionários`, `NFe`, `Configurações` | pendente |

## Trade-offs aceitos

- **MFG↔OS 30°** (warm cluster) — intencional, ambos são "produção física". Usuário entende pelo contexto da sidebar, não só pela cor.
- **CAD↔FIN 35°** (cyan vs verde) — tight mas distinto por família perceptual.
- **Stack-mode em viewport 1440 + drawer aberto** — header pula de 3-col pra 2-row quando drawer abre. Conscientemente aceito; preferível a comprimir layout.
- **Hover-prefetch via Inertia `router.prefetch`** com cancel em 80ms — pode gerar requests cancelados sob hover acidental. Lance < 0.1% do tráfego.

## Alternativas descartadas

| Alternativa | Por que não |
|---|---|
| Manter tabs em `.os-toolbar` separado | Aumenta altura visual sem ganho; tabs viram navegação irmã que pertence ao header |
| Hue global via `--accent` único | Perde contexto modular — usuário precisa ler título pra saber onde está |
| `position: sticky` no `.os-toolbar` em vez do header | Quebra hierarquia visual: header precisa estar grudado, toolbar é secundário |
| Flex em vez de grid pra zona-C centralizar | Bug v3: C cola em L com gap em vez de centralizar de fato |
| Tokens HSL em paridade com shadcn | Conflito com OKLCH já vigente em `styles.css`; complexidade extra sem ganho |
| Storybook stories | Storybook não está no stack do projeto. Pest 4 Browser baseline cumpre o papel de visual regression |

## Consequências

- **+1 arquivo CSS:** `resources/css/tokens/page-header.css` (≈350 LOC)
- **3 origin tokens novos** em `:root` e `[data-theme="dark"]` de `resources/css/styles.css` (`CAD/FIS/SIS`)
- **Markup quebra:** todas as Pages/Index/*.tsx precisam migrar `<div className="os-page-h">` → `<header className="os-page-h" data-group="...">` (BC quebra silenciosa: legado fica com look antigo até migrar, sem erro de render)
- **`.main-body` recebe `container-type: inline-size; container-name: page-shell`** em `AppShellV2.tsx`
- **Removida** a regra `.os-toolbar > .os-tabs` em `styles.css` (depende da onda 5 completar)
- **Pest 4 baseline:** 24 snapshots (3 density × 2 theme × 4 grupos) bloqueiam regressão

## Decisão de naming

Mantemos `.os-page-h` como classe raiz (não `.page-header`) por **continuidade com o canon existente** do Cockpit V2 — `.os-page`, `.os-stat`, `.os-toolbar`, `.os-table-wrap`. Migrar a família inteira pra `page-*` seria churn sem ganho. Detecção v4 vs legacy = presença do atributo `[data-group]` no header.

## Referências

- SPEC executável: `prototipo-ui-patch/pageheader-canon-v4/SPEC.md`
- Protótipo HTML rodável: `prototipo-ui-patch/pageheader-canon-v4/index.html`
- CSS canon pronto pra repo: `prototipo-ui-patch/pageheader-canon-v4/pageheader-canon.css`
- Aplicação piloto: `clientes-page.jsx` (patch desta sessão)
- ADR mãe: `memory/decisions/0008-cockpit-layout-mae-do-erp.md`
- ADR cowork loop: `memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md`

## Aprovação

- [ ] [W] Wagner — visual em `Oimpresso ERP - Clientes.html` aplicação piloto
- [ ] [CD] Claude Design — critique-score.json ≥ 80
- [ ] [CL] Claude Code — tradução pra Inertia/React em PR no repo
- [ ] [CA] Claude Accessibility — a11y-report.md (WCAG 2.1 AA)
- [ ] [W2] Wagner aprovador — F4 merge
