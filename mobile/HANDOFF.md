# HANDOFF — Oimpresso Mobile

> Documento de passagem para outra sessão/dev. Estado atual: **0 erros tsc**, design v3 aplicado, 4 telas de detalhe entregues, multi-tenant + offline-first funcional. APK release compilado em `android/app/build/outputs/apk/release/app-release.apk` (53 MB).

---

## 0. Como rodar AGORA

### Pré-requisitos da máquina
- Node 22 portátil em `C:\tools\node-v22.15.0-win-x64\`
- Android SDK em `%LOCALAPPDATA%\Android\Sdk\` (adb em `platform-tools/`)
- Android Studio JBR (Java 21) em `C:\Program Files\Android\Android Studio\jbr\`
- Drizzle DB: TiDB Cloud — `DATABASE_URL` no `.env`

### Subir backend + Metro

```bash
# API tRPC (porta 3000)
cd D:\Mobile\mini-erp-mobile
PATH="/c/tools/node-v22.15.0-win-x64:$PATH" NODE_ENV=development npx tsx watch server/_core/index.ts

# Metro bundler (porta 8081) — outro terminal
PATH="/c/Users/otavi/AppData/Local/Android/Sdk/platform-tools:/c/tools/node-v22.15.0-win-x64:$PATH" \
  REACT_NATIVE_PACKAGER_HOSTNAME=192.168.0.103 EXPO_OFFLINE=1 \
  npx expo start --port 8081 --offline
```

### Abrir no Expo Go

```bash
# Via adb (mais rápido)
adb shell am force-stop host.exp.exponent
adb shell am start -a android.intent.action.VIEW -d "exp://192.168.0.103:8081"
```

OU manualmente no Expo Go: digitar `exp://192.168.0.103:8081`. **Importante**: celular tem que estar na MESMA rede WiFi do PC. Dados móveis não alcança IP local.

### Login admin
- Email: `admin@erp.local`
- Senha: `admin12345`
- Re-seed (idempotente): `npx tsx scripts/seed-admin.ts`

### APK release
- Caminho: `D:\Mobile\mini-erp-mobile\android\app\build\outputs\apk\release\app-release.apk`
- Tamanho: 53 MB
- Recompilar: `cd android && JAVA_HOME="/c/Program Files/Android/Android Studio/jbr" ./gradlew assembleRelease`
- Instalar no device: `adb install -r android/app/build/outputs/apk/release/app-release.apk`

---

## 1. Arquitetura técnica

### Stack
- **Mobile**: Expo SDK 54, React Native 0.81, Expo Router 6 (file-based + stacks)
- **Estilo**: NativeWind 4 + `lib/oi-theme.ts` (primitivos Oi com `useOiTheme()`)
- **Backend**: Express + tRPC v11 + superjson, mesmo processo
- **DB**: Drizzle ORM + MySQL2 → TiDB Cloud
- **Auth**: JWT (jose) — email/senha bcrypt + OAuth Manus; SecureStore mobile / cookie web
- **Estado**: TanStack Query v5 + PersistQueryClient (AsyncStorage 7d)
- **Offline-first**: mutation queue (`lib/mutation-queue.ts`) + NetInfo replay
- **Multitenancy (F3-08)**: `companies` + `companyMembers` + `companyId` em 22 entidades + cookie `current_company_id` + `companyProcedure` middleware
- **Fontes**: IBM Plex Sans (400/500/600/700) + IBM Plex Mono (400/500/600) via `@expo-google-fonts`

### Design system Oimpresso (v3)
- **Brand**: magenta `#c12682`, dark-first, neutros com tinta roxa (hue 322)
- **Tokens**: `lib/oi-theme.ts` (light + dark palettes hex-convertidos de OKLCH)
- **22 primitivos** em `components/oi/*`:
  - Layout: `OiScreen`, `OiHeader`, `OiDetailHeader`, `OiSection`, `OiSectionHeader`, `OiCard`, `OiTabbar`
  - Inputs: `OiSearch`, `OiBtn`, `OiBtnRow`, `OiChip`, `OiChips`, `OiSheet`
  - Display: `OiList`, `OiListRow`, `OiKpi`, `OiKpis`, `OiMoney`, `OiOrigin`, `OiStatus`, `OiProgress`, `OiDl`, `OiDlRow`, `OiAvatar`, `OiBrandLogo`, `OiIcon`, `OiEmpty`, `OiFab`, `OiScanline`, `OiTenantPill`
- **Toolkit wizard**: `components/oi/OiWizard.tsx` (`OiWizardSteps`, `OiField`, `OiInput`, `OiSeg`, `OiCheckRow`, `OiSwitchRow`, `OiPillChip`, `OiDoneCard`)
- **Login**: gradient `brand.deep → brand.purple → brand.magenta` + logo CMYK + 2 CTAs

