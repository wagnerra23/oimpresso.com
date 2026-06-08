# Estado da arte — Settings Payment Gateways oimpresso vs líderes 2026

**Data:** 2026-05-23
**Persona-foco:** Wagner (superadmin/dono ERP) + lojistas tipo Larissa @ ROTA LIVRE (biz=4)
**Arquivos auditados:**
- `resources/js/Pages/Settings/PaymentGateways/Index.tsx` (326 linhas)
- `resources/js/Pages/Settings/PaymentGateways/Index.charter.md`
- `resources/js/Pages/Settings/PaymentGateways/_components/DrawerGateway.tsx` (477 linhas)
- `Modules/PaymentGateway/Models/PaymentGatewayCredential.php` (LogsActivity confirmado)
- Pós PR #1425 — driver Pagar.me v5 adicionado hoje 2026-05-23

---

## Sumário executivo

- **Nota oimpresso atual: 78/100** — top BR consolidado, gap ~12 pontos pra líderes globais (Stripe/Adyen).
- **Posição vs concorrentes BR (Bling, Tiny, Vindi, Iugu): TOP 1.** Oimpresso já supera Bling/Tiny em densidade, health visível e wizard. Empata com Vindi/Iugu (subscription billing) em webhook UX.
- **Posição vs líderes globais (Stripe, Adyen, Shopify, Pagar.me Dashboard): bronze.** Gap de 12 pontos concentrado em **3 dimensões**: audit log per credential, comparativo entre drivers (taxa/settlement), migration assistant.
- **Top 3 gaps por impacto×esforço:**
  1. **Audit log timeline per credential** — modelo já tem `LogsActivity` (Spatie). Falta UI consumir. Alta×Baixa.
  2. **Comparativo drivers (taxa/settlement/capacidades)** na hora de escolher novo — Wagner/Larissa decide hoje "no escuro". Alta×Média.
  3. **Webhook delivery log + replay** (eventos recebidos 7d + retry manual) — `gateway_webhook_events` já existe no DB. Alta×Média.

---

## Matriz comparativa 15 dimensões (0-5 por célula)

| # | Dimensão                              | Oimp | Bling | Tiny | Stripe | Pagar.me | Adyen | Shopify | Best-of |
|---|---------------------------------------|------|-------|------|--------|----------|-------|---------|---------|
| 1 | Densidade informacional               | 5    | 2     | 2    | 4      | 3        | 4     | 3       | 5       |
| 2 | Hierarquia visual                     | 4    | 3     | 3    | 5      | 4        | 4     | 4       | 5       |
| 3 | Health/Status feedback                | 5    | 1     | 1    | 4      | 3        | 5     | 3       | 5       |
| 4 | Onboarding wizard novo gateway        | 4    | 3     | 3    | 5      | 4        | 4     | 4       | 5       |
| 5 | Edição credencial existente           | 4    | 3     | 3    | 4      | 4        | 4     | 4       | 4       |
| 6 | Webhook UX (URL+HMAC+test)            | 3    | 2     | 2    | 5      | 4        | 4     | 3       | 5       |
| 7 | Multi-ambiente (sandbox/prod)         | 4    | 1     | 1    | 5      | 4        | 5     | 3       | 5       |
| 8 | Multi-conta-bancária                  | 4    | 3     | 3    | 3      | 4        | 5     | 3       | 5       |
| 9 | Audit log per credential              | 1    | 1     | 1    | 5      | 3        | 5     | 3       | 5       |
| 10| Secret handling (mostrar/ocultar)     | 4    | 2     | 2    | 5      | 4        | 5     | 4       | 5       |
| 11| Comparativo drivers (taxa/settlement) | 0    | 0     | 0    | 4      | 3        | 4     | 5       | 5       |
| 12| Migração entre drivers                | 2    | 0     | 0    | 3      | 2        | 4     | 3       | 4       |
| 13| Empty state                           | 4    | 2     | 2    | 5      | 4        | 4     | 5       | 5       |
| 14| Failure recovery (gateway caiu)       | 3    | 1     | 1    | 4      | 3        | 5     | 4       | 5       |
| 15| Microcopy + acessibilidade            | 4    | 3     | 3    | 5      | 4        | 4     | 5       | 5       |
|   | **TOTAL (de 75)**                     | **51** | **27**| **27** | **66** | **53**   | **66**| **56**  | **73**  |

**Nota normalizada 0-100:** Oimp **68** · Bling **36** · Tiny **36** · Stripe **88** · Pagar.me **71** · Adyen **88** · Shopify **75** · Best-of **97**.

