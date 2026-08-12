---
slug: 0377-append-only-adr-excecao-por-label-emenda-0094
number: 377
title: "Emenda à 0094 — append-only de ADR canon admite exceção por label `adr-body-edit-W` (autorização [W] por-PR)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-11"
module: governance
quarter: 2026-Q3
tags: [governanca, constituicao, emenda-0094, append-only, adr-canon, label, governance-gate, lc-10, tier-0]
supersedes: []
supersedes_partially: []
superseded_by: []
amends:
  - 0094-constituicao-v2-7-camadas-8-principios
related:
  - 0095-skills-tiers-convencao-interna
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0375-scope-md-sai-de-modules-para-memory-requisitos
pii: false
review_triggers:
  - "O label ser aplicado sem que [W] tenha autorizado no chat — sinal de que o ato deixou de ser consciente"
  - "Uso recorrente (>1×/mês) — sinal de que a regra append-only está errada e é ela que precisa mudar, não a exceção"
  - "Hook block-memory-drift ganhar um sinal versionado que dispense o OIMPRESSO_MEMORY_OVERRIDE"
---

# Append-only de ADR canon admite exceção por label — e por que label, não autor

## Contexto

O append-only de ADR canon é regra da Constituição ([Artigo 3](../governance/CONSTITUTION.md)) e da [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md): ADR aceita não se edita, cria-se nova com `supersedes`. Ele tem duas exceções anteriores, ambas **estreitas por construção** — a [0257](0257-adr-status-lifecycle-kind-modelo-canonico.md) libera só normalização de frontmatter (`status`/`lifecycle`/`kind`) sob label `adr-metadata-normalization`, e a 0297 libera migração legacy→canônico com corpo intacto.

Em 2026-08-11 a [ADR 0375](0375-scope-md-sai-de-modules-para-memory-requisitos.md) moveu 33 `SCOPE.md`/`LICOES-OPERACAO.md` de `Modules/<X>/` para `memory/requisitos/<X>/`. **16 ocorrências em 9 ADRs canon** passaram a citar paths que não existem mais. Nenhuma das duas exceções cobre isso: o que precisa mudar é o **corpo**, não o frontmatter, e não é migração legacy.

[W] decidiu, textual: *"remover o append-only para mim"*, e depois *"pode trocar todos"*.

## Decisão

O job *Append-only canon* do [`governance-gate.yml`](../../.github/workflows/governance-gate.yml) passa a **não bloquear** quando o PR carrega o label **`adr-body-edit-W`**. Diferente da 0257 e da 0297, esta exceção **não restringe quais linhas mudam** — libera o corpo.

**Por que label, e não autor — isto é o núcleo da decisão, e é medido.** Todo PR aberto por agente sai como `wagnerra23` (o #5568, aberto por agente, tem `author=wagnerra23`). Isentar por autor removeria o append-only de **todo PR de agente, em silêncio** — o oposto exato de *"para mim"*. Com label, o ato é **consciente** (alguém aplica), **por-PR** (não vale para o próximo) e **auditável depois** (`gh pr view --json labels`).

**Fail-closed por construção:** label ausente → `wedit_ok=0` → append-only normal. Falha da API de labels → `|| echo ""` → labels vazio → append-only normal. O caminho de erro nunca libera.

## O que a exceção NÃO cobre

- **`CONSTITUTION.md`** — segue com regra própria (label `constitution-amendment` + `audit-*.md` no mesmo PR, §10.4). O `adr-body-edit-W` não a alcança.
- **Handoffs** — seguem append-only sem exceção.
- **Reescrever fato datado.** A exceção libera *mexer*; ela não autoriza *falsificar*. Ponteiro que apodreceu se atualiza; afirmação sobre o que era verdade numa data se preserva. Aplicado no primeiro uso: das 16 ocorrências, **8 foram editadas** (6 repath + 2 de-link) e **8 preservadas** por serem fatos datados sem link.

## A segunda camada, e o resíduo honesto

O append-only tem **duas** camadas, e esta ADR só resolve uma. A outra é o hook PreToolUse [`block-memory-drift.mjs`](../../.claude/hooks/block-memory-drift.mjs), que **não conhece o label** — medido, `grep -c` = 0 — e casa o mesmo alvo do gate. Um hook PreToolUse **não pode** consultar label: ele roda antes de existir PR. Então o único escape local hoje é o `OIMPRESSO_MEMORY_OVERRIDE=1` que o próprio hook documenta.

**Consequência aceita:** editar ADR canon exige o override local **e** o label no PR. São dois atos conscientes, não um. Fechar isso com um sinal versionado no branch (que o hook poderia ler) é candidato — não feito, e é `review_trigger` desta ADR. Máquina nova exige FP medido antes.

## Consequências

- **Positiva:** ponteiro podre em ADR vira consertável. Antes, a única saída era criar ADR nova só para dizer "aquele link mudou de lugar" — cerimônia desproporcional que, na prática, deixava o link morto.
- **Negativa, declarada:** a garantia deixa de ser absoluta e passa a depender de quem aplica o label. É por isso que o `review_trigger` inclui *uso recorrente* — se virar rotina, a regra errada é o append-only, não a exceção.
- **Cascata LC-10:** enquanto `CLAUDE.md` e [`proibicoes.md`](../proibicoes.md) afirmavam append-only **absoluto em presente**, eles descreviam um enforcement que já não era o vigente. Reconciliados no mesmo PR desta ADR.

## Cascade Review

Exigida pela §10.4 (esta ADR emenda a Constituição). Relatório completo em [`audit-2026-08-11-v1.3.md`](../governance/audit-2026-08-11-v1.3.md).

## Alternativas descartadas

| Alternativa | Por que caiu |
|---|---|
| **Isentar por autor** | medido: todo PR de agente sai como `wagnerra23` ⇒ removeria a proteção de todos eles, em silêncio |
| **Estender a 0257** | ela é estreita **por desenho** (só frontmatter); alargá-la apagaria a distinção entre normalizar metadado e reescrever corpo |
| **Criar ADR nova a cada path podre** | cerimônia desproporcional; na prática o link ficava morto |
| **Ensinar o label ao hook** | impossível literal — hook PreToolUse roda antes de existir PR |
