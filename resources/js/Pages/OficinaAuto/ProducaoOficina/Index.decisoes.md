---
register: Produção / Kanban da Oficina · /oficina-auto/producao
irmao_charter: Index.charter.md
tecnica: Decision Register (anéis estilo Technology Radar — Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: "2026-06-02"
---

# Decision Register — Produção / Kanban da Oficina

> **Importado do handoff de design (Cowork) 2026-06-04.** Refs de build (`oficina-page.jsx`/`.css`) são do **protótipo de design**, não do código de produção — o código real vive em [`Index.tsx`](Index.tsx) + [`_components/`](_components). Nada aqui está graduado: deltas seguem **gated por [W]**.

> **O chão de debate da tela.** Aqui vivem as opções ainda sendo discutidas/testadas. O **charter** guarda só o que já fechou; este arquivo guarda o que está **em movimento**.
>
> **Ciclo de vida (anéis):**
> - 🔍 **AVALIAR** — ideia levantada, ainda não testada.
> - 🧪 **TESTAR** — [CC] protótipou; [W] está experimentando/decidindo.
> - ✅ **ADOTAR** — [W] aprovou → **grada pro charter** como ✅ e sai daqui.
> - ⛔ **DESCARTAR** — reprovado → vira anti-pattern no charter.
>
> **Como [W] usa:** mexe no campo `estado:` de cada item, ou escreve em `nota [W]:`.

---

## D-01 · Arrastar para avançar, com o gate como guarda
- **estado:** ✅ **ADOTADO 2026-06-04** — portado pra produção (`Index.tsx` + `KanbanDndProvider`/`CacambaKanbanColumn`), mergeado (PR #2228 · aabe70f4b). Veredito visual **delegado a [CC] por [W]** ("resolva, não é pergunta pra mim" 2026-06-04) → confirmado via render fiel dos componentes (`_preview/oficina-veredito.html` + screenshot) + gates visuais de CI verdes (PR UI Judge + visual-regression). Pendência menor: conferir no app LIVE com dado real quando deployar.
- **impl produção 2026-06-04:** feedback preditivo na coluna sob o cursor (verde "solte p/ avançar" / âmbar "abre detalhes") via `useKanbanDragState` + `evaluateDrop` (reusa `resolveDragMapping` — mesma máquina do drop) · em drop **bloqueado** o drawer abre no documento da OS (em vez de só toast). Confirm dialog mantido em transições críticas (ADR 0143 `is_critical`) — não removi a rede de segurança em LIVE prod (Martinho biz=164).
- **prioridade:** alta (é a "ideia melhor de interação" 2026-06-02)
- **contexto:** hoje avançar etapa = clicar card → abrir drawer → usar StageGate. Lento pro caminho feliz.
- **opção proposta:** arrastar o card pra próxima coluna = intenção de avançar; no *drop*, o StageGate valida. Gate ok → avança sem abrir nada. Gate falha → card volta e o drawer abre já no checklist do que falta.
- **escopo travado:** só ativo no **foco=Etapa**. **Drawer travado intacto** — só abre quando o gate barra.
- **TESTE FUNCIONAL (painel [W], 2026-06-02):** ✅ render 5 colunas, 12 cards arrastáveis · ✅ roteamento gate.next correto · ✅ caminho bloqueado (OS 8804 gate 3/4 → abre drawer no checklist) · ✅ caminho feliz (4/4 → avança) · ✅ zero regressão no drawer travado.
- **nota:** 1ª passada 8/10 → REFINO 2ª passada 9/10 (extraído `tryAdvance(os)`: arrasto E botões do card usam a MESMA porta gate-guardada — funde D-02; feedback preditivo na coluna-alvo verde/âmbar).
- **achados (refino antes de ✅):** (1) etapa terminal "Pronto" → "Entregar" é botão, não coluna; (2) touch/tablet: D-02 deixa de ser opcional; (3) verificação limitada ao painel [W] (iframe não roda host).
- **nota [W]:** _(vazio — veredito: ✅ adotar / 🧪 continuar / ⛔)_

## D-02 · Botão "→ próxima etapa" no próprio card
- **estado:** ✅ **ADOTADO 2026-06-04** (junto com D-01) — veredito visual delegado a [CC] por [W], confirmado via render + CI verde.
- **contexto:** obrigatório pra touch (mecânico no tablet, onde arrasto falha).
- **impl produção 2026-06-04:** "uma máquina, duas portas" — os botões de ação do `CacambaCard` (Iniciar/Recolher/Concluir/Entregar) disparam o MESMO `handleDragMove` do arrasto via `onAdvance` + mapa `NEXT_COLUMN_FOR`. "Acompanhar" (locada) segue abrindo o drawer (é ver, não avançar).
- **nota [W]:** _(vazio — junto com D-01)_

## D-03 · Capacidade visível em todas as colunas
- **estado:** 🔍 AVALIAR
- **contexto:** hoje só "Em execução" mostra X/5 boxes. Estender? Dúvida: Recepção/Pronto não têm capacidade física.
- **nota [W]:** _(vazio)_

## D-04 · Borda do card por SLA (verde/âmbar/vermelho)
- **estado:** 🔍 AVALIAR
- **contexto:** hoje urgência é booleano (tira vermelha). Trocar por gradiente de prazo? Risco: ruído visual vs. calma "Shopmonkey". Testar no modo Pressão.
- **nota [W]:** _(vazio)_

## D-05 · KPI clicável filtra o quadro
- **estado:** 🔍 AVALIAR
- **contexto:** os 6 KPIs são só leitura. KPI vira filtro de 1 clique (toggle)?
- **nota [W]:** _(vazio)_

## D-06 · Persistir visão/foco escolhido
- **estado:** 🔍 AVALIAR
- **contexto:** ao voltar volta no default (Etapa/Kanban). Lembrar a última escolha? localStorage no protótipo; preferência de usuário no real.
- **nota [W]:** _(vazio)_

## D-07 · Atalhos de teclado (N / barra / setas)
- **estado:** 🔍 AVALIAR
- **contexto:** Larissa é teclado-first. `N` nova OS · `/` foca busca · setas navegam · Enter abre.
- **nota [W]:** _(vazio)_

## D-08 · Foto real de entrada no card
- **estado:** 🔍 AVALIAR
- **contexto:** card mostra tag textual. Trocar por thumbnail real do check-in? Risco: densidade a 1280px; talvez só no modo "Detalhe".
- **nota [W]:** _(vazio)_

## D-09 · Card Vendas×Oficina no drawer (seção 2 travada — gap de impl)
- **estado:** ✅ **RESOLVIDO 2026-06-04** (lacuna de implementação fechada · aguarda veredito visual [W]).
- **contexto:** drawer travado prevê o card Vendas×Oficina quando etapa=pronto + venda derivada (split NF-e/NFS-e + ações fiscais). `ServiceOrderRichSheet` não renderizava.
- **impl produção 2026-06-04:** **reuso** do componente shared órfão `resources/js/Components/shared/VendaDerivadaCard.tsx` (Total · Data · breakdown peças/serviços · badge fiscal NF-e · CTAs Abrir/Imprimir/Compartilhar). Backend já servia `venda_derivada` (`ServiceOrderController::show` → `shapeVendaDerivada`, ADR 0192) — só faltava plugar no drawer. Renderiza como §2 (ordem travada), acende só quando `venda_derivada != null`. Sem backend novo. Empty-states fiscais/breakdown tolerantes (Fase B opcional).
- **dep:** venda derivada (origin:"oficina" + osRef · [ADR 0192](../../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)). Breakdown items_list/fiscal NF-e completo = wave futura (paridade `Modules/Repair buildVendaDerivadaPayload`).
- **nota [W]:** _(vazio — confirmar visual numa OS pronta com venda)_

---

## Graduados (saíram daqui → viraram ✅ no charter)
- **2026-06-04 · D-09** Card Vendas×Oficina no drawer (reuso `VendaDerivadaCard`). PR #2228.
- **2026-06-04 · D-01** Arrasto preditivo + drawer-on-block. PR #2228.
- **2026-06-04 · D-02** Avançar pelo card (mesma porta gate-guardada). PR #2228.
- _Veredito delegado a [CC] por [W] 2026-06-04; método: render fiel + CI visual verde. Conferência LIVE pós-deploy é follow-up._

## Descartados (viraram anti-pattern no charter)
- _(nenhum ainda)_

## Trilha do tempo
- 2026-06-02 · [CC] criou o Register (anéis Radar). Semeado com D-01…D-08 do inventário ⬜/💡 do charter.
- 2026-06-04 · [CC] importado pro repo via handoff de design. Pointers ajustados pra `Index.*`. D-09 adicionado (gap de impl da seção 2 travada do drawer).