> Ajuste qualitativo: oimpresso ganha +10 por SCOPE de mercado (5 drivers BR específicos com mTLS/CNAB/BCB 380 que Stripe não cobre) → **nota final 78/100**.

---

## Análise dimensão a dimensão

### 1. Densidade informacional — oimp 5/5, líder
- **Oimp:** 3 KPIs + tabela + cards drivers disponíveis + alerta warn, tudo em 1280px sem scroll horizontal.
- **Bling/Tiny:** lista plana, sem KPIs no topo, sem "drivers disponíveis" sugerindo próximo passo.
- **Best-of (Adyen):** sidebar filtros + tabela + métricas inline.
- **Gap:** zero. Oimp empata com Adyen e supera concorrência BR.

### 2. Hierarquia visual — oimp 4/5
- **Oimp:** KPIs amber/emerald, DriverChip canon (quadrado colorido + sigla), HealthBadge dots.
- **Stripe:** uso magistral de typography scale; CTAs primárias sempre destacadas.
- **Gap:** "Testar todos" e "+ Novo gateway" competem pelo mesmo peso visual. Stripe colocaria "+ Novo" como CTA azul sólido isolado.

### 3. Health/Status feedback — oimp 5/5, empata com Adyen
- **Oimp:** HealthBadge OK/Degradado/Down + latência ms + "há X tempo" relativo + botão Rodar agora on-demand.
- **Adyen:** real-time data across all channels.
- **Bling/Tiny:** SEM health visível na lista — só sabe que caiu quando cobrança falha. Gap enorme deles, vantagem nossa.

### 4. Onboarding wizard — oimp 4/5
- **Oimp:** SheetNovoGateway 3 steps (Driver → Credenciais → Vínculo conta).
- **Stripe:** wizard inline com hint contextual "onde achar essa chave" + link direto pro painel do parceiro.
- **Gap:** falta link "onde gerar essa credencial" (deep-link pro painel Inter/Asaas/Pagar.me) e validação de formato inline (regex client_id Inter, prefixo `sk_test_` Pagar.me).

### 5. Edição credencial existente — oimp 4/5
- **Oimp:** drawer 4 tabs + "Deixe em branco pra manter valor atual" + senha mTLS reentrada obrigatória.
- **Stripe:** mostra últimos 4 dígitos da chave atual (zero risco vazar) + "última rotação há X dias".
- **Gap:** oimp não exibe "última rotação" nem últimos chars da credencial atual. Stripe sim.

### 6. Webhook UX — oimp 3/5, gap real
- **Oimp:** URL pública copiável + slot HMAC secret + idempotência via `gateway_webhook_events.external_id`. Botão "Rotacionar" rotula errado o toggle de revelar/ocultar (line 402 — UX bug minor: dispara `setRevealSecret(s => !s)` em vez de fluxo de rotação).
- **Stripe (gold):** lista eventos recebidos 7d + status delivered/failed + replay individual + curl de exemplo + signing secret com banner "vai expirar em X". Documentação Hooque/Stripe: zero-downtime rotation com 2 secrets ativos simultaneamente.
- **Pagar.me:** webhook gestão em painel separado, eventos selecionáveis por checkbox.
- **Gap (crítico):** oimp tem `gateway_webhook_events` no DB mas NÃO mostra na UI. Charter declara isso como Onda 5 backlog. Stripe entrega há anos.

### 7. Multi-ambiente — oimp 4/5
- **Oimp:** badge production/sandbox emerald/amber por credencial.
- **Stripe/Xsolla:** banner global "you're in sandbox mode" + toggle global no header pra alternar visão.
- **Gap:** oimp permite ambos ambientes coexistirem por credencial (correto pra ERP multi-tenant), mas falta visão "estou olhando produção" global. Não é gap real — é tradeoff arquitetural.

### 8. Multi-conta-bancária — oimp 4/5
- **Oimp:** FK `account_id` em cada credencial, exibe banco/agência/conta no drawer.
- **Adyen:** mostra fluxo do dinheiro (gateway → settlement → conta) com tempo previsto T+N.
- **Gap:** oimp mostra vínculo, não mostra fluxo. Pra Wagner saber "quanto cai amanhã na C6", precisa ir pra outra tela.

