# PageHeader Canon — Matriz de Diferenças Técnicas e Pontos Permitidos

> Referência canônica de **o que DEVE ser igual** e **o que PODE variar** entre telas que adotam o pattern do header `os-page-h` da ADR 0180/0182. Use esta matriz como checklist de revisão de PR e fonte da skill `pageheader-canon`.

**Origem:** Wagner 2026-05-21 review smoke prod das 12 telas Financeiro → pediu matriz + skill pra padronização escalável.

---

## Dimensões fixas (IDÊNTICAS em todas as telas — drift = bug)

| # | Dimensão | Valor canon | Source of truth |
|---|---|---|---|
| F1 | Estrutura 3 zonas | L (`os-page-h-l` título+sub) · C (`os-page-h-r` ghosts+overflow) · R (`os-page-h-r` primary) | [ADR 0182](../../decisions/0182-pageheadertabs-canon-pattern-telas.md) |
| F2 | Componente ghost tabs | `<{Modulo}SubNav active="X" hidePrimary extraOverflowItems={[]}/>` | Wrapper por módulo em `_shared/` |
| F3 | ARIA tablist | `role="tablist"` + cada ghost `role="tab"` + `aria-selected` | `PageHeaderTabs.tsx` |
| F4 | Keyboard nav | ArrowLeft/Right/Home/End wrap-around | `PageHeaderTabs.tsx` |
| F5 | Overflow `⋯ Mais` | `DropdownMenu` shadcn quando ghosts > maxVisible OU extraOverflowItems > 0 | `PageHeaderTabs.tsx` |
| F6 | Ghost ativo **sempre visível** | Auto-promoção pra index < maxVisible mesmo se declarado depois | `PageHeaderTabs.tsx` (ADR 0182 patch 2026-05-21) |
| F7 | Hue OKLCH per-grupo | financas=145 · vender=60 · operar=350 · pessoas=295 · sistema=200 · ia=220 · atendimento=30 · equipe=270 | `cockpit/shared.ts SIDEBAR_GROUP_HUE` |
| F8 | Primary cor | `oklch(0.55 0.15 {hue_grupo})` — verde 145 pra Financeiro | `FinanceiroPrimaryButton.tsx` ou inline style canon |
| F9 | Labels CURTOS | 1-2 palavras (verbo ou substantivo único) — não "Contas a X" | DataController `data['ghosts']['label']` |
| F10 | Tipografia título h1 | `text-xl md:text-2xl font-semibold tracking-tight` | [ADR 0110](../../decisions/0110-tipografia-canon-h1-subtitle.md) |
| F11 | Multi-tenant Tier 0 | `shell.menu` filtra por `business_id`; SubNav retorna null se módulo desinstalado | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) |
| F12 | Botões duplicados-com-ghost | **proibido** — botão que navega pra outra tela que já é ghost = remover | ADR 0182 |

---

## Dimensões variáveis (PODE diferir entre telas — caso-a-caso)

| # | Dimensão | Permitido | Padrão recomendado |
|---|---|---|---|
| V1 | Label do primary | Contextual à tela (verbo ação): "Novo título" / "Nova categoria" / "Importar OFX" / "Receber" / "Pagar" | Sempre verbo de ação canônico do domínio |
| V2 | Existência de primary | Opcional — telas read-only (Fluxo / Relatórios) podem omitir | Telas CRUD/workflow têm; visualização pura omite |
| V3 | Tipo do elemento primary | `<button>` (default) OU `<a href>` (caso de link externo) OU `<button type="submit">` (form upload) | `<button>` via `FinanceiroPrimaryButton` componente |
| V4 | `extraOverflowItems` | 0 a N ações features-específicas (Resumir mês / Apresentar / Exportar / OCR / etc) | Telas com ações features ricas (Unificado/DRE/Cobrança); outras 0 |
| V5 | Ícone do primary | `<Plus/>` (default componente) OU outro (`<Upload/>` em Conciliação) | Default `<Plus/>`; override pra workflows não-create |
| V6 | maxVisible (ghosts inline) | 4-6 (default 5) | 5 — cabe Larissa 1280px |
| V7 | Subtítulo do título | Texto curto contextualizando (período / business / total) | Padrão `os-page-h-l p` |
| V8 | Modal/Sheet abertas via `extraOverflowItems` | Tela define handlers próprios (setNovaOpen / setResumoOpen / etc) | onClick stateful no Index.tsx |
| V9 | Filtros/KPIs abaixo do header | Layout livre pós-header (cards / filtros / tabela) | Cada tela define conforme persona |

