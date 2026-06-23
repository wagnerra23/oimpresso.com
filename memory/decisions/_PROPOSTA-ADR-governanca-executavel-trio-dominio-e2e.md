# _PROPOSTA_ ADR — Governança executável: trio-de-tela + caso↔teste + domínio + E2E viram **gates de CI bloqueantes** (ratchet)

> **Status:** PROPOSTA de [CC]. [W] autorizou no chat 2026-06-09 — textual: *"se não tiver testes vai desandar… se isso ficar apenas em memória sem uma regra obrigando fazer vai morrer no tempo. gere o handoff para [CL] pelo protocolo."*
> **Número/versão = só [W]/git** (soberania ADR 0238). [CL] confirma o nº livre (provável 0254+) e versiona.
> **Tier 0** (CI/governança). Estende **ADR 0244** (estratégia de teste estado-da-arte) com a camada de **enforcement**, e estende ADR 0243 (casos.md) + ADR 0094 (constituição).

- **Data:** 2026-06-09 · **Sessão:** Cowork [CC] ↔ Wagner

## Contexto / problema
[W]: *"a IA faz tudo muito rápido mas viaja muito."* O sistema **já tem** as 4 camadas que seguram drift — `charter.md` (spec homologada), `casos.md` (UC/BDD), Pest/Vitest (teste), `LICOES_CC`/`proibicoes` (lição negativa) — **mas a maioria não é obrigatória por máquina**: vive em memória/disciplina e **morre no tempo**. Hoje os gates de CI existentes cobrem **identidade visual** (`conformance`/`foundation`/`pageheader`/`design-spec`/`a11y`/`reuse`/`no-mock`/`layout`) — **nenhum** cobre **cobertura de spec, rastreabilidade caso→teste, comportamento na tela, nem coerência de domínio**. E os testes atuais são majoritariamente **estruturais** (reflection + source-grep: provam que o código *existe*, não que *funciona* — L-24 "presença ≠ correção"); faltam testes de **comportamento em navegador real**.

## Decisão — 4 gates novos, no mesmo padrão baseline/ratchet dos existentes

### G-1 · Gate de **trio-de-tela** (`casos:check`)
Todo `.tsx` **roteado** (página, não `_components/`) DEVE ter, ao lado: `*.charter.md` **e** `*.casos.md`. CI **falha** se faltar. Padrão ratchet (`--write-baseline` aceita o legado atual; novo arquivo sem trio bloqueia). Mata a cobertura desigual (Oficina tem trio, outras não).

### G-2 · Gate de **rastreabilidade caso↔teste** (parte do `casos:check`)
Todo `UC-*` declarado num `*.casos.md` DEVE ser referenciado por ≥1 teste (string do ID no arquivo de teste). UC órfão (sem teste que o defenda) = **falha** (ratchet). Fecha o "caso no papel sem teste".

### G-3 · **E2E de comportamento** — Playwright nos UCs críticos (ADR 0244 Fase 1, agora obrigatório)
Playwright (navegador real, headless no CI) cobrindo os 3 UCs críticos do piloto: **Oficina UC-06** (gate de etapa) · **Vendas UC-V05** (split NF-e/NFS-e) · **Financeiro UC-F02** (saldo). Locators resilientes (`getByRole`/`getByText`/`data-testid` — **nunca** classe CSS, L-24). `e2e:check` entra no CI **bloqueante** pra esses 3. Demais UCs migram por ratchet (Fase 2 Storybook).

### G-4 · Gate de **dicionário de domínio** (`dominio:check`)
Fonte única versionada por módulo em `memory/dominio/<modulo>.md`: estados FSM + enums (`order_type`, `tipo` de item, status) + regras de negócio. `scripts/domain-dict-guard.mjs`: todo enum definido em migration DEVE constar no dicionário (e vice-versa) — divergência = **falha**. **Semeado pela Oficina** já pós-erradicação de locação (`order_type ∈ {manutencao, mecanica}`; FSM ServiceOrder 0143; item ∈ {peca, mao_obra, servico_terceiro}). É a camada que a alucinação da locação atravessou (nenhuma spec de *tela* a pegava).

## Faseamento (cada fase é um PR; ratchet, não big-bang)
1. **F1 — gates como baseline (não-bloqueante 1 build):** `casos:check`+`dominio:check` rodam, gravam baseline do legado, reportam dívida. Playwright dos 3 UCs **já bloqueante** (são poucos).
2. **F2 — flip pra bloqueante:** após [W] ver o relatório de dívida, gates viram `required` no CI. Novo código não regride.
3. **F3 — ratchet:** dívida do baseline cai a cada PR que toca a tela (charter/casos/teste faltante vira obrigatório ao mexer). Storybook play-functions + visual regression (ADR 0244 Fase 2) cobrem o resto dos UCs.

## Consequências
- **+** a disciplina vira **máquina**: trio, rastreabilidade, comportamento e domínio param de depender de memória. Drift da IA falha o CI, não passa silencioso.
- **−** custo de setup (Playwright + 2 guards + dicionário semente) — por isso ratchet/faseado.
- Não substitui revisão humana nem teste de emissão fiscal real (backend SEFAZ).

## Não-objetivos
- Não criar harness paralelo: os guards seguem o padrão dos `scripts/*.mjs` existentes; Playwright e Storybook leem a MESMA fonte (ADR 0244).

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-06-09 | [CC] propõe | enforcement executável das 4 camadas; origem [W] "sem regra obrigando, morre no tempo" + "testes senão desanda". Estende ADR 0244. Handoff: `PROMPT_PARA_CODE_GOVERNANCA-EXECUTAVEL.md`. |
