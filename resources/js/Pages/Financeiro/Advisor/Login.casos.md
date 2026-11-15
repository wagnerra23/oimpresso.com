---
id: resources-js-pages-financeiro-advisor-login-casos
casos: Login do portal do contador · /advisor/login
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /advisor/login

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft**. Tela de auth isolada (fora do AppShellV2), guard `web-advisor`.

## UC-ADVL-01 — Entrar com credencial válida
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Quando o contador envia email+senha válidos · Então autentica no guard `web-advisor` e cai em `/advisor`.

## UC-ADVL-02 — Credencial inválida volta com erro, sem vazar dado
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
Senha errada, e-mail inexistente e conta inativa recebem o mesmo `flash.error`; a tela pré-auth nunca mostra dado de cliente.

## UC-ADVL-03 — "Lembrar de mim" é opcional
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
O cookie de lembrar só nasce quando o contador marca a opção.

## UC-ADVL-04 — Limite de tentativas contra força bruta
Status: 🧪 (`Advisor/AdvisorRotasContratoTest`)
5 erros na mesma conta e IP travam a conta por 1 minuto, mesmo com a senha certa depois; 20 erros do mesmo IP travam o IP. O acerto zera a conta e o aviso aparece na própria tela.

## Backlog de casos (sem id — dependem de backend novo)
- **[BACKLOG] Reset de senha** — NÃO existe rota no guard `web-advisor`; o link só entra na tela depois da rota (Anti-hook do charter).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (leva 4).
- 2026-11-09 · lote 3.2 da auditoria: limite de tentativas e testes pelas rotas.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
