---
date: "2026-08-12"
time: "10:43 BRT"
slug: hook-replay-2-contratos-e-o-teto-que-a-medicao-achou
tldr: "Escalar o hook-replay de 1 pra N deu N=2, e a medição diz que 2 é perto do TETO, não um começo: 6 candidatos avaliados, 4 rejeitados por razões distintas e medidas. O contrato novo (block-mwart-violation × porta viva) achou defeito real nos DOIS lados que compara — no hook e na porta viva. 4 PRs, todos MERGED."
prs: [5612, 5617, 5628, 5637]
decided_by: [W]
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0336-gates-design-promocao-por-mordida-provada-emenda-0314]
next_steps:
  - "Decidir related_runbook nos charters de Repair/DeviceModels/{Create,Edit,Index} — hoje resolvem AMBÍGUO"
  - "Decidir se block-bom-encoding vai a strict (hoje warn; 24 .tsx com BOM, 0 .php)"
  - "Se quiser fechar as 2 divergências restantes: é o match por SUBSTRING da porta viva, eixo separado e não medido"
---

# hook-replay: 1 → 2 contratos, e o teto que a medição achou

Origem: fraqueza **F1** da grade `memoria-conhecimento` de 2026-08-11 (`mem-lapide-recuperacao`).

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `sessions-recent limit:4` → índice parado em `session-2026-08-05-*` (lag de indexação)
- `gh pr view` dos 4 PRs → **#5612, #5617, #5628, #5637 todos MERGED** por [W]
- Base do handoff: branch novo de `origin/main` fresco (o checkout de trabalho estava **22 commits atrás** — o `08-handoff.md` é editado por toda sessão, escrever de base stale apagaria entrada alheia)

## O que aconteceu

O pedido era "escalar de 1 para N contratos". **N deu 2** — e o achado que vale mais que o número é *por que* deu 2.

Um contrato de replay exige **oráculo independente do critério do próprio hook**. Sem isso ele é tautológico: concorda 100% com qualquer implementação, inclusive a quebrada (§5 2026-06-05). Avaliei 6 candidatos contra o corpus real (376 sessões) e **4 morreram por razões diferentes, todas medidas** — nenhuma por falta de esforço:

| hook | por que não | medida |
|---|---|---|
| `block-ancora-no-olho` | ramo que discrimina tem **0 casos** | 42 Read de imagem, **0** print-semântico |
| `block-instrumento-sem-porta-viva` | oráculo reprovado em teste | 5 de 7 divergências eram **artefato do oráculo** (3 resultado vazio, 2 `content`/`count` que o hook passa por decisão correta) |
| `doc-fora-do-rag` | dono já comparado por teste próprio | o `.test.mjs` dele já lê o PHP; FP já medido no runtime (0 FP/0 FN) |
| `block-brl-values-in-memory` | tautológico **por import** | `brl-scan-diff.mjs` importa `scanBrlLeak` do hook |
| `block-bom-encoding` | critério **É** o ground truth | 4.081 gatilhos, mas `charCodeAt(0)===0xFEFF` é o fato, não proxy |
| `block-routes-string-legacy` | idem | 59 gatilhos, `findLegacyMatches` idem |

Isso fecha um **padrão** (no header do harness): contrato só existe quando o hook **re-implementa** regra com dono canônico (oráculo = o dono) ou **decide por proxy** de um ground truth (oráculo = o ground truth). Quando o critério já é a medição, `hook-bites` + o `--selftest` do hook já são os instrumentos certos.

### O contrato que entrou, e o que ele encontrou

`block-mwart-violation` × a porta viva `screen-coverage-map` — donos e algoritmos diferentes (readdir FLAT + kebab EXATO × walk recursivo + substring). Contrafactual: impl atual **87,8%** × impl pré-#4648 **75,5%**, discrimina por 12,3pp. Um 2º oráculo candidato (RUNBOOK que *cita* o path da Page) foi medido e **perdeu** (75,5% × 67,3%) — a escolha saiu de medição.

Ele então achou defeito **nos dois lados que compara**, que é a única evidência de que não estava medindo a si mesmo:

