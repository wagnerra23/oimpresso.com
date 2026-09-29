# Handoff — App Oimpresso Mobile · Claude Design → Claude Code

Pacote de exportação do **protótipo de design** (App Oimpresso Mobile) para **adaptação em produção**. Reúne, num só lugar, os arquivos operacionais, o skin/layout, o backend canônico, a indexação e a documentação que diz *o que* construir, *como* construir e *como aplicar*.

> **Esta é a porta de entrada. Leia os dois documentos abaixo ANTES de tocar em qualquer código.**

---

## 📌 Leitura prioritária (nesta ordem)

### 1️⃣ [`SDD-app-oimpresso-mobile.md`](SDD-app-oimpresso-mobile.md) — *o que o app é e por quê*
Software Design Document: visão geral, as duas verticais (gráfica/oficina), personas, governança (multi-tenant/LGPD/fiscal), design system, arquitetura em camadas, casos de uso (CU-M01..M16), requisitos não-funcionais, riscos e **roadmap em ondas**. É o mapa de cima — comece por ele.

### 2️⃣ [`GUIA-CONSTRUCAO-MOBILE.md`](GUIA-CONSTRUCAO-MOBILE.md) — *como construir sem regressão*
Hierarquia de autoridade (N0–N6), mapa documental, pipeline de construção (8 etapas), árvore de decisão por tipo de mudança, **lista dura de proibições** e Definição de Pronto. É o manual de obra.

### 3️⃣ [`HANDOFF-PROTOCOL.md`](HANDOFF-PROTOCOL.md) — *como exportar e aplicar*
Protocolo Design→Code: as duas naturezas dos artefatos (design vs. backend), estrutura do pacote, **relacionamento e tratamento dos arquivos**, indexação e o **passo a passo de aplicação na produção**.

### 4️⃣ [`INDEX.md`](INDEX.md) + [`manifest.yaml`](manifest.yaml) — *o catálogo*
Todo arquivo (css, yaml, ts, jsx, sql, json, html) com papel, camada e dependências — humano (`INDEX.md`) e máquina (`manifest.yaml`).

---

## O que tem nesta pasta

```
handoff_export_oimpresso/
├── README.md                       ← você está aqui
├── SDD-app-oimpresso-mobile.md     ← prioridade 1
├── GUIA-CONSTRUCAO-MOBILE.md       ← prioridade 2
├── HANDOFF-PROTOCOL.md             ← prioridade 3
├── INDEX.md / manifest.yaml        ← catálogo de todos os arquivos
├── app/        → DESIGN: protótipo operacional + skin (HTML + jsx + tokens.css)
└── backend/    → PRODUÇÃO: oimpresso-erp/ (TypeScript: domain, routers, schema, rls, shared, configs, testes)
```

---

## Regra de ouro do handoff

> **Regra de negócio** (preço, fiscal, estoque, parcelas) vem do **`backend/`**.
> **Aparência e fluxo** vêm do **`app/`** (design).
> **Nunca reimplementar regra de negócio no front** — ela já existe, tipada e testada, em `backend/`.

O `app/` é a especificação executável de UX (telas, tokens, fluxos). O caminho de produção **reproduz** essas telas trocando a camada de dados simulada (`mock-*.jsx` + `OIStore`/localStorage) por chamadas reais ao `backend/` (contratos em `packages/shared/contracts.ts`).

---

## Início rápido (resumo — detalhe no HANDOFF-PROTOCOL §5)

1. **Ler** SDD → GUIA → HANDOFF-PROTOCOL → INDEX.
2. **Rodar o protótipo:** abrir `app/Oimpresso Mobile.html` num navegador (não precisa build — React/Babel via CDN).
3. **Subir o backend:** em `backend/oimpresso-erp/` → `docker compose up` → `pnpm i` → `pnpm --filter server drizzle:migrate` → aplicar `db/rls.sql` → `pnpm --filter server seed` → `pnpm test`.
4. **Mapear contratos:** `backend/oimpresso-erp/packages/shared/contracts.ts` = forma dos dados que as telas esperam.
5. **Portar skin → reproduzir telas → religar fluxos via routers** (ordem completa no protocolo).
6. **Gates + rollout:** DoD do GUIA §6; flag por empresa → canary → monitor 30d.

---

## Estado de maturidade (honesto)

- **`app/`** — protótipo de alta fidelidade: telas, tokens, navegação, estado compartilhado (`OIStore`) e **fluxos E2E reais** (pedido/OS → faturar → financeiro). Dados **simulados**; a "sincronização entre os dois aparelhos" da demo é `localStorage` da mesma aba — **não** é multiusuário real.
- **`backend/`** — refundação real (Fases 0–2): modelo de dados (UUID, `tenant_id`, RLS, `timestamptz`, dinheiro em centavos, numeração atômica), orçamento+imposição+custeio, estoque dimensional, produção, financeiro (título/parcela/DRE), fiscal (ISS×ICMS + máquina de estados + adapter de provedor), com testes.
- **Fronteira:** ligar o `app/` ao `backend/` é o que separa protótipo de produto (ver SDD §5.1 e roadmap §10).
