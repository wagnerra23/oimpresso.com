---
sessao: "22"
titulo: Configurar pelo certificado — tela (UC-NFTR-18)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main 4bc5c314b8
prefixo_tocado: Tributacao/Index.tsx · Tributacao/_components/ConfigurarPeloCertificado.tsx (novo) · Index.casos.md · e2e/nfe-tributacao-onboarding.spec.ts (novo) · fora do prefixo, declarado no §2 — TributacaoController@aplicarTemplate (repasse do NCM) · TributacaoTemplateAplicarTest (+1 caso) · RUNBOOK-tributacao.md (novo) · SUPERFICIE.md
---
# _saida-22 · Configurar pelo certificado (tela)

**Resposta curta:** o botão "Configurar pelo certificado" abre um drawer lateral de 4 passos sobre a
leitura da thread 21. Sem regime ou sem NCM válido ele não avança; "Aplicar template" só existe no
passo 4; fechar não grava. O NCM escolhido na tela agora é o que a rota aplica.

## 1 · Feito

| arquivo | mudança |
|---|---|
| `_components/ConfigurarPeloCertificado.tsx` (novo) | Sheet lateral (PT-02). Lê `GET empresa-fiscal`; regime divergente vira `RadioGroup` e trava o passo 2; escolher o regime relê `?regime=` e reordena as sugestões; passo 3 exige NCM de 8 dígitos ≠ `00000000`; passo 4 resume regime, template, NCM e "regras por NCM mantidas", com aviso se já há config; "Aplicar template" faz `POST templates/{slug}/aplicar` com `ncm_default` |
| `Index.tsx` | botão no cartão "Configuração rápida por setor" + o drawer |
| `Index.casos.md` | UC-NFTR-14..17 (pedido literal da `_saida-21` §3 item 2) + UC-NFTR-18 |
| `e2e/nfe-tributacao-onboarding.spec.ts` (novo) | o UC-NFTR-18 inteiro; leitura mockada (SEFAZ/BrasilAPI ficam com o Pest da 21), POST de aplicar só contado |

## 2 · Fora do prefixo — e por quê

- **`TributacaoController::aplicarTemplate` passou a repassar `ncm_default`.** O `nao_toca` da thread
  diz `Modules/`, mas a `_saida-21` §3 item 1 pede exatamente esse repasse "na thread 22 ou numa sessão
  de backend". Sem ele o passo 3 seria inerte: a rota ignorava o corpo e aplicava o NCM que a empresa
  já tinha, ou dava 422. Prova: caso novo `UC-NFTR-17 · a rota aplica o NCM padrão que a tela manda`;
  com o controller do `main`, ele cai.
- **`RUNBOOK-tributacao.md` (novo).** O hook `block-mwart-violation` barra edição em `Index.tsx`
  sem RUNBOOK da tela, e ele não existia. Arquivo curto, conforme `runbook.schema.json`.

## 3 · Prova

| rodada | resultado |
|---|---|
| CT 100, worktree isolado: `TributacaoTemplateAplicarTest` + `TributacaoIndexContratoTest` + `EmpresaFiscalLookupTest` | **11 passed · 90 assertions** |
| caso novo com o `TributacaoController` do `main` | **1 failed** (a sessão volta com erro de validação: o NCM do corpo é ignorado) |
| e2e | roda na lane `e2e-gate.yml` deste PR (não roda local, ADR 0062) |

## 4 · Não feito

- **`design-diff` contra o alvo da 12:** nenhum dos 4 alvos medidos cobre o drawer (a `_saida-12`
  §2 registra: "drawers não medidos"). Não há alvo para comparar; medir o `TrOnboarding` é uma
  thread `ALVO` própria.
- **R-NFE-028 no SPEC NfeBrasil** (consolidação pedida na `_saida-21`): fora deste prefixo.
- O passo 1 mostra CNPJ e razão social, não arquivo e validade do certificado: o endpoint da 21 não
  devolve esses dois campos.