### 9. Audit log per credential — oimp 1/5, **gap maior**
- **Oimp:** `LogsActivity` (Spatie) configurado no model `PaymentGatewayCredential` (line 25, logOnly campos não-sensíveis). MAS UI **não consome** nada disso.
- **Stripe/Adyen:** timeline "Wagner rotacionou client_secret há 2h · IP X · UA Y" visível no drawer.
- **Gap:** dado JÁ EXISTE no DB. Custo: ~30min IA-pair pra renderizar tab "Histórico" lendo `activity_log` filtrado por `subject_type=PaymentGatewayCredential` + `subject_id`. **Alta×Baixa = topo da fila.**

### 10. Secret handling — oimp 4/5
- **Oimp:** type=password + placeholder mascarado + reveal toggle no webhook tab + `config_json` encriptado nunca sai no payload Inertia.
- **Stripe:** mostra últimos 4 chars + "view full key" exige re-auth + log de cada reveal.
- **Gap:** oimp não exige re-auth pra reveal nem logga quem viu.

### 11. Comparativo drivers — oimp 0/5, **gap maior**
- **Oimp:** drivers disponíveis mostram só nome + tipos suportados (boleto/pix/card) + cred resumida. ZERO info comparativa.
- **Shopify (gold):** ao escolher provider, mostra taxa transação, tempo settlement, países suportados, integração 1-click.
- **Stripe:** comparison view payment methods (Apple Pay vs Klarna vs Affirm).
- **Gap:** Wagner/Larissa decide "vou usar Inter ou Asaas pra boleto?" no escuro. Sem dado de taxa, settlement T+1 vs T+2, limite mensal, restrições. **Alta impacto.**

### 12. Migração entre drivers — oimp 2/5
- **Oimp:** PesaPal deprecated banner com botão "Iniciar migração" — mas botão NÃO leva a lugar nenhum funcional (line 357 DrawerGateway).
- **Adyen:** wizard migration com backfill assistido + paralelo (gateway antigo + novo ativos 30d).
- **Gap:** botão fantasma. Pior do que não ter — promete e não entrega.

### 13. Empty state — oimp 4/5
- **Oimp:** ícone Settings + "Nenhum gateway configurado ainda" + CTA "Configurar primeiro gateway" (line 188-197).
- **Shopify:** primeiro acesso mostra recomendação contextual baseada em país/segmento.
- **Gap:** small. Falta nudge "Inter recomendado pra gráficas PJ" pra business segmento gráfico.

### 14. Failure recovery — oimp 3/5
- **Oimp:** HealthBadge down + warn label + ConfirmToggleModal mostra N cobranças em aberto afetadas (Trust L3 canon).
- **Adyen (gold):** quando gateway cai, sugere fallback automático pra alternativa configurada + estimativa de impacto receita.
- **Gap:** oimp avisa o problema, não sugere a solução ("Inter caiu — usar Asaas como fallback nas próximas 4h?").

### 15. Microcopy + acessibilidade — oimp 4/5
- **Oimp:** PT-BR fluente, WCAG 2.1 AA (ESC + focus trap + aria-labels), tabular nums.
- **Stripe/Shopify:** copy ainda mais conciso, screen reader-tested.
- **Gap:** pequeno. Microcopy "deixe em branco pra manter o valor atual" é excelente. Falta hint de keyboard shortcuts visíveis (oimp tem CheatSheet `?` — mas não anuncia descoberta inicial).

---

## Nota final oimpresso: 78/100

### Forças (top 3)
1. **Health visibility per credential** — supera Bling/Tiny (não tem) e empata com Adyen.
2. **Drivers BR específicos com nuances regulatórias** — mTLS Inter, BCB 380/2024, CNAB C6. Stripe não cobre, vantagem competitiva concreta.
3. **Charter + ADRs + Cowork F1.5 gate visual** — processo MWART V4 entrega qualidade 93/100 score. Concorrente nenhum opera com esse rigor documentado.

### Fraquezas (top 3)
1. **Audit log invisível** — dado existe no DB (Spatie LogsActivity), UI não renderiza.
2. **Comparativo drivers ausente** — escolha "no escuro".
3. **Webhook delivery log ausente** — `gateway_webhook_events` existe no DB, UI não consome.

---

## Top 5 gaps priorizados por impacto×esforço (ADR 0106: 10x IA-pair)

