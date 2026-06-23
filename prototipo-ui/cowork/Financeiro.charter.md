---
page: /financeiro
component: financeiro-app.jsx (window.FinanceiroPage) · repo alvo resources/js/Pages/Financeiro/Index.tsx
owner: wagner
status: proposta (Cowork) — vira oficial quando no git main
last_validated: 2026-05-31
parent_module: Financeiro
related: [Método 9.75 Financeiro.html, GOLDEN-REFERENCE.md, ADR 0110]
persona: Eliana [E] (financeiro escritório) + Larissa (balcão 1280px)
tier: A
charter_version: 1
---

# Page Charter — /financeiro

> ⚠️ **SUPERSEDED (carimbo 2026-06-09):** a charter canônica desta tela é **`resources/js/Pages/Financeiro/Unificado/Index.charter.md` v13 no git @main** (CODE_NOTES 2026-05-31, PR #2053). Este arquivo local fica só como registro da direção de design de [W] 2026-05-31 (3 lentes = US-FIN-029, ainda pendente no live). Reavaliação completa: `Reavaliacao Financeiro - 2026-06-09.html`.
> **Status:** proposta de memória-por-tela (L-14). Captura o que [W] já disse que **precisa ter**, o que **aprovou**, e o que **reprovou** — pra eu não repetir pergunta. Vira oficial quando mirrorado pro git (L-13).
> **Norte de domínio:** `Método 9.75 Financeiro.html` (5 princípios · 22 features · roadmap 7,6→9,75).
> **Padrão visual:** Cockpit V2 (ADR 0110) · golden = `Sells/Create` (10 regras do GOLDEN-REFERENCE).

---

## Mission

Caixa unificado da gráfica: ver entradas e saídas numa lista só por data, saber **se vai ter dinheiro** (fluxo projetado), conciliar com o extrato, cobrar (boleto/PIX) e manter nota+imposto grudados no lançamento. A pergunta que a tela responde primeiro é **"tenho caixa dia 15?"**, não "qual meu lucro de competência?".

---

## Goals — Features (PRECISA TER)

**Vivo hoje (aprovado / manter):**
- Ledger unificado entrada/saída por data + `DirIcon` ↑/↓ + status badge (escala warm emerald/amber/rose) — **é o melhor da tela, não mexer**
- KPI hero "saldo previsto" + sparkline, full-width reflow (R5 corrigido 2026-05-31 · **2026-06-02 reflow migrado de `@media` viewport → `@container finbody`**: cura a colisão da Larissa @1280px que o media-query de viewport não pegava — agora reflua pela largura REAL do conteúdo pós-sidebar; medido limpo a 1200/1040/560px)
- Drawer de detalhe rico: FSM stepper, conciliação card, painel IA, anomalia, histórico/auditoria, comentários
- ⌘K (busca lançamento/cliente/NF/categoria) · atalhos J/K · marcar pago/recebido rápido · liquidar em lote no footer
- Densidade compacta/confortável/espaçosa · ageing "a receber"
- Digest do mês (IA) · trilha de fechamento · modo apresentação

**Aprovado nesta sessão ([W] 2026-05-31) — manter:**
- **3 lentes no header: Caixa · A receber · A pagar** (segmented), dirigindo o filtro
- **Menu ··· suspenso** com o resto (Buscar, Resumir mês, Fechamento, Apresentar, Imprimir, Exportar)
- **Sub-páginas no SIDEBAR** (Fluxo de caixa · Conciliação · DRE · Plano de contas), não no header

**Roadmap (Método 9.75 — precisa ter pra 9,75, hoje mock):**
- Fluxo projetado real 30/60/90 por vencimento · toggle regime caixa↔competência · multi-conta consolidada
- Conciliação de verdade: importar OFX/Open Finance + motor de match ±valor ±dias + baixa em lote
- Cobrança: emitir boleto/PIX + régua automática + baixa por retorno
- Fiscal: NF-e/NFS-e vinculada (card SEFAZ) + impostos a recolher (DAS/DARF/ISS) + retenções + calendário
- DRE gerencial + centro de custo · auto-categorização IA · previsão de saldo com alerta

---

## Non-Goals — Features (NÃO faz)

- ❌ Cobrança via WhatsApp cliente-facing (proibição charter)
- ❌ Detalhe em modal full-screen (canon = drawer lateral)
- ❌ Emissão fiscal completa (vai pros módulos NFe/NFSe — aqui só o vínculo + status)
- ❌ Contabilidade/balancete (vai pro módulo Accounting)

---

## UX Targets

- Cabe em 1280px sem scroll horizontal (Larissa ROTA LIVRE) — header reflua, nunca esmaga
- h1 24px · KPI value grande · escala warm semântica, zero cor crua
- Marcar pago/recebido < 1 clique no hover da linha
- Sub-navegação por sidebar; header = só lente + ação primária + ···
- 0 erros JS console

---

## UX Anti-patterns (REPROVADO — não repetir)

- ❌ **Header com fileira de 7 botões inline** (Resumir/Fechamento/Apresentar/Conciliar/Plano de contas/Export/Novo) — [W] 2026-05-31: *"está muito apertado"*; esmagava o título <1100px. → virou 3 lentes + ···.
- ❌ **Sub-páginas (Conciliação, Plano de contas) como botões no header** — [W] quer no sidebar.
- ❌ **Mexer na estrutura sem ler o domínio primeiro** — [W] 2026-05-31: *"não foi fiel ao projeto"*. Tweak antes de entender = retrabalho. Ler este charter + Método 9.75 ANTES de tocar.
- ❌ **Apresentar profundidade mock como pronta** — conciliação/cobrança/fiscal são casca hoje; não dizer que "está feito".
- ❌ `rounded-xl+` · `font-bold` em h1 · cor crua `bg-(gray|red)-N` (proibições charter/DS)
- ❌ Inglês em UI cliente-facing

---

## Diagnóstico atual (Método 9.75)

Composto **7,6/10** · usabilidade 8,0 · cobertura de domínio 6,5.
Por categoria: Caixa&Fluxo 7,5 · Conciliação 5,5 · Cobrança 4,5 · Fiscal 5,0 · IA&DRE 6,5.
Roadmap: 7,6 → 8,4 (fluxo) → 9,0 (conciliação) → 9,5 (cobrança+fiscal) → 9,75 (DRE+IA).

---

## Refs

- Método 9.75 Financeiro.html — bench vs Conta Azul/Bling/Omie/Granatum/Nibo/Asaas + QuickBooks/Stripe/Mercury
- GOLDEN-REFERENCE.md — golden Sells/Create + 10 regras binárias
- ADR 0110 — Cockpit Pattern V2
- Arquivos: financeiro-app.jsx · fin-boletos.css · financeiro.css · data.jsx (grupo FINANCEIRO) · app.jsx (rotas fin-*)