---

## 2. Estrutura de rotas (expo-router)

### Tabs visíveis (5)
```
app/(tabs)/
  index.tsx       # Início / Dashboard
  tarefas.tsx     # Tarefas (inbox agregada)
  vendas.tsx      # Pedidos (kanban)
  producao.tsx    # Produção (kanban OPs)
  mais.tsx        # Hub: Produtos, Clientes, Estoque, Fiscal, Pagamentos, Relatórios, Chat, etc.
```

### Tabs ocultas (`href: null`) — acessíveis via Mais
```
clientes, produtos, orcamentos, financeiro, chat, estoque, veiculos, oss,
relatorios, pagamentos, fiscal, dashboard
```

### Stack screens (`presentation: "card"`)
```
app/login.tsx
app/oauth/callback.tsx

app/estoque/[id].tsx
app/oss/[id].tsx
app/empresas.tsx

# Clientes (wizard + detail + ficha)
app/clientes/new.tsx
app/clientes/[id]/index.tsx     # detalhe (hero + KPIs + ações + ficha link + pedidos)
app/clientes/[id]/ficha.tsx     # ficha cadastral read-only (5 seções)
app/clientes/[id]/edit.tsx      # wizard 5 etapas (Dados/Contato/Endereço/Comercial/LGPD)

# Produtos (wizard + detail)
app/produtos/new.tsx
app/produtos/[id]/index.tsx     # detalhe (imagem + preço + estoque + specs + fiscal + movimentações)
app/produtos/[id]/edit.tsx      # wizard 5 etapas (Dados/Preços/Estoque/Fiscal/Ficha)

# v3 — 4 telas de detalhe entregues
app/tarefas/[id].tsx            # Detalhe de tarefa (header + hero + task viewer + histórico + Adiar/Concluir)
app/pedidos/[id].tsx            # Detalhe de pedido (stepper 5 etapas + arte + contato + action bar contextual)
app/producao/[id].tsx           # Detalhe de OP (MFG hero + stepper 4 estações + Iniciar/Concluir)
app/venda-rapida.tsx            # PDV: scanner + carrinho + pagamento + recibo
```

---

## 3. Schema do banco

11 migrations em `drizzle/`:
- `0000_elite_eternals` — users
- `0001_wet_human_robot` — produtos/pedidos/ops/transacoes
- `0002_nappy_omega_red` — pushTokens
- `0003_tiny_imperial_guard` — auth password hash
- `0004_plain_iron_lad` + `0005_backfill_customers` — customers + orderItems
- `0006_flashy_franklin_richards` — Phase 2 (CV+MEC+Transversal)
- `0009_minor_stepford_cuckoos` + `0010_backfill_companies` — F3-08 multitenancy
- `0011_payment_idempotency` — idempotency keys + webhookEvents
- `0012_extend_customers_produtos_v2` — wizard fields

### Entidades principais
- `users` (auth)
- `companies` + `companyMembers` (multi-tenant)
- `customers` — multi-papel (`papeis JSON`), PF/PJ, fiscal completo, LGPD
- `produtos` — NCM/CFOP/CEST, margem ao vivo, fornecedor FK, ficha técnica
- `pedidos` + `orderItems` (line items)
- `ops` — OPs de produção (kanban 4-status)
- `serviceOrders` + `serviceOrderItems` + `serviceOrderPhotos` (Mecânica, 9-stage)
- `vehicles` — placa Mercosul/antigo
- `quotes` + `quoteItems` + `priceTables` — orçamento m²
- `artworks` + `artworkApprovals` — arte digital + portal público
- `inventory` + `inventoryMovements` — estoque com FOR UPDATE lock
- `transacoes` — financeiro
- `fiscalDocuments` + `companySettings` — NFe/NFSe
- `paymentLinks` + `webhookEvents` — Asaas com idempotency
- `customerMagicLinks` + `customerSessions` — portal cliente
- `whatsappMessages` — Z-API log
- `pushTokens` — Expo push

### Migration manual (drizzle-kit)
```bash
# Generate
PATH="/c/tools/node-v22.15.0-win-x64:$PATH" npx drizzle-kit generate

# Apply (precisa DATABASE_URL com ssl options)
PATH="/c/tools/node-v22.15.0-win-x64:$PATH" npx drizzle-kit migrate
```

Se der erro de SSL TiDB, ajuste `.env`:
```
DATABASE_URL=mysql://user:pass@host:port/db?ssl={"minVersion":"TLSv1.2","rejectUnauthorized":true}
```

