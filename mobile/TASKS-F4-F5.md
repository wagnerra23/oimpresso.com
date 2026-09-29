# TASKS — Oimpresso Mobile · Fase 4 e Fase 5

> **Continuação do `TASKS.md` original** após conclusão das Fases 1, 2 e 3 (8/8 itens entregues + 2 patches P0 pós-audit). Status atual auditado: **8.4/10**. Objetivo deste roadmap: alcançar **9.7-9.8/10** em 6-10 semanas.

Status: `[ ]` pendente · `[~]` em andamento · `[x]` concluído · `[!]` bloqueado

---

## ✅ Resumo do que já foi entregue

### Fase 1 — Fundação (8.4/10 final)
- [x] F1-01 Autenticação (OAuth + Email/Senha + admin seedado)
- [x] F1-02 Sincronização com Backend (Drizzle MySQL + tRPC v11 + TanStack Query v5)
- [x] F1-03 Edição de Pedidos e OPs
- [x] F1-04 Busca e Filtros (debounce 300ms + período no Financeiro)
- [x] F1-05 Notificações Push (expo-notifications + tap-to-navigate)
- [x] F1-06 Correções Técnicas Base (UUID + ISO 8601 + returnKeyType)

### Fase 2 — Especialização Setorial (8.3/10 pós-patches)
- [x] F2-01 Calculadora de Orçamento por m² + PDF (pdfkit)
- [x] F2-02 Gestão de Arte e Aprovação Digital (link público `/a/:token`)
- [x] F2-03 Pipeline Expandido CV — coluna `pipelineStage` (UI toggle ainda TODO)
- [x] F2-04 Cadastro de Veículos com Histórico
- [x] F2-05 Ordem de Serviço Completa (peças+MO+fotos+aprovação)
- [x] F2-06 Pipeline 9 Estágios Mecânica
- [x] F2-07 Controle de Estoque (movimentações + baixa automática + FOR UPDATE lock)
- [x] F2-08 Integração WhatsApp (Z-API + templates + triggers)

### Fase 3 — Inteligência e Integração (8.25 → 8.4 pós-fixes)
- [x] F3-01 Emissão Fiscal NFe/NFSe (Focus NFe)
- [x] F3-02 Relatórios + Exportação PDF/Excel (DRE, vendas, produção, estoque)
- [x] F3-03 Gateway de Pagamento Asaas + webhook + idempotency end-to-end
- [x] F3-04 Offline-First pragmático (TanStack persistence + mutation queue + NetInfo)
- [x] F3-05 Portal Web do Cliente (magic-link + SSR)
- [x] F3-06 IA Sugestão de Diagnóstico (Mecânica) via invokeLLM
- [x] F3-07 IA Precificação de Orçamentos (CV) via invokeLLM
- [x] F3-08 Multiempresa (22 tabelas com `companyId` + switcher + cookie + cache isolado)

**Estado atual**: 25 tabelas, 17 routers, 16 tabs, 4 stack screens, 5 rotas Express públicas, 11 migrations, 0 erros TS, roda em Expo Go.

---

## 🟣 FASE 4 — Hardening + UX Polish (4-6 semanas → ~9.4)

> Fechar dívida técnica conhecida + LGPD compliance + polish que separa "app de dev" de "app de produto".

---

### [F4-01] Audit Log Central
**Prioridade:** P0 (LGPD bloqueante) | **Esforço:** Médio | **Impacto:** +0.2

**O que fazer:**
- Nova tabela `auditLogs` (id, userId, companyId, acao, entidade, entidadeId, diff JSON, ipAddress, userAgent, createdAt)
- Middleware tRPC que intercepta TODAS mutations dos routers de domínio e persiste o log
- Tela `app/auditoria.tsx` (admin-only) com filtros por usuário/entidade/período
- Export CSV do log

**Critério de aceite:**
- Toda mutação (create/update/delete) gera entrada no audit log
- Diff guarda before/after dos campos alterados
- Admin pode buscar quem alterou X em Y data
- Logs imutáveis (sem update/delete via API)

**Arquivos relevantes:** `server/_core/trpc.ts`, `drizzle/schema.ts`, `server/routers/audit.ts` (novo)

