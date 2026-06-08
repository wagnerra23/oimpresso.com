# PageHeader Canon v3 — Proposal Bundle

> **Status:** proposal · pending Wagner approval
> **Origem:** sessão 2026-05-24 — Wagner pediu spec 10/10 após auditoria que o header `/contacts?type=customer` estava sendo entregue "pela metade, sem detalhes que deixam lindo"

## Arquivos deste bundle

| Arquivo | Tamanho | Pra quê |
|---|---|---|
| **[index.html](index.html)** | ~17 KB · standalone | **Abrir no browser** — 5 demos interativos (header completo · loading · offline · 4 grupos comparados · 3 densidades) com controles em tempo real |
| **[SPEC.md](SPEC.md)** | ~38 KB · 30 seções | Spec executável definitiva — fundação, tokens, componente React, anti-padrões, migration plan 80+ telas |
| **[diagram.svg](diagram.svg)** | ~9 KB · vetorial | Geometria 3 zonas com medidas, baseline, tokens visuais, 5 regras críticas |
| **README.md** | este arquivo | Índice + checklist de validação Wagner |

## Como validar (15 min)

1. **Abra `index.html`** no browser (já no preview panel)
2. **Brinque com os controles** no topo: tema, densidade, grupo, loading, offline
3. **Hover/click nos botões e tabs** — sinta as microinterações (120ms hover, 60ms press, 0.97 scale)
4. **Abra `diagram.svg`** pra ver geometria com medidas absolutas
5. **Leia SPEC.md §1 (Mapa mental) + §30 (Anti-padrões)** — 5 min de leitura crítica
6. **Aprovar ou rejeitar** — se OK, codifico componente `<PageHeader>` + migration Wave 1 (Cliente + Cobrança)

## Checklist Wagner (10 perguntas pra você responder)

- [ ] **1.** Altura do header está bem proporcional? (compact 56 / cozy 64 / comfortable 80)
- [ ] **2.** Tabs alinhadas verticalmente com primary? (items-center · mesma baseline)
- [ ] **3.** Border do primary coerente com o bg? (mesmo hue, escuro 10%)
- [ ] **4.** Underline da tab ativa "morde" a linha do header? (`-mb-px`)
- [ ] **5.** Hover nos tabs é suave? (120ms ease-smooth)
- [ ] **6.** Press no primary tem feedback? (`scale(0.97)` + 60ms ease-snap)
- [ ] **7.** Loading skeleton ocupa MESMO espaço? (CLS = 0)
- [ ] **8.** Comparativo entre grupos (ciano/verde/âmbar/vermelho) mantém coerência?
- [ ] **9.** Densidades compact/cozy/comfortable fazem sentido?
- [ ] **10.** Tema dark mantém contraste WCAG AA?

## Cobertura — 17 dimensões prometidas (v3 entrega todas)

| Dim | Item | Onde |
|---|---|---|
| 1 | Mapa mental ("sensação" do header) | SPEC §1 |
| 2 | Geometria 3 zonas | SPEC §2 + diagram.svg |
| 3 | Container fundação | SPEC §3 |
| 4 | Zona L identidade | SPEC §4 |
| 5 | Zona C SubNav inline | SPEC §5 |
| 6 | Zona R actions | SPEC §6 |
| 7 | Density modes (3) | SPEC §7 + index.html demo 5 |
| 8 | Easing curves + timing scale | SPEC §8 |
| 9 | Estados completos (matriz) | SPEC §9 |
| 10 | Microinterações (10) | SPEC §10 + index.html interativo |
| 11 | Responsive + container queries | SPEC §11 |
| 12 | Loading skeleton | SPEC §12 + index.html demo 2 |
| 13 | Offline / Error state | SPEC §13 + index.html demo 3 |
| 14 | Dark mode tokens (par completo) | SPEC §14 + index.html toggle |
| 15 | Print stylesheet | SPEC §15 |
| 16 | Acessibilidade WCAG 2.1 AA + AAA | SPEC §16 |
| 17 | i18n + RTL | SPEC §17 |
| 18 | Keyboard shortcuts | SPEC §18 |
| 19 | View Transitions API | SPEC §19 |
| 20 | URL state sync | SPEC §20 |
| 21 | Page transition Inertia | SPEC §21 |
| 22 | Telemetry hooks | SPEC §22 |
| 23 | Performance budget | SPEC §23 |
| 24 | SEO + schema.org | SPEC §24 |
| 25 | Tokens canon (single source) | SPEC §25 |
| 26 | Componente `<PageHeader>` | SPEC §26 |
| 27 | Storybook stories | SPEC §27 |
| 28 | Visual regression Pest | SPEC §28 |
| 29 | Migration plan (80+ telas, 4 waves) | SPEC §29 |
| 30 | Anti-padrões (15 catalogados) | SPEC §30 |

## Se aprovado — próximos passos

### Wave 1 (2 dias, 2 telas piloto)

1. Criar `resources/css/tokens/page-header.css` (§25)
2. Criar `resources/js/Components/PageHeader/PageHeader.tsx` (§26)
3. Criar `resources/js/hooks/useOnline.ts` + `useDensity.ts`
4. Aplicar em `Cliente/Index.tsx` — substitui header atual
5. Aplicar em `Financeiro/Cobranca/Index.tsx` — substitui header atual
6. Storybook stories (§27)
7. Pest browser tests (§28) — 8 baselines
8. Smoke prod 7 dias

### Wave 2-4 — ver §29

## Se rejeitado

- O que NÃO ficou bom? Anotar nos checkboxes acima
- Iterar: alterar SPEC.md + index.html → revalidar
- Não promover ADR até este bundle ser aprovado

## ADR proposta (rascunho)

`memory/decisions/proposals/pageheader-canon-v3.md` — supersede ADR 0180 (origem) + 0182 (hue per grupo).

Conteúdo da ADR: extraído de SPEC.md §0 (Princípios duros) + §29 (Migration plan) + §30 (Anti-padrões).

---

**Hash bundle:** v3.0 · 2026-05-24 · 3 arquivos · ~64 KB total
