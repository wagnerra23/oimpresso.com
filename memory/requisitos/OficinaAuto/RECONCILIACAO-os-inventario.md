# Inventário de reconciliação — OS / Kanban da OficinaAuto

> 🛑 **ERRATA 2026-06-30 (musing-elion) — A CONCLUSÃO "C É DUPLICATA → DEPRECAR" ESTAVA ERRADA.**
> Ao começar a migração, o handler real `Sells/Index.tsx:1571` revelou: **`Repair/ProducaoOficina` (C) serve `Modules\Repair\Entities\JobSheet`** (vertical Repair genérico, `OS-{id}`), **NÃO** a OficinaAuto de veículo (`SO-{id}`). O Sells **já roteia certo por prefixo** (`OS-`→C/JobSheet · `SO-`→A/Board). **C NÃO é duplicata de A — são verticais diferentes. Deprecar C teria quebrado o Repair.**
> O ÚNICO bug real era um **mis-anchor**: o charter de C tinha `related_prototype: oficina-page.jsx` (o mockup de VEÍCULO da OficinaAuto) — corrigido: removido de C, `visual_source: oficina-page.jsx` posto no `ServiceOrders/Board` (A, parent OficinaAuto). Detector agora: `oficina-page → ServiceOrders/Board` (0 órfãos). **Migração CANCELADA. Decisão Tier 0 #2 (deprecar C) VOID.** A migração do C foi pega antes de qualquer dano pela disciplina de conferir o handler real antes de editar.
>
> **Status:** read-only, aguarda decisão Tier 0 de [W]. NÃO aplica nada.
> **Origem:** mapeamento do bundle Cowork ComVis (sessão musing-elion, 2026-06-30) — o mockup `oficina-os-page.jsx` (drawer de OS rico) que [W] aprovou **já está vivo**; mapear revelou duplicação de telas/charters pro mesmo conceito.
> **Pergunta que isto responde:** "vai arrumar o page charter?" → sim, e arrumar aqui = a reconciliação Tier 0 que o próprio `Os/Create.charter.md` registra como pendente.

## Os 4 caminhos sobrepostos (mesmo conceito: OS de veículo da OficinaAuto)

| # | Caminho | Páginas .tsx | Charter | Rota → Controller | Testes | Veredito |
|---|---|---|---|---|---|---|
| **A** | `OficinaAuto/ServiceOrders/` | Board, Create, Edit, Show (4) | 4× **live** | `/oficina-auto/ordens-servico*` → `ServiceOrderController` (módulo OficinaAuto) | **3** | ✅ **CANON** (live, rico, testado) |
| **B** | `OficinaAuto/ProducaoOficina/_components/` | — (7 componentes, sem página) | — | consumido por **A** (`Board.tsx`/`Create.tsx` importam `ServiceOrderRichSheet`) | via A | ✅ **CANON** (drawer rico do A) |
| **C** | `Repair/ProducaoOficina/` | Index (1) | 1× draft (status **em drift** — está wired+testado) | `/oficina-auto/producao-oficina` → `ProducaoOficinaController` (**módulo Repair**) | **1** | ⚠️ **DUPLICATA** (2º kanban, cross-module) |
| **D** | `OficinaAuto/Os/` | — (nenhuma; `Create.tsx` **não existe**) | 1× draft **ghost** | — (órfão) | **0** | ❌ **GHOST** (deprecar) |

### Detalhes que importam

- **O drawer de OS que [W] quer (mockup `oficina-os-page`) = o `ServiceOrderRichSheet.tsx` vivo** (B), consumido pelo `ServiceOrders/Board` (A). Mapeamento `os-drawer-build-map.md`: **11 de 13 partes já existem live**. Não é construir — é reconciliar.
- **O kanban do mockup (`oficina-page`) tem DOIS candidatos vivos:** o canon `ServiceOrders/Board` (A) **e** a duplicata `Repair/ProducaoOficina/Index` (C). O detector (`detectar-telas`) tinha latcheado em **C** (via `related_prototype` do charter de C) — alvo errado; o canon é **A**.
- **`OficinaAuto/Os/` (D)** = ghost puro: 0 testes, 0 .tsx, charter draft que **ele mesmo escreve em vermelho** que sobrepõe `ServiceOrders/Create` (A) e que "reconciliar é decisão Tier 0 de [W]; até lá fica draft".
- **C é cross-module:** módulo OficinaAuto roteia pra um controller do módulo Repair. Spread de responsabilidade.