---

### [F4-02] MFA TOTP + Biometria
**Prioridade:** P0 (LGPD bloqueante) | **Esforço:** Médio | **Impacto:** +0.2

**O que fazer:**
- Instalar `expo-local-authentication` + `otplib`
- Opt-in de MFA via TOTP (Google Authenticator / Authy) no perfil
- Backup codes (10 únicos) gerados na ativação
- Biometria (Face ID / Fingerprint) como segundo fator local para abrir o app
- Endpoint `auth.enableMfa({ secret, code })` e `auth.verifyMfa({ code })`
- Tela `app/seguranca.tsx` com QR code + backup codes

**Critério de aceite:**
- Usuário ativa MFA → próximo login pede código TOTP
- Backup codes funcionam 1× cada
- Biometria desbloqueia app sem digitar senha (até timeout de 24h)
- Disable MFA exige código atual + senha

**Arquivos relevantes:** `server/routers.ts` (auth), `lib/auth-context.tsx`, `app/seguranca.tsx` (novo)

---

### [F4-03] Rate Limit IA + Sanitização de Prompt Injection
**Prioridade:** P0 (custo + segurança) | **Esforço:** Pequeno | **Impacto:** +0.1

**O que fazer:**
- Tabela `aiUsage` (companyId, day, callCount, tokenCount)
- Middleware no `aiRouter` que verifica cota diária (default 50 calls/dia)
- Sanitização de `queixaCliente` e descrições: truncar a 500 chars, remover sequências `\n\n` repetidas, escapar tags `<>{}`
- Resposta `429 Too Many Requests` com `retryAfter` quando estoura cota
- Plano premium: cota maior configurável por company

**Critério de aceite:**
- Após 50 chamadas/dia, IA bloqueia com mensagem amigável
- Prompts hostis não conseguem mudar formato JSON da resposta
- Admin vê uso de IA no `app/(tabs)/relatorios.tsx`

**Arquivos relevantes:** `server/routers/ai.ts`, `drizzle/schema.ts`

---

### [F4-04] Magic Link One-Shot + queryClient.clear no switchCompany
**Prioridade:** P0 (segurança + UX) | **Esforço:** Pequeno | **Impacto:** +0.1

**O que fazer:**
- `customerMagicLinks.usedAt` agora invalida o token no primeiro `redeem` (emite cookie session)
- TTL do magic-link reduzido para 24h default (era 7d)
- Em `lib/auth-context.tsx switchCompany`: chamar `queryClient.clear()` ANTES de `utils.invalidate()` para eliminar flash de dados antigos

**Critério de aceite:**
- Magic-link aberto 2× redireciona ao login direto na 2ª (sem ativar nova session)
- Switch de empresa: nenhum frame mostra dados da empresa anterior
- Logout limpa cookies portal_session + current_company_id

**Arquivos relevantes:** `server/_core/customerPortal.ts`, `lib/auth-context.tsx`

---

### [F4-05] Retry Exponencial em Focus NFe e Asaas + DLQ na Mutation Queue
**Prioridade:** P0 | **Esforço:** Pequeno | **Impacto:** +0.1

**O que fazer:**
- Wrapper `withRetry(fn, { maxAttempts: 3, baseMs: 1000 })` em `server/_core/focusnfe.ts` e `server/_core/asaas.ts` (backoff: 1s, 2s, 4s)
- Diferenciar erros transient (5xx, network) de permanent (4xx) — só retry no primeiro
- Na mutation queue do cliente: após 5 tentativas falhando, mover para `dead-letter-queue-${companyId}` no AsyncStorage
- UI mostra DLQ com botão "Tentar novamente" ou "Descartar"

**Critério de aceite:**
- 5xx transitório do Focus/Asaas reprocessa automaticamente
- Erro 422 (dados inválidos) NÃO retenta
- DLQ visível ao usuário com possibilidade de reprocessar manualmente

**Arquivos relevantes:** `server/_core/focusnfe.ts`, `server/_core/asaas.ts`, `lib/mutation-queue.ts`

---

### [F4-06] Índices Compostos para Queries Pesadas
**Prioridade:** P1 | **Esforço:** Pequeno | **Impacto:** +0.1

