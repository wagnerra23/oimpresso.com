---
id: resources-js-pages-suporte-log-casos
casos: Suporte Log · /suporte/log
irmaos: Log.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: a trilha de suporte é a prova de que o acesso cross-tenant é auditado (RF3) — quem a lê, e o que ela nunca mostra, é fronteira Tier 0.
owner: wagner
last_run: "2026-10-06"
---

# Casos de Uso & Aceite — Suporte / Log de acessos

> Derivados do RF3 da [SPEC](../../../../memory/requisitos/Suporte/SPEC.md) (auditoria
> append-only "quem · qual empresa · quando") e da [ADR 0305](../../../../memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md)
> (operadora nunca alcançável). Provados por `tests/Feature/Support/SuporteLogContratoTest.php`
> (biz=1 `seededTenant` operadora · biz=99 `seededSupportClientTenant`). NUNCA biz=4.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-SUP-08 · Agente lê a trilha de acessos às empresas-cliente
- **Persona:** agente de suporte (time da operadora) — confere quem entrou em qual cliente.
- **Aceite:** Dado um acesso registrado de um agente à empresa-cliente 99 · Quando um agente abre
  `/suporte/log` e a lista deferida carrega · Então a página é `Suporte/Log` e a linha aparece com
  agente, empresa (`empresa_id` 99) e ação `entrou`.
- **Teste:** `SuporteLogContratoTest` ("UC-SUP-08 · agente lê a trilha da empresa-cliente").
- **Status: 🧪**

## UC-SUP-09 · A operadora nunca aparece no log — nem as negações contra ela
- **Persona:** Wagner (operador) — a biz=1 não aparece em vista nenhuma do Modo Suporte.
- **Aceite:** Dado uma linha `negado` cuja empresa-alvo é a operadora · Quando o agente abre a
  lista · Então essa linha **não** vem, e a linha da empresa-cliente vem (controle positivo).
- **Teste:** `SuporteLogContratoTest` ("UC-SUP-09 · negação contra a operadora fica fora do log").
- **Status: 🧪**

## UC-SUP-10 · Quem não é agente de suporte recebe 403
- **Persona:** usuário de empresa-cliente — não lê a trilha do time de suporte.
- **Aceite:** Dado um usuário de cliente sem concessão em `support_agents` · Quando abre
  `/suporte/log` · Então recebe **403** (middleware `support.access`).
- **Teste:** `SuporteLogContratoTest` ("UC-SUP-10 · não-agente recebe 403 no log").
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** Chegar ao log pelo botão "Log de acessos" da lista de empresas e da Visão (como no
  protótipo) — esses arquivos são `nao_toca` da thread 02; hoje só pela URL.
- **[BACKLOG]** Motivo declarado e duração por acesso — o schema não grava (ver charter §Lacunas).
