---
slug: 0396-prototipo-fonte-unica-build-sem-canon-sombra
number: 396
title: "Protótipo tem uma fonte ativa, transporte build-only e caminhos literais"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-11"
module: governance
tags: [design, cowork, prototipo, ssot, duplicatas, ancoras, transporte]
supersedes:
  - 0387-github-md-diario-cowork-aceito-e-tratado
superseded_by: []
related:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0379-bundle-design-transacao-manifesto-delta-staging
  - 0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline
pii: false
---

# ADR 0396 — uma fonte ativa e importação sem interferência

## Contexto

Em 2026-09-11, [W] reprovou o estado do protótipo: ainda havia arquivos byte-idênticos em
lugares diferentes, fontes antigas fora de `cowork/` e âncoras que pareciam mudar de lugar
durante a importação. A inspeção confirmou três causas mecânicas:

1. o bundle e o `--export-from` roteavam qualquer `.md` para
   `prototipo-ui/design-docs/`, criando uma árvore paralela fora dos gates do canon;
2. o importador legado alterava `related_prototype`: quando o caminho original não existia e
   um basename homônimo existia na raiz, ele removia diretórios do valor recebido;
3. o guard verificava nomes/pastas, mas não comparava o conteúdo rastreado por hash.

O efeito era o oposto de fonte única: importar podia criar outra cópia, mudar a semântica de
um ponteiro e deixar difícil descobrir qual arquivo aplicar.

## Decisão

**D1 — uma fonte ativa.** O build de design ativo vive somente em
`prototipo-ui/cowork/`. O histórico vive no Git; não se mantém segunda cópia física para
“segurança”. `prototipo-ui/prototipos/` aceita somente âncora histórica explicitamente
declarada enquanto um charter ainda depender dela.

**D2 — transporte build-only e atômico.** Bundle, payload legado e `--export-from` do destino
Cowork aceitam somente fontes/dependências executáveis do protótipo (HTML, CSS, JS/TS/JSX/TSX,
SVG, imagens e fontes). `.md`, charter, casos, contrato e outros artefatos de canon recusam o
lote inteiro antes da primeira escrita. Não há mais roteamento automático para
`design-docs/`.

**D3 — zero conteúdo duplicado.** Dois paths ativos com o mesmo SHA-256 são erro, mesmo quando
os nomes diferem. A trava existe tanto no contrato do bundle quanto no guard do repositório.
Cache derivado de preview (`_ds`) não é fonte ativa e fica fora desta identidade.

**D4 — caminho literal.** A importação preserva exatamente o path relativo declarado. Ela não
achata diretórios, não troca basename e não edita `related_prototype`. Âncora errada é corrigida
no charter canônico, com o alvo explícito, e verificada pelos gates de âncora.

**D5 — `design-docs/` foi removida.** A ADR 0387 foi supersedida:
`github.md` e demais `.md` do Cowork não descem automaticamente. O conteúdo útil é destilado
nos donos existentes (`COWORK_NOTES.md`, `CODE_NOTES.md`, charter, SPEC, ADR ou handoff). Em
2026-09-11, após autorização explícita de [W], as referências vivas foram migradas e os 306
arquivos da árvore legada foram excluídos. O histórico permanece recuperável pelo Git.

## Consequências

- importar deixa de mudar lugares ou criar canon-sombra;
- um lote ambíguo falha inteiro e mantém o estado anterior;
- duplicata byte-idêntica passa a ser regressão verificável em CI;
- decisões e instruções deixam de competir entre cópias e voltam aos seus donos canônicos;
- a árvore paralela deixou de existir; a recuperação de qualquer registro antigo é feita pelo Git.
