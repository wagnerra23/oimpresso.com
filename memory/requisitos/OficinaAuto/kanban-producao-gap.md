# Gap — Kanban "Oficina Auto" (mockup Cowork × tela viva)

> **Fase 1 read-only** da skill `aplicar-prototipo`. Mapa do que o mockup propõe que a tela viva não tem.
> NÃO toca código. Veredito + esforço/risco por parte.
>
> - **Mockup (design aprovado Wagner):** `_cowork-handoff-staging/.../project/oficina-page.jsx` (1296ln) + `oficina-forms.jsx` (702ln) + `oficina-fila.jsx` (302ln) + `oficina-page.css`
> - **Tela viva (alvo):** `resources/js/Pages/Repair/ProducaoOficina/Index.tsx` (631ln) + `Index.charter.md`
> - **Data:** mock da Oficina vive INLINE no `oficina-page.jsx` (`OS_LIST`, `STAGES`, `RECURSOS`, `MECANICOS`). O `data-os.jsx` lido é da **ComunicacaoVisual/gráfica** (stages rascunho→orçado→arte→produção…), NÃO da Oficina — não é shape relevante aqui.

---

## ⚠️ Restrições Tier 0 que enquadram TODO este gap

- **ADR 0265 — Oficina é REPARO/MECÂNICA.** O mockup é 100% domínio reparo (recepção→diagnóstico→peças→execução→pronto, veículo, placa, KM, mecânico, DVI). **ZERO traço de locação/caçamba** no mockup — limpo. `order_type ∈ {manutencao, mecanica}`. Nada a erradicar aqui.
- **Multi-tenant.** A tela viva já é shared multi-vertical (vocabulário genérico `code/item/usage_meter/executor/slot/area` + `label_overrides` + `slot_config` vindos do Controller — charter US-REPA-002). O mockup é **automotivo hardcoded** (placa/veh/km/mecanico/box/elevador). **Adotar o mockup ao pé da letra REGRIDE a tela viva** e quebra o CI guard `repair-shared-vocab.yml` (Non-Goal explícito do charter). Qualquer adoção tem de passar pela camada genérica + labels, não hardcodar automotivo.
- **FSM via `ExecuteStageActionService` (ADR 0143).** O mockup avança etapa por `setOsList(... stage: next)` (estado React local, mock). A tela viva já faz `router.post('/{id}/move')`. Avanço real = action FSM, NUNCA `UPDATE current_stage_id` direto.
- **Regra mestre VALOR/ESTOQUE.** Drawer do mockup edita peças+MO, DVI com `valor`, total de orçamento, "Autoriz. preliminar (R$)", gate de aprovação com total. **Tudo isso toca valor** — aqui só descrevo o visual; implementar exige dupla confirmação + apresentar antes→depois (fora do escopo desta Fase 1).

---

## Inventário por PARTE

### 1) Header + título
- **Mockup:** `<h1>Oficina Auto</h1>` + subtítulo "Recepção, diagnóstico, peças, execução e entrega de veículos" + 2 botões à direita: **Imprimir fila** + **Nova OS** (primary).
- **Vivo:** sem header próprio com título/subtítulo nesta página (entra dentro do AppShellV2 + topnav Repair). Não tem botão "Nova OS" (charter Non-Goal: CRUD vai pra `/repair/job-sheet`) nem "Imprimir fila".
- **Gap real (candidato):** título+subtítulo da página (P) — barato, melhora orientação. "Nova OS" e "Imprimir fila" = ver partes 6 e 8.
- **Esforço P · Risco baixo** (título/subtítulo).

### 2) KPIs (faixa de cartões clicáveis)
- **Mockup:** 6 KPIs — Recepção · Em diagnóstico · Aguardando peças (sub "N aguardam OK do cliente") · Em execução · **Urgentes** · **Valor em curso** (faturamento previsto). KPI é **clicável** e filtra o kanban (`kpiFilter`), com estado ativo visual + "limpar filtro".
- **Vivo:** NÃO tem faixa de KPIs. Só um contador discreto no canto da filter-bar ("X OS · Y aguardando aprovação").
- **Gap real (candidato — forte):** os 6 KPIs como resumo do dia + filtro-por-KPI são o maior salto de leitura da tela. **Urgentes** e os contadores por etapa derivam fácil das colunas. **"Valor em curso" ⚠️ toca valor (regra mestre — dupla confirmação)** — é soma de orçamentos previstos; precisa fonte de verdade no backend, não somar string no front.
- **Esforço M · Risco médio** (o KPI de valor puxa pra regra mestre; os outros 5 são contagem pura).

