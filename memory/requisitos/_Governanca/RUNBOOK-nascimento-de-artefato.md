---
title: "Nascimento de artefato — como cada um dos 8 nasce, e quem o cobra"
owner: W
status: ativo
last_validated: "2026-08-04"
related_adrs:
  - 0329-doutrina-documentacao-de-processo-executavel
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
  - 0341-schema-spec-charter-required
---

# Nascimento de artefato

> **Camada 3** da [ADR 0329](../../decisions/0329-doutrina-documentacao-de-processo-executavel.md) — *como operar*. Aponta para as camadas 0 e 1 (constantes e mecanismos); **não recopia** nem o conteúdo dos templates nem a lista de campos dos schemas. Se este runbook e um schema discordarem, **o schema vence**.
>
> **O porquê** vive na proposta [templates-dos-8-artefatos](../../decisions/proposals/2026-08-04-templates-dos-8-artefatos-e-onde-mora-o-contador.md), não aqui.

## Regra que atravessa os 8

**1 artefato = 1 template = 1 dono.** Se existirem dois templates para o mesmo artefato, um é fóssil — e o fóssil não se copia, se aposenta.

⚠️ **Placeholder de frontmatter nasce entre aspas: `"{{...}}"`.** `{{X}}` sem aspas não é string em YAML — vira *flow mapping*, e há consumidor que morre em `catch` silencioso por causa disso (ver §5 de [`proibicoes.md`](../../proibicoes.md), lápide 2026-08-04).

## Os 8, em ordem de quem tem menos ferramenta

### 1 · `Modules/<X>/SCOPE.md` — sem template, sem schema, sem gerador, sem check required

O artefato mais desprotegido do conjunto, e o que declara **fronteira de módulo**.

```bash
cp Modules/<ModuloVizinho>/SCOPE.md Modules/<X>/SCOPE.md
```

Depois cure à mão. Não há `--check` que valide o resultado. Confira manualmente que `contains[]` lista os controllers reais e que `db_tables_owned` não está vazio se o módulo tem tabela própria.

⚠️ `bin/check-scope.php` só verifica **código → declaração**. Declaração sem código (controller apagado que ficou no `contains`) **não é detectada** — houve caso de 7 semanas.

### 2 · `memory/requisitos/<Mod>/SPEC.md` — sem gerador, mas com 4 checks required

```bash
cp memory/requisitos/_TEMPLATE_SPEC.md memory/requisitos/<Mod>/SPEC.md
node scripts/memory-schemas/validate.mjs memory/requisitos/<Mod>/SPEC.md
node scripts/governance/anchor-lint.mjs memory/requisitos/<Mod>/SPEC.md --check
```

Cobrado por 4 checks **required**. Copiar à mão é o caminho oficial hoje — e é onde a armadilha do placeholder sem aspas mais aparece.

### 3 · `memory/requisitos/<Mod>/features/<slug>/{requirements,plan,tasks}.md` — gerador oficial

```bash
npm run feature:init -- <Mod>/<slug> --us US-<MOD>-<NNN> --dry-run
npm run feature:init -- <Mod>/<slug> --us US-<MOD>-<NNN>
node scripts/governance/feature-lint.mjs <Mod>/<slug> --check
```

A máquina **recusa destino existente, US ausente e US que não está no SPEC** — ela nunca inventa a US. Por isso o passo anterior obrigatório é criar/confirmar a US no `SPEC.md`.

Use só quando a feature for multi-sessão (≥3 tarefas, dependência real, regra de negócio, integração, fila, multi-tenant, valor ou estoque). **Fix pequeno de uma tarefa não usa trio.**

### 4 · `resources/js/Pages/<Mod>/<Tela>.{charter,casos}.md` — gerador oficial, template inline

```bash
npm run tela:criar -- <Mod>/<Tela> --pt PT-0X
node scripts/casos-coverage-guard.mjs
node scripts/qa/screen-coverage-map.mjs --screen <Mod>/<Tela>
```

O template vive **dentro** de `scripts/governance/criar-tela.mjs` (`charterTemplate()` e `casosTemplate()`). O arquivo `_DesignSystem/CHARTER-TEMPLATE.md` **não é lido pelo gerador** — não o use como fonte.

⚠️ UC declarado sem teste que o cite **quebra o `casos-gate`, que é required**. Se o comportamento ainda não tem teste, ele entra como bullet `[BACKLOG]`, não como UC.

### 5 · `memory/requisitos/<X>/BRIEFING.md` — template sim, gerador não

```bash
cp memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md memory/requisitos/<X>/BRIEFING.md
node scripts/memory-schemas/validate.mjs memory/requisitos/<X>/BRIEFING.md
```

**A virtude do BRIEFING é apontar.** Fato que existe em `SCOPE`, `SUPERFICIE` ou `SPEC` entra aqui como **link**, nunca como cópia — número recopiado aqui apodrece sozinho e já aconteceu (`138` × `157` na Jana, 2026-08-04).

## Antes de mandar o PR

```bash
node scripts/governance/deadlink-gate.mjs
node scripts/memory-schemas/validate.mjs <arquivos tocados>
```

⚠️ **Tocar arquivo legado o tira do grandfather.** Quatro dos templates já têm dívida registrada em `governance/deadlink-baseline.json`; o PR que os tocar tem que confirmar que nenhuma contagem **sobe**.

## O que este runbook deliberadamente NÃO diz

- **Quais campos cada arquivo tem** → o JSON Schema é o dono (`scripts/memory-schemas/*.schema.json`).
- **O texto de cada seção** → o template é o dono.
- **O que é `required` no merge** → [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) é o dono. Nenhum doc afirma o próprio enforcement (§5 2026-07-16).
- **Contagens** (quantos SPEC, quantas telas) → re-rode `module-surface`, `screen-coverage:report` e `casos:report`. Número escrito aqui apodrece.
