---
page: /suporte/empresas/{business}
component: resources/js/Pages/Suporte/Visao.tsx
page_id: suporte-visao
owner: wagner
status: draft
parent_module: Suporte
related_adrs:
  - 0308-modo-suporte-fase-a-acessar-como-login-as-guardado
  - 0305-modo-suporte-cross-tenant-exceto-operador
  - 0093-multi-tenant-isolation-tier-0
mission: "Dar ao agente de suporte a visão de uma empresa-cliente (resumo + usuários) e o ponto de entrada 'Acessar como' (login-as guardado) para atuar dentro dela, sem nunca alcançar a operadora nem virar um superadmin."
---

# Charter — Suporte / Visão

> Contrato vivo da tela. Lei sobre os [casos](Visao.casos.md). Backend (services + rotas + Pest) no mesmo PR; aval visual do screenshot dado pelo Wagner (2026-06-24). Pareado com [SPEC §fase A](../../../../memory/requisitos/Suporte/SPEC.md) + [ADR 0308](../../../../memory/decisions/0308-modo-suporte-fase-a-acessar-como-login-as-guardado.md).

## Mission

Destino do "Entrar (suporte)" da [Empresas](Empresas.charter.md). Mostra, de uma empresa-cliente, o **resumo** (usuários/contatos/produtos/vendas/compras) e a **lista de usuários**, e oferece **"Acessar como"** (login-as completo) por usuário — a única ação de escrita do Modo Suporte. Toda leitura é por `business_id` **explícito** (nunca a sessão); a autorização de nível-empresa + auditoria de entrada vivem no middleware `EnsureSupportAccess`, e a impersonação re-checa `canImpersonate` no servidor.

## Goals

- G1. O agente entende o cliente num relance (resumo) e age em ≤1 clique ("Acessar como").
- G2. A trava fica **visível**: alvos fora do alcance (operador/superadmin/inativo) aparecem como "indisponível" (não some, mostra que existe e está bloqueado).
- G3. Busca local por username/nome/email.
- G4. Depois de entrar, o agente sempre sabe que está "como" alguém e volta em 1 clique (faixa do AppShellV2 via `switched_from`).

## Non-Goals

- ❌ Editar dados **nesta** tela (read-only); a escrita acontece *dentro* do cliente após "Acessar como".
- ❌ Mostrar a empresa operadora (biz=1) ou qualquer usuário dela.
- ❌ "Acessar como" um superadmin/admin-username ou usuário inativo (botão vira "indisponível").
- ❌ Conceder/revogar a capability de suporte (é outra tela).
- ❌ Atribuição de auditoria por-ação — o log registra a **entrada** (quem virou quem), não cada ação (caveat assumido, ADR 0308).

## UX targets

- AppShellV2 + sidebar preta/dark-fixo (UI-0023). PT-BR. `tabular-nums` em ID/contagens.
- Tokens semânticos (sem cor crua) + primitivos `Inline`/`Stack`/`Grid` (ADR 0253). `rounded-lg` máx. Dark mode ok (contraste ≥ 4.5:1) — mesmos tokens da Empresas.
- Banner de Modo Suporte no topo; ícone em `--warn`.
- "Acessar como" = `Button` primário por linha; confirma antes (a ação troca a identidade); estado "Entrando…" enquanto posta.
- Estados: cheia · busca-sem-resultado · sem usuários.

## Anti-hooks

- O botão "Acessar como" **só** posta (POST + CSRF) — nunca GET; a decisão de poder é do servidor (`canImpersonate`), a tela só reflete `pode_acessar_como`.
- Nunca escopar dados do cliente pela sessão — `business_id` explícito (SPEC §Desenho seguro; o switch parcial de sessão foi descartado por vazamento).
- O agente NÃO é superadmin — login-as nunca alcança a biz=1 nem vira god (trava no `SupportAccessService`).
