---
casos: Manifesto DF-e · /fiscal/dfe
irmaos: Dfe.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Manifesto DF-e

> Persona: **Eliana (contadora)** — leitura/conferência fiscal. Tela do cockpit Fiscal (agregador thin sobre NfeBrasil/NFSe).
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75) sem roadmap paralelo.
>
> **Status:** ✅ passa (UC-id citado por teste) · 🧪 tem teste Feature mas **sem UC-id** (débito G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito = rastreabilidade, não ausência de teste.** Comportamento defendido por `DfeControllerTest` e `AcoesControllerTest` (Modules/Fiscal/Tests/Feature). Falta G-2: nenhum teste cita `UC-FISCAL-NN`. Cada item vira UC no mesmo PR que adicionar o id ao teste. CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste · Tier 0] Isolamento multi-tenant na listagem de DF-e** — Dado Eliana logada no business dela · Quando a lista de NF-e recebidas carrega · Então nenhuma NF-e recebida contra outro business aparece (`HasBusinessScope`, ADR 0093). _Coberto por `DfeControllerTest::NfeDfeRecebido HasBusinessScope esconde cross-tenant da listagem DF-e`._
- **[BACKLOG · 🧪 tem teste] Contrato dos 5 status de manifestação** — Dado o Model NfeDfeRecebido · Quando o Controller filtra por status · Então as constantes pendente/ciencia/confirmada/desconhecida/nao_realizada existem e batem. _Coberto por `DfeControllerTest::STATUS constants estão definidas — Controller depende delas pra filtros`._
- **[BACKLOG · 🧪 tem teste] Definição do que é "pendente de manifestação"** — Dado uma NF-e recebida · Quando o status é PENDENTE ou CIENCIA · Então conta como pendente de manifestação; CONFIRMADA não. _Coberto por `DfeControllerTest::isPendenteManifestacao retorna true pra status PENDENTE e CIENCIA`._ (Reflete os chips "pendentes" = pendente+ciencia e o KPI `valorPendente` do Controller.)
- **[BACKLOG · 🧪 tem teste] As 4 ações de manifestação SEFAZ (whitelist)** — Dado Eliana vai manifestar uma NF-e · Quando escolhe a ação · Então só valem exatamente Ciência (`cienciar`), Confirmação (`confirmar`), Desconhecimento (`desconhecer`) e Não Realizada (`nao_realizada`) — cancelar/aprovar/rejeitar são rejeitados. _Coberto por `AcoesControllerTest::manifestarDfe whitelist exatamente 4 ações canon SEFAZ`._
- **[BACKLOG · 🧪 tem teste] Justificativa exigida só em Desconhecimento e Não Realizada** — Dado a ação escolhida · Quando é `desconhecer`/`nao_realizada` · Então exige justificativa; `cienciar`/`confirmar` não exigem. _Coberto por `AcoesControllerTest::manifestarDfe desconhecer/nao_realizada exigem justificativa, cienciar/confirmar não`._
- **[BACKLOG · 🧪 tem teste] Superfície de ações fiscais existe (contrato do Controller)** — Dado o AcoesController · Quando inspecionado · Então expõe `manifestarDfe` (+ `cancelarNfe`, `cartaCorrecao`, `inutilizar`, `retransmitir` das ondas NFe). _Coberto por `AcoesControllerTest::AcoesController classe existe e tem 5 métodos públicos esperados (Waves 4+5+6)`._
- **[BACKLOG · ⬜ sem teste] Pílula de prazo legal com 3 níveis de urgência** — Dado uma NF-e recebida com `prazo_confirmacao_em` · Quando Eliana lê a linha · Então vê os dias restantes (`prazoDias`) sinalizados como crítico (<7d) / atenção (<30d) / ok — dentro do prazo legal de manifestação (NT 2014.002; charter fala 90d, mas a fonte de verdade é `prazo_confirmacao_em` do SEFAZ). _`prazoDias` é calculado em `buildRowsPayload`, mas nenhum teste valida o cálculo/níveis._
- **[BACKLOG · ⬜ sem teste] Filtro por status via chips** (pendentes=pendente+ciencia / confirmadas / desconhecidas / nao_realizadas / todas) — Dado Eliana clica num chip · Quando a lista recarrega · Então mostra só as NF-e daquele status. _`match` em `buildRowsPayload` existe, sem teste de resultado._
- **[BACKLOG · ⬜ sem teste] Busca por chave 44 / CNPJ emitente / nome emitente** — Dado Eliana digita um termo · Quando busca · Então filtra por `chave_44`, `cnpj_emitente` (dígitos via `preg_replace`) ou `nome_emitente`. _Sem teste de resultado da busca._
- **[BACKLOG · ⬜ sem teste · débito conhecido] Aba Histórico de manifestações processadas** — Dado Eliana abre a aba Histórico · Quando lê · Então vê manifestações já processadas (ação, ator, quando, obs). _Hoje é `mockHistorico()` no Controller (`TODO[CL]`: trocar por query real `status_manifestacao IN (confirmada/desconhecida/nao_realizada)` ordenada por `manifestado_em DESC`). Dado mockado → não é comportamento durável ainda._

> Nota de escopo (charter): esta tela é **leitura + filtros**; o *dispatch* real das 4 ações (mutação) vem em PR futuro. Os testes de `AcoesControllerTest` validam **contratos** (whitelist, regra de justificativa, existência de métodos, validação ≥15 chars CONFAZ), não a persistência ponta-a-ponta.

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — `DfeControllerTest` + `AcoesControllerTest` verdes.
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). Débito = UC-traceability.
