---
casos: NFS-e Emitidas · /fiscal/nfse
irmaos: Nfse.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — NFS-e Emitidas

> Persona: **Eliana (contadora)** — leitura/conferência fiscal. Tela do cockpit Fiscal (agregador thin sobre NfeBrasil/NFSe).
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75) sem roadmap paralelo.
>
> **Status:** ✅ passa (UC-id citado por teste) · 🧪 tem teste Feature mas **sem UC-id** (débito G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito = rastreabilidade, não ausência de teste.** Comportamento defendido por `NfseCockpitControllerTest` e `NfseCockpitMultiTenantTest` (Modules/Fiscal/Tests/Feature). Falta G-2: nenhum teste cita `UC-FISCAL-NN`. Cada item vira UC no mesmo PR que adicionar o id ao teste. CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste · Tier 0] Isolamento multi-tenant na listagem de NFS-e** — Dado que Eliana está logada no business dela · Quando a lista de NFS-e é carregada · Então nenhuma NFS-e de outro business aparece (global scope `HasBusinessScope`, ADR 0093). _Coberto por `NfseCockpitMultiTenantTest::NfseEmissao HasBusinessScope esconde cross-tenant da listagem do cockpit Nfse`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Bloqueio de acesso sem permissão** — Dado um usuário sem `superadmin` nem `fiscal.nfse.view` · Quando abre `/fiscal/nfse` · Então recebe HTTP 403. _Coberto por `NfseCockpitControllerTest::GET /fiscal/nfse aborta 403 sem permission superadmin nem fiscal.nfse.view`._
- **[BACKLOG · 🧪 tem teste] Renderização da tela com contrato de filtros** — Dado Eliana com permissão · Quando abre `/fiscal/nfse` · Então recebe 200 + componente Inertia `Fiscal/Nfse` com `filters` (search, status, mes) e `counts`. _Coberto por `NfseCockpitControllerTest::GET /fiscal/nfse renderiza Inertia Fiscal/Nfse com filters/counts canon`._
- **[BACKLOG · 🧪 tem teste] Painel de contagens com 6 indicadores** — Dado a tela carregada · Quando lê o cabeçalho · Então vê os 6 counts obrigatórios: total, autorizadas, rejeitadas, processando, canceladas, faturamento. _Coberto por `NfseCockpitControllerTest::counts shape canon — 6 chaves obrigatorias`._
- **[BACKLOG · 🧪 tem teste] Competência inválida não derruba a tela** — Dado Eliana passa um mês malformado (`?mes=INVALIDO`) · Quando a tela processa o filtro · Então ignora silenciosamente e ainda responde 200 (sem 500). _Coberto por `NfseCockpitControllerTest::filtro mes invalido nao crasha (ignora silenciosamente)`._
- **[BACKLOG · 🧪 tem teste] Contrato de status esperado pelo cockpit** — Dado o Model NfseEmissao (schema novo) · Quando o Controller mapeia status · Então as constantes authorized/rejected/pending/sent/cancelled existem e batem. _Coberto por `NfseCockpitMultiTenantTest::STATUS constants estão definidas no Model — Controller depende delas`._
- **[BACKLOG · ⬜ sem teste] Filtro por status via chip-row** (autorizadas/rejeitadas/processando=pending+sent/canceladas) — Dado Eliana clica num chip de status · Quando a lista recarrega · Então mostra só NFS-e daquele status. _Lógica existe em `buildRowsPayload`/`computeCounts`, mas nenhum teste exercita o resultado filtrado com dados reais._
- **[BACKLOG · ⬜ sem teste] Busca por número / código de verificação / nome / CPF-CNPJ do tomador** — Dado Eliana digita um termo · Quando busca · Então a lista filtra por `numero`, `provider_codigo_verificacao`, `tomador_nome`, `tomador_cnpj` ou `tomador_cpf`. _Sem teste que valide o resultado da busca._
- **[BACKLOG · ⬜ sem teste] Filtro por competência (mês) restringe as linhas** — Dado Eliana escolhe um mês · Quando a lista carrega · Então mostra só NFS-e daquela competência (`whereBetween created_at`). _Só o caso de mês inválido tem teste; o caminho feliz de filtragem por mês não._

> ⚠️ **Débito conhecido de execução (não é caso de uso, é infra):** `NfseCockpitControllerTest` fica `markTestSkipped` enquanto persistir o schema race `nfse_emissoes` (batch 69 velho vs batch 106 novo — coluna `emitted_at`). O Controller foi revertido pro schema velho (Caminho A) traduzindo status PT→EN, mas os testes de render ainda gateiam por `Schema::hasColumn('emitted_at')`. Ver cabeçalho do teste + `memory/sessions/2026-05-26-levantamento-martinho-ready.md §B1`.

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — `NfseCockpitControllerTest` + `NfseCockpitMultiTenantTest` verdes (os de render podem sair SKIPPED pelo schema race acima).
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). Débito = UC-traceability.
