---
date: "2026-08-03"
time: "14:32 BRT"
slug: "documentacao-sdd-operacional"
tldr: "O feature-lint ganhou --init não destrutivo e virou a porta de criação do trio; §B7 e US-CONN-013 estão prontos, enquanto geração/publicação OpenAPI seguem fail-closed."
decided_by: [W]
cycle: null
us: ["US-CONN-013"]
next_steps:
  - "Executar T-01 do plano connector-openapi em sessão de código, no CT 100."
  - "Submeter audiência e URL a [W]/[F] somente após o artefato seguro da T-03."
hour: "14:32 BRT"
topic: "Documentação SDD, piloto OpenAPI e máquina feature:init"
authors: [C]
prs: []
related_adrs: ["0105-cliente-como-sinal-guiar-sem-mandar", "0264-governanca-executavel-trio-dominio-e2e", "0306-strangler-spec-anchored-reconstrucao-sdd"]
---

# Handoff — SDD operacional no guia do sistema

## Pedido e decisão de escopo

[W] pediu para aplicar o plano comparado com o tutorial de SDD em Laravel. A primeira passagem não
apontou uma feature ativa; a varredura dirigida posterior encontrou sinal qualificado no Connector:
OpenAPI 3.0 era gap P0 do BRIEFING, Felipe/Maiara dependiam de ler código para suporte e o SPEC já
registrava que clientes pediam a documentação. A rodada ficou documental: formalizou o piloto sem
gerar artefato, publicar URL ou tocar runtime.

## Mudança

O §B7 passou a documentar:

- quando usar o trio e quando uma mudança simples deve seguir direto;
- as camadas `SPEC US → requirements → plan → tasks → tasks MCP → prova`;
- o ciclo `clarify → lint → teste falhando → implementação → smoke → anchor`;
- as fronteiras Tier 0 para multi-tenant, valor/estoque, PII, tela e runtime;
- o piloto já existente `RecurringBilling/features/gateway-ativacao` e o novo piloto seguro
  `Connector/features/openapi-connector` como exemplos reais.

A orientação foi corrigida contra o contrato vivo do template: copiam-se somente
`requirements.md`, `plan.md` e `tasks.md`. `_TEMPLATE_FEATURE/BRIEFING.md` é a porta canônica do
template e não é copiado para a feature.

No Connector foram criados:

- `US-CONN-013` no SPEC, com sinal, DoD e ponteiro para o trio;
- `requirements.md` com 7 AC e clarifications explícitas;
- `plan.md` com reuso do Scribe existente, plug-points, riscos Tier 0 e `Status vivo`;
- `tasks.md` com 6 tarefas, grafo acíclico e gate humano antes de qualquer publicação.

O default é fail-closed: escopo apenas `/connector/api/*`, response calls e fontes de exemplo real
proibidas, zero token/PII e nenhum uso de `/docs` até [W]/[F] decidirem audiência e URL.

## Máquina antes do trabalho manual

[W] determinou que criar/estender a máquina é mais importante que repetir o processo à mão. O dono
existente `scripts/governance/feature-lint.mjs` foi estendido, sem abrir gerador paralelo:

- `--init <Modulo>/<slug> --us US-MOD-NNN` lê os três templates canônicos e gera somente o trio;
- valida módulo, SPEC e ID exato da US antes de criar diretório;
- carimba `id`, módulo, slug, US, `parent_plan`, datas e `Status vivo`;
- `--dry-run` mostra os três destinos e não escreve;
- destino existente é recusado, inclusive para impedir overwrite acidental;
- o lint passou a tratar `{{placeholder}}` não curado como erro;
- `package.json` expõe `feature:init` e `feature:lint:selftest`.

O template/GUIA passou a mandar usar a máquina; copiar os arquivos à mão deixou de ser o caminho
operacional.

## Prova

- `node scripts/governance/feature-lint.mjs Connector/openapi-connector --check`:
  1 feature, 7 AC, 6 tasks, 0 erros e 0 avisos.
- `node scripts/governance/feature-lint.mjs --json`: 2 features, 0 erros e 0 avisos.
- `npm run feature:lint:selftest`: 31/31 controles positivos/negativos.
- `npm run feature:init -- Connector/maquina-probe --us US-CONN-013 --date 2026-08-03 --dry-run`:
  listou exatamente o trio e o diretório não foi criado.
- controle negativo no destino real `Connector/openapi-connector`: exit 2, overwrite recusado.
- `node scripts/governance/memory-health.mjs --json`: exit 0; os 27 links quebrados reportados
  já existiam e nenhum novo link apareceu. O `Status vivo` evitou aumentar `plan-health` de 22→23.
- `git diff --check`: exit 0.

`maquinas-inventario --check` encontrou um item fora do índice já existente,
`governance/nightly-floor.json` (arquivo de 2026-06-20, não rastreado); não foi criado nem alterado
nesta sessão e o aviso não veio da extensão do `feature-lint`.

## Estado final

Nenhum PHP/runtime ou ADR foi alterado; houve extensão Node da máquina de governança. Não houve
commit, push, PR ou deploy. As mudanças
anteriores da sessão de documentação e os itens não rastreados preexistentes foram preservados.

## Estado MCP no momento do fechamento

As tools MCP `brief-fetch`/`my-work` não estavam disponíveis nesta sessão Codex. O fechamento usou
o fallback canônico por filesystem (`08-handoff`, session mais recente, BRIEFING/SPEC) e não criou
task MCP; o `parent_plan=connector-openapi` ficou reservado no plano versionado.