**O que fazer:**
- Adicionar índices: `(companyId, createdAt)` em `transacoes`, `pedidos`, `serviceOrders`, `ops`
- Adicionar `(companyId, status)` em `pedidos`, `ops`, `serviceOrders`
- Adicionar `(companyId, data)` em `transacoes`
- EXPLAIN das top 10 queries (DRE, vendas por período, lista filtrada) — verificar uso dos índices
- Migration `0012_composite_indexes.sql`

**Critério de aceite:**
- DRE de 90 dias executa em <200ms com 10k transações
- EXPLAIN mostra `Using index` em todas listagens filtradas

**Arquivos relevantes:** `drizzle/schema.ts`

---

### [F4-07] Sentry + Breadcrumbs tRPC
**Prioridade:** P1 | **Esforço:** Pequeno | **Impacto:** +0.1

**O que fazer:**
- Instalar `@sentry/react-native` (cliente) + `@sentry/node` (server)
- Configurar DSN via env var `SENTRY_DSN`
- Capturar erros não-tratados, breadcrumbs de navegação e tRPC mutations
- Filtrar PII (senhas, tokens) antes do envio
- Source maps automáticos em build de produção

**Critério de aceite:**
- Erro em produção aparece no Sentry com stack trace + breadcrumbs
- Tokens nunca vazam para o Sentry
- Release tracking funciona por versão do app.json

**Arquivos relevantes:** `app/_layout.tsx`, `server/_core/index.ts`

---

### [F4-08] Skeletons + Pull-to-Refresh + Swipe Actions Padronizados
**Prioridade:** P1 | **Esforço:** Médio | **Impacto:** +0.15

**O que fazer:**
- Componente `<SkeletonCard />` reutilizável (anim com Reanimated)
- Substituir todos `<ActivityIndicator />` em listas por skeletons
- `RefreshControl` em todas ScrollViews/FlatLists
- Swipe-actions usando `react-native-swipe-list-view` ou Reanimated: swipe-left para editar, swipe-right para arquivar/deletar
- Aplicar em produtos, pedidos, OPs, OSs, clientes, transações, orçamentos

**Critério de aceite:**
- Toda lista mostra skeleton durante fetch inicial
- Toda lista atualiza via pull-down
- Swipe revela ações sem precisar abrir card

**Arquivos relevantes:** `components/skeleton.tsx` (novo), todas as `app/(tabs)/*.tsx`

---

### [F4-09] Acessibilidade — Cobertura ≥80%
**Prioridade:** P1 | **Esforço:** Médio | **Impacto:** +0.1

**O que fazer:**
- `accessibilityLabel` + `accessibilityRole` + `accessibilityHint` em todos `Pressable`, `TextInput`, `Switch`
- `accessibilityState` em tabs ativas, toggles, checkboxes
- VoiceOver/TalkBack pass manual em 5 fluxos críticos (login, criar pedido, avançar OS, gerar PDF, switch empresa)
- Contraste mínimo WCAG AA verificado em tokens de cor
- Documento `ACCESSIBILITY.md` com cobertura

**Critério de aceite:**
- 80%+ dos elementos interativos têm labels
- TalkBack consegue completar criação de pedido sem dicas visuais
- ESLint plugin `eslint-plugin-jsx-a11y` ativo

**Arquivos relevantes:** todos os componentes interativos

---

### [F4-10] Onboarding 3 Passos + Sample Data + Tour
**Prioridade:** P1 | **Esforço:** Médio | **Impacto:** +0.1

**O que fazer:**
- Nova rota `app/onboarding.tsx` exibida na primeira sessão pós-cadastro
- Passo 1: vertical (CV/Mecânica/Outro) — define configurações padrão
- Passo 2: dados da empresa (CNPJ, telefone, logo) — preenche `companySettings`
- Passo 3: criar primeira entidade (produto/cliente) com tooltip explicando
- Botão "Carregar dados de exemplo" → seed de 5 clientes, 10 produtos, 3 pedidos exemplo
- Tour interativo via `react-native-copilot` ou similar

**Critério de aceite:**
- Novo usuário sai do onboarding com app utilizável
- Sample data pode ser deletada num clique
- Tour pula com "X" no canto

