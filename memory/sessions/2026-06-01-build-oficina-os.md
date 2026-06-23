# Sessão 2026-06-01 — Nova Venda/OS Oficina (F1 build) · [CC]

**Pedido [W]:** "construa a nova venda" → escolheu **B = Oficina mecânica** como heroína. Antecedido por: crítica "venda feia/POS/amador" + benchmark do estado da arte.

## O que foi construído (F1, no shell — sem arquivo novo de módulo proibido; é tela do ERP)
- **`oficina-os-page.jsx`** (`window.OficinaOSPage`) — Nova Ordem de Serviço como **documento vivo**, não formulário. Referência travada: **Shopmonkey** (calma/polish) × **Tekmetric** (fluxo/densidade) × **Shop-Ware** (DVI).
  - Top sticky: título + `OS #4821` + **FSM stepper** (Recepção→Diagnóstico→Orçamento→Aprovação→Execução→Pronto) + switch de vertical (Oficina/CV/Vestuário).
  - **Hero veículo:** placa Mercosul, Civic EXL 2019, KM, combustível (gauge), mecânico, cliente.
  - **Check-in:** relato, avarias de entrada (chips), fotos de entrada.
  - **Inspeção (DVI):** itens com semáforo (g/y/r) clicável + foto; reprovado → "+ orçamento".
  - **Itens da OS:** abas Serviços (mão de obra + mecânico + horas) × Peças (estoque/reserva); busca rápida `/`.
  - **Rail:** Resumo (mão de obra/peças/total grande), **Gate de aprovação** (âmbar, pulse, "Enviar por WhatsApp" — não executa sem OK), **Fiscal** (NF-e peças + NFS-e serviço), garantia.
  - Footer sticky: recap (peças/m.obra/total) + Cancelar/Salvar/**Avançar p/ Aprovação**.
  - Verticais CV/Roupa = estados condicionais reduzidos (hero+seções trocam) — mesmo documento.
- **`oficina-os-page.css`** — escopo total `.ofx`, tokens próprios ancorados no verde ERP (155) + neutros quentes; respeitou proibições (sem rounded-xl+, sem emoji, cor só de token).
- Registrado: `<link>`+`<script>` no `oimpresso.com.html`; rota `oficina-os` no `app.jsx`; **ghost "Nova OS"** sob Oficina Auto no `data.jsx`.

## Decisões / correções no caminho
- **Erro de layout pego e corrigido:** `.main-body` é altura fixa + `overflow:hidden` → a página precisa rolar **internamente** (`.ofx-scroll{overflow-y:auto;min-height:0}` + `.ofx{height:100%}`). Sem isso o miolo era clipado.
- Typo CSS `#494views` corrigido.
- Screenshot não captura scroll (re-renderiza do topo) → verifiquei seções de baixo escondendo o topo via `multi_screenshot`.

## Resíduo / próximo passo
- **`Oficina.charter.md` ainda NÃO escrito** — construí antes do charter (a pedido de [W], com referência travada). **Backfill devido:** cristalizar "PRECISA TER (DVI, gate de aprovação, check-in, split peça×m.obra, histórico por placa) / NÃO FAZ (POS, cupom)" + carimbo de frescor (FRESCOR-DE-TELA). É o 1º uso real do par CONTEXTO+FRESCOR.
- Refino visual possível: stepper apertado em telas estreitas; promover switch de vertical/densidade pra Tweaks; dados reais da ROTA LIVRE quando [W] mandar.
- F1.5 (design-critique) + F2 (screenshot [W]) pendentes no protocolo.

## Refs
- `oficina-os-page.{jsx,css}` · `app.jsx` (rota) · `data.jsx` (ghost) · `oimpresso.com.html` · `Oficina - Benchmark Estado da Arte.html` (fundamentação) · screenshots/oficina-os*.png
