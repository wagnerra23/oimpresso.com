# Session log — 2026-07-03 · CAPTERRA-FICHA Fiscal (adversário de mercado — programa de ondas Passo 1)

> **Owner:** Wagner OK [W] 2026-07-03 (camada fiscal) · **Agente:** `capterra-senior` (executado inline + 3 sub-agentes de pesquisa paralelos)
> **Base:** worktree fresco de `origin/main` @ `7442c27c43` (base do checkout estava −4688 commits stale — guard SessionStart disparou; deliverable produzido em worktree fresco `claude/fiscal-capterra-onda`)
> **Sinal cliente (ADR 0105):** Larissa @ ROTA LIVRE biz=4 pre-canary (`config/governance/module_clients.yaml`)

## Objetivo

Passo 1 do template-onda-modulo para o módulo **Fiscal**: rodar `capterra-senior` → gerar `memory/requisitos/Fiscal/CAPTERRA-FICHA.md` (10 seções, nota 0-100, P0-P3, 10-15 concorrentes). **Read-only research.** Foco na camada de **configuração/orquestração fiscal** (motor tributário, regras ICMS-ISS, DF-e, manifestação, eventos, config, SPED) — **sem sobrepor** a ficha do NfeBrasil (que mede emissão).

## O que foi feito

1. **Protocolo base** — brief no SessionStart; leitura canônica via `origin/main` (base stale): `template-onda-modulo.md` (Passo 1) + `NfeBrasil/CAPTERRA-FICHA.md` (formato + fronteira) + Fiscal `SCOPE`/`BRIEFING`/`SPEC`/`AUDIT-SENIOR-2026-05-25`.
2. **Ground truth do código** (verificado contra `origin/main` @ `7442c27c43`):
   - Fiscal = thin cockpit/orquestração (module-grade-v3 interno = 66/100), **não** emissor.
   - `MotorTributarioService` (NfeBrasil, consumido pelo Fiscal): cascade-4 níveis, OTel + memoization, ICMS/PIS/COFINS/IPI/CFOP/CST/CSOSN + campo `mva`; **ICMS-ST/DIFAL parcial**.
   - GAP-FISCAL-003 (6 hardcodes SPED) **já fechado** — `SpedIcmsIpiGeneratorService` DI-integra o motor com constantes `FALLBACK_*` Simples (`SpedMotorTributarioIntegrationTest`).
   - DF-e real: `DistribuicaoDfeService` + `BuscarDfesRecebidosJob` (download automático) + 4 manifestações (ADR 0116).
   - Regras cadastradas via `TributacaoController` (templates + import CSV); Fiscal Config = espelho read-only.
   - **IBS/CBS = schema scaffold apenas** (colunas `cClassTrib`/`cst_ibs`/`cst_cbs`/`aliquota_ibs`/`aliquota_cbs` na migration 2026_05_26) — **0 lógica de cálculo** no motor.
3. **Pesquisa de mercado — 3 sub-agentes paralelos** (general-purpose, WebSearch/WebFetch, 24 buscas totais, ~45 fontes citadas, marcações DESCONHECIDO honestas):
   - **Agente 1** — TecnoSpeed (PlugDFe) · PlugNotas · Nuvem Fiscal
   - **Agente 2** — Focus NFe · Bling
   - **Agente 3** — Tiny (Olist) · Omie + estado-da-arte regulatório Reforma Tributária IBS/CBS
4. **Síntese** — matriz P0-P3 (21 capacidades × 8 players), nota ponderada, seções de diferenciais/gaps/reforma. Escrita da ficha (10 seções) + este log.

## Achados-chave

- **Middlewares ≠ motor tributário.** TecnoSpeed/Focus/Nuvem transmitem/distribuem; **o ERP decide a regra** (CST/CFOP/alíquota/MVA/DIFAL). É o papel que `Modules/NfeBrasil` cumpre. Exceção emergente: **Calculadora IBS/CBS do PlugNotas**.
- **Peers ERP reais = Bling/Tiny/Omie.** Todos têm motor por NCM/regime (table-stakes). **Omie** é o mais profundo em obrigações acessórias (SPED EFD-ICMS/IPI + Contribuições + ECD + IA Fiscal). **Bling** faz auto-fill IBS/CBS.
- **Diferenciais únicos do oimpresso** (nenhum concorrente tem): FSM cancel cascade (estorno financeiro + notif cliente), aviso antecipado `cSit` (ADR 0186), ⌘K palette cross-fiscal, "Jana sugere" determinístico, multi-tenant Tier 0.
- **Gap P0 vivo = IBS/CBS cálculo (GAP-FISCAL-004).** Prazo regulatório duro: homologação obrigatória **passou 01/07/2026**; produção obrigatória **03/08/2026** (~1 mês). Risco imediato **contido** porque biz=1 e biz=4 são Simples Nacional (destaque só em 2027-01), mas crítico se qualquer piloto migrar p/ Regime Normal. Dependência: `nfephp-org/sped-nfe` tem IBS/CBS só em `dev-master` (tag estável v5.1.34 sem reforma — issue #1274).

## Nota Capterra Fiscal = **75/100**

Ponderação P0=4/P1=2/P2=1/P3=0.5 · Σ 38.5 ÷ 51. P0: 7/8 cobertos (só IBS/CBS = ❌). P1 em meia-luz (ICMS-ST/DIFAL, ISS municipal, SPED, health-check cert = 🟡). P2/P3 escrituração faltando (EFD-Contribuições, MDF-e = ❌). Diferenciais seguram a nota alta.

## Entregáveis

- `memory/requisitos/Fiscal/CAPTERRA-FICHA.md` — ficha canônica 10 seções (novo)
- `memory/sessions/2026-07-03-capterra-fiscal.md` — este log (novo)

## Próximos passos (programa de ondas)

- **Passo 2** — `/comparativo Fiscal` → `CAPTERRA-INVENTARIO.md` (buckets ✅🟡❌) + batch `tasks-create` (aguarda OK [W]) + apenda US ao SPEC.
- **Passo 3** — régua por tela (screen-grade + casos_coverage) das 7 sub-páginas.
- **Passo 4** — catraca + sentinela.
- **Recomendação forte:** priorizar **Onda 6 IBS/CBS (GAP-FISCAL-004)** como próximo P0 — único P0 zerado + prazo regulatório 03/08/2026.

## Notas de processo

- Deliverable em worktree fresco de `origin/main` (base do checkout −4688 stale). PR a partir de `claude/fiscal-capterra-onda`, não da branch stale.
- Preços de concorrente descritos qualitativamente (sem dígitos R$) — convenção da ficha NfeBrasil + proibição BRL em memory.
- Read-only: zero mudança em código de `Modules/`. Só 2 arquivos markdown em `memory/`.
