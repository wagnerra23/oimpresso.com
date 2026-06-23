---
casos: Vendas · window.VendasPage (vendas-page.jsx + extras)
irmaos: Vendas.charter.md (local) · git Sells/Create.charter.md (tela-irmã de cadastro) · vendas-tweaks.jsx
tecnica: Caso de uso = narrativa do cliente + aceite verificável (Dado/Quando/Então)
nota_tela: 9.5 (piloto aprovado)
owner: wagner · last_run: 2026-06-02
---

# Casos de Uso & Aceite — Vendas

> Derivados do código real (`vendas-page.jsx`). `live` = checável no protótipo · `static` = wiring no código.

## UC-V01 · O dia em números
- **Persona:** Larissa / Wagner. **Como usa:** bate o olho nos KPIs do topo — faturado hoje (+sparkline), ticket médio, a receber.
- **Aceite:** Então KPIs "Faturado hoje", "Ticket médio", "A receber" com valor; hero tem sparkline 30d.
- **Check:** static `vd-kpi-hero` + `kpi_total/kpi_avg/kpi_ar`. · **Status: ✅ static**

## UC-V02 · Nova venda num toque
- **Persona:** Larissa (venda no balcão). **Como usa:** clica "Nova venda" (ou tecla **N**, ou **⌘K**) → página de cadastro.
- **Aceite:** Quando clica/N/⌘K · Então abre o cadastro de venda.
- **Check:** static botão "Nova venda" + kbd N + palette. · **Status: ✅ static**

## UC-V03 · Visões salvas (a árvore)
- **Persona:** Larissa/gestor. **Como usa:** filtra por Pendentes (por vendedor), Faturadas (B2B/B2C), Origem (balcão/oficina/online) ou Favoritas.
- **Aceite:** Quando escolhe uma saved view/filho · Então a lista filtra conforme.
- **Check:** static `topView`/`subView` + ramos b2b/b2c/origem/favoritas. · **Status: ✅ static · live ⬜**

## UC-V04 · A receber com ageing (SLA)
- **Persona:** Eliana (financeiro). **Como usa:** vê quanto há a receber e quantos títulos estão **estourados/atrasando**; clica "ver estouradas".
- **Aceite:** Dado títulos vencidos · Então KPI A receber marca alerta + CTA filtra os estourados.
- **Check:** static `slaCounts` + `vd-kpi-ar-cta` + view `atrasadas`. · **Status: ✅ static**

## UC-V05 · Emitir/ver NF-e + NFS-e
- **Persona:** Larissa/Eliana. **Como usa:** abre a venda; no drawer vê os cards **NF-e** e **NFS-e** com número/chave SEFAZ (copiável).
- **Aceite:** Quando abre venda com fiscal · Então `VdFiscalCard` por documento, com chave de 44 dígitos.
- **Check:** static `VdFiscalCard` + `nfe`/`nfse`. · **Status: ✅ static · live ⬜**

## UC-V06 · Venda que nasceu na Oficina
- **Persona:** Larissa. **Como usa:** uma OS pronta gera a venda; ao abrir pela Oficina, a venda derivada aparece (origin=oficina).
- **Aceite:** Quando dispara `oimpresso:open-venda{id}` · Então a venda abre; filtro Origem=oficina lista as derivadas.
- **Check:** static listener `oimpresso:open-venda` + `source==="oficina"`. · **Status: ✅ static (contraparte do UC-08 da Oficina)**

## UC-V07 · Comando rápido (⌘K)
- **Persona:** Larissa (teclado-first). **Como usa:** ⌘K → "Nova venda", "Emitir NF-e em lote", "Buscar por chave SEFAZ".
- **Aceite:** Quando ⌘K · Então paleta com esses comandos.
- **Check:** static palette shortcuts. · **Status: ✅ static · live ⬜**

## UC-V08 · Emitir NF-e em lote
- **Persona:** Eliana. **Como usa:** seleciona várias vendas → emite NF-e de todas de uma vez.
- **Aceite:** Dado seleção > 0 · Quando "Emitir em lote" · Então abre o fluxo de emissão em lote.
- **Check:** static `setBulkEmitOpen` + seleção. · **Status: ✅ static · live ⬜**

## Conformância DS (régua-drift · L-29) — a classe que vira TESTE, não memória
> Régua = `ds-v6/gabarito-vendas.html` · `ds:v6` (charter). Asserção por **computed-style**, locator resiliente. Estes bloqueiam o merge no CI ([CL]). 🧪 = spec pronta, falta wirar Playwright.

## UC-V09 · Accent conforma ao ds:v6 (roxo, não verde)
- **Aceite:** Dado o charter `ds:v6` · Então o accent computado do primary "Nova venda" + abas ativas == `--accent` roxo (≈oklch 0.55 0.15 295), **não** verde 155.
- **Locator:** `getByRole('button',{name:/nova venda/i})` → `getComputedStyle().backgroundColor`. · **Check: ci** **Estado: ⬜** (bloqueado pela decisão A/B de [W]). · **Pega:** o erro de 2026-06-03.

## UC-V10 · Zero cor crua — só tokens do DS
- **Aceite:** Dado o CSS de Vendas · Então 0 `oklch(...)`/hex cru fora de `:root`/`[data-theme]` (exceto transcript A4 + apresentação dark, declarados). Toda cor resolve pra token DS.
- **Locator:** static (DS-GUARD / stylelint anti-hex #2054). · **Check: static+ci** **Estado: 🧪** (CSS-2 em andamento).

## UC-V11 · Detalhe é drawer lateral, não modal
- **Aceite:** Quando abre uma venda · Então `role=dialog` lateral ~480px (Sheet), nunca modal central full-screen (proibição charter).
- **Locator:** `getByRole('dialog')` → largura + ancoragem direita. · **Check: ci** **Estado: ⬜**

## UC-V12 · Os 2 temas passam (claro + escuro)
- **Aceite:** Dado claro E escuro · Então status legíveis (sem white-on-white), contraste AA; KPI cards usam `--surface` (não branco fixo no dark).
- **Locator:** `[data-theme]` toggle + contraste computado. · **Check: ci (visual-regression golden ×2 temas)** **Estado: ❌** (hoje: card branco no dark — dívida shell `--surface`, STATUS m/k).

## Evolução
- 2026-06-03 · [CC] add **classe conformância DS** (UC-V09..V12 · METODO §"Duas dimensões") — fecha o buraco que a L-29 expôs: régua vira teste que bloqueia, não memória. V09 pega verde×roxo; V12 já está ❌ (card branco no dark).
- 2026-06-02 · [CC] criou a suíte (8 UCs) grounded em `vendas-page.jsx`. Static a seguir; live pendente (rodar na rota Vendas).