## Débito cruzado (relacionado, NÃO confundir com a reconciliação)

- **23 ocorrências de `locada`/`locacao`/`cacamba` em código vivo** (`.tsx`/`.php`, fora de comentário) — resíduo da erradicação ADR 0265 (reparo, não locação). Já catalogado e rastreado em [`RUNBOOK-erradicacao-locacao.md`](RUNBOOK-erradicacao-locacao.md). Os mockups Cowork estão **limpos** disso; o débito é só no live. **Tratar separado** (não é parte desta reconciliação de telas, mas a reconciliação não pode reintroduzir).

## Recomendação (para [W] aprovar — zero-duplicação)

| Ação | Caminho | Detalhe | Risco |
|---|---|---|---|
| **Manter canon** | A + B | `ServiceOrders/` + `ProducaoOficina/_components` é o realizado, rico, testado | — |
| **Deprecar ghost** | D | `OficinaAuto/Os/Create.charter.md` → `lifecycle: historical` + ponteiro pro canon A. Sem código pra remover (não existe). | baixo (0 testes, órfão) |
| **Apontar detector pro canon** | — | `visual_source: oficina-os-page.jsx` no charter de `ServiceOrders/Show` (ou Create) + `visual_source: oficina-page.jsx` no `ServiceOrders/Board`. Tira o latch errado em C. | baixo |
| **DECIDIR o 2º kanban** | C | `Repair/ProducaoOficina/Index` está wired (`/oficina-auto/producao-oficina`) + testado. É **redundante** com `ServiceOrders/Board`? Se sim → deprecar (migrar a rota pro Board) + reconciliar status do charter. Se é view distinta → manter e corrigir só o status drift. | **médio — Tier 0 [W]** |

### Ganhos do mockup a colher no canon (do `kanban-producao-gap.md`, ADOTAR-PARCIAL)
Sem tocar valor/FSM: busca multi-campo (P) · faixa de 6 KPIs clicáveis por etapa (M) · view Lista (P) · placa Mercosul condicional (P). Aplicar **no canon A**, não na duplicata C.

## Decisões Tier 0 pendentes de [W]

1. **Deprecar o ghost `OficinaAuto/Os/`?** (recomendado: sim — é órfão, o charter já se declara conflito pendente).
2. **O 2º kanban `Repair/ProducaoOficina` (C) é redundante com `ServiceOrders/Board` (A)?** Se sim, deprecar C e unificar na rota do Board. Se é uma view com propósito próprio, manter e só corrigir o status do charter (drift draft→live).

## Decisões de [W] (2026-06-30, sessão musing-elion)

1. ✅ **Deprecar ghost `OficinaAuto/Os/`** — APLICADO. `Os/Create.charter.md` → `status: deprecated` + banner de decisão; `visual_source: oficina-os-page.jsx` em `ServiceOrders/Show.charter.md` → detector aponta o mockup pro canon (verificado: `oficina-os-page → ServiceOrders/Show.tsx`, 0 órfãos).
   - _Update 2026-07-09:_ charter deprecated **arquivado** — movido de `Pages/OficinaAuto/Os/Create.charter.md` → [`_arquivo/Os-Create.charter.md`](_arquivo/Os-Create.charter.md) (lápide L-22; pasta `Os/` removida das Pages). Motivo: promoção do IT2 (integrity-check §15) a duro — charter em Pages exige `.tsx` irmão vivo.
2. ✅ **C (`Repair/ProducaoOficina`) é redundante → deprecar** — DECIDIDO, plano abaixo (NÃO aplicado: mexe em rota/controller/Sells/teste → gate de paridade + go de [W]).

---

## Plano de migração — deprecar C (`Repair/ProducaoOficina`) → canon `ServiceOrders/Board`

> ⚠️ Toca **Sells** (ROTA LIVRE, 99% volume) via deep-links. NÃO aplicar sem o gate de paridade verde + smoke. Plano, não execução.

### O que C tem hoje (superfície a migrar)
- **Rotas:** `oficinaauto.producao-oficina` (`/oficina-auto/producao-oficina`) **+** `/repair/producao-oficina` (módulo Repair) — ambas → `ProducaoOficinaController` (módulo **Repair**).
- **Controller:** `index()` (render kanban) + **`move()`** (`POST /repair/producao-oficina/{id}/move` — drag-drop FSM) + `KanbanProductionService`.
- **Deep-links do Sells:** `SellsTabelaUnificada.tsx` e `VdSource.tsx` navegam pra `/repair/producao-oficina?os=OS-NNNN` e `#osRef`.
- **Teste:** `Modules/Repair/Tests/Feature/ProducaoOficinaTest.php` (4 casos: render, 5 colunas na ordem do charter, totals, mock 17 OS).
- **Página:** `Repair/ProducaoOficina/Index.tsx` (631ln) + charter (status drift: diz draft, está live).