### 3) Filtros — boxes/elevadores
- **Mockup:** aba "Todos os boxes" + 1 botão por recurso (Box 1-3, Elevador 1-2) com dot colorido + contagem (`prod-equip-filters`). Filtro único de recurso.
- **Vivo:** filtro JÁ EXISTE e é **mais genérico/poderoso** — `FilterChips` por `slot_config` (N grupos: Box, Elevador, ou Mesa/Bancada/Máquina conforme vertical), "Todos" + "Limpar filtros" + contador "X de Y".
- **Vivo à frente (mockup stale nesta parte):** o mockup hardcoda box/elevador automotivo; o vivo já parametriza por vertical. Adotar o visual do mockup é OK (estética de chips), mas a **lógica do vivo é superior** — manter `slot_config`/`label_overrides`.
- **Esforço — (não adotar lógica; no máximo reskin de chips) · Risco: alto se hardcodar (quebra guard).**

### 4) Busca livre
- **Mockup:** input de busca "placa · veículo · cliente · sintoma · #OS" filtrando todas as views (`query`), com contador de resultados + limpar.
- **Vivo:** NÃO tem busca textual — só filtros de slot.
- **Gap real (candidato — forte):** busca textual multi-campo é ganho direto de operação (achar uma OS rápido). No vivo seria sobre os campos genéricos (`code`/`item`/`brand`/`executor` + sintoma se exposto).
- **Esforço P-M · Risco baixo** (client-side sobre props já carregadas).

### 5) Toggles de view (Kanban / Lista / Grade / Fila)
- **Mockup:** 4 views — **Kanban** (5 colunas), **Lista** (tabela OS/placa/veículo/cliente/etapa/box/mecânico/prazo/valor), **Grade** (matriz veículo × serviço com semáforo heurístico por sintoma), **Fila** (master-detail: lista esquerda + detalhe inline centro + rail "Apps vinculados" OS/CRM/WhatsApp à direita — `oficina-fila.jsx`). Também um popover "Visão" com **Foco** (Etapa/Box/Mecânico — repivota as colunas) + Densidade + Pressão.
- **Vivo:** SÓ Kanban. Sem Lista, Grade, Fila, nem popover de Foco/Densidade.
- **Gap real (candidato):**
  - **Lista** — P, ganho claro (tabela densa). ⚠️ coluna Valor toca valor (só exibir, vem do backend).
  - **Foco Box/Mecânico (repivot)** — M, útil pra oficina (ver carga por mecânico/box). Reaproveita a camada `slot_config`/`executor` genérica.
  - **Fila (master-detail + rail Apps vinculados)** — G, é praticamente uma tela nova; rail CRM/WhatsApp depende de integração real. Adiar.
  - **Grade (matriz serviço)** — M-G, a heurística sintoma→serviço do mockup é mock frágil; precisa de catálogo de serviços real. Adiar.
  - **Densidade/Pressão** — P cosmético, baixa prioridade.
- **Esforço: Lista P · Foco M · Fila G · Grade M-G · Risco baixo-médio** (Fila/Grade são as caras).

### 6) Colunas + cards de OS
- **Mockup:** 5 colunas (Recepção/Diagnóstico/Aguardando peças/Em execução/Pronto p/ retirar). Cards **ricos e específicos por etapa** (`CardRecepcao`/`CardDiagnostico`/`CardPecas`/`CardExecucao`/`CardPronto`):
  - **Placa Mercosul** estilizada (faixa "BR · MERCOSUL") + veículo + KM + cliente
  - Sintoma reportado
  - Badge de recurso (Box/Elevador), avatar+nome do mecânico
  - Barra de progresso % (diagnóstico/execução), ETA ("ETA diag", "resta 2h40")
  - **Badge gate FSM "lock N/M"** (`StageGateMini` — quantos requisitos faltam pra avançar) — o "2/5 1/4" do enunciado
  - Status de peças (Peças OK / Encomendado / Aguardando aprovação) + label
  - Countdown urgente (T-Xh), strip vermelha de urgência
  - Botões inline por etapa: Triagem→ / Iniciar→ / Cobrar OK / Entregar→
  - `CardExtra`: thumb de foto + "últ. atividade"
- **Vivo:** 1 card único genérico (`JobCard`) — `code` mono (placa/nº), `item · brand`, `usage` (KM via `formatUsage`), badge slot/area, executor+iniciais+ETA, banner amber `pending_approval` + total do orçamento. Drag-and-drop nativo entre colunas (otimistic + POST `/move`). Empty state por coluna.
- **Gap real (candidato):**
  - **Placa Mercosul estilizada** — P, charmoso e específico; mas é automotivo → entra como *render opcional quando `label_overrides.code==='Placa'`*, não hardcode.
  - **Badge gate "lock N/M"** — M-G, é o conceito mais forte do mockup (checklist de bloqueio por etapa), mas **NÃO existe no backend FSM hoje** (o gate do mockup é localStorage + heurística mock). Casaria com FSM/`sale_stage_actions` se houver requisitos por stage. Decisão de domínio + backend. Adiar/escopo próprio.
  - **Barra de progresso % + ETA** — M, depende de dado real (não há % no shape vivo). Adiar.
  - **Botões de avanço inline no card** — M, hoje só drag-drop; botão por etapa acelera. Avanço tem de passar por FSM action.
  - **Cards por-etapa especializados** — médio reskin; o vivo é mono-card. Ganho de leitura real.
