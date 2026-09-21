# Auditoria de baselines — 2026-09-21

Snapshot analisado: `origin/main` `e8de1317dc951cadaf0a2bc9bb351f4f77a7d096`, extraído por `git archive`, sem checkout e sem modificar baselines. A proteção viva de `main` foi consultada separadamente pela API GitHub e estava em `7f995280849301a9d05f079a9162b7c854198874` no instante da leitura.

## Veredito

A objeção de Wagner procede para **baselines de tolerância**. Esses arquivos convertem defeito conhecido em resultado aceito e fazem o verde significar apenas “não piorou além do retrato antigo”. Não provam conformidade.

“Baseline” também nomeia snapshots visuais, inventários esperados, fixtures de teste, resultados históricos de avaliação e classes de teste. Esses usos não têm todos a mesma semântica. Apagar tudo pelo nome retiraria detectores úteis. O alvo correto é extinguir tolerância herdada e manter contratos absolutos; artefatos restantes devem ser renomeados conforme sua função.

## Dívida tolerada medida

| Artefato | Dívida aceita |
|---|---:|
| `phpstan-baseline.neon` | 4.489 padrões; 6.677 ocorrências ignoradas |
| `config/ui-lint-baseline.json` | 7.594 violações |
| `config/eslint-baseline.json` | 2.339 violações |
| `config/stylelint-baseline.json` | 440 violações, incluindo 1 `CssSyntaxError` |
| `config/typecheck-baseline.json` | 333 erros |
| `config/a11y-baseline.json` | 246 violações |
| `scripts/layout-primitives-baseline.json` | 2.450 achados em 394 arquivos |
| `.conformance-baseline.json` | 1.057 ocorrências toleradas |
| `.fontramp-baseline.json` | 405 ocorrências toleradas |
| `.foundation-guard-baseline.json` | 100 ocorrências toleradas |
| `.dsih-baseline.json` | 20 ocorrências toleradas |
| `scripts/casos-coverage-baseline.json` | 83 violações grandfathered |
| `scripts/domain-dict-baseline.json` | 120 violações grandfathered |
| `governance/multi-tenant-scope-baseline.json` | 66 Models grandfathered; 1 exceção documentada |
| `governance/anchor-entry-baseline.json` | 655 isenções |
| `governance/doneness-baseline.json` | 85 conflitos grandfathered |
| `governance/uc-lane-baseline.json` | 71 UCs órfãs de lane |
| `governance/module-coupling-baseline.json` | 22 acoplamentos grandfathered |
| `governance/module-table-coupling-baseline.json` | 18 grandfathered; 1 exceção |
| `governance/dependency-direction-baseline.json` | 19 violações grandfathered |
| `scripts/no-mock-baseline.json` | 23 achados tolerados |
| `scripts/reuse-duplicates-baseline.json` | 21 duplicações toleradas |

As métricas não podem ser somadas: sobrepõem arquivos, regras e conceitos diferentes.

## Visual

Foram encontrados 104 snapshots `.snap`, totalizando 18.493.356 bytes. Eles detectam alteração contra uma imagem anterior, mas o protótipo Cowork é a fonte soberana de forma. Um snapshot velho pode validar a forma errada. Para telas provenientes do protótipo, a prova mais forte é gerar a referência do protótipo canônico no mesmo run e comparar com a aplicação renderizada. O snapshot persistido pode ser recibo histórico, não autoridade nem tolerância.

## Proteção efetiva

A API de branch mostrou requireds que explicitamente usam ratchet/baseline: PHPStan, Stylelint, ESLint, casos, domínio, layout e deadlink, entre outros. Assim, verde significa “não ultrapassou o teto existente”.

O `baseline-tamper-guard` detecta alguns afrouxamentos, mas:

1. guarda apenas schemas cadastrados em `GUARDED`; não cobre todos os baselines acima;
2. permite `BASELINE-ABSORB` quando baseline e código mudam juntos;
3. permite curadoria em PR isolado para a maioria dos baselines;
4. exige `BASELINE-GROW` somente em três listas;
5. o contexto `baseline-tamper-guard (anti-grandfather)` não constava nos requireds vivos consultados.

Logo, ele melhora auditabilidade, mas não garante uma régua imutável nem conformidade absoluta.

## Modelo sem baseline de tolerância

1. Regra nova/arquivo tocado: zero violações absolutas, sem consultar retrato antigo.
2. Árvore inteira: campanha explícita para zerar dívida, com contador informativo que nunca dá crédito de aprovação.
3. Tier 0, especialmente multi-tenant, valores, estoque, PII e fiscal: zero grandfather; somente exceções arquiteturais nominadas, justificadas e testadas.
4. Visual: protótipo canônico renderizado no mesmo run contra a aplicação; screenshot fica como recibo por SHA.
5. Inventários esperados, como required checks, devem chamar-se `contract`, `manifest` ou `expected-state` e ser verificados por igualdade, sem tolerância.
6. Métricas históricas de negócio/IA podem manter o termo baseline estatístico, mas não devem liberar merge de código defeituoso.

Remover os arquivos hoje, sem substituir os consumidores, apagaria detectores ou deixaria o CI todo vermelho. A migração segura troca cada gate por uma regra absoluta e só então remove seu baseline. Isso exige ADR nova porque altera enforcement canônico e decisões existentes.

## Evidência reproduzível

- O snapshot veio de `git archive e8de1317dc951cadaf0a2bc9bb351f4f77a7d096`, sem alterar o checkout.
- As contagens vieram dos campos de cada JSON e das entradas `ignoreErrors` do
  `phpstan-baseline.neon`; as unidades estão nomeadas na tabela para não serem somadas.
- A quantidade e o tamanho dos `.snap` vieram da enumeração da árvore versionada desse SHA.
- Os requireds vieram da proteção de branch consultada pela API GitHub em 2026-09-21.
- O código e os arquivos citados acima são as fontes que permitem repetir cada contagem.

Nenhum teste Pest/PHPStan foi executado localmente; a regra CT 100 foi respeitada. Nenhum baseline operacional foi escrito, absorvido ou regenerado.
