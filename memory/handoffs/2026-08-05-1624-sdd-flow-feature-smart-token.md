---
date: "2026-08-05"
time: "1624 BRT"
slug: "sdd-flow-feature-smart-token"
tldr: "A cadeia SPEC/US → trio da feature → SDD/CU → tela ganhou geração, lint e recibo. A prova por hash reutiliza o smart token Git SHA já existente; o piloto Financeiro não ganhou verde fabricado em checkout shallow."
decided_by: [W]
cycle: null
prs: []
us: ["US-FIN-003"]
next_steps:
  - "Executar o recibo em checkout com histórico suficiente e revalidar a US-FIN-003 somente após implementação e smoke reais"
  - "Usar sdd:init com --sdd/--cu/--screen nas próximas features complexas"
  - "Derivar um SDD do Connector pelas três fontes antes de ligar openapi-connector; não converter o charter em SDD por atalho"
related_adrs: ["0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0302-fonte-unica-doneness-anchor-aposenta-status-spec", "0306-strangler-spec-anchored-reconstrucao-sdd", "0351-sdd-from-source", "0368-funil-admissao-feature-pesquisa-propoe-w-admite"]
---

# Handoff 2026-08-05 16:24 BRT — SDD por feature com smart token Git SHA

## Resultado

A ambiguidade foi removida sem criar outro documento:

| Unidade | Dono |
|---|---|
| escopo do módulo | `SPEC.md` / US |
| comportamento durável do domínio/família | `SDD-*.md` / fluxos e CU |
| mudança executável | `features/<slug>/requirements.md`, `plan.md`, `tasks.md` |
| lei e aceite da tela | `*.charter.md`, `*.casos.md` |
| prova de entrega | teste/smoke + âncora da US |

`sdd:init` cria o trio; `sdd:flow:check` valida o contrato; `sdd:flow:receipt` só fecha quando os
elos estruturais e hashes estão mensuráveis. O recibo não substitui `casos-gate` nem as lanes CT 100.

## Hash estilo Swimm, sem duplicação

- US: `anchor-lint --stale --json` consome `verificado@<sha7>` e a base derivada já existente;
- refs por linha: `ancora-codigo-sync --check --require-stamp --doc <doc.md>` exige smart token
  válido, detecta `MOVEU/PERDIDO/AMBIGUO` e nunca reescreve a afirmação do contrato;
- escopo: trio + charter/casos da feature, evitando cobrar todo o SPEC/SDD compartilhado.

## Evidências

- regressões de `feature-lint` e `sdd-flow`: verdes; fixture Git prova hash válido → libera e
  alteração contrafactual do código → US stale + referência por linha inválida;
- bite-test de âncora: **11/11**;
- censo do trio: **3 features, 0 erros, 0 avisos**;
- `gateway-ativacao` ligado a `SDD-cobranca-recorrente-v1.0.md` / novo `CU-RB-15`, sem tela por desenho;
- inventário derivado: **457 máquinas, 0 faltando, 0 ghost**;
- piloto Financeiro: cadeia encontrada, mas recibo bloqueado por âncora anterior à feature e
  `checkout_shallow`; esse é o comportamento fail-safe esperado.

## Estado

Não houve alteração de PHP, banco, cálculo de valor/estoque ou runtime. Os diretórios não rastreados
preexistentes foram mantidos fora do escopo. A integração Git foi autorizada por [W] após a validação.
