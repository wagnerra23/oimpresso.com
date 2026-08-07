---
date: "2026-08-03"
hour: "14:32 BRT"
duration: "1h"
topic: "Adoção do SDD, piloto OpenAPI e extensão da máquina feature-lint"
authors: [C]
prs: []
us: ["US-CONN-013"]
outcomes:
  - "O fluxo SDD local foi documentado no §B7 do GUIA-DO-SISTEMA, fonte runtime de /documentacao."
  - "O sinal P0 do Connector foi formalizado como US-CONN-013 e trio openapi-connector, sem geração ou publicação."
  - "A receita passou a gerar somente requirements/plan/tasks pela máquina; o BRIEFING do template não se copia."
  - "O feature-lint ganhou --init/--dry-run, scaffold não destrutivo e erro para placeholder não curado."
  - "O self-test da máquina passou 31 controles e o dry-run real não escreveu no workspace."
  - "Feature lint full-tree ficou verde com 2 features, 0 erros e 0 avisos; memory-health e diff-check passaram."
related_adrs: ["0105-cliente-como-sinal-guiar-sem-mandar", "0264-governanca-executavel-trio-dominio-e2e", "0306-strangler-spec-anchored-reconstrucao-sdd"]
---

# Sessão — documentação SDD operacional

## Contexto

[W] pediu para executar a melhoria depois da comparação entre o tutorial externo e a estrutura
do O Impresso. A análise mostrou que o repositório já tinha template, linter e um piloto completo;
o gap era tornar o caminho fácil de encontrar e executar.

## Implementação documental

Foi criada a seção `B7. Como especificar e executar uma feature complexa (SDD)` em
`memory/GUIA-DO-SISTEMA.md`. Ela preserva os donos existentes: requisito na US do SPEC, detalhe
no trio colocado junto ao módulo, estado no MCP e done-ness na âncora viva da US.

Uma varredura dirigida encontrou sinal inequívoco no Connector: OpenAPI era gap P0 no BRIEFING,
Felipe/Maiara não conseguiam prestar suporte sem ler código e o SPEC registrava que clientes
pediam a documentação. Foi criada a US-CONN-013 e o trio `features/openapi-connector/`.

O plano reutiliza o Scribe já instalado e impede o estado inseguro atual de virar publicação por
acidente: matcher largo, response calls GET, exemplos derivados de Models e saída `public/docs`
viraram alvos explícitos do teste failing-first. Audiência e URL permanecem gate humano [W]/[F].

## Correção de orientação

A leitura integral do `_TEMPLATE_FEATURE/BRIEFING.md` mostrou que ele é a porta canônica e não
deve ser copiado. Portanto, a receita publicada manda copiar apenas os três arquivos validados
pelo `feature-lint`: `requirements.md`, `plan.md` e `tasks.md`.

Após orientação explícita de [W] de que máquina é mais importante que execução manual, a receita
foi elevada novamente: agora o caminho é `npm run feature:init -- <Mod>/<slug> --us US-...`.
O `--init` vive dentro do próprio `feature-lint.mjs`, lê os templates canônicos, valida a US e é
fail-closed para destino existente. O lint também acusa placeholder não curado, evitando scaffold
que parece completo sem ter sido especificado.

## Validação

- feature-lint do Connector: verde, 7 AC, 6 tasks, 0 erros, 0 avisos;
- feature-lint full-tree: 2 features, 0 erros e 0 avisos;
- `feature:lint:selftest`: 31/31 controles, incluindo ID exato de US, zero BRIEFING, plano vivo,
  recusa de overwrite/US ausente e dry-run sem escrita;
- invocação real via `npm.cmd run feature:init -- ... --dry-run`: listou somente o trio e não criou
  `Connector/features/maquina-probe`;
- memory-health: exit 0, sem link novo quebrado;
- plan-health permaneceu no baseline anterior de 22 avisos após inclusão do `Status vivo`;
- git diff-check: exit 0;
- sem testes PHP, pois a mudança de código é Node/governança; runtime CT 100 não foi tocado.

## Estado final

Mudança documental apenas, ainda sem commit/PR. O worktree já estava sujo pela rodada anterior
de documentação; esses arquivos e os itens não relacionados foram preservados.
