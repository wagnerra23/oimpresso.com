---
slug: 0309-modo-suporte-operadora-e-o-time-de-suporte
number: 309
title: "Modo Suporte: a empresa operadora (biz=1) É o time de suporte — capability por membership, além da concessão explícita — emenda à 0305"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-24"
module: core
tags: [multi-tenant, suporte, cross-tenant, capability, operador, tier-0, acesso]
supersedes: []
related: ["0305-modo-suporte-cross-tenant-exceto-operador", "0308-modo-suporte-fase-a-acessar-como-login-as-guardado", "0093-multi-tenant-isolation-tier-0"]
pii: false
---

# ADR 0309 — Modo Suporte: a empresa operadora (biz=1) É o time de suporte — emenda à 0305

> **Status:** aceito — decisão do Wagner (sessão 2026-06-24: *"todas da empresa 1 podem fazer isso"*). **Emenda** ao RF4 da [ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md) (que dizia "acesso concedido/revogado por conta"). A ratificação se concretiza com a aprovação/merge desta PR.

## Contexto

- A [ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md) RF4 definiu a capability "suporte" como **concedida por conta** (linha em `support_agents`), e a guarda `EnsureSupportAccess` decide por `isSupportAgent` (service-direct, não Gate). Bom: o superadmin NÃO é automaticamente agente (provado em prod — 403 pro superadmin sem concessão).
- Na prática, **o time de suporte É a empresa operadora (biz=1)** — a equipe da oimpresso/WR2 trabalha toda na biz=1. Conceder um a um (RF4) é fricção sem ganho: todo mundo da operadora deve poder atender cliente.
- Origem: Wagner, ao ver o 403 do superadmin, decidiu que **não é só ele** — *"todas da empresa 1 podem fazer isso"*.

## Decisão

`isSupportAgent(user)` é verdadeiro se **(a)** o usuário pertence à empresa **operadora** (`business_id === OPERATOR_BUSINESS_ID`, default 1) **OU (b)** tem concessão explícita ativa em `support_agents`.

1. **(a) Operadora = suporte:** todo usuário da biz=1 é agente, sem concessão explícita. Ponto único: `SupportAccessService::isSupportAgent` (mesmo lugar que já resolvia o resto).
2. **(b) Concessão explícita (RF4) preservada:** continua valendo para agentes **fora** da operadora (ex. contratado externo) — via `support_agents` + comando `suporte:conceder`.
3. **A exclusão da operadora no ALCANCE não muda** ([ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md)): um agente da biz=1 acessa **todos os clientes EXCETO a própria biz=1** (`accessibleBusinessIds`/`canAccessBusiness` intactos). A biz=1 só é acessível pelo caminho normal (eles já estão logados nela), nunca pela via de suporte.
4. **Sem escalonamento ([ADR 0308](0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md)):** o "Acessar como" segue barrando alvo superadmin/operador. Um agente da biz=1 NÃO pode impersonar usuário da biz=1 (operador excluído no `canImpersonate`).

## Consequências

**Positivas:** zero fricção pro time (todo mundo da operadora atende cliente direto); o modelo casa com a realidade (operador = suporte); concessão explícita continua para os casos de fora; tudo num ponto único.

**Custos / riscos:**
- **Amplia o Tier 0 conscientemente:** **qualquer** usuário da biz=1 — inclusive contas de baixo privilégio — passa a poder ver todos os clientes e **"Acessar como"** (impersonar/atuar). Wagner aceitou explicitamente (2026-06-24). Se um dia precisar restringir, refinar para "biz=1 **com** papel/permissão X" é trocar a condição (a) num único método.
- Testes negativos ("sem capability = 403") **não podem mais usar usuário da biz=1** — têm que usar usuário-cliente (biz≠1) sem concessão. Ajustado nesta PR (senão a suíte mentiria: um biz=1 "sem grant" agora É agente).

**Tier 0:** continua exceção explícita e auditada; a fronteira que protege o operador (biz=1 inalcançável pela via de suporte + sem virar god) é **a mesma**. O que muda é só QUEM é agente (membership da operadora, não concessão um-a-um).

## Alternativas consideradas (e por que não)

1. **Manter só concessão por conta (RF4 original)** — fricção: conceder a cada membro do time da operadora, um a um, sem ganho. ❌ (é o que esta emenda destrava.)
2. **Superadmin auto-passa na guarda** — rejeitado nesta mesma sessão (mistura god no audit de suporte; o superadmin tem o caminho `/superadmin/*`). A escolha foi capability-only, agora estendida a "biz=1 membership".
3. **biz=1 com papel/permissão específico** — mais restrito, mas Wagner pediu **"todas"** da biz=1. Fica como refino futuro fácil (trocar a condição (a)).
4. **Operadora = suporte por membership + grant explícito pra fora (escolhido)** — casa com a realidade, zero fricção, mantém o caminho RF4 pros externos. ✅

## Refs

- [ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md) — Modo Suporte (RF4 "por conta", emendado aqui) · [ADR 0308](0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md) — "Acessar como".
- [`SupportAccessService::isSupportAgent`](../../app/Services/Support/SupportAccessService.php) — ponto único (biz=1 OU grant) · `suporte:conceder` (grant explícito).
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (e exceções).
