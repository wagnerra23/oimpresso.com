---
date: "2026-07-21"
topic: "Proteção automática anti-duplicação de ESTRUTURA/TEMA: detector advisory tema-owner (dono-de-tema por sobreposição de entidade declarada, consome catalog.json)"
authors: [C]
tags: [governanca, anti-duplicacao, catalogo, idp, topicos, adr-0345, hook-advisory, tema-owner]
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0314-poda-gates-onda-2-lei-fusoes
prs: []
---

# Sessão 2026-07-21 — `tema-owner`: detector advisory de DONO-DE-TEMA

> **TL;DR:** [W] pediu proteção automática contra duplicação de ESTRUTURA/TEMA (*"como sei que a
> Maiara não está criando outra estrutura e duplicando as coisas?"*). Diagnóstico confirmado: a
> Maiara **não** está duplicando (BRIEFING #4601 converge com a ADR 0345), mas a defesa contra
> estrutura-paralela era 100% manual/cultural. Construí um detector **advisory** que, ao criar um
> doc de estrutura novo sob `memory/requisitos/`, aponta o(s) dono(s) existente(s) do MESMO tema —
> medindo **sobreposição de ENTIDADE declarada** (tabela/função/model/tela), NÃO nome de arquivo.
> Consome o `catalog.json` (grafo tipado, PR #4629, que tinha 0 leitores) + os tópicos da ADR 0345.
> Núcleo consultável + hook `PreToolUse(Write)` wired + selftest de mordida. Advisory de nascença.

## O pedido (chip [W] 2026-07-21)

"Proteção automática" contra estrutura-paralela: ao criar um assunto novo, apontar se ele já tem
dono. Advisory (avisa, não bloqueia). Deixou a **forma** a meu critério (hook vs comando), pedindo
a de menor teatro + maior chance de ser invocada no fluxo real.

## Reconhecimento (o que JÁ existia — reusar, não duplicar)

| Peça | O que faz | Serve pra dono-de-tema? |
|---|---|---|
| `catalog-graph.mjs` → `catalog.json` (#4629) | grafo tipado módulo/tabela/api/adr/componente, derivado dos `SCOPE.md` | **fonte** (tabela→dono). Tinha **0 leitores** — a "UI consultável" da grade seguia pendente |
| `dup-detector.mjs` | colisão de **arquivo EXATO** entre PRs abertos (trabalho concorrente) | não — é arquivo, não tema |
| `preflight-new-capability.mjs` (hook) | anti-reinvenção de **CÓDIGO** (Checker/Service) por nome de arquivo | não — código, não doc |
| `topico.schema.json` (ADR 0345) | `anchors` estruturados: tables/functions/models/controllers/telas/adrs | **sinal de tema** real |
| `briefing-completeness.mjs` (proposto 2026-07-20) | — | **REFUTADO** como presence-gate (não repetir) |

**Gap real (verificado):** nada aponta o **dono-de-tema por sobreposição de entidade** ao criar um
doc. O `catalog.json` responde a nível de módulo/tabela mas ninguém o consulta.

**Chip irmão** (`task_17e2dd7a`, scorecard sinais-vivos + UI consultável): checado — **nenhum PR
aberto** ainda. Usei o `catalog.json` direto como base (como o chip instruiu) e deixei a borda
`loadCorpus()` como ponto de extensão: se o irmão entregar um índice consultável genérico, o
`tema-owner` passa a consumi-lo em vez de reparsear o catálogo. **Não** criei 2º motor de consulta.

## O que foi construído

- **`scripts/governance/tema-owner.mjs`** — núcleo + CLI consultável.
  - `node ... --tema-arquivo <path.md>` (extrai anchors do frontmatter + entidades reconhecíveis do corpo) · `--tabelas/--functions/--models/--module` · `--json` · `--selftest`.
  - Mede **sobreposição de entidade**: mesma `tabela`/`função`/`model`/`tela` que um tópico existente cita → mesmo tema (nome de arquivo IRRELEVANTE). ADR **não** conta como sinal (transversal — 0093 aparece em quase todo tópico → viraria ruído).
- **`.claude/hooks/tema-owner-advisory.mjs`** — `PreToolUse(Write)`, wired no `settings.json`. Dispara ao criar `.md` novo sob `memory/requisitos/`. Chama o **mesmo** núcleo (não duplica lógica). Fala só quando encontra dono; silencia em tema novo (evita ruído). Advisory `allow`, fail-open.
- **`tema-owner.test.mjs`** (16 casos) + **`tema-owner-advisory.test.mjs`** (16 casos) — no CI `governance-script-tests.yml`.
- `_HOOKS-INDEX.md` regenerado (derivado, não à mão).

## Prova (DoD — 2 casos reais + selftest de mordida)

CLI contra o corpus real do repo:

```
CASO A (COM dono): --tabelas tax_rates --functions app/Utils/ProductUtil.php::calculateInvoiceTotal
  ⚠️ SOBREPÕE memory/requisitos/produto/topicos/calculo-total-fatura.md
     (compartilha: tabela tax_rates, função productutil.php::calculateinvoicetotal)

CASO B (NOVO): --tabelas zzz_relatorio_novo --module Exportacao
  ✅ nenhuma entidade colide — aparenta ser tema NOVO
```

**Selftest de mordida** (o que impede virar teatro — quebra se a lógica for afrouxada):
- BITE positiva: tema com `tax_rates` MORDE o tópico que a declara, com nome de arquivo diferente.
- ANTI-SINTÁTICO: nomes de arquivo **parecidos** + entidades diferentes → **não** colidem (prova que mede entidade, não nome — se alguém trocar por comparação de nome, este teste fica vermelho).
- ANTI-ADR: dois docs que só compartilham 0093 → não colidem.
- SELF-EXCLUDE / PASSA-LIMPO (entidade fantasma → 0 donos, não inventa) / fail-open.
- Hook: **controle-negativo de acoplamento** — dispara em Write de doc-de-estrutura; **silencia** em código, `memory/sessions`, Edit, tema novo. Prova que o gatilho é o caminho real (anti-chokepoint-fantasma).

## Travas §5 respeitadas (as variantes já refutadas)

- **NÃO presence-gate** (§5 2026-07-20 `briefing-completeness` refutado): não afirma "existe arquivo logo está coberto"; **mostra quais entidades concretas colidem** e recomenda estender. "existência de dono ≠ cobre CERTO" está no próprio output.
- **NÃO sintático de nome/pasta** (§5 2026-06-30 ancora-guard): mede **entidade declarada**; o teste ANTI-SINTÁTICO prova.
- **ADVISORY, nunca trava** (ADR 0224): `allow` + exit 0 sempre.
- **NÃO duplica régua** (§5 2026-07-09): **consome** o `catalog.json`; complementar ao dup-detector e ao preflight-new-capability.

## Limites honestos (não inflar)

- Só mede **entidade declarada**. Doc de pura prosa sem anchors nem tabela reconhecível → silêncio (não inventa — é a fronteira "mecânico=derivável / humano=julgamento" da sessão 2026-07-20).
- Corpus de **tópicos** é pequeno hoje (3, piloto Produto) — o valor cresce conforme a ADR 0345 se dissemina. O `catalog.json` já dá cobertura ampla a nível de módulo/tabela (88 tabelas, 43 apis).
- `tax_rates` **não** está no catálogo (tabela core UltimatePOS sem `SCOPE.md` a declarando) — o overlap de TÓPICO pegou mesmo assim. Ou seja: as duas fontes se complementam.

## Estado no fechamento

- 4 arquivos novos + 3 modificados (settings wiring · `_HOOKS-INDEX` regenerado · CI). Todos os testes verde (exit 0). Encoding UTF-8 sem BOM, LF.
- Merge = decisão [W] (R10). PR a abrir.