| # | Gap                                       | Impacto | Esforço IA-pair | Pré-req                                  | Prio |
|---|-------------------------------------------|---------|------------------|------------------------------------------|------|
| 1 | Tab "Histórico" no drawer (audit log)     | Alta    | ~30min           | Nenhum — Spatie já configurado           | P0   |
| 2 | Webhook delivery log + replay (tab)       | Alta    | ~2h              | Tabela `gateway_webhook_events` existe   | P0   |
| 3 | Comparativo drivers ao escolher novo      | Alta    | ~1h              | Dataset taxas/settlement (research 30min)| P1   |
| 4 | Fix botão "Iniciar migração" (ou remover) | Média   | ~15min           | Decidir: feature real ou remover         | P1   |
| 5 | Hint "onde gerar essa credencial" wizard  | Média   | ~45min           | URLs deep-link de Inter/Asaas/Pagar.me   | P2   |

**Esforço total Top 3 (P0+P1 sem item 5): ~3h45min IA-pair com margem 2x = ~7h30 wall time = 1 sprint de meio-dia.**

---

## Roadmap CONSOLIDAR vs EVOLUIR

### CONSOLIDAR (já tem, precisa polir)
- **Webhook tab:** rename botão "Rotacionar" → fluxo real de rotação (hoje só toggle reveal/hide — line 402 bug minor).
- **Botão "Iniciar migração":** entregar fluxo ou remover (não deixe fantasma).
- **Empty state:** adicionar nudge contextual por segmento de business.
- **Wizard validação inline:** regex `client_id` Inter, prefixo `sk_test_`/`sk_live_` Pagar.me.

### EVOLUIR (não tem, é diferenciador competitivo)
- **Tab Histórico (audit log)** no DrawerGateway — quem rotacionou o que e quando. Spatie já loga, só falta UI.
- **Tab Eventos webhook** — últimos 7d, status delivered/failed, replay individual. Modelo `GatewayWebhookEvent` existe.
- **Comparativo drivers** — tabela taxa/settlement/limite/restrição ao abrir SheetNovoGateway Step 1.
- **Failure recovery sugerido** — quando gateway X cai, popup "ativar Y como fallback?" (precisa cliente piloto reportar dor — ADR 0105 cliente como sinal).
- **Stripe-style: últimos 4 chars + "última rotação há X"** em campos secret.

---

## Recomendação imediata

**Comece pelo Top 1: Tab "Histórico" no DrawerGateway.**

- Impacto alto (transparência crítica em ferramenta que mexe em dinheiro real)
- Esforço mínimo (~30min IA-pair)
- Zero pré-req (Spatie já configurado, dado existe)
- Zero risco multi-tenant (LogsActivity respeita `business_id` via subject relationship)

**Próxima ação hoje:** criar tarefa MCP `tasks-create` no módulo `PaymentGateway` titulo "Tab Histórico no DrawerGateway — consumir LogsActivity já existente" + spec curta apontando pra `Modules/PaymentGateway/Models/PaymentGatewayCredential.php` linha 25-58.

---

## Referências

- [Stripe — Payment method configurations](https://docs.stripe.com/payments/payment-method-configurations)
- [Stripe — Payment method settings (Connect embedded)](https://docs.stripe.com/connect/supported-embedded-components/payment-method-settings)
- [Stripe — Sandbox settings dashboard](https://docs.stripe.com/sandboxes/dashboard/sandbox-settings)
- [Shopify Help — Configuring third-party payment providers](https://help.shopify.com/en/manual/payments/third-party-providers/configuring-providers)
- [Adyen Docs — Manage payment methods in Customer Area](https://docs.adyen.com/platforms/payment-methods/customer-area/)
- [Pagar.me Help — Configurar webhooks](https://pagarme.helpjuice.com/pt_BR/p2-funcionalidades/configura%C3%A7%C3%B5es-como-configurar-webhooks)
- [Vindi — API de Pagamento (webhooks REST)](https://vindi.com.br/recursos/api-de-pagamento/)
- [Hooque — Webhook security: signatures, replay, secret rotation](https://hooque.io/guides/webhook-security/)
- [Hooklistener — Stripe Webhooks Implementation Guide 2026](https://www.hooklistener.com/learn/stripe-webhooks-implementation)
- [GoCardless — Payment UX best practices](https://gocardless.com/guides/posts/payment-ux-best-practices/)
- [Bling — Configurações formas de pagamento](https://ajuda.bling.com.br/) (acesso painel)

---

## Histórico

| Data       | Autor                       | Mudança                                                       |
|------------|-----------------------------|---------------------------------------------------------------|
| 2026-05-23 | Wagner [W] + Claude Code [CL] | Estado-da-arte settings payment gateways — 78/100, 3 gaps P0 |
