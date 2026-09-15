# [W → CC] F0 batch — PaymentGateway UI · 2026-05-19

**Quem:** Wagner [W] + Claude Cowork (este turn) · disparado pela ADR 0144 PaymentGateway (Onda 0 docs mergeada).

**Tipo:** F0 batch novo (3 telas relacionadas, §7 PROTOCOL.md). NÃO é amendment a pedido anterior — é batch novo derivado de decisão ADR.

**Decisões Wagner F0 2026-05-19 vinculadas:**
1. PIX Automático = driver BCB direto (não passa por Inter/Asaas)
2. Subscription Superadmin elimina-se: Wagner cobra tenants via Plan em RB no `business_id=1` (dogfooding)
3. PaymentGateway pode substituir PesaPal desde que tenha funções
4. Módulo separado pelo tamanho e ligação cross-module

---

## Contexto canibalização

| Hoje | Vira |
|---|---|
| `/financeiro/boletos` (charter live 2026-05-14, persona Eliana, score Boletos atual) | `/financeiro/cobranca` (rename + expansão tipo/gateway/origem) |
| `boleto-contas-app.jsx` linhas 215-557 (`TelaBoletos`+`DrawerBoleto`+`SheetEmitirBoleto`+`SheetRemessaRetorno`) | Material canon pra Tela 1 |
| `boleto-contas-app.jsx` linhas 559-826 (`TelaContas`+`CardConta`+`SheetConfigInter`+`SheetNovaConta`) | `SheetConfigInter` (linhas 668-826, 4 tabs) → Material canon pra Tela 2. `TelaContas` JÁ está coberto por `ContasBancarias/Index` em prod — **não duplicar** |
| Credenciais coladas em `accounts.{rb_gateway_credential_id, gateway_banco, gateway_ambiente, gateway_client_id}` | Settings/PaymentGateways/Index (Tela 2) gerencia em `payment_gateway_credentials` separado (Onda 2 backend) |
| `Sells/Index` drawer (A+ 9,75 KB-9.75 v2 PR #1064) | + botão "Emitir cobrança" no drawer SaleSheet (Tela 3) |

---

## TELA 1 — `Financeiro/Cobranca/Index` (P0)

**URL:** `/financeiro/cobranca` (301 redirect de `/financeiro/boletos` por 60d)
**Persona:** Eliana[E] dia-a-dia + Larissa drawer Sells emite via esta tela
**Charter:** rename `Boletos/Index.charter.md` → `Cobranca/Index.charter.md` no F3
**Material canon:** `boleto-contas-app.jsx` linhas 215-557
**KB-9.75 obrigatório:** mira mesmo bar de Vendas/Financeiro Unificado PR #1064

### Pedidos

- [ ] **Rename "Boletos" → "Cobrança"** em todos pontos UI. Boleto Inter atual = caso particular de tipo=boleto+gateway=inter
- [ ] **Filtro TIPO** (chips topo, abaixo tabs status): `Todos · Boleto · PIX · PIX Automático · Cartão`. URL sync `?tipo=boleto`
- [ ] **Filtro GATEWAY** (dropdown ao lado de "Conta bancária"): `Todos · Inter · C6 · Asaas · BCB Pix`. URL sync `?gateway=inter`
- [ ] **Filtro ORIGEM** (chip secundário, menos prominente): `Todas · Venda · Fatura recorrente · Mensalidade SaaS`. Última opção SÓ aparece quando logado em `business_id=1`. URL sync `?origem=invoice`
- [ ] **Chip GATEWAY+TIPO composto** na tabela (substitui chip-banco COMPE atual): `[Inter] boleto` / `[Asaas] PIX cob` / `[BCB] PIX Aut.` / `[Asaas] Cartão`. Quadrado `rounded-sm 8x8` colorido por gateway + sigla + tipo em `text-[10px]` ao lado. Chip = **emissor (driver)**, não conta destino
- [ ] **Coluna "Conta destino"** (nova posição 4): mostra qual `account` recebe — conta bancária vinculada à credencial. Separa emissor (chip) de destino (coluna)
- [ ] **Funil 5 etapas atual**: mantém. PIX vencido = "Vencido"; PIX Aut. com mandato cancelado = chip lateral fora do funil "Mandato cancelado" (rose)
- [ ] **3 KPIs cards + KPI #4 contextual**:
   - Padrão: Pago no mês · Vencido · Em aberto (emerald/rose/default — mantém)
   - `tipo=pix_recv`: KPI #4 = "Mandatos ativos" (qtd contratos vigentes)
   - `origem=subscription_license`: KPI #4 = "MRR cobrado este mês" (R$ + qtd tenants pagos)
- [ ] **Drawer detalhe — render condicional por tipo**:
   - `boleto`: linha digitável + código de barras + botão "Copiar linha digitável" + link "Baixar PDF"
   - `pix_cob`/`pix_cobv`: BR Code copia-e-cola (textbox) + QR code PNG inline 180×180px + "Copiar BR Code"
   - `pix_recv`: dados mandato (CNPJ recebedor, ciclo, próxima cobrança) + status `ativo`/`cancelado`
   - `card`: bandeira + últimos 4 + status emissor + flag 3DS
- [ ] **Drawer — seção "Origem"** (nova): chip clicável linkando:
   - `sale` → drawer Sells/Index aberto na venda
   - `invoice` → drawer RecurringBilling/Invoice
   - `subscription_license` → drawer SuperadminTenant (biz=1 only)
   - `null` (avulsa) → não mostra seção
- [ ] **Drawer — botão "Estornar"** quando `status=paga` e driver suporta refund (Asaas + PesaPal sim; Inter parcial com tooltip; BCB Pix desabilitado tooltip "Não disponível PIX Aut."). Modal confirmação 2 etapas: "Tem certeza?" + input valor (default total, editável pra parcial)
- [ ] **Botão header "Emitir boleto" → "Nova cobrança"**: Sheet 4 steps:
   - Step 1: TIPO (radio cards `Boleto / PIX / PIX Auto / Cartão` — desabilitar sem driver ativo em Settings/Gateways)
   - Step 2: contato (autocomplete)
   - Step 3: valor + vencimento + descrição + dados específicos do tipo (PIX Aut. = ciclo + duração mandato)
   - Step 4: revisar + confirmar
- [ ] **Sheet "Remessa/Retorno"** existente: manter, esconder quando filtro de gateway não inclui C6 (único CNAB direct)
- [ ] **Header breadcrumb dinâmico**: `Financeiro · Cobrança · {Gateway}` quando `?gateway=X` ativo
- [ ] **Empty state com filtros zerando**: CTA "Configurar gateway" linka pra Settings/Gateways

### Inspirações
- <https://dashboard.asaas.com.br> — referência DIRETA BR, filtros tipo+gateway+origem
- <https://app.iugu.com> — concorrente BR mesma densidade
- <https://stripe.com/docs/payments/dashboard> — densidade tabular global
- <https://app.contaazul.com/recebimentos> — KPIs + funil 5 etapas
- **Excluir:** Inter PJ web (foco emissão), Bradesco Net Empresa (UI 2010)

### Restrições
- multi-tenant: `cobrancas.business_id` global scope ADR 0093
- mobile: desktop-first (Eliana 1280px), responsivo opcional
- monitor 1280px crítico: funil 5 etapas + 3 KPIs sem scroll
- densidade KB-9.75: `text-[12.5px] tabular-nums`, `h-12` header, `w-[480px]` drawer
- token Cockpit V2: `bg-primary` Oimpresso laranja; warm semantic emerald/rose/amber/sky
- PII: pagador NUNCA mostra CPF/CNPJ raw — `***.***.***-**`
- localStorage: `oimpresso.financeiro.cobranca.*`

### Não-fazer
- ❌ `rounded-xl+` (canon `rounded-md` cards, `rounded-lg` drawers)
- ❌ Inventar paleta (só shadcn + warm semantic)
- ❌ Animação > 300ms
- ❌ Telas separadas "PIX" vs "Boleto" — **UMA tela** com filtro tipo
- ❌ Emoji — só lucide-react
- ❌ Opacity em cores (`bg-amber-500/10`) — usar escala `bg-amber-50`
- ❌ Feature fora escopo (export CSV, share, comments)
- ❌ Tutorial-shadcn look (shadow-lg+, gradient-bg, hero-section)
- ❌ Mostrar "Visão unificada" no sidebar interno dim (linha 122 do canon) — Cockpit V2 não tem sidebar interna do módulo

---

## TELA 2 — `Settings/PaymentGateways/Index` (P1)

**URL:** `/settings/payment-gateways`
**Persona:** Wagner (admin financeiro). NÃO cliente-facing — `paymentgateway.credenciais.*` required
**Charter:** criar do zero no F3
**Material canon:** `boleto-contas-app.jsx` linhas 668-826 (`SheetConfigInter` — 4 tabs)

### Contexto
Hoje credenciais bancárias estão coladas em `accounts.gateway_*` (Wagner correto: "deveria desvincular"). Onda 2 backend extrai pra `payment_gateway_credentials`. UI precisa CRUD próprio em Settings — não confundir com "Contas bancárias" (`/financeiro/contas-bancarias` já existe).

### Pedidos

- [ ] **Lista de credenciais** (página principal): tabela `text-[12.5px] tabular-nums` com colunas: Apelido · Driver (chip colorido Inter/C6/Asaas/BCB/PesaPal) · Ambiente (badge sandbox/production) · Conta destino (FK accounts, linka) · Status health check (chip ok/degraded/down + timestamp última verificação) · Ações
- [ ] **Header**: título "Gateways de Pagamento" · breadcrumb "Configurações · Pagamento" · botão primário "+ Novo gateway"
- [ ] **KPIs topo (3 cards)**:
   - Credenciais ativas (qtd)
   - Health check fail (qtd de status≠ok — chip rose)
   - Cobranças emitidas hoje (cross-cobrancas — info navegacional)
- [ ] **Sheet "Novo gateway"** (Step 1 driver / Step 2 credenciais / Step 3 vínculo conta + ambiente):
   - Step 1: radio card 5 drivers (Inter / C6 / Asaas / BCB Pix / PesaPal — última marcada `deprecated` chip amber). Cada card mostra: nome, logo placeholder, tipos suportados (chips: boleto/PIX/cartão), nota técnica curta ("mTLS cert" / "api_key" etc)
   - Step 2: campos dinâmicos por driver (canon = `SheetConfigInter` linhas 712-797):
     - **Inter**: client_id + client_secret + upload cert.crt + upload cert.key + senha cert (todos `Crypt::encryptString` server-side)
     - **C6**: agencia + conta + codigo_cliente
     - **Asaas**: api_key (input password com show/hide)
     - **BCB Pix**: upload cert mTLS + CNPJ recebedor homologado (warn: "exige homologação prévia BCB")
     - **PesaPal**: api_key + consumer_secret (chip amber "Deprecated — migrar pra Asaas")
   - Step 3: dropdown account_id (FK pra accounts existentes) + radio ambiente (sandbox/production) + toggle "Ativar imediatamente"
   - Botão "Testar conexão" no final Step 2 (canon `testar()` linha 673) — chip loading "Testando..." → ok/erro
- [ ] **Drawer detalhe credencial** (clica linha): tabs (canon = `SheetConfigInter` tabs):
   - Identificação (apelido, FK account, ambiente — editáveis)
   - Credenciais (campos masked com "Editar" pra revelar — config_json decryptado server-side)
   - Webhook (URL pública pra colar no painel do banco — `{APP_URL}/webhooks/{driver}` + assinatura HMAC secret)
   - Health check (timestamp último check, latência, status atual, botão "Rodar agora")
- [ ] **Botão "Rodar health check"** disparam `PaymentGateway::healthCheck()` por credencial individual + bulk action "Testar todas"
- [ ] **Empty state** primeira instalação: ilustração placeholder + texto "Nenhum gateway configurado ainda" + CTA "+ Adicionar primeiro gateway" + link "Como funciona?" linka pra `Modules/PaymentGateway/README.md`

### Inspirações
- <https://dashboard.stripe.com/settings/payments> — Stripe API keys (referência cards de driver + masked secrets)
- <https://dashboard.asaas.com.br/integracoes> — referência BR direta
- <https://app.intercom.com/a/settings> — pattern Settings sidebar + content
- **Excluir:** Mercado Pago (UI cliente-facing, não settings técnico)

### Restrições
- multi-tenant: `payment_gateway_credentials.business_id` global scope
- mobile: desktop-only (Wagner escritório 1440px) — sem responsive
- monitor 1280px: tabela cabe em 1280
- credenciais NUNCA em plain text na response — `client_secret` / `api_key` / `webhook_secret` sempre masked `••••••••` com botão "Editar" disparando server-side decrypt sob auth
- token Cockpit V2 igual Tela 1

### Não-fazer
- ❌ Duplicar tela "Contas bancárias" (`/financeiro/contas-bancarias` já existe — esta tela aponta pra ela via FK, não duplica CRUD)
- ❌ Mostrar config_json bruto na UI — sempre via campos tipados por driver
- ❌ Permitir delete sem confirm (cred com cobrança em aberto = bloquear delete + sugerir "Desativar"; cred sem cobrança = soft delete)
- ❌ Adicionar driver "PIX direto sem gateway" — não existe (PIX sempre passa por driver)
- ❌ UI cliente-facing — esta tela é Wagner-only, não inventar onboarding pra Larissa

---

## TELA 3 — `Sells/Index` drawer — botão "Emitir cobrança" (P0)

**URL:** `/sells` drawer existente
**Persona:** Larissa balcão ROTA LIVRE
**Charter:** [Sells/Index charter] já live, **A+ 9,75 KB-9.75 v2** — risco regressão visual ALTO
**Material canon:** drawer atual + botões existentes

### Contexto
Tela já gold-standard. Modificação **pontual e cirúrgica**: adicionar 1 botão no drawer SaleSheet que dispara emissão de cobrança vinculada àquela venda. Não rebobinar layout.

### Pedidos

- [ ] **Botão "Emitir cobrança"** no drawer SaleSheet (próximo aos botões existentes "Imprimir" / "PDF" / "Cancelar venda"):
   - Estado A (`sale.payment_status='pending'` ou `null`): botão primário `bg-primary` "Emitir cobrança"
   - Estado B (`sale.cobranca_id != null` + cobranca status `emitida|paga`): chip status (com link "Ver cobrança" linkando `/financeiro/cobranca/{id}`) — botão "Emitir" desaparece
   - Estado C (cobranca status `cancelada|erro`): chip + botão secundário "Reemitir"
- [ ] **Modal "Emitir cobrança da venda #{sale.invoice_no}"** (versão simplificada do Sheet 4-step da Tela 1 — única diferença: contato + valor pre-preenchidos):
   - Tipo (Boleto / PIX / PIX Auto / Cartão — drivers ativos)
   - Conta destino (dropdown account_id — default = principal do business)
   - Vencimento (default = hoje+7d, editável)
   - Multa / juros / desconto (opcional, accordion fechado)
   - "Confirmar emissão" → POST → atualiza drawer com chip novo + toast sonner
- [ ] **Sem mudanças visuais fora do botão.** Layout/header/timeline/itens/totais inalterados.

### Inspirações
- <https://app.iugu.com> — botão "Cobrar" inline em fatura
- <https://app.contaazul.com> — botão "Gerar boleto" no detalhe venda

### Restrições
- preservar A+ 9,75 KB-9.75 v2 — qualquer regressão visual em outras áreas do drawer reprova F1.5
- Pest GUARD esperado: drawer renderiza Em ESTADO A sem cobrança vinculada, ESTADO B com chip+link, ESTADO C com reemitir

### Não-fazer
- ❌ Adicionar nada fora do botão + modal — não rebobinar drawer
- ❌ Mexer em timeline FSM, header sale, tabela itens, totais, abas
- ❌ Criar fluxo paralelo de cobrança fora do modal padrão (Tela 1 sheet 4-step)

---

## Material adicional pra carregar

**Tela 1:** `boleto-contas-app.jsx` linhas 215-557 (sub-blocos: `TelaBoletos` 215-389, `DrawerBoleto` 391-457, `SheetEmitirBoleto` 459-513, `SheetRemessaRetorno` 515-557). Charter atual `Boletos/Index.charter.md` (rename pra `Cobranca/Index.charter.md` em F3).

**Tela 2:** `boleto-contas-app.jsx` linhas 668-826 (`SheetConfigInter` 4 tabs). Tabs Inter atuais: `identificacao / boleto / inter / beneficiario` — generalizar pra `identificacao / credenciais / webhook / health` (4 tabs canônicas pra qualquer driver, não só Inter).

**Tela 3:** `Sells/Index.tsx` drawer SaleSheet (não recriar — pino conceitual no protótipo Cowork chamando "drawer existente + botão novo").

---

## Critério F1.5 (ponderado por tela)

| Tela | Score mínimo gate | Peso anti-pattern | Peso novidade |
|---|---|---|---|
| 1 Cobrança | ≥85 (alta — replaces tela live com charter ativo) | -15 cada anti-pattern KB-9.75 reaparecido | +5 cada filtro tipo/gateway/origem funcional |
| 2 PaymentGateways | ≥80 (média — tela nova) | -10 cada anti-pattern Settings Cockpit V2 | +5 cada driver com config Step 2 correto |
| 3 Sells botão | ≥90 (alta — risco regressão A+ 9,75) | -20 qualquer mudança visual fora botão/modal | +5 chip Estado B/C correto |

Score < gate → 1 round refator. Score < 70 → discussão (abrir reflexão).

---

## Próxima ação

[CC] consome este F0 batch + lê ADR 0144 + SCOPE/README/CONTRACTS PaymentGateway + canon `boleto-contas-app.jsx`. Produz 3 protótipos:
- `prototipos/financeiro-cobranca/page.tsx`
- `prototipos/settings-payment-gateways/page.tsx`
- `prototipos/sells-emitir-cobranca-modal/page.tsx` (pino conceitual + modal)

3 `COMPARISON.md` separados (15 dimensões cada).

[CD] roda F1.5 critique por tela. Wagner aprova screenshot em sequência (Tela 1 → Tela 2 → Tela 3).

[CL] F3 = 3 PRs separados (1 por tela — preserva commit discipline §7 PROTOCOL.md). PR Tela 1 = mais pesado (rename + expansão + redirect 301). PR Tela 2 = nova tela com charter novo. PR Tela 3 = modificação cirúrgica drawer SaleSheet.

Backend (Onda 2-4 ADR 0144) caminha em paralelo. Pode mergeárem PRs de UI antes mesmo de cobrancas table existir — UI usa mock data + flag feature toggle.
