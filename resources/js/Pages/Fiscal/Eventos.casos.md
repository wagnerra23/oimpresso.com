---
casos: Eventos Fiscais · /fiscal/eventos
irmaos: Eventos.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Eventos Fiscais

> Persona: **Eliana (contadora)** — leitura/conferência fiscal. Cockpit Fiscal (agregador thin).
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75).
>
> **Status:** ✅ passa (UC-id citado por teste) · 🧪 tem teste Feature mas **sem UC-id** (débito G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito = rastreabilidade, não ausência de teste.** Comportamento defendido por `EventosCockpitMultiTenantTest` + `AcoesControllerTest` (mutações que geram os eventos). Falta G-2: nenhum teste cita `UC-FISCAL-NN`. CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste · Tier 0] Timeline nunca mostra eventos de outro tenant** — Dado que Eliana está no business 1 · Quando abre a timeline de eventos · Então só vê `NfeEvento` do business 1 (HasBusinessScope ADR 0093), nunca cross-tenant. _Coberto por `EventosCockpitMultiTenantTest::'NfeEvento HasBusinessScope esconde cross-tenant — listagem timeline scoped'`._
- **[BACKLOG · 🧪 tem teste] Timeline é append-only — evento não é editável** — Dado um evento SEFAZ já registrado · Quando a tela renderiza a timeline · Então nenhuma linha oferece edição (`NfeEvento::UPDATED_AT = null`). _Coberto por `EventosCockpitMultiTenantTest::'NfeEvento é append-only (UPDATED_AT = null)'`._
- **[BACKLOG · 🧪 tem teste] Os 7 tipos SEFAZ canônicos são reconhecidos e rotulados** — Dado eventos de tipos 110110/110111/110140/210200/210210/210220/210240 · Quando Eliana filtra por categoria · Então cada tipo é mapeado ao seu `kind` (cce/cancel/epec/manifest) e label PT-BR. _Coberto por `EventosCockpitMultiTenantTest::'mapa de TIPOS cobre os 7 códigos SEFAZ canônicos'`._
- **[BACKLOG · 🧪 tem teste · gera evento] Carta de Correção (CC-e 110110) exige texto 15–1000 chars + n_seq 1–20** — Dado que Eliana pede uma CC-e · Quando o texto tem <15 ou >1000 chars, ou a sequência está fora de 1–20 (CONFAZ Art. 14) · Então a ação é rejeitada; texto válido gera o evento na timeline. _Coberto por `AcoesControllerTest::'cartaCorrecao rejeita <15'`, `'rejeita >1000'`, `'rejeita n_seq fora de 1-20'`, `'aceita texto válido'`._
- **[BACKLOG · 🧪 tem teste · gera evento] Cancelamento de NF-e exige motivo ≥15 chars (CONFAZ SINIEF 07/2005)** — Dado um cancelamento · Quando o motivo tem <15 chars · Então é rejeitado; motivo válido gera evento de cancelamento (110111). _Coberto por `AcoesControllerTest::'cancelarNfe rejeita motivo < 15 chars'` + `'aceita motivo válido ≥15'`._
- **[BACKLOG · 🧪 tem teste · gera evento] Inutilização de faixa valida modelo 55/65 + faixa coerente + justificativa 15–255** — Dado uma inutilização de faixa numérica · Quando modelo ∉ {55,65}, ou `numero_ate < numero_de`, ou justificativa <15 chars · Então é rejeitada; payload válido é aceito. _Coberto por `AcoesControllerTest::'inutilizar valida modelo'`, `'rejeita faixa inválida'`, `'rejeita justificativa <15'`, `'aceita payload válido'`. (Nota: inutilização vive em `NfeInutilizacao`, não em `NfeEvento` — sub-página separada.)_
- **[BACKLOG · 🧪 tem teste · gera evento] Retransmitir só vale pra rejeitada/denegada/erro_envio** — Dado uma NF-e · Quando o status ∈ {rejeitada, denegada, erro_envio} · Então a retransmissão é permitida; nunca pra autorizada/cancelada/inutilizada/pendente. _Coberto por `AcoesControllerTest::'retransmitir contrato: status válidos'` + `'route POST registrada (acoes.nfe.retransmitir)'` + `'NfeService::retransmitir signature'`._
- **[BACKLOG · 🧪 tem teste · gera evento] Manifestação DF-e restrita às 4 ações SEFAZ; desconhecer/nao_realizada exigem justificativa** — Dado uma nota de terceiro · Quando Eliana manifesta · Então só {cienciar, confirmar, desconhecer, nao_realizada}; desconhecer/nao_realizada exigem justificativa (gera evento 210200/210/220/240). _Coberto por `AcoesControllerTest::'manifestarDfe whitelist exatamente 4 ações'` + `'desconhecer/nao_realizada exigem justificativa'`._
- **[BACKLOG · ⬜ sem teste] Gate `fiscal.access` no acesso à timeline** — Dado um user sem `fiscal.access` nem `superadmin` · Quando abre `/fiscal/eventos` · Então recebe 403 (`EventosController::index` linha 39). _Comportamento no Controller, sem teste Feature dedicado._
- **[BACKLOG · ⬜ sem teste] Filtro por `kind` e janela temporal (7/30/90d, default 30d)** — Dado Eliana em conferência · Quando escolhe categoria + período · Então `buildRowsPayload` restringe por `kind` e `created_at >= cutoff`. _Comportamento no Controller, sem teste Feature dedicado._
- **[BACKLOG · ⬜ sem teste] Justificativa truncada em 200 chars (anti-PII no xMotivo)** — Dado evento com justificativa longa · Quando renderiza a linha · Então mostra no máx. 200 chars (`mapRow`). _Anti-hook do charter, sem teste._

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — `EventosCockpitMultiTenantTest` + `AcoesControllerTest` verdes. (SQLite skipa: `nfe_eventos`/`nfe_emissoes` exigem schema MySQL, ADR 0101.)
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). Débito = UC-traceability.
