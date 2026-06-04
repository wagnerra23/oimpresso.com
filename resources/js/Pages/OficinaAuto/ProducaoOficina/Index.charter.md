---
page: /oficina-auto/producao
component: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-02"
parent_module: OficinaAuto
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0110-tipografia-canon-h1-subtitle
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
charter_version: 3
---

# Page Charter — /oficina-auto/producao (Produção / Kanban da Oficina)

> **Status:** live (V2 rica). Painel vivo do pátio — todos os veículos em produção num relance — com um **drawer que é o documento vivo da OS**, do check-in à entrega.
> **Personas:** Larissa (balcão, 1280px) · mecânico (tablet) · Wagner (governança).
> **Referência travada (design):** Shopmonkey (calma/polish) × Tekmetric (densidade/fluxo) × Linear (kanban) × Stripe Tax (split fiscal invisível). Nota [W] do design: **9.5**.
>
> **charter_version 3 (2026-06-02):** backfill de design [CC] que **trava o conceito** (drawer + modelo). Versão 2 (2026-05-26) pré-data a trava do drawer e a decisão de modelo abaixo. Co-vive com o Decision Register irmão [`Index.decisoes.md`](Index.decisoes.md) (o que está em movimento) e os casos de uso [`Index.casos.md`](Index.casos.md) (comportamento durável + aceite).
>
> ⚠️ **Divergência conhecida (dívida F3):** o código de produção hoje espelha o protótipo **caçamba** antigo (colunas `disponivel/locada/aguardando/manutencao/pronta`, vocabulário sub-vertical 4 mecânica pesada — Martinho biz=164 LIVE prod, [ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)). A **verdade de design** é o modelo **(A) reparo** (ver decisão abaixo). Convergir é tarefa **F3** — não regredir o existente até lá.

## Mission

Dar à oficina um painel Kanban tempo-real pra o gerente movimentar OS entre estágios via drag-drop (confirmação em transições críticas, [ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) `is_critical`), com placa Mercosul visual e um drawer que concentra todo o documento da OS.

## ⚠️ Modelo decidido por [W] 2026-06-02 — referência (A) reparo automotivo

> [W] 2026-06-02: **"referência é a A".** Decisão cravada — o kanban segue o modelo de **oficina de reparo automotivo** (boxes/elevadores, mecânico, diagnóstico, ETA, partsStatus). Não é mais default provisório. Opções B/C descartadas.

- Modelo canônico do kanban + drawer = **reparo**. Colunas-alvo: **Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto p/ retirar**.
- A produção real caçamba (Martinho · biz 164, CNAE 4520) roda hoje colunas `disponivel · locada · aguardando · manutencao · pronta`. **Consequência (A):** a caçamba **sobe** pro nível do protótipo de reparo na F3 — NÃO o contrário. Quando a F3 chegar, `ProducaoOficina/Index` adota colunas/cards/drawer de reparo; locação vira caso particular.
- [ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) marca a dívida técnica (`cacamba_locacao` legado → `mecanica_pesada_basculante`).

## 🔒 DRAWER — TRAVADO (canônico · anti-regressão · não redesenhar)

> [W] 2026-06-02: "Drawer está com os conteúdos perfeitos, trave nisso." A ORDEM e a PRESENÇA das seções abaixo são **contrato**. Mudanças só por novo OK explícito de [W] (registrar na trilha do tempo). Implementação atual: [`_components/ServiceOrderRichSheet.tsx`](_components/ServiceOrderRichSheet.tsx).

Ordem canônica das seções do `Drawer`:

