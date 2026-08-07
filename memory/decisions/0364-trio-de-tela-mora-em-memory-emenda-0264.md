---
slug: 0364-trio-de-tela-mora-em-memory-emenda-0264
number: 364
title: "O trio de tela (charter + casos) muda de casa para memory/requisitos/<Modulo>/_telas/ — emenda parcial à 0264, decisão organizacional [F] ratificada por [W]"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W, F]
decided_at: "2026-08-01"
accepted_at: "2026-08-01"
accepted_via: "Wagner no chat 2026-08-01, após o merge do PR #5136 (proposta v3): 'Flip autorizo'. [F] patrocinou a decisão organizacional em 2026-07-31/08-01 ('prefiro mudar para memória… mover os arquivos é escolha minha e eu quero'); [W] ratifica porque emenda ADR canon, reescreve gate required e toca Modules/Jana (CODEOWNERS)."
module: governance
quarter: 2026-Q3
tags: [governance, memoria, rag, charter, casos, casos-gate, trio-de-tela, forward-only, tier-0]
supersedes: []
supersedes_partially:
  - 0264-governanca-executavel-trio-dominio-e2e
superseded_by: []
related:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0053-mcp-server-governanca-como-produto
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0097-brief-model-gpt4o-mini-supersede-parcial-0091
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

# ADR 0364 — o trio de tela muda de casa: `memory/requisitos/<Modulo>/_telas/`

