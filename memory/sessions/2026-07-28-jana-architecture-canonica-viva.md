---
date: "2026-07-28"
hour: "15:28 BRT"
duration: "0.25h"
topic: "Consolidação da planta viva no documento canônico de arquitetura da Jana"
authors: [W, C]
outcomes:
  - "memory/requisitos/Jana/ARCHITECTURE.md passou a ser gerado por system-map.mjs"
  - "memory/reference/PLANTA-IA.md foi removido para eliminar o dono paralelo"
  - "Painel, workflow e gates passaram a apontar para o endereço canônico"
prs: []
us: []
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0048-framework-agentes-laravel-ai-vizra-rejeitada"
  - "0062-separacao-runtime-hostinger-ct100"
---

# Session log 2026-07-28 — arquitetura canônica viva da Jana

## TL;DR

Wagner escolheu `memory/requisitos/Jana/ARCHITECTURE.md` como casa da nova documentação. O gerador foi realinhado para escrever diretamente nesse arquivo; a página paralela em `memory/reference/` foi removida.

## Decisão de localização

| Conteúdo | Endereço |
|---|---|
| Arquitetura, topologia e inventário derivado | `memory/requisitos/Jana/ARCHITECTURE.md` |
| Regras funcionais | `memory/requisitos/Jana/SPEC.md` |
| Intenção do produto | `memory/requisitos/Jana/BRIEFING.md` |
| Operação | `memory/requisitos/Jana/RUNBOOK*.md` |
| Diagramas detalhados futuros | `memory/requisitos/Jana/diagrams/` |
| Auditorias datadas futuras | `memory/requisitos/Jana/audits/` |

Não foi criada pasta adicional agora: a topologia atual cabe no `ARCHITECTURE.md`. `diagrams/` só nasce quando houver mais de um diagrama com responsabilidade independente.

## Mudanças

- `system-map.mjs` escreve `Jana/ARCHITECTURE.md`.
- O conteúdo antigo, congelado em 2026-04 e ainda centrado em tabelas `copiloto_*`, foi substituído pelo retrato derivado do código atual.
- `PAINEL-SISTEMA.md` aponta para o novo endereço.
- O workflow observa as três saídas geradas, inclusive contra edição manual.
- O canário de links e o executor de realocação reconhecem o novo output.
- O recibo anterior preserva o endereço intermediário como história e aponta para o destino atual.

## Reconciliação com a atualização paralela do Claude

O merge local inicial não conflitou porque `origin/main` ainda estava em `523e1b3ef2`; os quatro commits do Claude viviam somente em `origin/claude/system-flow-diagram-eb5e1e`. A simulação entre as branches encontrou colisões reais em `system-map.mjs`, `system-map.yml`, `PAINEL-SISTEMA.md` e no onboarding gerado.

A resolução preservou as duas linhas de trabalho:

- censo por contrato `implements`, provedores de `config/ai.php`, `MemoriaContrato`, rerankers e self-test adversarial vieram da atualização do Claude;
- topologia visual, compose, probes e o destino canônico `Jana/ARCHITECTURE.md` vieram desta sessão;
- a tabela detalhada de agentes agora recebe a lista do censo por contrato, não uma segunda varredura restrita à pasta convencional.

## Validação

- `node scripts/governance/system-map.mjs --check`
- `node scripts/governance/system-map-ia.test.mjs`
- `node scripts/governance/onboarding-paths-check.mjs`
- `node scripts/governance/document-relocation-executor.mjs --selftest`
- `node scripts/memory-schemas/validate.mjs --selftest`
- `git diff --check`
- `memory-health.mjs --json --warn-only`: zero falhas; avisos preexistentes fora deste recorte

## Referências

- [Arquitetura viva da Jana](../requisitos/Jana/ARCHITECTURE.md)
- [Painel do sistema](../reference/PAINEL-SISTEMA.md)
- [Sessão anterior](2026-07-28-planta-ia-documentacao-viva.md)