1. **Header** — `OS #id · <etapa>` · modelo do veículo · cliente · botão **Editar** · fechar.
2. **Card Vendas×Oficina** *(só etapa = `pronto` E existe venda derivada)* — "esta OS gerou a venda #id"; grid **Total · Peças (NF-e) · Mão-de-obra (NFS-e)**; badges fiscais NF-e / NFS-e (ok/aguarda/erro); ações **Abrir venda ↗ · DANFE · DANFS-e · Ver no Caixa do dia**. Ponte oficial Oficina→Vendas (origin:"oficina" + osRef). _Impl 2026-06-04 (D-09): reuso do shared `VendaDerivadaCard`; acende com `venda_derivada != null`. Breakdown fiscal completo = wave futura._
3. **Hero do veículo** — Placa Mercosul · KM · Box · Mecânico · Valor.
4. **Sintoma reportado.**
5. **Vistoria Digital · DVI** — editor item a item, semáforo ok/atenção/reprovado, + "aprovar por WhatsApp". _Gap atual: existe `DviPhotoGrid` parcial; editor inline pendente._
6. **Aviso de aprovação** *(só etapa `pecas` + `approval`)* — "aguardando aprovação do cliente", botão Cobrar.
7. **Fotos & Laudo** — thumbs de entrada + adicionar foto.
8. **Peças & Mão de obra** — `ItemsEditor` (peça / mão de obra / terceiro), split por natureza.
9. **Checklist de etapa** — `StageGate`: bloqueia avanço enquanto a etapa não fecha (hoje via `ServiceOrderFsmActionPanel`).
10. **Linha do tempo** — eventos da OS com status done/now.
11. **Ações de rodapé** — Conversa cliente · Imprimir OS.

**Regras travadas:** seções acendem por contexto (não mostrar vazio); fiscal é split peça→NF-e / mão de obra→NFS-e e a tela **prepara**, não emite; abre por clique no card e fecha por backdrop/✕; scroll interno (header da OS fixo).

## 📋 Inventário de funções — placar [W]

> **Legenda:** ✅ aprovado (fica como está) · ⬜ a decidir/melhorar · 💡 ideia nova [CC] (proposta, ainda não no build). O debate de cada ⬜/💡 vive no Register [`Index.decisoes.md`](Index.decisoes.md) (anéis Avaliar→Testar→Adotar→Descartar). Item aprovado lá **grada pra cá como ✅**. IDs `D-NN` referenciam o Register.

### A. Quadro / Kanban
- [x] ✅ Colunas por etapa · [x] ✅ Card contextual por etapa · [x] ✅ Realce de OS urgente
- [ ] ⬜ Capacidade visível por coluna `D-03`
- [ ] 💡 Arrastar card entre colunas (drag-and-drop) `D-01` · 💡 Avançar etapa direto no card `D-02` · 💡 Alerta de prazo no topo da coluna `D-04`

### B. Cards
- [x] ✅ Placa Mercosul + veículo + KM + cliente + sintoma · [x] ✅ Mecânico · prazo · valor · countdown · [x] ✅ foto-tag + última atividade + StageGate mini
- [ ] ⬜ Foto real de entrada no card `D-08` · 💡 Cor da borda por SLA `D-04`

### C. KPIs
- [x] ✅ 6 KPIs (Recepção · Diagnóstico · Aguardando peças · Execução · Urgentes · Valor em curso)
- [ ] ⬜ KPI clicável = filtra o quadro `D-05` · 💡 Mini-tendência ↑/↓ vs. ontem

### D. Visões & Foco
- [x] ✅ 3 visões (Kanban · Lista · Grade) · [x] ✅ 3 focos (Etapa · Box · Mecânico)
- [ ] ⬜ Persistir visão/foco escolhido `D-06` · 💡 Visão Linha do tempo do dia