- **no hook** (#5617): `Compras/Index`, `Repair/Index`, `Repair/Show` bloqueadas **tendo** RUNBOOK — o hook não conhecia a convenção `RUNBOOK-<modulo>-<tela>.md`. 120 das 123 seguem bloqueadas.
- **na porta viva** (#5628): `npm run screen:files` dizia `RUNBOOK ✗ ausente` com o arquivo no disco — `nameKeys` era `toLowerCase()` sem kebab, então `FeedbackPublico` nunca casava `feedback-publico`. **8 artefatos** invisíveis em 445 telas.

Acordo do contrato: **85,2% → 86,9% → 96,8%**, divergências **9 → 2**.

## Artefatos gerados

| PR | arquivo | o quê |
|---|---|---|
| #5612 | `scripts/governance/hook-replay.{mjs,test.mjs}` | contrato 2 + `ctx`/`preparar`/indeterminados + 4 rejeições no header · selftest 31/31 |
| #5617 | `.claude/hooks/block-mwart-violation.{mjs,test.mjs}` | 4º param `primarioKebab` + 6 asserts |
| #5628 | `scripts/qa/screen-coverage-map.mjs` | `kebabDoSegmento`/`chavesDeNome` + 9 asserts |
| #5637 | header de `hook-replay.mjs` + `block-bom-encoding.mjs` | +2 rejeições + o padrão + recibo do BOM |

## Decisões que seguraram a régua (e por quê)

- **Prefixo de módulo só no kebab PRIMÁRIO.** Aplicado a todos os candidatos destravava 8 telas, mas **5 eram falso-resgate** — 4 telas `Repair/*/Index` distintas casavam o **mesmo** `RUNBOOK-repair-index.md`. Viraria carimbo. Restrito: destrava 3, todas flat e inequívocas.
- **Kebab local na porta viva, NÃO importado do hook.** Importar acoplaria os dois e tornaria o oráculo tautológico. A duplicação é o mecanismo — está documentado no código pra ninguém "consolidar" e quebrar em silêncio.
- **Perna `_telas/` descartada**: medida, destravava **0** (todas já passam por charter). Ligar caminho que não trabalha é superfície não-testada.
- **Indeterminado fica FORA da conta**, não como acordo (§5 2026-08-04).
- **Não limpei os 24 `.tsx` com BOM**: tocar legado em massa é o big-bang que morre no CI (§5 2026-07-12).
- **Não apertei o match por substring** da porta viva: apertar o oráculo até concordar com o hook é fabricar o resultado.

## Persistência

- **git**: 4 PRs merged em `main`
- **MCP**: propaga por webhook (~2min) — sem cycle ativo, nada a atualizar em tasks
- **BRIEFING**: não aplicável (tooling de governança, nenhum `Modules/<X>/` tocado)

## Lições catalogadas

**Nenhuma lápide §5 nova, deliberadamente.** Os deslizes desta sessão foram instâncias de classe **já catalogada** (LC-08) e todos pegos por controle **antes** de virar afirmação publicada: extrator de required lendo só `classic_protection` (perderia o `Governance Gate`, que só existe no ruleset — a união é 43, depois 44); `Object.values` sobre `rulesets` devolvendo 0; heredoc comendo `\\` num regex. Inflar contador com recibo repetido é o vício que o próprio ledger alerta.

Registro à parte, porque é do método e não do código: **medi "unique → ambiguous" e quase reportei como regressão**. Era o oposto — antes a porta dava resposta **errada com cara de certeza** (`RUNBOOK-jobsheet-create.md` como o RUNBOOK único de `DeviceModels/Create`), agora lista os dois e sinaliza. Diff de estado não se lê pelo rótulo; lê-se olhando o caso.

## Próximos passos pra retomar

```bash
node scripts/governance/hook-replay.mjs
```

## Pointers detalhados

- Padrão de elegibilidade + as 4 rejeições: header de [`scripts/governance/hook-replay.mjs`](../../scripts/governance/hook-replay.mjs)
- Recibo do BOM (27 arquivos, 0 `.php`): header de [`.claude/hooks/block-bom-encoding.mjs`](../../.claude/hooks/block-bom-encoding.mjs)
- Fraqueza de origem: `memory/reguas/fraquezas.json` id `mem-lapide-recuperacao`
