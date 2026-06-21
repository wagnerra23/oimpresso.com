# DS v6 — Reuse-Mapping do kit (showcase `c-*` → componente no repo)

> **Pra que serve:** o `showcase.html` é a **régua visual** do DS v6 — 11 componentes desenhados como
> classes CSS `c-*` (HTML estático, dois temas, consumindo só token). Esta tabela faz a ponte
> **reuse-first** pra produção: cada peça do kit → o que **JÁ existe** no repo React/Inertia (reusa),
> ou o que **genuinamente FALTA** (e, se faltar, qual a natureza/governança de criar).
>
> **Princípio (CLAUDE.md · receita passo 5):** reusar/renomear/documentar, **não recriar**. O kit `c-*` é a
> linguagem visual; o repo já tem a maioria das peças via `@/Components/ui` (shadcn + Radix + CVA, bridge
> Cowork `cw-*`) + componentes bespoke. Onde já existe equivalente, a régua valida o existente — não pede
> um componente novo.
>
> **Origem:** PR2 do roadmap DS v6 (handoff Cowork 2026-06-03, DS v6 aprovado [W]). Aditivo / não-Tier-0.
> A referência (`showcase.html` · `receita.html` · `gabarito-vendas.html`) já está no repo via **PR #2165**.
> **Tokens** de fundação (`--stage-*` + `-soft`) já estão via **PR #2170** (PR1).

## Como ler

- **Reusa** = já existe equivalente React no repo. Régua valida; **não cria nada**.
- **FALTA** = não há equivalente direto. Coluna "Natureza" diz se criar é aditivo ou Tier-0 (pára e [W] decide).
- Os `c-*` são **classes CSS de apresentação**; no repo a mesma semântica vive em componentes React
  (shadcn/CVA/bespoke). Equivalência é **funcional/semântica**, não 1:1 de nome de classe.

---

## Tabela — 11 componentes do showcase

| # | Kit (showcase) | Papel | Equivalente no repo | Status | Natureza se criar |
|---|---|---|---|---|---|
| 1 | `c-btn` (primary/ghost/danger) | botão; ação primária = accent | `Button` `@/Components/ui/button` (`variant="cowork-primary\|cowork-ghost"`, `variant="destructive"`) | **Reusa** | — |
| 2 | `c-pill` (pos/neg/warn/info) | status por significado | `Badge` `@/Components/ui/badge` + `StatusBadge` `@/Components/shared/StatusBadge.tsx` (mapping por domínio) | **Reusa** | — |
| 3 | `c-stage` (etapa pipeline) | ponto da esteira, `--stage-*` | `OsStageBadge` `Pages/ConsultaOs/_components/OsStageBadge.tsx` + `board/badges.ts` (Kanban Oficina) | **Reusa** | — |
| 4 | `c-kpi` (stat, tom semântico) | número grande mono | `KpiCard` `@/Components/shared/KpiCard.tsx` + `KpiGrid` + `KpiStripClickable` | **Reusa** | — |
| 5 | `c-tabs` (segmentadas) | troca de visão | `Segmented` `@/Components/ui/segmented.tsx` (Radix ToggleGroup, `.cw-segmented`) + `PageHeaderTabs` | **Reusa** | — |
| 6 | `c-plate` (placa Mercosul) | identidade do veículo, mono | `MercosulPlate` `@/Pages/OficinaAuto/ProducaoOficina/_components/MercosulPlate.tsx` (sm/md/lg) | **Reusa** | — |
| 7 | `c-asset` (card de ativo/frota) | veículo c/ status na borda | `ServiceOrderKanbanCard` + `CacambaKanbanColumn` cards (Oficina) — card de ativo já materializado nos Kanbans | **Reusa (parcial)** | composição existente; sem card "frota 360" genérico — ver nota |
| 8 | `c-id` (header de identidade) | avatar + nome + saúde + ações | `PageHeader` v3 (ADR 0180/0190) cobre header de tela; **ficha 360** (avatar+LTV+ações inline) não tem componente dedicado | **FALTA (ficha 360)** | net-new surface → **Tier-0 / pára [W]** |
| 9 | `c-tl` (timeline unificada) | eventos cross-módulo + origem | `SaleTimeline` `@/Pages/Sells/_components/SaleTimeline.tsx` + `ServiceOrderTimeline` (Oficina) | **Reusa (parcial)** | timelines por-módulo existem; **timeline unificada cross-módulo** (badge origem OS/FIN/CRM) não — ver nota |
| 10 | `c-rail` (bloco contexto "Apps Vinculados") | app irmão ao lado do trabalho | `LinkedApps` `@/Components/cockpit/LinkedApps.tsx` (Cockpit V2) | **Reusa** | — |
| 11 | `c-nba` (próxima-melhor-ação Jana) | sugestão proativa + CTA | sem componente dedicado; padrão Jana vive em `Components/cockpit/*` (Thread/TweaksPanel) | **FALTA (NBA card)** | net-new surface → **Tier-0 / pára [W]** |

