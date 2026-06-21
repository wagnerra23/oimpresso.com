---
slug: 0264-governanca-executavel-trio-dominio-e2e
number: 264
title: "Governança executável: trio-de-tela + caso↔teste + domínio + E2E viram gates de CI (baseline→required→ratchet)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-09"
accepted_at: "2026-06-09"
accepted_via: "Wagner autorizou no chat 2026-06-09 (zero-toque): 'se não tiver testes vai desandar… se isso ficar apenas em memória sem uma regra obrigando fazer vai morrer no tempo. gere o handoff para [CL] pelo protocolo.' Numeração/ratificação por [CL] sob soberania ADR 0238."
module: governance
quarter: 2026-Q2
tags: [governance, ci, enforcement, ratchet, testing, e2e, playwright, domain-dictionary, casos, charter, tier-0]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0261-enforcement-faseado-gates-ci", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0263-identidade-cor-gate-bloqueante", "0250-screen-qa-specialist-sustentavel", "0108-regressao-visual-pest-browser-tier-2", "0128-smoke-testing-e2e-pos-cycle", "0255-contrato-view-deterministico-charter-design-spec", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
---

# ADR 0264 — Governança executável: as 4 camadas que seguram drift viram gate de CI

## Contexto / problema

Wagner (2026-06-09): *"a IA faz tudo muito rápido mas viaja muito."* + *"se não tiver testes vai desandar… se isso ficar apenas em memória sem uma regra obrigando fazer vai morrer no tempo."*

O sistema **já tem** quatro camadas que seguram o drift da IA:

1. **`charter.md`** — spec homologada da tela (Mission/Goals/Non-Goals/UX targets).
2. **`casos.md`** — casos de uso (UC) com aceite verificável (Dado/Quando/Então).
3. **Pest/Vitest** — teste automatizado.
4. **`LICOES_CC`/`proibicoes`** — lição negativa (o que não repetir).

**Mas a maioria não é obrigatória por máquina** — vive em disciplina/memória e **morre no tempo**. Censo em `@main` (2026-06-09): **277 páginas roteadas**, **138 `charter.md`**, e **apenas 1 `casos.md`** (Oficina). Os gates de CI existentes cobrem **identidade visual** (`conformance`/`foundation`/`pageheader`/`design-spec`/`a11y`/`reuse`/`no-mock`/`layout`) — **nenhum** cobre **cobertura de spec, rastreabilidade caso→teste, comportamento na tela, nem coerência de domínio**. E os testes atuais são majoritariamente **estruturais** (reflection + source-grep: provam que o código *existe*, não que *funciona* — lição L-24 "presença ≠ correção"); faltam testes de **comportamento em navegador real**.

Isso colide com o **princípio duro #4 (loop fechado por métrica)** e com a lei da catraca de sobrevivência ([ADR 0256]: *o que é derivado + enforçado sobrevive; o que é escrito + lembrado apodrece*). É exatamente o gap que a alucinação da "locação de caçamba" (ver [ADR 0265]) atravessou: **nenhuma spec de tela nem gate de domínio a pegava.**

## Decisão — 4 gates novos, no **mesmo padrão baseline/ratchet** dos existentes

Não se inventa harness novo. Os guards seguem o idioma dos `scripts/*.mjs` (ROOT=`process.cwd()`, walk `node:fs`, baseline JSON com `_meta`, flag `--write[-baseline]`, exit 1 só em regressão) — gêmeos de `pageheader-migration-guard.mjs` e `no-mock-in-prod.mjs`. O enforcement é **faseado** exatamente como o [ADR 0261] (não-bloqueante → required → ratchet), e o flip para bloqueante segue o precedente do [ADR 0263] (identidade de cor: baseline primeiro, required depois).

### G-1 · Gate de **trio-de-tela** (`casos:check`)
Toda `.tsx` **roteada** em `resources/js/Pages/**` (exclui `_components/`) DEVE ter, ao lado: `*.charter.md` **e** `*.casos.md`. CI **falha** se faltar — padrão ratchet (`--write-baseline` aceita o legado atual; arquivo NOVO sem trio bloqueia). Mata a cobertura desigual (1 de 277 telas tem `casos.md`).

### G-2 · Gate de **rastreabilidade caso↔teste** (parte do `casos:check`)
Todo `UC-*` declarado num `*.casos.md` DEVE ser referenciado por ≥1 arquivo de teste (string do ID em `Tests/**`, `tests/**`, ou spec Playwright). UC órfão (caso no papel sem teste que o defenda) = **falha** (ratchet). Fecha o "caso no papel sem teste".

### G-3 · **E2E de comportamento** — Playwright nos UCs críticos
Playwright (navegador real, headless no CI) cobrindo os 3 UCs críticos do piloto: **Oficina UC-06** (gate de etapa bloqueia avanço sem checklist) · **Vendas UC-V05** (split fiscal NF-e/NFS-e) · **Financeiro UC-F02** (saldo). Locators **resilientes** (`getByRole`/`getByText`/`data-testid` — **nunca** classe CSS, L-24). `e2e:check` entra no CI **bloqueante** para esses 3 (são poucos; sem deadlock). Demais UCs migram por ratchet (Storybook play-functions + regressão visual — eixo do [ADR 0108]).