> **Status:** `aceito` — ratifica a proposta [`documentacao-do-fonte-layout-canonico`](proposals/documentacao-do-fonte-layout-canonico.md) (v3, mergeada no [PR #5136](https://github.com/wagnerra23/oimpresso.com/pull/5136)). **Esta ADR ratifica a DECISÃO; a execução é projeto separado** — o plano de 6 passos vive na proposta (dono do tema) e cada flip por módulo exige smoke + adversarial + aprovação [W].

## Contexto

A proposta nasceu perguntando onde mora a documentação do fonte e passou por três versões, todas registradas nela própria:

- **v1 → Opção B** (trio fica ao lado do `.tsx`, achabilidade por índice derivado). Refutada.
- **v2 → Opção A "porque o RAG exige"**. **Refutada pelo adversário do plano** (2026-08-01): o trio pode ser indexado **in-place** (glob adicional no `IndexarMemoryGitParaDb`), sem mover nada e sem tocar gate. A tese "só A põe o trio no RAG" era falsa.
- **v3 → Opção A por escolha organizacional [F]** (*"raiz é `memory/`, nada fora"*), **ciente** de que o RAG não exige o move. A alternativa in-place fica registrada na proposta como o caminho de menor custo, caso [W] reavalie.

Registrar isso importa: a justificativa desta ADR é **organizacional**, não técnica. Vender o move como consequência técnica seria o padrão que o §5 de [`proibicoes.md`](../proibicoes.md) já enterrou (*importar solução sem checar a premissa* / *afirmar sem medir a fonte certa*).

## Decisão

1. **Casa canônica única = `memory/requisitos/<Modulo>/`.** O trio migra para `memory/requisitos/<Modulo>/_telas/<Tela>.charter.md` + `<Tela>.casos.md` — mesma estrutura, raiz `memory/`.
2. **O `.tsx` permanece em `resources/js/Pages/`** (é código). O vínculo trio↔tela vira explícito no frontmatter (`tela: resources/js/Pages/<Mod>/<Tela>.tsx`) — **dado inerte até o gate ler**, e é por isso que a reescrita do resolver é o passo 1, não uma consequência.
3. **Emenda parcial à [ADR 0264](0264-governanca-executavel-trio-dominio-e2e.md)** — ver a seção seguinte.

## Por que `supersedes_partially` e não `supersedes` (correção à letra da proposta)

A proposta escreveu `supersedes: [264]`. **Executo como emenda parcial**, e o motivo é verificável: supersessão total marca a 0264 `status: superseded` + `lifecycle: substituido` (é o que o `adr-supersede.mjs` faz, atomicamente), e o filtro de canon vivo — [`McpMemoryDocument::scopePorStatusAtivo`](../../Modules/Jana/Entities/Mcp/McpMemoryDocument.php) — só deixa passar `aceito`/`accepted`/`accepted-historical`/`recusado`. Ou seja: sumiria da busca default a ADR-mãe de **dois gates que continuam `required` e valendo** (`casos-gate` G-1…G-7 e `dominio-gate` G-4), além do E2E Playwright e do ratchet.

O campo `supersedes_partially` existe exatamente para este caso. Ele nasceu na [ADR 0317](0317-maquina-revisao-adr-quando-rever-gatilhos.md) (*"o conserto canônico é `supersedes_partially`, não `supersedes`"* — Onda 1, campo adicionado ao `adr.schema.json`), cujo vocabulário é a fonte única: *emenda/corrige parcialmente sem matar; a base permanece `lifecycle: ativo`; **não rebaixa o alvo***. Precedente do mesmo formato: [ADR 0097](0097-brief-model-gpt4o-mini-supersede-parcial-0091.md).

O que esta ADR emenda da 0264 é **um eixo só**: a premissa de que o trio mora ao lado do `.tsx` e é resolvido por **path-irmão computado**. Tudo o mais da 0264 segue vivo e intocado (append-only — a 0264 não é editada por este PR).

> Se [W] quiser mesmo a supersessão **total**, é uma linha de frontmatter + `adr-supersede.mjs --new 364 --old 264 --write` sob a label `adr-metadata-normalization`. Não fiz por conta própria porque o efeito colateral (gates required órfãos de lei ativa) é maior que o ganho de literalidade.

## Invariantes de execução (Tier 0 — não negociáveis)

Estas quatro vivem aqui, e não só na proposta, porque são o que impede a migração de quebrar gate required:

1. **Dual-resolver ANTES de qualquer move.** O `casos-coverage-guard.mjs` resolve o `.tsx` por path-irmão computado e **não lê frontmatter**; sem a reescrita que aceita as duas árvores, o G-6 (frescor) **pula em silêncio** — verde por não-execução (LC-13).
2. **Nada de `move-with-tombstone`.** É a ferramenta errada: é `memory/`-scoped, só relinka referências **literais**, e o stub que deixa em `Pages/` quebra o Charter schema required e **finge G-1 verde** (LC-11).
3. **Forward-only, módulo a módulo, verde a verde.** Cada charter movido é arquivo "novo" no diff → revalidado contra o `charter.schema.json` **strict**, então dívida latente hoje grandfathered **falha** (EMENDA §5 2026-07-27, classe de 3 eixos). Antes de tocar cada módulo, **enumerar os globs de gate diff-aware** que os arquivos casam e medir cada um — medir um eixo só é incompleto por construção.
4. **A IA não altera sozinha a máquina que a fiscaliza.** A reescrita dos gates required é [W] + adversarial + bite-test (fixture ruim → exit≠0) antes de cada flip.

O inventário completo dos consumidores (workflows path-gated, scripts/hooks que derivam o path-irmão, os required omitidos na v2 — `anchor-content-check`, `charter-live-signal`, `visreg-states-lint` + manifesto — e o `requisitos-status.mjs` que **regride** lendo `_telas/` como fluxo blade) está na proposta, com data e autoria da medição. Não é restateado aqui de propósito: número em doc canônico aponta pro dono ou carrega recibo (§5 2026-07-17).

## Consequências

**Melhora:** casa única (`memory/` = raiz de tudo que é documentação); o trio entra no RAG pelo sync automático do webhook ([ADR 0053](0053-mcp-server-governanca-como-produto.md)) junto com o resto de `memory/`, sem glob especial.

**Piora (declarado, não escondido):** o trio deixa de ficar **ao lado** do código que descreve — a proximidade física era parte do anti-rot da 0264 (quem edita a tela vê o charter). O contrapeso é mecânico, não cultural: o dual-resolver e depois o resolver por `tela:` continuam cobrando o vínculo no CI. Enquanto a transição durar, **duas árvores válidas** convivem — estado que só é seguro com o passo 1 de pé.

**Custo:** projeto separado, não turn-key. Reescrita de gate required + inventário de consumidores + migração por módulo.

## Gate de reversão

Reabrir esta ADR (nova ADR, append-only) se: (a) o dual-resolver produzir falso-verde ou falso-vermelho em qualquer módulo do piloto; (b) a taxa de charters que **falham** o schema strict ao serem movidos inviabilizar o forward-only; ou (c) [W]/[F] concluírem que a proximidade física valia mais que a casa única. O caminho de recuo é a alternativa **in-place** já registrada na proposta (RAG sem mover) — ela não deixa de existir por causa deste flip.