---

## 4. Histórico de fases (resumo)

| Fase | Itens | Nota auditoria |
|---|---|---:|
| F1 (Foundation) | F1-01..06: auth, persistência, edição, busca, push, UUID/ISO | 8.4 |
| F2 (Especialização) | F2-01..08: orçamento m², arte, pipeline CV/MEC, veículos, OS, estoque, WhatsApp | 8.3 |
| F3 (IA + Integração) | F3-01..08: fiscal, relatórios, Asaas, offline-first, portal, IA, multiempresa | 8.25 → 8.4 pós-patches |
| Design v2 (Oimpresso) | Tokens magenta + wizards Cliente/Produto + Login redesign | — |
| Design v3 | 4 telas de detalhe (tarefas/pedidos/producao/venda-rapida) | — |

Audit detalhada da Fase 3 em `STRUCTURE-PROPOSAL.md` + `FSM-ADOPTION-STRATEGY.md` + `TASKS-F4-F5.md`.

---

## 5. O que tem de pendente

### Bugs/UX conhecidos
- **F2-03**: pipeline 7 estágios CV — coluna `ops.pipelineStage` migrada mas UI toggle ainda não construído (TODO comment em `producao.tsx`)
- **F1-04 Mileage GPS** (técnicos externos) — não implementado
- **OCR de recibo/boleto** (F5-02) — não implementado
- **Barcode scanner câmera** — UI hint presente, `expo-camera` não integrado
- **DVI Mecânica** (F5-03) — não implementado
- **E-signature em orçamento** (F5-04) — não implementado
- **CNPJ Receita auto-fetch** — placeholder
- **Imagem produto upload** — TODO no wizard
- **Magic-link one-shot** — atualmente reusável até TTL
- **AI rate limit + prompt sanitization** — pendente
- **MFA TOTP + biometria** (Fase 4) — não implementado
- **Audit log** central — não implementado

### Auditor sênior — P1/P2 abertos
- `pedidos.update.data` validation com `.datetime()` aplicada
- **idempotency end-to-end** em pagamentos (aplicada)
- **FOR UPDATE** em inventory (aplicada)
- **OS approval idempotency** (aplicada)
- **WhatsApp em OS pronta** (aplicado)
- **pedidos.create transactional** (aplicado)

### Adoção (FSM)
Ver `FSM-ADOPTION-STRATEGY.md` — top 3 alavancas pra usuário REAL adotar:
1. Scanner universal (placa/GTIN/QR)
2. Foto antes/depois → WhatsApp automático
3. Histórico do cliente/veículo rico

---

## 6. Decisões arquiteturais importantes

### Por que TanStack persist em vez de WatermelonDB (F3-04)
WatermelonDB exige dev build (não Expo Go), múltiplas semanas de refator de hooks. Entreguei TanStack Query persistence + mutation queue: 80% do valor offline em 10% do tempo. Funciona em Expo Go. Documentado em `OFFLINE.md`.

### Por que 5 tabs visíveis (em vez de 16)
Design Oimpresso v2/v3 segue padrão consagrado (TOTVS, Bling): tab bar com 5 + hub "Mais" para o resto. Reduz cognitive load. Routes hidden continuam funcionais via `router.push()`.

### Multi-papel em `customers` (não tabela separada)
Cliente, Fornecedor, Funcionário, Transportadora — uma única tabela `customers` com coluna `papeis JSON` (multi-select). Evita N tabelas duplicadas. Filtros nos hooks (ex: `useCustomers().filter(c => c.papeis.includes("fornecedor"))`).

### Cookie isolation no F3-08
- Admin: `app_session_id` (auth user)
- Portal cliente: `portal_session_id` (auth customer)
- Empresa ativa: `current_company_id`

Separados para evitar cross-contamination quando user troca empresa ou tem perfil duplo.

### Tokens via `useOiTheme()` em vez de NativeWind dark prefix
NativeWind 4 dark mode tem caveats de re-render. Optei por `useOiTheme()` hook + `palette.X` direto no `style`. Mais previsível, melhor perf, sem flashes. NativeWind ainda usado para `bg-background`/`text-foreground` (alinhados ao tema atual).

### Arquivos `ref/design*` excluídos do tsc
`tsconfig.json` exclui `ref/`, `android/`, `ios/`. Os JSX do design referenciam módulos inexistentes (apenas referência).

---

## 7. Variáveis de ambiente

