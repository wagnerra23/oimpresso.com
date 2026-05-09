# Protótipo F1 — financeiro-dre

**Status:** 🟡 WAITING_FOR_BACKEND
**Aprovado por:** [W] 2026-05-09 (Cowork)
**Stories:** US-FIN-016 (dre-mensal), US-FIN-017 (dre-comparativo)

> ⚠️ **Antes de começar F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório (T-AP-1 a T-AP-15) + 6 meta-anti-padrões + convenções pt-BR. Sem ele, mesmo erro do batch original repete.

## Por que está bloqueado

`DREController.php` do Cowork tinha 18 linhas DRE hardcoded sem tenant scope. Export PDF/Excel = `abort(501)`.

## Dependência: `financeiro-plano-contas`

DRE hierárquico precisa do plano de contas existir primeiro pra agrupar lançamentos por conta-mãe → subconta. Ordem certa:

1. `financeiro-plano-contas` (Model `ChartOfAccount` + tabela)
2. **DRE** (consome plano + soma `Titulo` por conta)

## Decisão Wagner pré-prompt

> "DRE: parar em **Resultado operacional** (Simples Nacional, sem CSLL/IR)"

Bom — protótipo já trata isso (`R-FIN-010 simples-nacional-sem-csll`). Não precisa expandir.

## Pré-requisitos pra abrir F3

1. `chart_of_accounts` table + Model + seeder "Comunicação Visual · 2 níveis"
2. `Modules/Financeiro/Services/DREService::montar(businessId, periodo, granularidade)`
3. ADR técnica `arq/0007-dre-hierarquico.md` (não existe) — define como soma flui da subconta pra conta-mãe
4. Decisão sobre export PDF/Excel — package usar (Spatie ou outro)

## Próximo passo

Atacar **depois** de Fluxo de caixa entregue + plano de contas.
