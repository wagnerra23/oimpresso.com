---
date: "2026-08-05"
topic: "SDD por feature com recibo e smart token Git SHA"
slug: "sdd-flow-feature-smart-token"
status: "fechada-sem-publicacao"
scope: "governança executável de SPEC, SDD, feature e tela"
---

# Sessão — SDD por feature com recibo e smart token Git SHA

## Pedido

Clarificar a função do SDD, adotar `requirements → plan → tasks` por feature e automatizar a cadeia.
Durante a implementação, [W] exigiu a verificação por hash no estilo Swimm que já existia no projeto.

## Decisão aplicada

O documento `SDD-*.md` permaneceu como contrato durável do domínio/família de telas. O trio
`requirements.md`, `plan.md` e `tasks.md` ficou por feature complexa, ligado explicitamente a US,
SDD, CU e telas. Não nasceu um hash novo: o recibo passou a consumir os dois mecanismos existentes:

- `anchor-lint --stale --json` para o `verificado@<sha7>` da US, com base derivada pós-squash;
- `ancora-codigo-sync --check --require-stamp --doc` para referências `arquivo:linha` nos documentos
  exclusivos da feature/tela.

SPEC e SDD compartilhados não entram na varredura integral por linha, para uma feature não herdar
dívida de outra; seus elos continuam sob `anchor-lint` e validação exata dos CU.

## Entregas

- `sdd:init` gera o trio e aceita `--sdd`, `--cu` e `--screen`;
- `feature-lint` valida ligações em ambas as direções úteis, AC/tasks, dependências, DoD e fechamento;
- `sdd:flow`, `sdd:flow:check` e `sdd:flow:receipt` produzem o recibo estrutural;
- templates e `GUIA-DO-SISTEMA.md` documentam a unidade correta de cada artefato;
- workflow de scripts de governança executa o bite-test novo;
- o exemplo `Financeiro/recebimento-parcial-parcela` recebeu ligações factuais ao SDD/CU/tela.
- `RecurringBilling/gateway-ativacao` foi ligado ao SDD existente, que ganhou `CU-RB-15`; a ausência
  de tela ficou explícita por desenho porque a entrega é um comando operacional.

## Validações

| Verificação | Resultado |
|---|---|
| `feature-lint.test.mjs` | verde |
| `sdd-flow.test.mjs` | verde; hash válido libera e drift contrafactual morde US + ref por linha |
| `ancora-codigo-sync --selftest` | 11/11 |
| `feature-lint --check` | 3 features; 0 erros; 0 avisos |
| `sdd-scorecard --ratchet` | verde após redestilar o `BRIEFING` de RecurringBilling com `CU-RB-15`; baseline intacto |
| inventário de máquinas | 457 cobertas; 0 faltando; 0 ghost |
| `git diff --check` | verde |

## Limite honesto observado

O piloto Financeiro ficou em `em-execucao`: a âncora de US é de 2026-07-27, anterior ao trio criado
em 2026-08-03, e o eixo temporal retornou `unknown:checkout_shallow`. O recibo falhou de propósito;
`unknown` não foi convertido em fresco. Nenhum teste de PHP/runtime foi executado, pois a mudança é
Node/documentação e o runtime de testes pertence ao CT 100.

## Estado de integração

- A integração Git foi autorizada por [W] após a validação; PR #5329 foi aberto a partir de branch
  fresca baseada em `origin/main` e ficou sujeito aos checks do SHA final.
- MCP não foi materializado porque nenhuma tool MCP de tarefas estava disponível nesta execução.
- Diretórios não rastreados preexistentes foram preservados.
- O Connector continuou sem ligação SDD: não existe `SDD-*.md` nesse módulo e a triangulação não foi
  fabricada a partir do código; uma futura criação deve passar por `sdd-from-source`.
