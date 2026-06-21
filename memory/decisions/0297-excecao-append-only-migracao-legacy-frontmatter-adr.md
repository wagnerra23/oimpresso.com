---
slug: 0297-excecao-append-only-migracao-legacy-frontmatter-adr
number: 297
title: "Exceção append-only: migração legacy→canônico de frontmatter de ADR sob label, corpo byte-idêntico (emenda 0257)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-21"
module: governance
kind: meta
supersedes: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
---

# ADR 0297 — Exceção append-only: migração legacy→canônico de frontmatter de ADR

> **Emenda operacional da [ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md).** 0257 protege a
> decisão e libera consertar a *etiqueta*, mas só criou exceção para `status/lifecycle/kind/authority`.
> Esta ADR estende a mesma filosofia para o **rename de campos** dos ADRs pré-Constituição v2 (legacy),
> com uma salvaguarda mais forte: o corpo da decisão tem que ser **byte-idêntico**.

## Contexto

Auditoria 2026-06-21 (sentinela `memory-health` Check L — integridade proposto×realizado, [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md))
listou ADRs **vivo-mas-proposto**: decisões já realizadas em código mas com `status: proposed/proposto`.
A maioria foi ratificada (proposto→aceito) sem atrito porque já estava em formato canônico.

Sobraram **3 ADRs legacy** (`0123-modules-arquivos-backbone`, `0124-curador-conhecimento-pipeline`,
`0189-pageheader-canon-v3-1-cadastro-roxo`) com frontmatter pré-canônico:
`adr:` (em vez de `slug`+`number`+`type`), `deciders: [Wagner]` (em vez de `decided_by: [W]`),
`date:` (em vez de `decided_at:`), `references:` (em vez de `related:`), `status: proposed`,
`lifecycle: active`, e sem `authority`.

**O deadlock estrutural:**
- O gate `block-adr-edits` (`.github/workflows/governance-gate.yml`, Constituição Art. 3) só libera
  edição de ADR existente sob o label `adr-metadata-normalization`, e mesmo assim apenas mudanças em
  `status|lifecycle|kind|authority|superseded_by|supersedes` + itens de lista que começam com dígito.
  Migrar `adr→slug/number/type`, `deciders→decided_by`, etc. cai **fora** dessa exceção → bloqueado.
- O outro lado do torniquete: mudar **só** `status: proposed→aceito` (o que a exceção 0257 permite)
  faz o `memory-schema-gate` revalidar o arquivo inteiro e **reprovar** por faltarem 6 campos
  obrigatórios (`slug/number/type/authority/decided_by/decided_at`).

Resultado: ADR legacy **não pode** virar schema-válido sem uma edição não-normalização, e edição
não-normalização é bloqueada. Evidência de que a parede já travou alguém: `scripts/fix_adr_legacy_schema.py`
existe, tem como `TARGETS` exatamente `0122..0126`, está hardcoded para um worktree antigo e **nunca landou**.

## Decisão

Adicionar uma **segunda exceção cirúrgica** ao gate `block-adr-edits`, sob o label dedicado
**`adr-legacy-schema-migration`**, que libera a migração completa de frontmatter legacy→canônico
**desde que o corpo da decisão seja byte-idêntico** entre a base e o HEAD do PR.

Invariante (mais forte que a checagem por-linha da 0257):

```
corpo(base) ≡ corpo(head)   onde corpo = tudo após o 2º fence '---' do frontmatter
```

Se o corpo mudou um único byte, a exceção **não aplica** e o append-only bloqueia normalmente.
Isso honra a Constituição Art. 3 ao pé da letra: **a decisão é imutável; só a etiqueta migra.**

Implementação (step `legacymig` em `governance-gate.yml`): exige o label, extrai o corpo de cada ADR
modificado via `awk` (preservando `---` do corpo), compara `base` vs `head` com `diff -q`, e só marca
`legacymig_ok=1` se todos os corpos forem idênticos. O step de bloqueio passa a falhar apenas quando
**nenhuma** das duas exceções (0257 metadados OU 0297 migração) se aplica.

Regras de uso:
- Label `adr-legacy-schema-migration` aplicado **conscientemente** no PR (mesma disciplina do label 0257).
- 1 PR de migração ≠ PR de decisão nova (commit-discipline). A migração não pode alterar o corpo.
- `rename` de arquivo continua **proibido** (ADR mantém número/slug = nome do arquivo).

## Consequências

**Positivas:**
- Destrava a migração dos 3 ADRs legacy → fecha o débito do Check L de forma honesta (não no leitor).
- Salvaguarda forte e simples (hash de corpo) — mais difícil de burlar que allowlist por-linha.
- Os ADRs legacy entram no regime do `memory-schema-gate` (validados como os novos).

**Negativas / riscos:**
- Mais uma exceção no gate Tier-0 = mais superfície. Mitigado pelo label consciente + corpo-idêntico.
- Não cobre ADRs cujo *corpo* também precise de normalização (raro; esses seguem o caminho supersede).

## Alternativas consideradas

1. **Superseder com ADR nova** (caminho que o próprio gate sugere): deixar os legacy imutáveis e criar
   ADRs novas que os supersedem. Rejeitado: polui a numeração e duplica decisões **já realizadas** —
   supersede é para mudar a decisão, não para corrigir a etiqueta.
2. **Normalizar só no leitor** (como o `adr-index-generate` já faz com status/lifecycle): o débito do
   Check L "some" sem tocar o arquivo. Rejeitado como solução única: o `memory-schema-gate` segue cego
   a esses arquivos e a base canônica mantém duas gramáticas de frontmatter indefinidamente.
3. **Ampliar a allowlist por-linha da 0257** para incluir os campos legacy. Rejeitado: frágil (cada
   campo novo vira regex) e mais fácil de burlar que a checagem de corpo-idêntico.
