---
date: "2026-08-12"
time: "18:10 BRT"
slug: fronteira-modulos-shared-kernel-e-o-vazamento
tldr: "Fronteira entre módulos medida e reduzida: 57→37 pares, cobertura 28,1%→37,8%, seta invertida app/→Modules de 31→19 arquivos. 4 passos (catraca de direção · scopes · PiiRedactor · catraca de acoplamento) em 5 PRs. No caminho, um codemod que assumia 1 forma de referência quando eram 4 vazou um CPF pra telemetria — o Pest pegou, não chegou em prod."
prs: [5657, 5661, 5670, 5674, 5675]
decided_by: [W]
related_adrs: [0370-module-surface-catalog-graph-required-emenda-0314, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0093-multi-tenant-isolation-tier-0]
next_steps:
  - "Decidir as 19 fronteiras de NEGÓCIO (não-mecânicas; as quentes tocam dinheiro — regra Tier 0 de cálculo de valor)"
  - "Avaliar adoção do Deptrac (dependência nova ⇒ exige ADR): cobriria o eixo import por AST, mas não o de tabela nem chave de scope"
  - "Pendência pré-existente: citação 'ADR 0094 §Princípio 6' no PII-LGPD §1 é imprecisa (P6 = multi-tenant)"
---

# Fronteira entre módulos — 4 passos, e o vazamento no meio do caminho

## Estado MCP no momento do fechamento

⚠️ **MCP não conectado nesta worktree** — usei o fallback filesystem documentado em
[`how-trabalhar.md`](../how-trabalhar.md) §Fallback. Snapshot por git/glob:

- **PRs meus mergeados hoje:** `#5657` `#5661` `#5670` `#5674` `#5675` (o `#5677` entrou na base do `#5675`)
- **PRs fechados/substituídos:** `#5664` e `#5668` (nasceram de branch squash-mergeada ⇒ `DIRTY`, zero checks)
- **Handoffs irmãos de hoje:** 5 (índice em append concorrente — resolvi por merge, append-only)
- **Brief do SessionStart:** cycle sem foco declarado; 5 HITL pendentes [W]

## O que aconteceu

Pedido: *"o quanto pode melhorar"* na arquitetura de fronteira. **Não criei agente** — rodei o
inventário e o dono existia (`catalog-graph.mjs`, required pela ADR 0370). Estendi.

O achado que sustentou tudo: o grafo media a fronteira **declarada** (frontmatter à mão). Medindo a
**real** (import no código): **28,1% de cobertura**. E um eixo que nenhum medidor via — `app/`
importando `Modules/` em 60 imports / 31 arquivos, com o trait multi-tenant **Tier 0 do núcleo**
importando de dentro de um módulo.

Depois: parecer de especialista (que corrigiu **um bug meu** — `Subscription` são 2 classes
homônimas e meu detector agrupava por nome curto), 4 refactors, 2 catracas.

| | pares | cobertura | alojamento | seta invertida |
|---|---|---|---|---|
| início | 57 | 28,1% | 22 | 31 arquivos |
| fim | **37** | **37,8%** | **2** | **19 arquivos** |

## O vazamento (a parte que importa)

O codemod procurou o FQCN com **uma** barra; em string literal PHP os bytes têm **duas**, e as formas
não se cruzam como substring. 16 sites perdidos — num deles, `class_exists()` com **fail-open**
devolveu `null` e **desligou a redação de PII em silêncio**. Um CPF foi parar nos eventos de
telemetria. O `ChatStreamObservabilityTest` reprovou. **Não chegou em prod.**

Foram **4 formas** de referência e **nenhuma eu achei**: string escapada (Pest) · nome curto em
mesmo-namespace (PHPStan) · link meio-atualizado (deadlink-gate) · registro datado (refutador GT-G5).

Pior: eu **refutei 2 achados verdadeiros** com `grep -v "^[+-][+-]"`, que em arquivo de bullets
descarta a linha alterada e devolve **zero por construção** — e escrevi isso num commit como fato.
Uma das reescritas atingiu uma **lápide append-only Tier 0**. Foram **3 rodadas** de refutação
(12,5% → 8,7% → 0%).

## Artefatos gerados

| Artefato | Onde |
|---|---|
| Medidor de fronteira (2 eixos: import + tabela) | `scripts/governance/catalog-graph.mjs` — modo `--acoplamento` |
| Catraca de direção `app/ ↛ Modules/` | `tests/Feature/Architecture/DependencyDirectionTest.php` + baseline |
| Catraca de acoplamento módulo→módulo | `catalog-graph.mjs --catraca` + `governance/module-coupling-baseline.json` |
| Parecer do especialista | [`sessions/2026-08-12-arte-shared-kernel-laravel.md`](../sessions/2026-08-12-arte-shared-kernel-laravel.md) |
| Evidência das 3 refutações | [`sessions/2026-08-12-refutacao-lote-pr5675.md`](../sessions/2026-08-12-refutacao-lote-pr5675.md) |
| Narrativa completa | [`sessions/2026-08-12-fronteira-modulos-4-passos-e-o-vazamento.md`](../sessions/2026-08-12-fronteira-modulos-4-passos-e-o-vazamento.md) |

## Persistência

- **git:** 5 PRs em `main`
- **ledger GT-G5:** entry do `#5675` (`aprovado`, 22 itens, 0 erros, refutador Fable tier superior)
- **§5 + ledger LC:** lápide `2026-08-12` · LC-16 → 4 · LC-08 → 88

## Próximos passos pra retomar

```bash
node scripts/governance/catalog-graph.mjs --acoplamento
```

Devolve o estado vivo: pares, cobertura, primitivas e as fronteiras de negócio pendentes.

## Lições catalogadas

- **Teste de identidade prova a MECÂNICA, não o SENTIDO** — passou em 100% dos 371 arquivos enquanto o codemod comia fixture e falsificava história.
- **Contagem de diff é `--numstat`**, nunca regex sobre o texto do diff.
- **Substituição uniforme não distingue doc VIVO de registro DATADO** — e não é resolvível por path (CHANGELOG é arquivo vivo cheio de entradas congeladas).
- **História × link:** registro datado cita path histórico como **texto com nota de data**, nunca como link.
- **Declaração serve pra NORMA, não pra FATO** — por isso catraca em vez de preencher `depends_on`.

Nenhuma virou gate novo (predicado semântico; 5 lápides de guard sintático já medidas). O que
segurou foi **defesa em profundidade real**: 4 donos distintos, cada um pegando uma forma.

## Pointers detalhados

- Protocolo de refutação: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2.6/§4
- Lápide do episódio: [`licoes-rejeitadas.md`](../licoes-rejeitadas.md) §`2026-08-12`
