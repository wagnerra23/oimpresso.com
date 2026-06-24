---
slug: 0306-modo-suporte-fase-a-acessar-como-login-as-guardado
number: 306
title: "Modo Suporte fase A: atuar via \"Acessar como\" (login-as) guardado — emenda à 0305"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-24"
module: core
tags: [multi-tenant, suporte, cross-tenant, impersonation, login-as, superadmin, auditoria, tier-0, acesso, escrita]
supersedes: []
related: ["0305-modo-suporte-cross-tenant-exceto-operador", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
---

# ADR 0306 — Modo Suporte fase A: atuar via "Acessar como" (login-as) guardado — emenda à 0305

> **Status:** aceito — decisão do Wagner (sessão 2026-06-24: *"Acessar como é isso que preciso"* → pro time de suporte, com trava, podendo virar qualquer usuário do cliente inclusive Admin). **Emenda** à [ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md): não a revoga — destrava a **fase A ("atuar")** que a 0305 deixou explicitamente como futura. A ratificação se concretiza com a aprovação/merge desta PR pelo Wagner. Pareada com a [SPEC Suporte](../requisitos/Suporte/SPEC.md) §fase A.

## Contexto

- A [ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md) criou o **Modo Suporte** (cross-tenant a todas as empresas-cliente **exceto a operadora biz=1**, auditado, sem escalonamento) e o entregou **somente leitura** — a SPEC listou *"Login as / impersonar usuário = futuro, fora de escopo v1"*.
- **Read-only não basta pra dar suporte de verdade:** corrigir uma venda, ajustar uma config, reimprimir, destravar um cadastro — tudo isso é **escrita** dentro do cliente. O time precisa **atuar**, não só olhar.
- O mecanismo **"Acessar como"** já existe no core ([`ManageUserController::signInAsUser`](../../app/Http/Controllers/ManageUserController.php)) — `session()->flush()` + `Auth::loginUsingId($id)` — mas hoje é **`superadmin`-only** (guard `can('superadmin')`), **sem exclusão da biz=1** e **sem auditoria** do evento. É o caminho que o operador (Wagner) usa hoje pela tela `superadmin/business/{id}`.

### Esclarecimento técnico (corrige a leitura da 0305)

O **login-as completo** (`loginUsingId`) **NÃO é** o *split-brain* que a 0305 descartou. Aquele risco era de uma abordagem **diferente** — trocar só o `business_id` da **sessão** mantendo o agente logado como ele mesmo (aí `CashRegisterUtil::getRegisterDetails` / `TransactionUtil::payContact` / criação de usuário, que leem `auth()->user()->business_id`, vazariam o operador / gravariam no tenant errado). No login-as completo o **auth-user inteiro** passa a ser o usuário do cliente → `business_id` consistente em todos os caminhos. O que a 0305 fez foi **adiar "atuar"**; esta ADR é essa fase A — uma **escolha consciente de escopo**, não a reintrodução do split-brain.

## Decisão

Liberar **"Acessar como" (login-as) para o agente de suporte**, como porta de entrada **guardada**, reusando o primitivo do core. O agente pode virar **qualquer usuário do cliente, inclusive Admin** — **nunca** o operador (biz=1) nem um `superadmin`.

1. **Reusa o primitivo do core** (`Auth::loginUsingId` + sessão `previous_user_id`/`previous_username` + prop Inertia `switched_from` já exposta em [`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php)). **Não duplica auth** nem inventa mecanismo de impersonação.
2. **Trava Tier 0 num ponto único** (`SupportAccessService::canImpersonate(agent, target)`), avaliada **antes** do `loginUsingId`:
   - **a.** quem inicia é **agente de suporte** (`isSupportAgent`) — não precisa ser `superadmin`;
   - **b.** a empresa do alvo ∈ `accessibleBusinessIds()` → **nunca a operadora (biz=1)** (reusa `canAccessBusiness`, ponto único de exclusão da 0305);
   - **c.** o alvo **não** é `superadmin` nem está em `ADMINISTRATOR_USERNAMES` → **sem escalonamento pra god**;
   - **d.** o alvo está **ativo** e **pertence mesmo** àquela empresa;
   - **e.** Admin do cliente é **permitido** (poder pra resolver de verdade) — a única exclusão de papel é operador/superadmin.
3. **Auditoria append-only (RF3):** cada início de impersonação grava em `support_access_logs` — *agente · usuário-alvo · empresa · rota · quando*. A **negação** também é registrada.
4. **Volta segura:** reusa `switched_from` + a rota `sign-in-as-user/{previous_user_id}` do core (o guard já libera quem tem `previous_user_id`) — banner "Voltar pra mim" no AppShellV2, **de graça**.
5. **`superadmin` (Wagner) segue pleno** — vê tudo e impersona qualquer um, inclusive na biz=1, pela tela do Superadmin (inalterada).

## Consequências

**Positivas:** o time **resolve de verdade** dentro do cliente; reusa o caminho de impersonação **já testado** do core (menor risco que reinventar); a volta sai de graça (`switched_from` já no React); cada **entrada** fica auditada; a exclusão da biz=1 continua num **ponto único**.

**Custos / riscos:**
- **Exceção Tier 0 mais forte** que a read-only da 0305: agora é **escrita** cross-tenant. A blindagem por testes Tier 0 é obrigatória (suporte impersona cliente · **NUNCA** biz=1 · **NUNCA** superadmin/admin-username · auditoria grava · alvo inativo barra · agente não-suporte barra).
- **CAVEAT inerente ao login-as (atribuição por-sessão, não por-ação):** enquanto "como" o cliente, as ações gravadas saem com **autor = usuário do cliente** (`created_by` etc.), **não** o agente. O `support_access_logs` registra a **entrada** (quem virou quem, quando), mas **não** cada ação individual. Atribuição por-ação exigiria refactor grande do core — **fora de escopo desta fase**. Aceito conscientemente por Wagner (2026-06-24).
- O agente **herda o papel do alvo** dentro daquele cliente (se virar um Admin do cliente, age como Admin do cliente). Mitigado pela trava (nunca operador/superadmin) + auditoria de entrada.

**Tier 0:** exceção **explícita e auditada** ao isolamento, **mais restrita** que o `superadmin` (exclui a biz=1 e bloqueia virar god). A trava dura num ponto único impede que o Modo Suporte vire caminho de escalonamento.

## Alternativas consideradas (e por que não)

1. **Manter só read-only (0305 como está)** — não resolve "atuar"; o suporte fica olhando sem poder consertar. ❌ (é justamente o que esta emenda destrava, de propósito.)
2. **Troca parcial de contexto de sessão** — o *split-brain* já descartado pela 0305 (vaza operador / grava no tenant errado). ❌
3. **Caminhos de escrita específicos e auditados por-ação** (sem login-as) — daria **atribuição por-ação** (mais auditável), mas exige refactor grande e cobre só o que for explicitamente portado; o login-as dá **cobertura total** reusando código testado. 🟡 Possível **evolução futura** por cima desta fase; por ora aceitamos o caveat de atribuição em troca de cobertura + reuso.
4. **Dar `superadmin` ao time** — expõe o operador (biz=1) e abre escalonamento. ❌ (já rejeitado pela 0305.)
5. **"Acessar como" guardado para o agente de suporte (escolhido)** — resolve "atuar", reusa o primitivo testado, protege o operador pela mesma trava da 0305 e audita a entrada. ✅

## Refs

- [ADR 0305](0305-modo-suporte-cross-tenant-exceto-operador.md) — Modo Suporte (read-only; esta ADR é a fase A "atuar") · [SPEC Suporte](../requisitos/Suporte/SPEC.md) §fase A.
- [`ManageUserController::signInAsUser`](../../app/Http/Controllers/ManageUserController.php) — primitivo `loginUsingId` + `previous_user_id` reusado · [`HandleInertiaRequests`](../../app/Http/Middleware/HandleInertiaRequests.php) — prop `switched_from` (banner "voltar").
- [`SupportAccessService`](../../app/Services/Support/SupportAccessService.php) — ponto único de alcance/exclusão da biz=1 (ganha `canImpersonate`).
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant isolation Tier 0 (e exceções `superadmin`) · [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Art. 6 multi-tenant).
