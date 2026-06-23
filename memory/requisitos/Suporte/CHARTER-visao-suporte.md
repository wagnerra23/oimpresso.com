# Charter (draft) — Visão de Suporte (Modo Suporte, ADR 0305 · fase B read-only)

> Contrato da(s) tela(s) de suporte **antes** de existir `.tsx`. Pareado com o [draft visual](mockup-visao-suporte.html) (abrir no navegador → screenshot pra aprovação Wagner, R2/R7). Quando aprovado, vira `resources/js/Pages/Suporte/Empresas.charter.md` + `Visao.charter.md` na PR-C2, junto do `.tsx` (o `OrphanRenderGate` exige page+render juntos). Backend já no `main` (PRs #3263/#3264/#3266+#3267).

## Missão

Dar à **equipe de suporte do operador** uma forma segura de **ver** o que se passa dentro de uma empresa-cliente (inclusive o financeiro **dela**) pra resolver chamados — **sem nunca** alcançar a empresa operadora (biz=1) e com **toda** entrada auditada. v1 é **somente leitura** ("ver", não "atuar").

## Telas (2)

### 1. `Suporte/Empresas` — escolher a empresa (PT-01 Lista)
- Lista as empresas-cliente **acessíveis** (todas exceto a operadora — vem de `SupportAccessService::accessibleBusinessIds`).
- Cada linha: nome da empresa + ação **"Entrar (suporte)"** → leva à Visão.
- Busca por nome. Vazio = empty-state "Nenhuma empresa-cliente acessível".

### 2. `Suporte/Visao` — visão read-only da empresa-cliente
- **Banner fixo, inconfundível:** "🛟 Modo Suporte — você está vendo **{Empresa}** · **somente leitura**" + botão **"Sair do suporte"**. Cor de alerta (não o roxo normal) pra deixar claro que NÃO é a sessão normal do operador.
- **Cabeçalho:** nome da empresa + id.
- **Resumo (cards):** Usuários · Transações · Contatos (contagens cross-tenant do cliente). Expansível depois (financeiro do cliente, últimas vendas) — v1 começa enxuto.
- Tudo **read-only**: zero botões de ação que escrevem (sem editar/excluir/criar). "Atuar" é fase A (futuro, com auditoria de scoping).

## Goals
- G1. Em ≤2 cliques, o agente vê o resumo de qualquer empresa-cliente.
- G2. Fica **óbvio** na tela que (a) é Modo Suporte, (b) é só leitura, (c) qual empresa.
- G3. A operadora **nunca** aparece na lista nem é alcançável (já garantido no backend).
- G4. Toda entrada numa empresa é auditada (já no backend, via middleware).

## Non-Goals (v1)
- ❌ Editar/criar/excluir qualquer coisa do cliente ("atuar" = fase A).
- ❌ Impersonar um usuário específico do cliente.
- ❌ Alcançar a empresa operadora (biz=1) — bloqueado por design.
- ❌ Conceder/revogar a capability aqui (isso é a tela da PR-D).

## UX targets / Anti-hooks
- **Anti-hook nº1:** o agente esquecer que está "como suporte" e achar que é a sessão normal → o **banner de alerta persistente** + cor distinta evitam isso.
- Acessível (WCAG AA), 1280/1440px, sidebar light (UI-0009/0014), PageHeader canon (roxo 295) — **exceto** o banner de Modo Suporte, que usa cor de alerta de propósito.
- Estados: loading (skeleton via `Inertia::defer` se as contagens pesarem), empty, erro.

## Data contract (props Inertia)
- `Suporte/Empresas`: `empresas: {id:int, name:string}[]` (já exceto operador).
- `Suporte/Visao`: `empresa: {id:int, name:string}` + `resumo: {usuarios:int, transacoes:int, contatos:int}`.

## Invariantes herdadas (backend, já no main)
Resolução exclui operador (config `OPERATOR_BUSINESS_ID`) · capability por conta (`support_agents`) · middleware `EnsureSupportAccess` (service-direct, não-Gate) audita `entrou`/`negado` · sem escalonamento. Ver [ADR 0305](../../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) + [SPEC](SPEC.md).