---

## Matriz por tela (Financeiro — estado atual)

| Tela | Label canon | Active visível? | Primary | Cor primary | extraOverflowItems |
|---|---|---|---|---|---|
| Unificado | Financeiro | ✅ | ✅ "Novo título" | hue 145 ✅ | 7 (Buscar/Resumir/Fechamento/Apresentar/Imprimir/Exportar/OCR) |
| Contas a Receber | Receber | ✅ | ✅ "Novo recebimento" | hue 145 ✅ | 0 |
| Contas a Pagar | Pagar | ✅ | ✅ "Novo pagamento" | hue 145 ✅ | 0 |
| Fluxo de Caixa | Fluxo | ✅ | ⚠️ omitido (read-only) | — | 0 |
| Cobrança | Cobrança | ✅ | ✅ "Nova cobrança" | hue 145 ✅ | 3 (Resumir/Gateways/Remessa) |
| Caixa do turno | Caixa | ⚠️ 500 prod | — | — | — |
| Conciliação | Conciliação | ✅ (auto-promove F6) | ✅ "Importar OFX" (submit form) | hue 145 ✅ | 0 |
| DRE | DRE | ✅ (auto-promove F6) | ✅ "Novo lançamento" | hue 145 ✅ | 5 (Buscar/Resumir/Fechamento/Apresentar/Exportar) |
| Relatórios | Relatórios | ✅ (auto-promove F6) | ⚠️ omitido (read-only) | — | 1 (Exportar CSV) |
| Contas Bancárias | Bancos | ✅ (auto-promove F6) | ✅ "Nova conta" (`<a href>`) | hue 145 ✅ | 1 (Gateways) |
| Plano de Contas | Plano | ✅ (auto-promove F6) | ✅ "Nova conta" | hue 145 ✅ | 0 |
| Categorias | Categorias | ✅ (auto-promove F6) | ✅ "Nova categoria" | hue 145 ✅ | 0 |
| Contador | Contador | ✅ (auto-promove F6) | (tela config, no header próprio) | — | — |

**Score consistência:** 11/13 telas com pattern 100% canon · 1 tela 500 prod (Caixa) · 1 tela config (Contador)

---

## Pontos de revisão de PR (checklist canon)

Pra cada PR que tocar tela com sub-navegação, reviewer DEVE verificar:

- [ ] **F1**: header tem 3 zonas (`os-page-h-l` + `os-page-h-r` com ghosts/overflow + primary direita)?
- [ ] **F2**: usa `<{Modulo}SubNav active="X"/>` (não inline JSX)?
- [ ] **F6**: ghost ativo aparece inline (não no overflow)?
- [ ] **F8**: primary cor harmônica com hue do grupo (não magenta canon UPOS default)?
- [ ] **F9**: labels dos ghosts são curtos (≤2 palavras)?
- [ ] **F12**: botões inline NÃO duplicam com ghosts (Conciliar/Plano de contas removidos quando ghost cobre)?
- [ ] **V1-V5**: variações documentadas (primary opcional pra read-only OK; tipo do elemento OK)?
- [ ] **F11**: SubNav retorna null se tenant sem módulo (Multi-tenant Tier 0)?

---

## Refs

- [ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3 5 grupos canon
- [ADR 0182](../../decisions/0182-pageheadertabs-canon-pattern-telas.md) — PageHeaderTabs canon pattern 3 zonas
- [ADR 0110](../../decisions/0110-tipografia-canon-h1-subtitle.md) — Tipografia h1 + subtitle
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant
- [Skill `pageheader-canon`](../../../.claude/skills/pageheader-canon/SKILL.md) — aplicação automatizada
- PRs Fase 5 Financeiro: #1363/#1364/#1365/#1366/#1367/#1368/#1369
- Wagner reviews 2026-05-21 (revisão visual prod 12 telas)
