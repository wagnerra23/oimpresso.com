---
casos: Suporte Empresas · /suporte/empresas
irmaos: Empresas.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: o comportamento (operadora inalcançável, cliente listado, sem-capability bloqueado) é durável — não muda no refactor.
owner: wagner
last_run: "2026-06-23"
---

# Casos de Uso & Aceite — Suporte / Empresas

> Tela de entrada do Modo Suporte (read-only). Os UCs blindam as invariantes Tier 0 do
> [ADR 0305](../../../../memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md):
> a operadora (biz=1) é inalcançável, o cliente é listado, e quem não tem capability é barrado.
> Provados por `tests/Feature/Support/SupportEmpresasHttpTest.php` (biz=1 `seededTenant`, biz=99 cliente).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-SUP-01 · Agente vê a lista de empresas-cliente acessíveis
- **Persona:** agente de suporte (equipe do operador) — precisa escolher qual cliente atender.
- **Aceite:** Dado um usuário com capability de suporte ativa · Quando abre `/suporte/empresas` · Então recebe a página `Suporte/Empresas` com a lista das empresas acessíveis.
- **Teste:** `SupportEmpresasHttpTest` ("UC-SUP-01 · agente vê a lista").
- **Status: 🧪**

## UC-SUP-02 · A operadora (biz=1) nunca aparece na lista
- **Persona:** Wagner (operador) — o financeiro/dados dele jamais entram na visão de suporte.
- **Aceite:** Dado a resolução que alimenta a tela (`SupportAccessService::accessibleBusinessIds`) · Então o `business_id` da operadora (config `OPERATOR_BUSINESS_ID`) NÃO está na lista.
- **Teste:** `SupportEmpresasHttpTest` ("UC-SUP-02 · operadora ausente").
- **Status: 🧪**

## UC-SUP-03 · Usuário sem capability de suporte é bloqueado (403)
- **Persona:** usuário comum / agente revogado — não pode listar empresas-cliente.
- **Aceite:** Dado um usuário SEM `support_agents` ativo · Quando abre `/suporte/empresas` · Então recebe **403** (middleware `support.access`).
- **Teste:** `SupportEmpresasHttpTest` ("UC-SUP-03 · sem capability 403").
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste)

- "Entrar (suporte)" leva à visão read-only do cliente (`Suporte/Visao`) — depende da tela Visao (PR seguinte).
- Busca local filtra por nome/ID (cobertura de UI, não-crítica).
