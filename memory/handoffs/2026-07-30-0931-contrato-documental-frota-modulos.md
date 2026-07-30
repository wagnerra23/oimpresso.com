---
title: "Contrato documental da frota e ativação atômica de módulos"
date: "2026-07-30"
time: "09:31"
slug: contrato-documental-frota-modulos
tldr: "A frota passou a ter fontes canônicas, projeções regeneráveis e ativação atômica cobrada no mesmo PR."
status: concluido
branch: codex/module-documentation-activation
pr: 5069
---

# Contrato documental da frota e ativação atômica de módulos

## TL;DR

A frota passou a ter fontes canônicas, projeções regeneráveis e ativação atômica
cobrada no mesmo PR. O inventário classificou todos os 16.237 arquivos rastreados
e a auditoria documental fechou os 35 módulos atuais sem lacunas.

## Resultado

O ciclo documental passou a partir do universo exato do Git e separou fontes de
projeções:

- `git ls-files -z`: **16.237/16.237** paths classificados; `unclassified: []`;
- frota atual: **35/35** manifestos com `SCOPE`, `BRIEFING`, `SPEC`,
  `SUPERFICIE`, teste e nó de catálogo;
- `VozDoCliente/SPEC.md` fechou a única ausência da frota;
- `memory/modulos/INDEX.md` e `memory/requisitos/INDEX.md` passaram a listar
  somente os manifestos atuais; snapshots antigos permaneceram históricos;
- `Financeiro/SCOPE.md` declarou `depends_on: [Sells, Compras]`, e o catálogo
  provou a relação sem inferência por prosa;
- novo `module.json` passou a iniciar uma transação atômica de ativação, cobrada
  no workflow existente `module-surface.yml`;
- o fechamento de impacto passou de um salto para fecho transitivo com
  profundidade explícita.

## Organização adotada

| Papel | Dono |
|---|---|
| Existência no runtime | `Modules/<M>/module.json` |
| Fronteira e relações | `Modules/<M>/SCOPE.md` |
| Estado factual | `memory/requisitos/<M>/BRIEFING.md` |
| Contrato funcional | `memory/requisitos/<M>/SPEC.md` |
| Prova | `Modules/<M>/Tests/**` ou teste equivalente |
| Inventário da raiz | `memory/requisitos/<M>/SUPERFICIE.md` — gerado |
| Relações | `memory/governance/catalog.json` — gerado |
| Navegação global | painel e índices — gerados |

Os `memory/modulos/*.md` antigos não foram apagados nem movidos: são contratos de
path e registros históricos. A limpeza foi de **autoridade**, não de evidência.

## Provas executadas

- `documentation-loop --selftest`: 17/17, incluindo bite negativo de módulo sem
  SPEC, grafo profundo e toda a frota real;
- testes Node combinados: 42/42;
- `module-surface --all --check`: 39 contextos sem drift;
- `catalog-graph --check`: 38 módulos, 612 nós, 935 arestas, zero pendurados;
- `system-map --check` e `system-map-ia.test`: verdes;
- `memory-health`: zero falhas; avisos históricos permaneceram não bloqueantes;
- impacto real: exit 0, `activation_ok: true`, 16.237 classificados, zero sem dono;
- `git diff --check`: verde; marcadores `<<<<<<<`/`>>>>>>>`: zero.

## Limite honesto

PHP e `vendor/` não estavam disponíveis localmente. Os dois comandos Artisan
alterados precisam de lint/teste no CT 100, conforme ADR 0062. Dez módulos
históricos usam layouts alternativos de rota/composer; não foram normalizados
em massa. O contrato rígido de runtime ficou restrito a módulos novos, e a frota
existente ficou sob o contrato documental comum.

Durante a atualização do PR, o `main` removeu o módulo Admin. A resolução preservou
a remoção e regenerou catálogo, superfícies, painel e índices; por isso o denominador
final passou de 36 para 35 sem lista manual.

## Estado MCP no momento do fechamento

Nenhum MCP de domínio participou desta mudança. A prova foi produzida diretamente
no checkout Git e no PR #5069 por scripts determinísticos do repositório; os checks
de PHP/Pest/PHPStan ficaram delegados ao CT 100 conforme ADR 0062.
