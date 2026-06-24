---
casos: Suporte Visão · /suporte/empresas/{business}
irmaos: Visao.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: o comportamento (login-as guardado, operadora/superadmin/inativo bloqueados, entrada auditada) é durável — é a fronteira Tier 0 e não muda no refactor.
owner: wagner
last_run: "2026-06-24"
---

# Casos de Uso & Aceite — Suporte / Visão

> Destino do "Entrar (suporte)". Os UCs blindam as invariantes Tier 0 da fase A
> ([ADR 0306](../../../../memory/decisions/0306-modo-suporte-fase-a-acessar-como-login-as-guardado.md)):
> o agente vira QUALQUER usuário do cliente (incl. Admin), mas NUNCA a operadora (biz=1) nem um
> superadmin; alvo inativo barra; toda impersonação é auditada com o usuário-alvo explícito.
> Provados por `tests/Feature/Support/SupportAcessarComoTest.php` (biz=1 `seededTenant`, biz=99 cliente).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-SUP-04 · Agente "Acessa como" um usuário do cliente (login-as) e fica registrado
- **Persona:** agente de suporte — precisa atuar dentro do cliente pra resolver de verdade.
- **Aceite:** Dado um agente de suporte ativo e um usuário comum/ativo de uma empresa-cliente · Quando posta `acessar-como/{user}` · Então passa a estar **logado como** aquele usuário (redirect `home`) e grava `acessou_como` em `support_access_logs` com `target_user_id`.
- **Teste:** `SupportAcessarComoTest` ("UC-SUP-04 · agente acessa-como usuário do cliente").
- **Status: 🧪**

## UC-SUP-05 · "Acessar como" na operadora (biz=1) é 403
- **Persona:** Wagner (operador) — ninguém do suporte vira usuário da biz=1.
- **Aceite:** Dado um agente de suporte · Quando tenta `acessar-como` um usuário cujo `business_id` é a operadora · Então recebe **403** (barrado no nível-empresa pelo middleware) e a identidade NÃO muda.
- **Teste:** `SupportAcessarComoTest` ("UC-SUP-05 · acessar-como na operadora é 403").
- **Status: 🧪**

## UC-SUP-06 · "Acessar como" um superadmin do cliente é 403 + negação auditada
- **Persona:** agente de suporte — não pode escalar virando um superadmin/admin-username.
- **Aceite:** Dado um alvo cujo username está em `administrator_usernames` (mesmo dentro do cliente) · Quando o agente tenta `acessar-como` · Então recebe **403** (`canImpersonate` falha no controller), a identidade NÃO muda, e grava `negado` com `target_user_id`.
- **Teste:** `SupportAcessarComoTest` ("UC-SUP-06 · acessar-como um superadmin do cliente é 403").
- **Status: 🧪**

## UC-SUP-07 · A tela Visão mostra resumo + usuários da empresa-cliente
- **Persona:** agente de suporte — entende o cliente num relance.
- **Aceite:** Dado um agente de suporte ativo · Quando abre `/suporte/empresas/{business}` · Então recebe a página `Suporte/Visao` com `empresa`, `contagens` e `usuarios`.
- **Teste:** `SupportAcessarComoTest` ("UC-SUP-07 · tela Visao renderiza resumo + usuários").
- **Status: 🧪**

---

## Coberto pelo service (provado em `SupportAcessarComoTest`, nível-unidade)

- `canImpersonate` LIBERA usuário comum/Admin ativo de cliente acessível.
- `canImpersonate` NEGA: operadora (biz=1) · superadmin/admin-username · inativo/sem `allow_login` · iniciador não-agente.

## Backlog de casos (sem id — entram quando tiverem teste)

- Faixa "Voltar para X" reaparece em toda tela do cliente após o login-as (vem do AppShellV2 `switched_from` — cobertura de UI).
- Busca local filtra usuários por username/nome/email (cobertura de UI, não-crítica).