### GATE DE PARIDADE — RODADO 2026-06-30 (read-only), resultado: **VERDE exceto 1 gap pequeno**
Board (A) está **À FRENTE** de C, não atrás:
- [x] **Drag-drop FSM** — ✅ Board tem `KanbanDndProvider` + drag via `ExecuteStageActionService` (gateway canônico ADR 0143), melhor que o `KanbanProductionService` do C. Teste `ServiceOrderBoardTest` caso #2 GUARD cobre "drag avança via ExecuteStageActionService + grava history".
- [x] **Colunas/totals/KPIs** — ✅ `ServiceOrderBoardTest` tem **9 casos** (seeder 6 etapas, colunas, KPIs Onda 1.5, multi-tenant Tier 0, smoke recepcao→pronto) — cobre e **excede** os 4 casos do teste de C.
- [x] **Abrir OS (drawer)** — ✅ Board já tem `openOsId` + `handleCardClick` (abre o `ServiceOrderRichSheet`).
- [ ] **Deep-link `?os=OS-NNNN`** — ⚠️ **ÚNICO GAP**. Board lê `?view=` da URL (`URLSearchParams`) mas **não** `?os=` pra auto-abrir uma OS. O Sells linka com `?os=`. Fix = espelhar a lógica do `?view=` → `setOpenOsId` no mount. **Pequeno** (a maquinaria `openOsId` já existe).

**Conclusão:** migração é MENOR que o temido. Não precisa reconstruir nada do C no Board — só **adicionar o deep-link `?os=`** ao Board (1 peça aditiva, MWART-governada pois é `.tsx` vivo) e repointar o Sells. O resto do C (controller `move()` + `KanbanProductionService`) é **redundante** com o caminho FSM canônico do Board → descartável após o redirect.

### Passos (só com gate verde + go de [W])
1. **Board ganha `?os=` deep-link** (se faltar) — abre o drawer da OS direto. Pest cobre.
2. **Repointar Sells** — `SellsTabelaUnificada.tsx` + `VdSource.tsx`: `/repair/producao-oficina?os=` → rota do Board (`/oficina-auto/ordens-servico/board?os=`). Smoke no Sells (biz=1, não biz=4).
3. **Rotas de C** → redirect 301 pro Board (preserva links externos/bookmarks) OU remover, conforme [W]. Manter o redirect ao menos 1 canary.
4. **Deprecar página + charter de C** — `Repair/ProducaoOficina/Index.charter.md` → `status: deprecated` + ponteiro; `Index.tsx` removido só após o redirect provar 0 tráfego perdido.
5. **Controller/Service** — `ProducaoOficinaController` + `KanbanProductionService`: remover se não usados em outro lugar (grep antes); senão, manter o que for compartilhado.
6. **Teste** — garantir que o teste do Board cobre os 4 casos de C; só então remover `ProducaoOficinaTest`.
7. **Detector** — `visual_source: oficina-page.jsx` no `ServiceOrders/Board.charter.md` + remover `related_prototype: oficina-page.jsx` do charter de C (tira o latch errado). Aí `oficina-page → ServiceOrders/Board`.

### Risco
**Médio.** Vetores: links do Sells (cliente 99% volume) quebrarem; `move()` FSM sem paridade no Board; remover controller usado por outra tela. Mitigação: gate de paridade + redirect-antes-de-remover + smoke biz=1 + canary. É migração MWART-like, não delete — provavelmente PR próprio (ou 2: "Board ganha paridade" → "deprecar C").

### Ganhos do mockup (`kanban-producao-gap.md`) — aplicar NO CANON Board, depois da migração
Busca multi-campo (P) · faixa 6 KPIs clicáveis (M) · view Lista (P) · placa Mercosul condicional (P). Sem tocar valor/FSM.

---

## Débito separado (não desta reconciliação)
23 ocorrências `locada`/`cacamba` no código vivo → [`RUNBOOK-erradicacao-locacao.md`](RUNBOOK-erradicacao-locacao.md). A migração do C **não pode reintroduzir** (ADR 0265). Tratar no fluxo próprio.
