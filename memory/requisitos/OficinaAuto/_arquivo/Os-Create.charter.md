---
page: /oficina-auto/os/create
component: resources/js/Pages/OficinaAuto/Os/Create.tsx
page_id: oficina-os-create
owner: wagner
status: deprecated
last_validated: "2026-06-01"
parent_module: OficinaAuto
related_us: [US-OFICINA-006, US-OFICINA-035, US-OFICINA-038, US-OFICINA-014, US-OFICINA-018]
tier: A
charter_version: 1
validated_against: "oficina-os-page.jsx @ cowork-2026-06-01"
referencia_travada: "Shopmonkey (calma/polish) × Tekmetric (fluxo/densidade) × Shop-Ware (inspeção DVI)"
fundamentacao: "Oficina - Benchmark Estado da Arte.html (16 camadas + 2 matrizes)"
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0093-multi-tenant-isolation-tier-0
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0110-tipografia-canon-h1-subtitle
  - 129
  - 143
---

# Charter — Nova Ordem de Serviço (Oficina · documento vivo)

> 🪦 **LÁPIDE — arquivado em 2026-07-09** (movido de `resources/js/Pages/OficinaAuto/Os/Create.charter.md` pra cá; a pasta `Os/` ghost foi removida das Pages pra promoção do IT2 a duro — charter em Pages exige `.tsx` irmão vivo).
>
> 🪦 **DEPRECATED — conflito RESOLVIDO por [W] em 2026-06-30 (sessão musing-elion).** Decisão Tier 0: **o canon é [`OficinaAuto/ServiceOrders/`](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/)** (Board/Create/Edit/Show — live, Martinho biz=164, 3 testes) + o drawer rico `ProducaoOficina/_components/ServiceOrderRichSheet.tsx`. O mapeamento do bundle Cowork (`os-drawer-build-map.md`) provou que a visão "documento vivo" deste charter (stepper FSM + DVI + gate + split fiscal) **já está realizada** no canon — 11 de 13 partes vivas. Esta pasta `Os/` era um ghost: `Create.tsx` **nunca existiu**, 0 testes. **Não construir.** O detector (`detectar-telas`) aponta o mockup `oficina-os-page.jsx` pro canon via `visual_source` em `ServiceOrders/Show.charter.md`. Inventário completo: [`memory/requisitos/OficinaAuto/RECONCILIACAO-os-inventario.md`](../RECONCILIACAO-os-inventario.md).
>
> _Histórico (antes da decisão): charter F1 persona Larissa @ balcão 1280px, landado draft 2026-06-01 do handoff Cowork; sobreposição com `ServiceOrders/Create` (live) registrada como conflito aberto até [W] decidir._

> **1ª aplicação do par `CONTEXTO-DE-TELA` + `FRESCOR-DE-TELA`.** Escrito DEPOIS do build (a pedido de [W]) — backfill que trava o conceito pra não derivar de novo.
> **Missão:** dar à oficina um **documento vivo de OS** — do check-in do veículo à entrega — que parece estado-da-arte e não cupom de balcão. Persona: Larissa (balcão, 1280px) + mecânico (tablet).

## Goals — PRECISA TER (features + padrões aprovados)

- **FSM stepper** visível: Recepção → Diagnóstico → Orçamento → Aprovação → Execução → Pronto → Entregue.
- **Hero do veículo:** placa Mercosul, modelo/ano, KM, combustível (gauge), mecânico responsável, cliente + histórico ("6 OS desde 2021").
- **Check-in de entrada:** relato do cliente + **avarias marcadas** + **fotos de entrada** — o estado que protege oficina e cliente.
- **Inspeção (DVI):** item a item com **semáforo (ok/atenção/reprovado)** + foto/anotação; **reprovado vira linha de orçamento em 1 clique** (ponte inspeção→venda).
- **Itens split por natureza:** **Serviço** (mão de obra + mecânico + horas estimadas/reais) × **Peça** (estoque/reserva amarrada à OS). Busca rápida `/`.
- **Gate de aprovação do cliente:** estado "aguardando aprovação", **enviar orçamento por WhatsApp**; **a execução NÃO inicia sem o cliente autorizar** (bloqueia "Avançar").
- **Fiscal split:** Peça → **NF-e 55**, Mão de obra → **NFS-e**; garantia de serviço/peça. (Emissão é listener backend — a tela prepara.)
- **Documento vivo, não formulário:** seções que acendem por contexto; teclado-first; densidade calma que cabe em 1280px sem overflow.
- **Histórico por placa** (a completar): manutenções anteriores guiam o "o que vem agora".

