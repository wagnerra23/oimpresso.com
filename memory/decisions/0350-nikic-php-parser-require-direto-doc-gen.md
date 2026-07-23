---
slug: 0350-nikic-php-parser-require-direto-doc-gen
number: 350
title: "nikic/php-parser promovido a dependência direta (auto-document código→KbNode, Fase B)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-23"
module: kb
quarter: 2026-Q3
tags: [kb, dependencia, php-parser, ast, doc-gen, doc-codigo, swimm]
supersedes: []
superseded_by: []
related: [0150-kb-unificado-grafo-conhecimento-modulo-ia-central, 0062-separacao-runtime-hostinger-ct100, 0035-stack-ai-canonica-wagner-2026-04-26]
---

# ADR 0350 — `nikic/php-parser` promovido a dependência direta

## Contexto

O trilho doc↔código do KB (estilo Swimm) tem duas metades: **(1)** detectar quando um doc
cita código que sumiu (`kb:drift-detector`, Fases A1/A2 — LIVE) e **(2)** **gerar** doc a
partir do código (Fase B, `#3` do mapa). A geração precisa ler a **estrutura** do PHP
(namespace, classe, métodos públicos, docblock), o que exige um parser de AST, não regex.

`nikic/php-parser` (v5.7.0) **já está no `composer.lock`**, mas apenas **transitivamente**,
como dependência de `phpstan`/`larastan` (que são `require-dev`). Depender dele de forma
**implícita** (via dev-dep de terceiro) é frágil: um bump de PHPStan que troque de parser, ou
um deploy `--no-dev`, quebraria o consumidor sem aviso.

A stack já foi decidida PHP-first para B/C/D ([W] 2026-07-23): os softwares novos serão
Laravel/PHP, então o motor de geração reaproveita AST de PHP em vez de tree-sitter/LSP
language-agnostic.

## Decisão

**Promover `nikic/php-parser` a dependência direta em `composer.json` `require`** (não `require-dev`),
fixada em `^5.7.0` (a versão já resolvida no lock).

- **Runtime:** o consumidor (`kb:code-scan` e futuros da Fase B/D) é **comando artisan de
  dev/CI/CT 100** — geração de documentação, **nunca** invocado no runtime web do Hostinger
  ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md) segue válido: o pacote fica no vendor,
  mas nenhum daemon/rota o expõe). Promover a `require` (não `require-dev`) garante que o
  binário exista mesmo num deploy `--no-dev`, tornando o comando robusto onde quer que rode.
- **Escopo:** só a promoção da dependência. O consumidor (`kb:code-scan`) e o modelo de dados
  (reuso de `type='reference'` no `KbNode`, sem migration — decisão [W] 2026-07-23) entram em
  PRs próprios.

## Consequências

**Positivas**
- Dependência **explícita e versionada** — some o acoplamento implícito ao parser interno do PHPStan.
- Destrava a Fase B (auto-document) e a Fase D (engine que colhe o grafo do Larastan) PHP-first.
- Custo trivial: pacote pequeno, zero-dep, já presente e amplamente usado (é o parser do PHPStan/Rector).

**Negativas / limites**
- +1 dependência direta de manutenção (bumps de major do php-parser passam a ser decisão nossa).
- Não muda o runtime Hostinger: continua proibido expor/rodar qualquer coisa da Fase B como
  daemon/rota lá — é ferramenta de dev/CT 100.

**Reversão:** se a Fase B for abandonada, remover de `require` (o pacote volta a ser só
transitivo de dev) + rodar `composer update --lock`. Sem migration, sem dado a limpar.
