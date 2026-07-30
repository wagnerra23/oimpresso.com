---
title: "Organização e contrato documental dos módulos"
date: "2026-07-30"
topic: "Organização e contrato documental dos módulos"
status: concluida
branch: codex/module-documentation-activation
pr: 5069
---

# Sessão — organização e contrato documental dos módulos

## TL;DR

As fontes canônicas, os índices derivados e a ativação de módulos foram separados
por responsabilidade. A máquina passou a auditar a frota inteira e a cobrar no
mesmo PR os arquivos correlacionados de cada módulo novo.

## Pedido

Organizar os arquivos para manutenção, eliminar a disputa entre documentos antigos
e atuais e fazer a máquina cobrar arquivos correlacionados quando um módulo nasce
ou muda.

## Diagnóstico

Havia quatro causas:

1. `memory/modulos/INDEX.md` misturava o checkout atual com branches antigas;
2. os geradores PHP escreviam em dois formatos concorrentes, inclusive
   `memory/requisitos/<M>.md` fora da pasta canônica;
3. `module-surface` omitira manifestos, documentos e assets não reconhecidos;
4. o impacto documental parava no primeiro vizinho e não tinha contrato de
   ativação nem inventário completo do Git.

## Implementação

- geradores PHP passaram a descobrir apenas manifestos atuais e ganharam
  `--index-only`; requisitos novos passaram para `<M>/SPEC.md`;
- superfícies passaram a inventariar todos os arquivos nas raízes declaradas;
- catálogo ganhou `depends_on` estruturado;
- impacto passou a calcular fecho transitivo;
- inventário do repositório passou a classificar todos os paths rastreados;
- ativação de módulo novo passou a cobrar runtime, documentação, teste e
  projeções no mesmo commit;
- toda a frota passou a ser auditada por contrato documental;
- workflow existente ganhou selftests e checks de superfície, catálogo, mapa e
  ativação;
- índices vivos deixaram de apresentar snapshots cross-branch como estado atual.

## Evidência

Resultado final local:

- inventário: 16.236 total, 16.236 classificados, zero sem dono;
- frota: 35 total, 35 completos, zero incompletos;
- selftest do ciclo: 17/17;
- testes Node: 44/44, incluindo colisão de casing e seleção por raiz Git;
- superfícies: 39 contextos sem drift;
- catálogo: zero módulo/ADR pendurado;
- sistema: mapa, arquitetura IA e onboarding em dia;
- saúde da memória: zero falhas; somente avisos históricos não bloqueantes;
- conflitos Git: zero marcadores reais.

O gate Linux encontrou uma colisão que o filesystem case-insensitive do Windows
escondia: NfeBrasil rastreava `pt-BR/nfebrasil.php` e `pt-br/nfebrasil.php` com o
mesmo blob. O duplicado minúsculo saiu do índice; o gerador deixou de percorrer o
filesystem como autoridade e passou a usar índice Git + novos arquivos não ignorados,
menos paths deletados.

O checkout raso do job agregado ainda fazia `system-map` fabricar `2026-07-30` como
último toque de todos os BRIEFINGs. O workflow recebeu `fetch-depth: 0`, e o script
agora falha se o commit usado como evidência for exatamente a fronteira rasa. Um clone
temporário `--depth 1` comprovou a mordida; o worktree com histórico suficiente liberou.

## Pendente de ambiente

Executar no CT 100 o lint/teste PHP dos comandos
`GenerateModuleSpecsCommand` e `GenerateModuleRequirementsCommand`. O checkout
local não continha PHP nem `vendor/autoload.php`; nenhum verde PHP foi inferido.

## Handoff

[2026-07-30 09:31 — contrato documental da frota](../handoffs/2026-07-30-0931-contrato-documental-frota-modulos.md)