### G-4 · Gate de **dicionário de domínio** (`dominio:check`)
Fonte única versionada por módulo em `memory/dominio/<modulo>.md`: estados FSM + enums (`order_type`, `tipo` de item, status) + regras de negócio. `scripts/domain-dict-guard.mjs`: todo `enum(...)` definido em migration de módulo DEVE constar no dicionário (e vice-versa) — divergência = **falha** (ratchet). **Semeado pela Oficina** já pós-erradicação de locação ([ADR 0265]): `order_type ∈ {manutencao, mecanica}`; FSM ServiceOrder ([ADR 0143]); item `tipo ∈ {peca, mao_obra, servico_terceiro}`. É a camada que a alucinação da locação atravessou.

## Faseamento (cada fase é um PR; ratchet, não big-bang) — espelha ADR 0261

1. **F1 — gates como baseline (não-bloqueante 1 build):** `casos:check`+`dominio:check` rodam, gravam baseline do legado, **reportam a dívida** (o relatório que Wagner pediu para ver). Playwright dos 3 UCs **já bloqueante**.
2. **F2 — flip para bloqueante:** após Wagner ver o relatório de dívida, os 2 guards viram `required` no mesmo eixo de CI dos `conformance`/`foundation` (always-run + skip-as-pass, padrão ADR 0261 Alavanca 2). Novo código não regride o baseline.
3. **F3 — ratchet:** a dívida do baseline cai a cada PR que **toca a tela** (charter/casos/teste faltante vira obrigatório ao mexer). Storybook + regressão visual cobrem o resto dos UCs. **DoD final:** baseline `casos:check` = **0** nas telas em produção (Officeimpresso/OficinaAuto/Vendas/Financeiro/Fiscal núcleo); `dominio:check` = 0; Playwright cobre os UCs críticos de cada vertical viva. Aí o sistema **se obriga sozinho** — é o estado que impede "morrer no tempo".

## Reconciliação de numeração (soberania ADR 0238)

O handoff Cowork (`PROMPT_PARA_CODE_GOVERNANCA-EXECUTAVEL.md`) referenciou esta decisão como "estende **ADR 0244** (estratégia de teste estado-da-arte)" + "**ADR 0243** (casos.md)" e instruiu "ratifique também a ADR 0244". **Esses números pertencem a um esquema de numeração paralelo do Cowork e não batem com o `@main`:** no repo, `0243` = *processo-memoria-evolucao-design-cowork* e `0244` = *ds-v5-canon-oficina-padrao*. Não existe uma "ADR 0244 de estratégia de teste" no repo, e seu texto-proposta não foi entregue a [CL]. Por **ADR 0238** ([CL] numera/versiona/ratifica), esta decisão **landa como ADR 0264** (próximo nº livre — maior no disco era 0263) e **absorve a estratégia de teste** (E2E de comportamento Playwright + regressão visual Storybook) aqui, fundamentada nas ADRs de teste que **de fato** existem: [0108] (regressão visual Pest Browser Tier 2), [0128] (smoke E2E pós-cycle), [0255] (contrato view determinístico charter+design-spec) e o eixo de catraca de [0256]/[0261]/[0263]. **Não se fabrica uma ADR 0244-equivalente que não foi recebida.** A divergência de numeração foi sinalizada a Wagner no relatório da sessão (HITL informativo, não-bloqueante).

## Consequências

- **+** A disciplina vira **máquina**: trio, rastreabilidade, comportamento e domínio param de depender de memória. Drift da IA **falha o CI**, não passa silencioso.
- **+** Fecha o gap da catraca ([ADR 0256]): as 4 camadas passam de "escrito + lembrado" para "derivado + enforçado".
- **−** Custo de setup (Playwright + 2 guards + dicionário semente) — por isso ratchet/faseado.
- **−** Não substitui revisão humana nem teste de emissão fiscal real (backend SEFAZ).
- Risco de gate flaky travar merge → **mesma mitigação do ADR 0261**: E2E LLM-eval nunca é required; só os 3 UCs determinísticos.

## Não-objetivos

- Não criar harness paralelo: os guards seguem o padrão dos `scripts/*.mjs` existentes; Playwright e Storybook leem a MESMA fonte (`casos.md` + dicionário).
- Não bloquear o legado de uma vez (quebra o repo) — é ratchet.
- Não usar seletor de classe CSS em teste (L-24) — `data-testid`/role only.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-09 | [CC] propõe / [CL] ratifica | Enforcement executável das 4 camadas. Origem Wagner "sem regra obrigando, morre no tempo" + "testes senão desanda". Numerada 0264 sob soberania ADR 0238; reconciliação dos refs Cowork 0243/0244 → ADRs reais. Handoff: `PROMPT_PARA_CODE_GOVERNANCA-EXECUTAVEL.md`. |
