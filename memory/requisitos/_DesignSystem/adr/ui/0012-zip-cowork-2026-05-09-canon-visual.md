---
id: requisitos-design-system-adr-ui-0012-zip-cowork-2026-05-09-canon-visual
---

> ⚰️ **SUPERSEDED em 2026-06-06 por [UI-0018](0018-canon-visual-vivo-ds-v6-manual-identidade.md).**
> Este zip-snapshot de 2026-05-09 **deixou de ser canon visual** — a verdade viva é DS v6 + primitivos
> (ADR 0253) + Manual de Identidade. Preservado como **histórico** (append-only); NÃO copiar.

# ADR UI-0012 · Zip Cowork 2026-05-09 atualiza canon visual; novas telas (vendas, clientes, financeiro, produto, produção, inventário)

- **Status**: superseded · lifecycle: substituido
- **Substituído por**: [UI-0018 — Canon visual vivo DS v6 + Manual](0018-canon-visual-vivo-ds-v6-manual-identidade.md)
- **Data**: 2026-05-09
- **Decisores**: Wagner, Claude
- **Categoria**: ui · estruturante
- **Refs**: [UI Kit Cowork 2026-05-09](../../ui_kits/cowork-2026-05-09/README.md), [_DS UI-0010 — precedente](0010-zip-cowork-2026-04-27-canon-visual.md), [_DS UI-0008](0008-cockpit-layout-mae-do-erp.md), [_DS UI-0009](0009-cockpit-sidebar-light-padrao.md), [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
- **Substitui parcialmente**: [_DS UI-0010 — Zip Cowork 2026-04-27](0010-zip-cowork-2026-04-27-canon-visual.md) onde overlap (mesmo arquivo, versão nova vence)
- **Convive com**: [_DS UI-0009](0009-cockpit-sidebar-light-padrao.md), [_DS UI-0011](0011-sidebar-single-pane-cascata-user-menu.md) — decisões posteriores ao snapshot sobrevivem (ver §3)

## Contexto

Em 2026-05-09 Wagner exportou novo zip do projeto Anthropic Cowork "Oimpresso ERP Comunicação Visual" (`(1).zip`, 1.1MB / 156 arquivos). Comparado com o zip 2026-04-27 ([UI-0010](0010-zip-cowork-2026-04-27-canon-visual.md)):

- **Mesmos canon globais** atualizados: `os-page.jsx`, `chat.jsx`, `tasks.jsx`, `sidebar.jsx`, `linked-apps.jsx`, `tweaks-panel.jsx`, `app.jsx` — pequenas refinamentos visuais
- **CSS expandido**: `styles.css` 120KB (vs 90KB em 2026-04-27, +30KB de classes pras telas novas)
- **Telas NOVAS** que não existiam em 2026-04-27:
  - `vendas-page.jsx` + `vendas-extras.jsx` (Sells/Create + Sells/Index — P0 da fila)
  - `clientes-page.jsx` (Cliente/Index)
  - `producao-page.jsx` (Repair/ProducaoOficina — já portada como referência)
  - `produto-app.jsx` (Produto/Unificado — Catálogo)
  - `financeiro-app.jsx` + `financeiro-telas-extras.jsx` (Financeiro/Unificado/Fluxo/Conciliacao/DRE/PlanoContas)
  - `prod-page.jsx`, `orc-page.jsx` (Produto + Orçamento)
- **HTMLs standalone novos**: `Inventario - Migracao Blade React.html` (29KB), `Produção Oficina - Tela.html` (46KB)
- **6 screenshots PNG** (~640KB total) — F2 evidence material

A mesma sessão Cowork também produziu o batch F3 Financeiro (`prototipo-ui-patch/Modules/Financeiro/Http/Controllers/*.php` + `.tsx`) que foi **rejeitado pré-merge** ([`LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md), [PR #365](https://github.com/wagnerra23/oimpresso.com/pull/365)) por 21 anti-padrões (Models inventados, tenant scope ausente, middleware fantasma, etc).

**Esta ADR só formaliza os assets visuais** (.jsx referência + HTML + CSS + screenshots). Os controllers e .tsx do batch F3 ficam fora — pinos visuais já estão em [`prototipo-ui/prototipos/financeiro-*/`](../../../../../prototipo-ui/prototipos/) ([PR #366](https://github.com/wagnerra23/oimpresso.com/pull/366)).

## Decisão

### 1. Zip Cowork 2026-05-09 atualiza canon visual

Snapshot importado integral em [`memory/requisitos/_DesignSystem/ui_kits/cowork-2026-05-09/`](../../ui_kits/cowork-2026-05-09/) (~40 arquivos: `.jsx` canon + telas novas + 5 HTMLs + `styles.css` 120KB + 6 PNGs).

**Substitui parcialmente UI-0010** onde o arquivo existe nas duas versões (preferir 2026-05-09). UI-0010 fica como referência histórica imutável.

### 2. Padrões canônicos expandidos

Lista UI-0010 §2 evolui pra cobrir telas novas:

| Arquivo do UI Kit | Padrão canônico de | Status atual no repo |
|---|---|---|
| `os-page.jsx` ⭐ | **Tela list+detail** (CRUD operacional) | já consumido |
| `tasks.jsx` | **Inbox unificada** master/detail | Pages/Tarefas (a criar) |
| `viewers.jsx` | **Viewer de tarefa** por tipo | a criar |
| `chat.jsx` | **Conversação** | Pages/Jana/Chat.tsx — charter live |
| `sidebar.jsx` | **Sidebar dual Chat/Menu** | já portado |
| `linked-apps.jsx` | **Coluna direita Apps Vinculados** | já portado |
| `tweaks-panel.jsx` | **Vibe/Densidade/Accent** | já portado |
| **`vendas-page.jsx` + `vendas-extras.jsx`** ⭐ NOVO | **Sells/Create + Sells/Index** | P0 — pino F1 em [`sells-create/`](../../../../../prototipo-ui/prototipos/sells-create/) |
| **`clientes-page.jsx`** ⭐ NOVO | **Cliente/Index** | P2 — sem charter ainda |
| **`producao-page.jsx`** ⭐ NOVO | **Repair/ProducaoOficina** | já em prod (referência refator visual) |
| **`produto-app.jsx` + `produto-data.jsx`** ⭐ NOVO | **Produto/Unificado** (Catálogo) | P2 — controller candidato existe (não copiar literal) |
| **`financeiro-app.jsx` + `financeiro-telas-extras.jsx`** ⭐ NOVO | **Financeiro/{Unificado,Fluxo,Conciliacao,DRE,PlanoContas}** | Unificado em prod; resto pinos F1 |
| **`Inventario - Migracao Blade React.html`** ⭐ NOVO (standalone 29KB) | **Inventário** (migração Blade→React) | sem charter — pino F1 em [`inventario-migracao/`](../../../../../prototipo-ui/prototipos/inventario-migracao/) |

### 3. Conflitos resolvidos (herda UI-0010 §3 + adições)

Decisões POSTERIORES ao snapshot 2026-05-09 sobrevivem (zip NÃO sobrescreve):

| Aspecto | Decisão repo | Justificativa |
|---|---|---|
| **Sidebar light** ([UI-0009](0009-cockpit-sidebar-light-padrao.md)) | branca | Wagner 2026-05-04, ratificado |
| **CSS escopado em `.cockpit{}`** | repo escopa | evita vazamento Site/Cms |
| **AppShell legado removido** | repo removeu | Cockpit é shell único |
| **Stack IA Vizra ADK** ([ADR 0048](../../../../decisions/0048-vizra-adk-rejected.md)) | rejeitado | decisão técnica |
| **F3 batch Financeiro** | rejeitado pré-merge ([LICOES](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)) | 21 anti-padrões |
| **`/financeiro/unificado`** ([#355](https://github.com/wagnerra23/oimpresso.com/pull/355), [#358](https://github.com/wagnerra23/oimpresso.com/pull/358)) | em prod com fixes | T-AP-4 — não sobrescrever |
| **`Modules\Produto` namespace** | UPOS canon (`App\Product` direto) | herdado UPOS v6 |
| **`Pages/Copiloto`** | renomeado pra `Pages/Jana` | sessão renomeação prévia |

### 4. Plano de portagem (atualiza UI-0010 §4)

Telas-alvo ranqueadas por prioridade da fila ([`TELAS_REVIEW_QUEUE.md`](../../../../../prototipo-ui/TELAS_REVIEW_QUEUE.md)):

**P0:**
1. **Sells/Create** + **Sells/Index** — `vendas-page.jsx` + `vendas-extras.jsx` referência. Pino F1 em [`sells-create/`](../../../../../prototipo-ui/prototipos/sells-create/) (deste PR). Wagner abre F0 em `COWORK_NOTES.md`.

**P1 (charter existente):**
2. **Repair/{Dashboard,JobSheet,Status}** — `producao-page.jsx` referência (`Repair/ProducaoOficina` já mergeado por loop)
3. **Financeiro/{ContasBancarias,Extrato}** — charters existem
4. **Financeiro/{Fluxo,PlanoContas,DRE,Conciliacao}** — pinos F1 em `prototipo-ui/prototipos/financeiro-*/`. Bloqueados em backend (ver READMEs).

**P2:**
5. **Cliente/Index** — `clientes-page.jsx` referência. Sem charter — criar antes.
6. **Produto/Unificado** — `produto-app.jsx` referência. Controller candidato em `prototipo-ui-patch/app/Http/Controllers/ProdutoUnificadoController.php` (NÃO copiar literal — usar como inspiração, ver T-AP-1).
7. **Inventário** — `Inventario - Migracao Blade React.html` (standalone). Sem charter. Pino F1 em [`inventario-migracao/`](../../../../../prototipo-ui/prototipos/inventario-migracao/).
8. **Orçamento** — `orc-page.jsx`.

Cada portagem em PR separado obedecendo loop F0→F4 ([ADR 0114](../../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)) com gate visual ([ADR 0107](../../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).

### 5. Lições do batch F3 rejeitado (pré-flight obrigatório)

**Antes de qualquer F3 que cite este UI Kit como referência:**

Ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 meta-anti-padrões + 15 técnicos catalogados. Pré-flight checklist obrigatório (Models reais, tenant scope, middleware UPOS canon, shape mapping, services existentes).

Skill `multi-tenant-patterns` (Tier A) e `mwart-comparative` (Tier A) já cobrem boa parte. Doc adiciona convenções pt-BR herdadas (Titulo, ContaBancaria, baixar() vs nomes inventados).

## Consequências

### Positivas

- **Canon visual atualizado** — telas novas (vendas, clientes, financeiro, produto, produção, inventário) ganham referência canônica
- **Pinos F1 alinhados** com canon — sells-create + inventario-migracao têm material visual pronto
- **Lições do batch rejeitado preservadas** — ADR linka pro doc institucional [PR #365](https://github.com/wagnerra23/oimpresso.com/pull/365), evitando reincidência
- **Screenshots como F2 evidence** — 6 PNGs cobrem aprovação visual sem precisar Wagner re-aprovar tela por tela
- **Onboarding novo dev** — UI Kit + ADRs UI-0010/0012 explicam evolução visual sem precisar abrir Cowork

### Negativas / mitigações

- **Snapshot envelhece** (igual UI-0010) — zip 2026-05-09 ficará obsoleto eventualmente. **Mitigação:** próxima ADR substitutiva criada quando novo zip chegar; `cowork-2026-05-09/` permanece read-only como histórico
- **Risco de regressão visual** ao portar tela usando este UI Kit como referência — mesmo padrão T-AP-4 que catalogamos. **Mitigação:** doc lições + cada portagem em PR separado com smoke visual
- **CSS 120KB pra portar gradual** (gap zip vs `cockpit.css` no repo). **Mitigação:** portar por escopo de tela com `.cockpit{}` mantido
- **Telas P2-P3 sem charter** (Cliente, Produto, Inventário, Orçamento) — UI Kit fornece visual mas charter precisa ser criado antes do F3. **Mitigação:** skill `charter-write` (dormente — S4) cobre

## Alternativas consideradas

- **Substituir UI-0010 inteiramente** — rejeitada: UI-0010 cita arquivos `cowork-2026-04-27/` que ainda valem como histórico; substituição parcial preserva ambos
- **Não importar telas novas** (vendas/clientes/etc) — rejeitada: perde valor de calibração visual; charter sem referência canônica vira chute
- **Importar só os controllers do `prototipo-ui-patch/`** — rejeitada categoricamente: 21 anti-padrões catalogados em [`LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
- **Importar `uploads/Design System/` (versão Cowork dos components/CSS)** — rejeitada: repo está MUITO à frente (17 components vs 13 do zip, AppShellV2 já portado, etc)

## Validação pendente

- [ ] Portagem da próxima tela (provável `Sells/Create` P0) usando `vendas-page.jsx` como canon
- [ ] CHANGELOG do `_DesignSystem` atualizado
- [ ] Screenshot comparativo zip ↔ repo em cada portagem que cite este UI Kit

---

**Última atualização:** 2026-05-09
