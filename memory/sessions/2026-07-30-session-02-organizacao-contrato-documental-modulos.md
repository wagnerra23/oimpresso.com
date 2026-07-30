---
title: "Organização e contrato documental dos módulos"
date: "2026-07-30"
status: concluida
branch: codex/module-documentation-activation
pr: 5069
---

# Sessão — organização e contrato documental dos módulos

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

- inventário: 16.349 total, 16.349 classificados, zero sem dono;
- frota: 36 total, 36 completos, zero incompletos;
- selftest do ciclo: 15/15;
- testes Node: 42/42;
- superfícies: 40/40 sem drift;
- catálogo: zero módulo/ADR pendurado;
- sistema: mapa, arquitetura IA e onboarding em dia;
- conflitos Git: zero marcadores reais.

## Pendente de ambiente

Executar no CT 100 o lint/teste PHP dos comandos
`GenerateModuleSpecsCommand` e `GenerateModuleRequirementsCommand`. O checkout
local não continha PHP nem `vendor/autoload.php`; nenhum verde PHP foi inferido.

## Handoff

[2026-07-30 09:31 — contrato documental da frota](../handoffs/2026-07-30-0931-contrato-documental-frota-modulos.md)