### E. Toolbar & Busca
- [x] ✅ Busca livre (placa/veículo/cliente/sintoma/#OS) + contador · [x] ✅ Filtro por box/elevador
- [ ] ⬜ Filtros combinados salvos como "vistas"

### F. Tweaks inline
- [x] ✅ Foco · Densidade · Pressão
- [ ] ⬜ Densidade "detalhe" revisada

### G. Ações de topo
- [x] ✅ Nova OS · Editar · Imprimir fila
- [ ] 💡 Atalhos de teclado (`N` nova OS · `/` busca · setas) `D-07`

### H. Drawer
- [x] ✅ **TRAVADO** — ver seção "🔒 DRAWER" acima (nota 9.5). Não redesenhar.

## Goals — Features (faz)

- AppShellV2 + topnav + `<PageHeader>` "Produção Oficina" + filtros.
- Kanban por stage FSM (`CacambaKanbanColumn`) — heading + count + total valor da coluna.
- Cards arrastáveis (`CacambaCard`) — placa Mercosul + veículo + cliente + tempo em estágio + flag overdue.
- Drag-drop @dnd-kit → `DragConfirmDialog` em transições críticas; transição padrão → ação imediata via `ExecuteStageActionService`.
- `KanbanDndProvider` isola contexto drag.
- Drawer detail (`ServiceOrderRichSheet`) ao clicar card — drawer travado (11 seções).
- Faixa de 6 KPIs.
- Busca livre + contador de resultados.
- Multi-tenant Tier 0 — colunas scopadas `business_id` ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

## Non-Goals — Features (NÃO faz)

- **NÃO emite nota** — split fiscal é preparado; emissão é listener backend.
- **NÃO é a Nova OS** (`ServiceOrders/Create`) — esta é a visão de pátio.
- **NÃO é POS / venda comercial** — sem bipe-código, sem Consumidor Final default, sem NFC-e.
- Real-time WebSocket Centrifugo (futuro V1 — [ADR 0058](../../../../../memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)).
- Edição de campos via Kanban (drawer ou navegar pra Edit). Bulk drag-drop (1 card por vez).

## UX Targets + Tests

- 1280px sem overflow horizontal; colunas rolam internamente.
- Drawer abre/fecha sem layout shift; seções na ordem canônica travada.
- p95 first-paint < 1.2s (até 100 cards / 5 colunas); drag < 16ms/frame (60fps); drop→update < 300ms.
- Placa Mercosul reconhecível < 50ms render.
- `valor em curso` = soma consistente (não parse frágil de string `R$` no destino real).
- Foco=Box mostra capacidade ocupada por box; Foco=Mecânico agrupa por responsável.
- design-critique ≥ 80 (F1.5) antes de F2.

### Anti-patterns
- Drag sem feedback visual (canon = shadow + ghost card).
- Drop em coluna inválida sem mensagem (canon = toast + revert otimista).
- Polling agressivo < 5s. Card overflow text (canon = truncate + tooltip placa completa).

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php)
- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php)
- Casos de uso UC-01..UC-10 em [`Index.casos.md`](Index.casos.md).

## Refs

- Design (Cowork): `oficina-page.{jsx,css}` · `oficina-forms.jsx` · `Oficina - Benchmark Estado da Arte.html`
- Irmãos no repo: [`Index.decisoes.md`](Index.decisoes.md) (Decision Register) · [`Index.casos.md`](Index.casos.md) (casos & aceite)
- [SPEC.md US-OFICINA-004 Kanban OS multi-item](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [producao-oficina-cacamba-visual-comparison.md](../../../../../memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md)
- Irmão de tela: `Oficina.charter.md` (Nova OS · `ServiceOrders/Create`) — NÃO confundir; este é a PRODUÇÃO/kanban.

## Evolução / trilha do tempo

- 2026-05-26 · charter_version 2 — live V0 caçamba (Martinho biz=164, sub-vertical 4 ADR 0194).
- 2026-06-02 · [CC] backfill de design (charter_version 3). **DRAWER travado por [W]** (nota 9.5). **[W] decidiu modelo (A) reparo automotivo** como referência; B/C descartadas; convergência caçamba→reparo é dívida F3.
- 2026-06-04 · [CC] sync do handoff de design → repo: charter v2→v3 alinhado ao design travado; Decision Register + casos importados como irmãos. Deltas de código (⬜/💡) permanecem gated por [W] no Register.
- 2026-06-04 · [CC] implementou (greenlight [W]) **D-09** (drawer §2 Vendas×Oficina via reuso `VendaDerivadaCard`) + **D-01/D-02** (feedback preditivo de arrasto + drawer-on-block + avançar pelo card). tsc/eslint sem erro novo. **Aguarda veredito VISUAL [W]** (gate MWART) pra os checkboxes 💡 D-01/D-02 e ✅ D-09 graduarem oficialmente. Full migração modelo-(A) segue F3.