### Backend (`.env`)
```
DATABASE_URL=mysql://...?ssl={"minVersion":"TLSv1.2","rejectUnauthorized":true}
JWT_SECRET=...
OAUTH_SERVER_URL=https://api.manus.im

# Pagamentos Asaas (F3-03)
ASAAS_API_KEY=
ASAAS_ENV=sandbox
ASAAS_WEBHOOK_TOKEN=

# Fiscal Focus NFe (F3-01)
FOCUS_NFE_TOKEN=
FOCUS_NFE_ENV=homologacao

# WhatsApp Z-API (F2-08)
ZAPI_INSTANCE_ID=
ZAPI_TOKEN=
ZAPI_CLIENT_TOKEN=
```

### Cliente (`.env` com prefixo `EXPO_PUBLIC_`)
```
EXPO_PUBLIC_API_BASE_URL=http://192.168.0.103:3000  # IP do PC na rede local
EXPO_PUBLIC_APP_ID=...
EXPO_PUBLIC_OAUTH_PORTAL_URL=https://manus.im
EXPO_PUBLIC_OAUTH_SERVER_URL=https://api.manus.im
EXPO_PUBLIC_OWNER_OPEN_ID=...
EXPO_PUBLIC_OWNER_NAME=...
```

**Importante**: ao mudar de rede WiFi, atualizar `EXPO_PUBLIC_API_BASE_URL` com o IP novo do PC e reiniciar Metro.

---

## 8. Comandos úteis

```bash
# Type-check
PATH="/c/tools/node-v22.15.0-win-x64:$PATH" npx tsc --noEmit

# Seed admin (idempotente)
PATH="/c/tools/node-v22.15.0-win-x64:$PATH" npx tsx scripts/seed-admin.ts

# Drizzle generate + migrate
npx drizzle-kit generate
npx drizzle-kit migrate

# Compilar APK release
cd android
JAVA_HOME="/c/Program Files/Android/Android Studio/jbr" \
  ./gradlew assembleRelease

# Test login direto API
curl -X POST http://192.168.0.103:3000/api/trpc/auth.loginWithPassword \
  -H "Content-Type: application/json" \
  -d '{"json":{"email":"admin@erp.local","password":"admin12345"}}'
```

---

## 9. Documentos de referência neste projeto

- `OI-DESIGN.md` — uso dos primitivos Oi
- `OFFLINE.md` — limitações do modo offline atual
- `STRUCTURE-PROPOSAL.md` — proposta de RBAC + Home contextual + telas dedicadas
- `FSM-ADOPTION-STRATEGY.md` — Fogg + Hook Model + padrões Field Service
- `TASKS-F4-F5.md` — roadmap completo Fase 4 + 5 (chegar a 9.7-9.8)
- `HANDOFF.md` — este documento
- `ref/design-v3/` — última versão do design Claude
- `ref/design-v2/`, `ref/design/` — versões anteriores
- `references/periodic-updates.md` — cron pattern do template

---

## 10. Próximos passos sugeridos

### Curto prazo (1 sprint)
1. **Testar fluxo completo no APK** — login, criar cliente via wizard, criar pedido, avançar status, abrir detalhe
2. **Wirar tarefas/[id].tsx no tap** — atualmente tab tarefas usa Tarefa LOCAL (não `useTarefas()`). Migrar para o hook e linkar
3. **Mileage cleanup**: substituir TODO de imagem do produto, scanner GTIN câmera (`expo-camera`)
4. **Audit log** central (Fase 4.1) — destrava LGPD compliance

### Médio prazo (1-2 meses)
5. MFA TOTP + biometria (Fase 4.2)
6. DVI Mecânica (Fase 5.3) — diferenciação vs AutoLeap/Shopmonkey
7. OCR de recibo (Fase 5.2)
8. Billing Stripe/Asaas (Fase 5.9) — destrava receita

### Long shot
9. WatermelonDB real (Fase 5+) — quando justificar dev build
10. i18n (es/en) — só se houver mercado fora do Brasil

---

## 11. Contato com sessão anterior

Esta sessão (Claude Code) acumulou:
- 25 tabelas
- 17 routers tRPC
- 16 tabs + 14 stack screens
- 11 migrations
- ~3 fases completas + design v3
- 0 erros tsc no fim

Tudo persiste em `D:\Mobile\mini-erp-mobile`. Não há repo git inicializado — primeiro passo de outra sessão deveria ser `git init` para tracking.

**Decisões tomadas que outra sessão NÃO deveria reverter** (sem boa razão):
- Magenta como cor primária (brand)
- Multi-papel via JSON em customers
- Tab consolidation 5+11 hidden
- TanStack persist em vez de WatermelonDB
- Wizards em vez de modals para Cliente/Produto
- `useOiTheme()` para cores dinâmicas (em vez de NativeWind dark prefix)

---

Documento revisado em 29 de maio de 2026.
