---
name: Como escrever doc que o RAG do oimpresso consegue recuperar
description: Regras de escrita derivadas do código real do indexador (IndexarMemoryGitParaDb) e do DocumentChunker — onde o arquivo mora decide se ele existe pro RAG, e cada seção `##` vira um chunk isolado sem contexto. Escrito errado, o doc fica invisível ou devolve trecho sem sentido.
type: guide
authority: canonical
lifecycle: ativo
updated_at: '2026-07-28'
related_adrs: ['0053-mcp-server-governanca-como-produto', '0256-knowledge-survival-meia-vida-catraca-sentinela']
---

# Como escrever doc que o RAG do oimpresso consegue recuperar

> **Por que este doc existe:** escrever conhecimento em `memory/` não garante que a IA o encontre. O pipeline de indexação tem regras duras (allowlist de nomes, chunk por heading, zero overlap) que fazem um doc bem escrito ficar **invisível** ou ser devolvido como **trecho sem sentido**. As regras abaixo foram lidas no código, não supostas.
>
> **Todas as medições desta página são datadas de 2026-07-28** e foram tiradas do container `oimpresso-mcp` no CT 100 (DB `u906587222_oimpresso`) e do código em `Modules/Jana/Services/`. Número que envelhece não se edita à mão — **re-rode o comando** citado em cada seção.

## Regra 1 — o PATH decide o `type` e o `module`, não o frontmatter

O indexador [`IndexarMemoryGitParaDb.php`](../../Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php) monta a lista de arquivos por **glob de caminho** e atribui `type` e `module` a partir do diretório e do nome do arquivo. O frontmatter YAML **não** é consultado para isso.

Consequência prática: pôr o arquivo na pasta errada não é questão de organização — muda como o RAG classifica o documento, ou faz ele não existir.

Onde cada coisa cai hoje:

| Onde você escreve | `type` que o RAG atribui | Cobertura |
|---|---|---|
| `memory/reference/**` | `reference` | **recursiva** — indexa tudo, em qualquer subpasta |
| `memory/governance/**`, `memory/sprints/**`, `memory/requisitos/_DesignSystem/**` | `reference` | recursiva |
| `memory/decisions/*.md` | `adr` | glob simples |
| `memory/sessions/*.md`, `memory/handoffs/*.md` | `session`, `handoff` | glob simples |
| `memory/requisitos/<Mod>/SPEC.md` e `BRIEFING.md` | `spec`, `briefing` | glob dedicado |
| `memory/requisitos/<Mod>/<QUALQUER-OUTRO>.md` | — | **allowlist de 9 nomes — ver Regra 2** |

Verificar onde um arquivo caiu: `grep -n "coletarRecursivo(\|glob(\"\$base" Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php`

## Regra 2 — em `memory/requisitos/<Mod>/`, só 9 nomes exatos entram no RAG

O laço que varre `memory/requisitos/*/*.md` tem um `continue` duro:

```php
if (!isset($docsPorModulo[$name])) continue;
```

A allowlist aceita **nome exato**, sem prefixo nem sufixo: `SPEC`, `BRIEFING`, `RUNBOOK`, `ARCHITECTURE`, `GLOSSARY`, `CHANGELOG`, `README`, `COMPARATIVO_CONCORRENCIA`, `SUPERFICIE`.

`RUNBOOK-criar-modulo.md` **não** casa `RUNBOOK`. `PEGADINHA-x.md`, `AUDITORIA-x.md`, `EVIDENCE-x.md` não casam nada. Esses arquivos existem no git, aparecem no `Glob` do agente, e **não existem para a busca da IA**.

Medido em 2026-07-28 (re-rodar antes de citar):

```bash
for f in $(git ls-files ':(glob)memory/requisitos/*/*.md'); do n=$(basename "$f" .md); \
  case "$n" in SPEC|BRIEFING|RUNBOOK|ARCHITECTURE|GLOSSARY|CHANGELOG|README|COMPARATIVO_CONCORRENCIA|SUPERFICIE) ;; \
  *) echo "$f";; esac; done | wc -l
```

Resultado naquele dia: **455 de 715 fora do RAG**, incluindo **125 arquivos `RUNBOOK-*`**.

> ⚠️ **O `:(glob)` no pathspec é obrigatório e não é detalhe.** Sem ele, o `git ls-files 'memory/requisitos/*/*.md'` usa wildmatch, onde `*` **atravessa `/`** — e passa a contar `_telas/`, `_legado-fullpage/` e qualquer subpasta. O `glob()` do PHP, que é o que o indexador realmente usa, **não recursa**. A primeira versão desta seção citava 743/1.011/144 por causa disso; os 19 `RUNBOOK-*` a mais viviam em profundidade ≥2 e **nunca estiveram ao alcance do indexador**. Quando for medir cobertura de um glob de código, replique a semântica **daquela linguagem**, não a do seu shell.

