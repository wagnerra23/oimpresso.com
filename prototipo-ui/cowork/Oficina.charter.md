---
page: Oimpresso · Nova Ordem de Serviço (Oficina) · window.OficinaOSPage
component: oficina-os-page.jsx (+ oficina-os-page.css)
repo_alvo: resources/js/Pages/Oficina/Os/Create.tsx (a criar na F3)
status: F1 (protótipo Cowork) — aguarda F1.5 design-critique + F2 screenshot [W]
owner: wagner
last_validated: 2026-06-01
validated_against: oficina-os-page.jsx @ cowork-2026-06-01
referencia_travada: Shopmonkey (calma/polish) × Tekmetric (fluxo/densidade) × Shop-Ware (inspeção DVI)
fundamentacao: "Oficina - Benchmark Estado da Arte.html" (16 camadas + 2 matrizes)
---

# Charter — Nova Ordem de Serviço (Oficina)

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
- `oficina-os-page.{jsx,css}` (build F1) · `Oficina - Benchmark Estado da Arte.html` (16 camadas/2 matrizes) · `memory/sessions/2026-06-01-build-oficina-os.md`
- FSM ADR 0129/0143 · `CASO-PRATICO-OS-COMUNICACAO-VISUAL.md` (multi-doc fiscal) · `CONTEXTO-DE-TELA.md` + `FRESCOR-DE-TELA.md` (intake)

## Evolução / trilha do tempo
- 2026-06-01 · criado por [CC] (backfill pós-build, conceito travado). Nenhum anterior superseded.