**Arquivos relevantes:** `app/onboarding.tsx` (novo), `lib/auth-context.tsx`

---

### [F4-11] Empty States Ilustrados
**Prioridade:** P2 | **Esforço:** Pequeno | **Impacto:** +0.05

**O que fazer:**
- Componente `<EmptyState illustration title description ctaLabel onCtaPress />`
- Ilustrações SVG simples (linha minimalista) por contexto: sem pedidos, sem clientes, sem estoque, etc.
- CTA contextual: "+ Criar primeiro pedido"
- Substituir todos os "Nenhum X cadastrado" simples por este componente

**Critério de aceite:**
- Cada lista vazia tem ilustração + CTA claro
- Componente reusável em qualquer entidade

**Arquivos relevantes:** `components/empty-state.tsx` (novo)

---

### [F4-12] EAS Build + EAS Submit Pipeline
**Prioridade:** P1 | **Esforço:** Médio | **Impacto:** +0.1

**O que fazer:**
- Configurar `eas.json` com profiles: development, preview, production
- Build canais: dev (interno), preview (TestFlight/Internal), production (stores)
- Pipeline GitHub Actions (ou similar) que faz `eas build` em push para `main` + `eas submit` em tag
- Documento `BUILD.md` com instruções

**Critério de aceite:**
- `eas build --profile production --platform all` gera artifacts
- Tag `v1.x.x` dispara submit automático
- Source maps subidos pro Sentry pelo pipeline

**Arquivos relevantes:** `eas.json`, `.github/workflows/build.yml`, `BUILD.md` (novos)

---

## 🟢 FASE 5 — Feature Parity Premium (3-6 meses → 9.7-9.8)

> Diferenciação vertical + paridade com líderes (AutoLeap, Shopmonkey, Printavo, Bling, Omie).

---

### [F5-01] Barcode/QR Scanner para Estoque e Produtos
**Prioridade:** P0 | **Esforço:** Médio | **Impacto:** +0.1

**O que fazer:**
- Instalar `expo-camera` com `BarCodeScanner`
- Botão "📷 Escanear" em estoque, produtos, orçamentos
- Detecta EAN-13, Code-128, QR
- Auto-preenche código quando scanner encontra match na base
- Gerar QR único por produto/item de estoque para impressão de etiqueta

**Critério de aceite:**
- Funciona iOS + Android + Expo Go (se possível) e dev build
- Latência <500ms entre scan e auto-fill
- Histórico de scans recentes

**Arquivos relevantes:** `app/(tabs)/estoque.tsx`, `app/(tabs)/produtos.tsx`, `components/barcode-scanner.tsx` (novo)

---

### [F5-02] OCR de Recibo / Boleto → Transação Automática
**Prioridade:** P0 | **Esforço:** Grande | **Impacto:** +0.15

**O que fazer:**
- Botão "📸 Capturar recibo" em transações + pagamentos
- Foto → upload S3 → análise via Claude vision OR Google Vision API
- Extrai: valor, data, descrição, código de barras (boletos)
- Preenche formulário de transação automaticamente — usuário confirma
- Para boletos: extrai linha digitável + valida dígito verificador, oferece pagamento via Asaas

**Critério de aceite:**
- Recibo legível extrai valor correto em >85% dos casos
- Boleto preenche linha digitável + vencimento + valor
- Falha cai para preenchimento manual sem perder a foto

**Arquivos relevantes:** `server/_core/ocr.ts` (novo), `app/(tabs)/financeiro.tsx`, `app/(tabs)/pagamentos.tsx`

---

### [F5-03] DVI (Digital Vehicle Inspection) Mecânica
**Prioridade:** P0 (diferenciação vertical) | **Esforço:** Grande | **Impacto:** +0.2

**O que fazer:**
- Nova entidade `vehicleInspections` vinculada a OS
- Template de checklist configurável (motor, suspensão, freios, fluidos, pneus, elétrica, etc.) — cada item com 3 estados: 🟢 OK / 🟡 Atenção / 🔴 Crítico
- Cada item permite anexar foto/vídeo + nota
- Geração de PDF de inspeção com semáforo visual
- Link público compartilhável com cliente: cliente vê inspeção, aprova trabalhos sugeridos com checkbox + e-signature
- Integração: itens críticos viram items na OS automaticamente