**O que fazer:** conhecimento que precisa ser recuperável pela IA vai em `memory/reference/` (cobertura recursiva). Conhecimento que é anexo de um módulo e só o agente lê por path pode ficar em `memory/requisitos/<Mod>/` — sabendo que está fora da busca.

## Regra 3 — arquivo começando com `_` ou chamado `README` é pulado

A coleta recursiva descarta antes de indexar:

```php
if (str_starts_with($name, '_') || $name === 'README') continue;
```

Isso é intencional (templates e índices não devem poluir o recall), mas surpreende quem nomeia um doc de conteúdo como `_INDEX-ALGO.md` achando que ganha destaque. Ganha o oposto: some.

## Regra 4 — cada `##` ou `###` vira um chunk separado, e ele viaja sozinho

O [`DocumentChunker`](../../Modules/Jana/Services/Memoria/Contextual/DocumentChunker.php) quebra o markdown assim:

- alvo de **~3200 chars** (~800 tokens) por chunk;
- quebra preferencial em heading **`##` ou `###`** — o heading abre a seção nova e é preservado no chunk;
- `#` (h1) **não** quebra nada;
- seção maior que o alvo cai pro fallback por parágrafo, e aí **o heading fica só no primeiro pedaço**;
- **overlap = 0**.

O que a IA recebe numa busca é **um chunk**, não o documento. Se a seção diz *"essa correção resolve o problema acima"*, o leitor do chunk não tem "acima" nenhum.

## Regra 5 — Contextual Retrieval está DESLIGADO em produção

O código prevê gerar 50-100 tokens de contexto por chunk e prependar antes do embedding (padrão Anthropic). Isso tornaria chunks auto-suficientes automaticamente. **Não está ativo.**

Medido em 2026-07-28 no container `oimpresso-mcp`:

```
db=u906587222_oimpresso   contextual=false   docs=2011   com_contexto=0
```

Comando (re-rodar antes de citar o número):

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-mcp php artisan tinker --execute="echo config(\"copiloto.contextual_retrieval.enabled\");"'
```

Enquanto `JANA_CONTEXTUAL_RETRIEVAL` for `false`, **a auto-suficiência de cada seção é responsabilidade de quem escreve**. Não existe rede de proteção.

## Regra 6 — o frontmatter é o filtro indexado, não enfeite

A migration `add_typed_cols_to_mcp_memory_documents` promove campos do frontmatter a **colunas indexadas** de `mcp_memory_documents`: `status`, `authority`, `lifecycle`, `quarter`, `decided_at`, mais `tags`/`related`/`supersedes` como JSON consultável por `whereJsonContains`.

Isso é o que permite `decisions-search status:aceito authority:canonical` funcionar como filtro em vez de busca textual. Um doc sem frontmatter só é alcançável por similaridade — o caminho mais caro e menos preciso.

O campo `description` tem função específica, declarada no próprio schema: *"1 linha — usada pra decidir relevância no recall"*. Escreva-o como **resumo que responde**, não como título repetido.

Famílias com schema validado por AJV no CI vivem em `scripts/memory-schemas/`. Para `memory/reference/`, o schema exige `name`, `description`, `type` (`reference|feedback|protocol|guide|index`) e `authority` (`canonical|generated`).

## Regra 6-bis — o campo `status` decide se o doc é ACHÁVEL, e hoje o vocabulário é só de ADR

Estar indexado não é o mesmo que ser encontrável. Os **dois** caminhos de retrieval descartam documentos pelo campo `status` do frontmatter:

- FULLTEXT — `McpMemoryDocument::scopePorStatusAtivo()`
- híbrido/Meilisearch — o filtro `status IN [...]` no mesmo model

Ambos aceitam apenas `aceito`, `accepted`, `accepted-historical`, `recusado` — **ou `status` ausente**. Esse vocabulário é o das ADRs, mas é aplicado a **todo tipo de documento**. Os schemas canônicos dos outros tipos definem enums que não intersectam:

| schema | enum de `status` | interseção com o filtro |
|---|---|---|
| `runbook.schema.json` | `rascunho, ativo, arquivado, historical` | **vazia** |
| `briefing.schema.json` | `producao, piloto, em-construcao, parcial, backlog, shared-infra, meta, deprecated` | **vazia** |
| `reference.schema.json` | *não define `status`* | — |

A consequência é uma inversão perversa: **o documento que obedece ao schema do seu tipo fica invisível; o que não declara `status` aparece.** É por isso que `memory/reference/` funciona bem — o schema dele simplesmente não tem o campo.

Medido no banco de produção do MCP em 2026-07-28 (`u906587222_oimpresso`): **285 de 2.012 documentos indexados (14%) são invisíveis à busca**, entre eles **53 dos 62 SPECs (85%)**, **31 dos 79 BRIEFINGs (39%)** e **6 dos 11 RUNBOOKs (55%)**.

Enquanto isso não for reconciliado, ao escrever um doc que precisa ser encontrado: **ou omita `status`, ou use um dos quatro valores aceitos** — e saiba que omitir conflita com o schema do tipo quando ele exige o campo. Não há saída limpa hoje; a saída é o conserto do filtro, que é decisão do dono do módulo Jana.

## Regra 7 — o corpo passa por redação de PII antes de ser indexado

O indexador aplica redação e marca `has_pii` no registro. Isso é rede de segurança, **não** licença para escrever dado sensível: valores em R$ são proibidos em `memory/**` por decisão do dono (o time amplo tem acesso de leitura ao git), e CPF/CNPJ de cliente nunca entram.

## A fórmula, em ordem de execução

Antes de escrever qualquer doc que deva ser recuperável:

1. **Procure o dono do tema** — `git ls-files | grep -i <tema>` e leia `memory/reference/_INDEX.md`. Se já existe doc do assunto, **edite-o**. Dois docs do mesmo tema divergem e o RAG passa a servir a versão errada (aconteceu — ver Regra 9).
2. **Escolha o path pela Regra 1 e 2**, não pela estética da pasta.
3. **Escreva o frontmatter primeiro**, com `description` que funcione como resposta curta — e confira a Regra 6-bis antes de preencher `status`, porque esse campo pode tornar o doc inencontrável.
4. **Estruture em `##` auto-suficientes** — cada seção nomeia seu sujeito e cabe em ~3200 chars.
5. **Não restateie número que outro sistema sabe melhor** — aponte para o comando que o produz e, se precisar do valor, carimbe com data e diga qual sistema foi medido.
6. **Ao medir cobertura de um glob que vive em código, replique a semântica da linguagem daquele código.** `git ls-files 'a/*/*.md'` não é `glob("a/*/*.md")` do PHP: o pathspec do git deixa `*` atravessar `/`, o `glob()` não recursa. Use `:(glob)` para igualar. Errar isso produz um número maior e plausível — o pior tipo de erro, porque não parece erro.
7. **Verifique que entrou E que é achável** — são coisas diferentes. Estar em `mcp_memory_documents` não basta: o filtro de `status` (Regra 6-bis) pode descartar o doc na consulta. Confirme com uma busca real pelo termo do doc, não com a presença da linha na tabela.