### Bônus (foto — citado no handoff como provável reuso)
| Foto/grid de evidência | `DviPhotoGrid` `@/Pages/OficinaAuto/ProducaoOficina/_components/DviPhotoGrid.tsx` | **Reusa** | — |

---

## Veredito

- **8 de 11 reusam** equivalente que já vive no repo (incluindo a foto bônus). A régua DS v6 **valida o
  existente**; nada precisa ser recriado pra essas peças.
- **3 são gaps reais** — e os três são **net-new component surfaces que virariam padrão visual do app**:
  - `c-id` (**ficha de identidade 360** — avatar + LTV + saúde + ações). `PageHeader` cobre header de
    *tela*, não a *ficha* de uma entidade.
  - `c-tl` **unificada cross-módulo** (existem timelines por-módulo; falta a unificada com badge de origem).
  - `c-nba` (**card de próxima-melhor-ação da Jana**).
- **`c-asset` genérico** (card "frota 360" reusável fora dos Kanbans) é um meio-termo: a composição existe
  em Kanbans, mas não há um `AssetCard` genérico. Por ora **não criar** — surge naturalmente quando a
  ficha de frota (`c-id`) for portada.

## Governança dos gaps (CRÍTICO)

Os 3 gaps **NÃO são aditivos triviais**: criar `c-id`/`c-tl`-unificada/`c-nba` cunha **superfície de
componente nova que vira padrão visual do app** → **Tier-0** (Constituição UI v2 · ADR UI-0013). Por isso
**não foram criados neste PR**. O caminho canônico:

1. Nascem quando a **primeira tela que os consome** for portada (gabarito da ficha de frota / CRM 360),
   via **MWART** (ADR 0104) + **gate visual** (ADR 0107/0114) + [W] aprovar **screenshot**.
2. Aí entram no `@/Components/ui` (ou `Components/shared`) consumindo **só token** (`--accent`, `--pos/neg/warn`,
   `--origin-*`, `--stage-*`), claro/escuro de fábrica, sem `oklch` cru — e são **registrados** no
   `REGISTRY_DS_COMPONENTES.md` (vira "Onda" nova, como a Onda F).

> Enquanto não existirem, a régua deixa explícito que são **buraco do DS** (receita passo 5), não licença
> pra hand-rolar na tela.

## Dívida de token conhecida (não é deste PR)

`MercosulPlate.tsx` e o próprio showcase usam `oklch(...)` cru pro azul da faixa Mercosul (250). Não há
token de marca pra "azul Mercosul" — é cor institucional do veículo, não cor semântica do DS. Fica
catalogado aqui como dívida; tokenizar (ou aceitar como exceção institucional) é decisão [W] futura, fora
do escopo PR2.

---

**Refs:** PR #2165 (referência DS v6 landada) · PR #2170 (tokens `--stage-*`) · ADR UI-0013 (Constituição UI v2) ·
ADR 0235/0190 (roxo 295 canon) · ADR 0104 (MWART) · ADR 0107/0114 (gate visual) · `receita.html` (método 6 passos).