**Critério de aceite:**
- Mecânico completa inspeção de 30 itens em <10min no celular
- Cliente recebe link, vê semáforo, aprova/rejeita itens individualmente
- Aprovação gera items na OS

**Arquivos relevantes:** `drizzle/schema.ts`, `server/routers/inspections.ts` (novo), `app/oss/[id].tsx`, `server/_core/inspectionPortal.ts` (novo)

---

### [F5-04] Aprovação Online de Orçamento com E-Signature
**Prioridade:** P0 | **Esforço:** Médio | **Impacto:** +0.1

**O que fazer:**
- Expandir `/portal/orcamento/:id` com canvas de assinatura (`react-native-signature-canvas` ou via Web Canvas)
- Capturar assinatura em base64, persistir em `quoteApprovals` (cliente_nome, signature_image_key, ip, timestamp)
- Carimbo timestamp digital com hash do conteúdo do orçamento (impede contestação posterior)
- E-mail automático ao admin quando aprovado
- PDF do orçamento aprovado tem assinatura embutida

**Critério de aceite:**
- Cliente assina no celular/desktop sem login
- Aprovação registra IP + user-agent + hash do documento
- PDF gerado pós-aprovação tem assinatura na última página

**Arquivos relevantes:** `server/_core/customerPortal.ts`, `server/routers/quotes.ts`

---

### [F5-05] NFCe + CT-e + MDF-e
**Prioridade:** P1 | **Esforço:** Grande | **Impacto:** +0.1

**O que fazer:**
- Estender `fiscalDocuments.tipo` para incluir `NFCe`, `CTe`, `MDFe`
- NFCe: emissão direta no PDV mobile (consumidor final, varejo)
- CT-e: para transportes (oficinas que entregam veículo retirado)
- MDF-e: manifesto eletrônico de documentos fiscais
- Cada um requer body distinto no Focus NFe — implementar builders separados
- Impressão NFCe via impressora Bluetooth (suporte futuro)

**Critério de aceite:**
- NFCe emitida em <5s, QR code SAT impresso/exibido
- CT-e válido em homologação SEFAZ
- MDF-e fecha viagem corretamente

**Arquivos relevantes:** `server/_core/focusnfe.ts`, `server/routers/fiscal.ts`, `app/(tabs)/fiscal.tsx`

---

### [F5-06] Marketplace de Integrações
**Prioridade:** P1 | **Esforço:** Grande | **Impacto:** +0.1

**O que fazer:**
- Tabela `integrations` (companyId, provider, status, config JSON, lastSyncAt)
- Integrações prioritárias:
  - **Mercado Livre / Shopify** (sync de pedidos + estoque) — destino: pedidos
  - **Bling Hub** — import de produtos
  - **Google Calendar / Outlook** — agendar OS, follow-up
  - **Google Drive / Dropbox** — backup de arquivos
- UI em `app/(tabs)/integracoes.tsx` mostra cards das integrações disponíveis + status
- Cada integração tem OAuth wizard próprio

**Critério de aceite:**
- Pedido vendido no Mercado Livre aparece na aba Vendas em <5min
- Calendário do Google mostra agendamentos de OS
- Desativar integração para sync sem perder dados existentes

**Arquivos relevantes:** `drizzle/schema.ts`, `server/routers/integrations.ts` (novo), `app/(tabs)/integracoes.tsx` (novo)

---

### [F5-07] Internacionalização (i18n)
**Prioridade:** P2 | **Esforço:** Médio | **Impacto:** +0.05

**O que fazer:**
- Instalar `i18next` + `react-i18next` + `expo-localization`
- Extrair todas strings hardcoded para `locales/pt-BR.json`, `en.json`, `es.json`
- Formatação de moeda/data/número via `Intl` com locale do usuário
- Seletor de idioma no perfil
- Server-side: Accept-Language header → respostas localizadas

**Critério de aceite:**
- Trocar para inglês muda UI inteiro
- Datas exibidas no formato local (MM/DD vs DD/MM)
- Moedas: BRL, USD, EUR conforme empresa