## Non-Goals — NÃO FAZ (vai pra outra superfície)

- **NÃO é POS / frente de caixa** (`/sale-pos`) — sem "Consumidor Final" default, sem **bipe de código de barras**, sem cupom **NFC-e** como caminho principal.
- **NÃO é a venda comercial de Comunicação Visual** (essa é `vendas/create` vertical `cv`) — aqui o switch de vertical só reduz/contextualiza.
- **NÃO emite a nota** na tela — prepara o documento; a emissão é gate FSM + listener.

## UX Anti-patterns (REPROVADO — anti-regressão)

- ❌ **"Bipe o código de barras" / autofocus em campo de bipe** → ergonomia de balcão de mercado. (origem do erro venda→POS)
- ❌ **"Consumidor Final" como cliente padrão** → venda anônima; oficina é cliente+veículo cadastrados.
- ❌ **Tudo no mesmo peso tipográfico, selects nativos crus, sem estados** → "feio/amador" ([W] 2026-06-01).
- ❌ **Entrega/endereço tratado como "frete" solto** → é estrutura (destinatário↔local), não logística.
- ❌ **Iniciar execução sem aprovação do cliente** → fere o gate; carro fica parado até o "ok".

## UX Targets + Tests

- 1280px **sem overflow horizontal**; página rola internamente (footer e header fixos).
- Reprovado na inspeção → **1 clique** adiciona ao orçamento.
- Botão **"Avançar p/ Execução" bloqueado** enquanto status = aguardando aprovação.
- Split fiscal correto: soma peças = NF-e, soma serviços = NFS-e.
- **design-critique ≥ 80** (F1.5) antes de F2.

## Refs
- `oficina-os-page.{jsx,css}` (build F1 Cowork) · `Oficina - Benchmark Estado da Arte.html` (16 camadas/2 matrizes) · `memory/sessions/2026-06-01-build-oficina-os.md`
- FSM **ADR 0129 / 0143** · `CASO-PRATICO-OS-COMUNICACAO-VISUAL.md` (multi-doc fiscal) · `CONTEXTO-DE-TELA.md` + `FRESCOR-DE-TELA.md` (intake)
- Charter live relacionado (NÃO duplicar): [`OficinaAuto/ServiceOrders/Create.charter.md`](../../../../resources/js/Pages/OficinaAuto/ServiceOrders/Create.charter.md)

## Evolução / trilha do tempo
- 2026-06-01 · criado por [CC] (backfill pós-build, conceito travado). Nenhum anterior superseded.
- 2026-06-02 · landado por [CL] como `draft` em `OficinaAuto/Os/` (caminho canônico do módulo, sem fork `Oficina/`). Conflito de sobreposição com `ServiceOrders/Create` (live) registrado acima — aguarda decisão Tier 0 de [W]. Refs: PR fiscal-status `#2130` (irmão do mesmo handoff Cowork).
- 2026-06-30 · deprecated por decisão [W] (sessão musing-elion): canon = `ServiceOrders/` + `ServiceOrderRichSheet`; **não construir** `Os/Create.tsx` (nunca existiu). Banner de decisão no topo.
- 2026-07-09 · [CC] arquivado: movido de `resources/js/Pages/OficinaAuto/Os/Create.charter.md` → `memory/requisitos/OficinaAuto/_arquivo/Os-Create.charter.md` (lápide L-22). Motivo: promoção do IT2 (integrity-check §15) a duro — charter em Pages exige `.tsx` irmão vivo; este é tela-ghost decidida "não construir". Links relativos re-apontados. Zera 1 dos 2 refs quebrados da catraca `charter-refs` (ceiling 2→0).