- **Esforço: placa P · gate G · progresso/botões M · Risco: médio** (gate e avanço tocam FSM — alto se feito errado).

### 7) Drawer (detalhe da OS)
- **Mockup:** drawer riquíssimo — header com Editar; **card "Esta OS gerou a venda #V" (Vendas×Oficina)** com breakdown peças/serviço + badges fiscais NF-e/NFS-e + CTAs; card do veículo (placa/KM/box/mecânico/valor); sintoma; **DVI editável (Vistoria Digital)** com semáforo ok/atenção/crítico + valor por item + **gate de aprovação** (none→pending→approved/declined via WhatsApp); **Fotos & Laudo** com upload/drag/lightbox/legenda; **Peças & Mão de obra editável** (ItemsEditor CRUD inline); **Checklist de etapa (StageGate)**; **Linha do tempo FSM**; ações Conversa cliente / Imprimir OS.
- **Vivo:** drawer enxuto (read-only) — header code+slot+item+brand; banner aprovação (Reenviar); **`VendaDerivadaCard`** (Onda 5/FASE B — JÁ tem breakdown peças/serviço + badge fiscal NF-e + lista expandível! cross-módulo shared); sintoma (texto fixo mock); fotos & laudo (grid estático placeholder); itens sugeridos (PriceRow estático); linha do tempo (estática). **Sem edição inline de nada** (Non-Goal explícito do charter: edição vai pra JobSheet).
- **Gap real (candidato) + sinal de stale:**
  - **VendaDerivadaCard** — *vivo À FRENTE* do mockup numa coisa: já é componente shared real (`@/Components/shared/VendaDerivadaCard`), o mockup é mock inline. Não regredir.
  - **DVI (Vistoria Digital) com semáforo** — G, conceito forte de oficina, **não existe no vivo nem no domínio**. ⚠️ toca valor (valor por item). Backend + decisão de domínio. Escopo próprio.
  - **Fotos & Laudo com upload real + lightbox** — M-G; vivo é placeholder estático. Upload real = `Modules/Arquivos` (o próprio mockup marca como "F3"). Adiar.
  - **Edição inline de peças/MO + gate de aprovação** — colide com Non-Goal do charter ("edição vai pra JobSheet", "aprovação não via esta tela"). ⚠️ toca valor. **NÃO adotar sem Wagner reabrir o Non-Goal.**
  - **Linha do tempo real (FSM)** — M, hoje é estática; ligar no `sale_stage_history` daria timeline auditável de verdade.
- **Esforço: timeline real M · DVI/Fotos/gate G · Risco: alto** (edição+valor+Non-Goal).

### 8) Impressão de fila
- **Mockup:** botão "Imprimir fila" (header) + "Imprimir OS" (drawer/fila) via `window.OficinaPrint.printFila/printOS` (helper não incluído no bundle lido — `oficina-print.js` referenciado mas ausente).
- **Vivo:** sem impressão.
- **Gap real (candidato):** impressão da fila/OS é pedido clássico de oficina (ordem de serviço em papel pro mecânico/cliente). Helper de print NÃO veio no handoff — _pendente_ a fonte do `OficinaPrint`.
- **Esforço M · Risco baixo** (mas depende de artefato ausente).

---

## Veredito: **ADOTAR-PARCIAL**

O mockup está à frente em **densidade de informação e leitura operacional**, mas **atrás** na arquitetura: é automotivo-hardcoded + mock localStorage, enquanto a tela viva já tem a camada genérica multi-vertical, drag-drop FSM real e o `VendaDerivadaCard` shared. **Adotar o visual, NÃO a arquitetura.** Várias peças "ricas" (DVI, gate lock, fotos-upload, edição inline) tocam **valor** e/ou **domínio/FSM/Non-Goal do charter** — exigem decisão de Wagner + backend, não cabem num import visual.

**Não regredir (vivo à frente):** `slot_config`/`label_overrides` genéricos · `VendaDerivadaCard` shared real · drag-drop com POST `/move` · guard de vocabulário shared.

**Adotar (ordem por ROI · barato→caro, sem tocar valor/FSM):**
1. **Busca textual multi-campo** (parte 4) — P, ganho direto, client-side.
2. **Faixa de KPIs por etapa + Urgentes** (parte 2, exceto "Valor em curso") — M, maior salto de leitura; filtro-por-KPI.
3. **View Lista** (tabela densa) + **título/subtítulo** da página (partes 5,1) — P.
4. **Placa Mercosul estilizada** condicional a `label_overrides.code==='Placa'` (parte 6) — P, charme sem hardcode.

**Adiar / escopo próprio (toca valor, FSM ou domínio — exige Wagner + backend):** KPI "Valor em curso" · gate "lock N/M" + checklist por etapa · DVI com semáforo+valor · edição inline peças/MO + gate aprovação (colide com Non-Goal) · Fotos upload real (`Modules/Arquivos`) · barra progresso/ETA · view Fila (rail CRM/WhatsApp) · view Grade · impressão (helper `OficinaPrint` _ausente_ no handoff) · Foco Box/Mecânico (repivot).