**Arquivos relevantes:** `locales/*.json` (novos), todos os componentes com texto

---

### [F5-08] Mileage GPS para Técnicos Externos
**Prioridade:** P2 | **Esforço:** Médio | **Impacto:** +0.05

**O que fazer:**
- Tabela `mileageTrips` (userId, companyId, vehicleId, startLocation, endLocation, distanceKm, purpose, osId nullable)
- Botão "Iniciar viagem" usa `expo-location` em background (com permissão)
- Auto-detect chegada via geofencing (50m de raio)
- Relatório mensal de quilometragem por técnico para reembolso
- Integração com Google Maps para verificar rotas

**Critério de aceite:**
- Trip iniciada continua mesmo com app em background
- Bateria <2% de impacto/dia
- Export PDF do relatório mensal

**Arquivos relevantes:** `drizzle/schema.ts`, `server/routers/mileage.ts` (novo), `app/(tabs)/mileage.tsx` (novo)

---

### [F5-09] Billing (Stripe ou Asaas) com Planos Free/Pro/Business
**Prioridade:** P1 (destrava receita) | **Esforço:** Grande | **Impacto:** +0.1

**O que fazer:**
- Tabela `subscriptions` (companyId, plan, status, currentPeriodEnd, providerSubscriptionId)
- 3 planos:
  - **Free**: 50 pedidos/mês, 1 usuário, sem NFe
  - **Pro** (R$ [redacted Tier 0]/mês): ilimitado, 3 usuários, NFe ilimitada, IA com cota
  - **Business** (R$ [redacted Tier 0]/mês): ilimitado, multiempresa, IA sem cota, BI avançado, prioridade no suporte
- Paywall em features por plano (decorator `requirePlan(plans[])`)
- Trial de 14 dias automático ao criar conta
- Cancelamento self-service com retenção de dados por 30 dias
- Integração com Asaas (cliente do projeto já) para boleto/PIX/cartão recorrente

**Critério de aceite:**
- Usuário em trial vê banner com dias restantes
- Excedeu cota free → modal de upgrade
- Downgrade preserva dados mas bloqueia features
- Cancelamento envia confirmação por email

**Arquivos relevantes:** `drizzle/schema.ts`, `server/_core/billing.ts` (novo), `server/_core/trpc.ts`, `app/billing.tsx` (novo)

---

### [F5-10] Voz para Notas em OS
**Prioridade:** P2 | **Esforço:** Pequeno | **Impacto:** +0.05

**O que fazer:**
- Botão "🎤 Ditar" em campos de queixa/diagnóstico/observações
- Integração com `expo-speech-recognition` (ou Whisper API via server)
- Transcrição automática para pt-BR
- Edição manual após ditado

**Critério de aceite:**
- 5s de ditado vira texto editável
- Funciona offline (fallback para Whisper online se precisão baixa)

**Arquivos relevantes:** `app/oss/[id].tsx`, `components/voice-input.tsx` (novo)

---

### [F5-11] Dashboard Executivo com Drill-Down
**Prioridade:** P1 | **Esforço:** Médio | **Impacto:** +0.1

**O que fazer:**
- Reformular `app/(tabs)/dashboard.tsx` (ou criar `app/(tabs)/executivo.tsx`)
- KPIs principais: receita do mês, ticket médio, pedidos abertos, OSs em andamento, estoque crítico, contas a receber
- Cada card é tappable → drill-down filtrado
- Comparativo período anterior (mês vs mês, ano vs ano) com %delta
- Mini-charts inline (sparkline 30 dias)
- Filtro de empresa (se multi)

**Critério de aceite:**
- Tap em "Receita R$ [redacted Tier 0]" abre `relatorios.tsx` filtrado no mesmo período
- Sparklines renderizam em <100ms
- Cards reordenáveis pelo usuário

**Arquivos relevantes:** `app/(tabs)/index.tsx`, `app/(tabs)/relatorios.tsx`

---

### [F5-12] Testes E2E Maestro (ou Detox)
**Prioridade:** P1 | **Esforço:** Médio | **Impacto:** +0.05