## Regra 8 — teste de auto-suficiência antes de commitar

Leia cada seção `##` **isolada**, fingindo que é a única coisa que você recebeu. Se alguma dessas perguntas ficar sem resposta, a seção falha:

- De que sistema/arquivo/comando isto está falando? (nome completo, não "ele")
- Isto é regra vigente ou relato histórico?
- Qual a data ou a fonte do número citado?

Anáforas que quebram chunk: *"como vimos"*, *"essa correção"*, *"o problema acima"*, *"conforme a tabela"*. Substitua pelo sujeito nomeado.

Checar tamanho das seções:

```bash
awk '/^## /{if(n)print len" chars — "n; n=$0; len=0} {len+=length($0)+1} END{if(n)print len" chars — "n}' <arquivo.md>
```

Qualquer seção acima de ~3200 chars será partida por parágrafo e perderá o heading nos pedaços seguintes.

## Regra 9 — o caso real que motivou este documento

Em 2026-07-28, a pegadinha de `git worktree remove` seguir junction do Windows e apagar o `vendor/` real existia em **dois** documentos:

- `memory/reference/gotcha-worktree-junction-vendor-rm.md` — versão de 2026-05-26, que atribui a causa ao `--force`. **Está no RAG.**
- `memory/requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md` — versão corrigida em 2026-07-14, que registra que o `--force` **não** é a causa (o comando sem flag também apaga) e que vale para `node_modules/` também. **Fora do RAG**, por causa da Regra 2.

Resultado: quem perguntasse à IA sobre o assunto recebia a versão desatualizada de uma operação destrutiva, e a correção era invisível. As duas causas se somaram — duplicação de tema (violação do passo 1 da fórmula) e path fora da cobertura (Regra 2).

Verificar o que está indexado sobre um tema:

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-mcp php artisan tinker --execute="foreach(\DB::table(\"mcp_memory_documents\")->where(\"git_path\",\"like\",\"%<termo>%\")->get([\"type\",\"git_path\"]) as \$r) echo \$r->type.\" \".\$r->git_path.PHP_EOL;"'
```

## O que este documento NÃO cobre

Este doc trata de **como escrever para ser recuperado**. Não trata de:

- **como o retrieval funciona por dentro** (embedder, BM25, reranker, hybrid) — isso é `memory/requisitos/Jana/RETRIEVAL-GOTCHAS.md` e `RETRIEVAL-ESTADO-ARTE-2026-05.md`;
- **quais schemas cada família exige** — isso é `scripts/memory-schemas/*.schema.json`, validado por AJV no CI;
- **o que pode ou não ser escrito em `memory/`** — isso é `memory/proibicoes.md`.

Se uma dessas fronteiras precisar mudar, o dono do tema é o doc citado — estenda-o, não abra paralelo.
