# Protótipo F1 — financeiro-plano-contas

**Status:** 🟡 WAITING_FOR_BACKEND — pré-requisito do DRE.
**Aprovado por:** [W] 2026-05-09 (Cowork)
**Stories:** US-FIN-018 (plano-contas-hierarquico)

> ⚠️ **Antes de começar F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório (T-AP-1 a T-AP-15) + 6 meta-anti-padrões + convenções pt-BR. Sem ele, mesmo erro do batch original repete.

## Por que está bloqueado

Plano de contas é fundação que **não existe** ainda no projeto:

- Tabela `chart_of_accounts` — não existe
- Model `ChartOfAccount` — não existe
- Seeder do template "Comunicação Visual · 2 níveis" — não existe

`PlanoContasController.php` do Cowork tinha 18 contas hardcoded em PHP. `create/edit/importar` retornavam `abort(501)`. `store/update/destroy` faziam redirect vazio (NO-OP).

## Decisão Wagner pré-prompt

> "Plano de contas: **Comunicação Visual · 2 níveis**"

Modelo único pra todos os tenants ROTA LIVRE-like. Bom — protótipo já assume isso.

## R-FIN-011 — código imutável com lançamentos

Regra crítica do protótipo: se conta tem lançamento (`qtd_lancamentos_mes > 0`), código não pode mudar (quebra integridade DRE histórico). Trigger MySQL ou validação Eloquent + Pest covering.

## Pré-requisitos pra abrir F3

1. ADR `arq/0008-plano-contas-hierarquico.md` — decide:
   - hierarquia 2 ou 3 níveis (protótipo assume 3: 1 → 1.1 → 1.1.01)
   - código `1.1.01` é livre ou enforced (regex)?
   - relação `categoria_id` (já existe em `Titulo`) → migrar pra `chart_of_account_id` ou manter dual?
2. Migration `chart_of_accounts` + Model com tenant scope global
3. Seeder template "Comunicação Visual" (~18 contas do mock viram seed)

## Próximo passo

Atacar **antes** do DRE. Ordem do batch:
1. Fluxo de caixa (sem dependência)
2. Plano de contas (fundação)
3. DRE (consome plano)
4. Conciliação (escopo separado, depois)
