# Oimpresso ERP

ERP vertical para **gráficas / comunicação visual** (Brasil). Multi-tenant, PT-BR.
Stack: **React 19 + TypeScript · tRPC · Drizzle · PostgreSQL**.

> Este repositório está em **Fase 0 — Refundação** (ver `docs/handoff_oimpresso/BUILD-GUIDE.md`). A fundação de dados, numeração, dinheiro, auditoria e tokens já está semeada. Features de domínio (orçamento, fiscal, financeiro) vêm depois, na ordem do cronograma.

## Estrutura
```
oimpresso-erp/
├── apps/
│   └── server/                     # API tRPC
│       └── src/
│           ├── index.ts            # ✅ entry HTTP (Passo 2)
│           ├── db/
│           │   ├── client.ts       # ✅ pool + drizzle
│           │   ├── schema.ts       # ✅ modelo de dados (Drizzle) — Passo 1
│           │   ├── rls.sql         # ✅ Row-Level Security por tenant — Passo 3
│           │   └── seed.ts         # ✅ prova a DoD (rename preserva, nº distintos)
│           ├── lib/
│           │   ├── sequences.ts    # ✅ numeração atômica server-side
│           │   └── audit.ts        # ✅ audit log append-only
│           ├── domain/
│           │   ├── policy.ts       # ✅ autorização RBAC (servidor)
│           │   └── policy.test.ts  # ✅ teste (Passo 4)
│           ├── auth/
│           │   ├── session.ts      # ✅ sessão → AuthUser
│           │   ├── password.ts     # ✅ senha+2FA (primário)
│           │   └── oauth.ts        # ✅ OAuth (secundário)
│           ├── trpc/
│           │   ├── trpc.ts         # ✅ procedures (protected/public)
│           │   ├── context.ts      # ✅ escopo de tenant (alimenta RLS)
│           │   └── appRouter.ts    # ✅ router raiz
│           └── routers/
│               └── pedidos.ts      # ✅ fatia vertical de exemplo (o molde)
├── packages/
│   ├── shared/src/
│   │   ├── money.ts (+test)        # ✅ centavos + format PT-BR
│   │   └── contracts.ts (+test)    # ✅ zod schemas + tipos (client/server)
│   └── tokens/
│       └── tokens.css              # ✅ design tokens (claro/escuro) — Passo 5
└── docs/handoff_oimpresso/         # handoff completo (copie a pasta para cá)
```
`*` money fica em `packages/shared` para uso no client e no server.

## O que a Fase 0 já garante (Definição de Pronto)
- **Integridade referencial:** pedido→cliente por `uuid`, não por nome. Renomear não órfã histórico.
- **Numeração:** `sequences` atômica por (tenant, série) — dois processos recebem números distintos; base para a sequência fiscal.
- **Dinheiro:** centavos `bigint` + `money.ts`; soma sem erro de float.
- **Tempo:** `timestamptz` em prazos/vencimentos; atraso calculado, não digitado.
- **Concorrência:** coluna `version` + escrita otimista; conflito é avisado, não sobrescrito.
- **Multi-tenant:** `tenant_id` em tudo + RLS; endpoint não confia só na UI.
- **Auditoria:** `audit_log` append-only nas ações sensíveis.
- **Autorização:** `policy.ts` no servidor; menu por perfil é projeção, não controle.

## Próximos passos (cronograma)
1. **Client** — Vite + React 19 + TanStack Query ligado ao `AppRouter`; shell desktop (sidebar + topbar) sobre os tokens.
2. **Passo 4** — backup PITR, OpenTelemetry, mais testes (concorrência/idempotência).
3. **Fase 1** — `domain/quote` (orçamento + imposição) e `domain/inventory` (estoque dimensional + custeio).
4. **Fase 2** — `domain/fiscal` (NF-e/NFS-e) e financeiro (títulos/conciliação/DRE).

## Setup (quando for ligar)
```bash
pnpm install
docker compose up -d postgres
pnpm --filter server drizzle:generate && pnpm --filter server drizzle:migrate
psql "$DATABASE_URL" -f apps/server/src/db/rls.sql   # aplica RLS
pnpm --filter server dev
```

## Regras de ouro
Ver `docs/handoff_oimpresso/CLAUDE.md`. Resumo: **model-first**, PT-BR na UI, fidelidade aos tokens, sem float em dinheiro, sem data-texto, sem vínculo por string, fundação antes de feature.