**O que fazer:**
- Instalar Maestro (recomendado pela simplicidade YAML)
- Suites E2E cobrindo:
  - Auth: login senha + login OAuth (mock)
  - Venda: criar pedido com 2 itens, avançar status, emitir NFe
  - OS: criar OS com peças+serviços, gerar link aprovação, aprovar via portal
  - Pagamento: gerar link Asaas, simular webhook, verificar status
  - Multiempresa: switch e isolamento
- Pipeline CI roda E2E em emulador a cada PR

**Critério de aceite:**
- 5 suites E2E passam em <10min total
- Quebra impede merge

**Arquivos relevantes:** `.maestro/*.yaml` (novos), `.github/workflows/e2e.yml` (novo)

---

## 📊 Resumo de Impacto

| Fase | Esforço total | Nota alvo | Δ |
|---|---|---|---|
| Status atual | — | 8.4 | — |
| **Fase 4 completa** | 4-6 semanas | ~9.4 | **+1.0** |
| **Fase 5 completa** | 3-6 meses | 9.7-9.8 | **+0.3-0.4** |

> 10/10 é mito. 9.7-9.8 é o teto realista para ERP mobile maduro.

---

## 🎯 Top 3 Ações de Maior Alavancagem (custo/benefício)

1. **F4-01 + F4-02** (Audit log + MFA + biometria) — destrava conformidade LGPD e mercado enterprise. ~1 sprint. **+0.4**
2. **F5-03 + F5-04** (DVI mecânica + e-signature) — único vetor real de diferenciação na vertical Mecânica vs. AutoLeap/Shopmonkey, replica padrão para Printavo na CV. ~2 sprints. **+0.3**
3. **F4-08 + F4-10** (Skeletons + swipe + onboarding tour) — sobe percepção de qualidade em 1 nível inteiro, separa "app de dev" de "app de produto". Esforço médio. **+0.25**

---

## ⚠️ Caminhos a Evitar

- **Não persiga 10/10** — sempre haverá algo a melhorar.
- **Não internacionalize antes do mercado** — F5-07 só se já tiver cliente fora do Brasil.
- **Não construa marketplace de integrações antes de ter clientes pagantes** — F5-06 vem depois do F5-09 (billing).
- **Faça billing primeiro** (F5-09) — sem receita, decisões viram política interna sem dados.

---

## 📚 Benchmarks Consultados

### Brasileiros
- Bling (Olist) — bling.com.br
- Omie — omie.com.br
- Conta Azul de Bolso — getapp.com/operations-management-software/a/contaazul
- Tiny ERP — tiny.com.br
- TOTVS Meu Protheus — play.google.com

### Vertical Mecânica
- **AutoLeap** — autoleap.com/features/technician-app
- **Shopmonkey** — shopmonkey.io/product/digital-vehicle-inspection
- **Tekmetric** — tekmetric.com

### Vertical CV (Comunicação Visual)
- **Printavo** — printavo.com/features
- **shopVOX** — shopvox.com/print-shop-software

### Globais
- QuickBooks Mobile — quickbooks.intuit.com/accounting/mobile
- Zoho Books / Expense — zoho.com/us/expense
- SAP Business One Sales/Service — help.sap.com
- FreshBooks, Wave Mobile, Xero

---

## Notas para a IA Desenvolvedora

- **Continuar respeitando** as convenções existentes (TypeScript strict, NativeWind, ScreenContainer, optimistic updates, transações onde houver multi-table writes).
- **Sempre executar `npx tsc --noEmit`** ao fim de cada feature — meta é 0 erros.
- **Migrations** sequencialmente numeradas (próxima: 0012); coexistir com agentes paralelos renomeando se houver collision.
- **Tenant isolation**: todo router novo de domínio usa `companyProcedure` e `eq(table.companyId, ctx.companyId)`.
- **F3-08 multiempresa** está em vigor — toda entidade nova precisa de `companyId` FK + index.
- **Append-only no schema** ao trabalhar em paralelo.
- **Verificar `OFFLINE.md`** antes de adicionar mutation crítica (decidir se precisa de idempotencyKey).
- **Português pt-BR** em toda UI até F5-07 ser implementado.
- **Fire-and-forget** padrão para integrações externas (WhatsApp, push, IA) — nunca bloqueia mutação principal.
