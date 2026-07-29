---
name: Como escrever doc que o RAG do oimpresso consegue recuperar
description: Regras de escrita derivadas do código real do indexador (IndexarMemoryGitParaDb) e do DocumentChunker — onde o arquivo mora decide se ele existe pro RAG, e cada seção `##` vira um chunk isolado sem contexto. Escrito errado, o doc fica invisível ou devolve trecho sem sentido.
type: guide
authority: canonical
lifecycle: ativo
updated_at: '2026-07-29'
related_adrs: ['0053-mcp-server-governanca-como-produto', '0256-knowledge-survival-meia-vida-catraca-sentinela']
---

# Como escrever doc que o RAG do oimpresso consegue recuperar

> **Por que este doc existe:** escrever conhecimento em `memory/` não garante que a IA o encontre. O pipeline de indexação tem regras duras (allowlist de nomes, chunk por heading, zero overlap) que fazem um doc bem escrito ficar **invisível** ou ser devolvido como **trecho sem sentido**. As regras abaixo foram lidas no código, não supostas.
>
> **As medições desta página são datadas de 2026-07-28**, exceto as da Regra 2, refeitas em **2026-07-29**. Todas saem do container `oimpresso-mcp` no CT 100 (DB `u906587222_oimpresso`) e do código em `Modules/Jana/Services/`. Número que envelhece não se edita à mão — **re-rode o comando** citado em cada seção.

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

O número não se conta mais à mão. O comando é:

```bash
node .claude/hooks/doc-fora-do-rag.mjs --measure
```

Medido em 2026-07-29: **447 de 744 fora do RAG** nesse nível, incluindo **118 `RUNBOOK-*`** — e **591 de 1.077** contando a subárvore inteira de `memory/requisitos/`.

> ⚠️ **Errata da primeira redação desta seção.** Ela publicava **481/744** e **125 `RUNBOOK-*`**, medidos por um `for`+`case` de shell que compara **só o basename** contra os 9 nomes. Esse critério ignora que `memory/requisitos/_DesignSystem/` tem **cobertura recursiva própria** — os 40 arquivos de lá (34 fora da allowlist, 7 deles `RUNBOOK-*`) estão **dentro** do RAG e eram contados como fora. `481 − 34 = 447`; `125 − 7 = 118`. Fica registrado, não apagado: o mesmo erro da nota abaixo, uma camada acima — replicar *um* glob do indexador e esquecer os outros mede a coisa errada com a mesma confiança.

> ⚠️ **O `:(glob)` no pathspec é obrigatório e não é detalhe.** Sem ele, o `git ls-files 'memory/requisitos/*/*.md'` usa wildmatch, onde `*` **atravessa `/`** — e passa a contar `_telas/`, `_legado-fullpage/` e qualquer subpasta. O `glob()` do PHP, que é o que o indexador realmente usa, **não recursa**. A primeira versão desta seção citava 743/1.011/144 por causa disso; os 19 `RUNBOOK-*` a mais viviam em profundidade ≥2 e **nunca estiveram ao alcance do indexador**. Quando for medir cobertura de um glob de código, replique a semântica **daquela linguagem**, não a do seu shell.

**O que fazer:** conhecimento que precisa ser recuperável pela IA vai em `memory/reference/` (cobertura recursiva). Conhecimento que é anexo de um módulo e só o agente lê por path pode ficar em `memory/requisitos/<Mod>/` — sabendo que está fora da busca.

Isto deixou de depender de alguém lembrar da regra: quem avisa, no momento em que o arquivo nasce, é [`.claude/hooks/doc-fora-do-rag.mjs`](../../.claude/hooks/doc-fora-do-rag.mjs) (`PreToolUse:Write`). Ele replica os globs do indexador e foi conferido contra o índice de produção — 486/486, zero falso-positivo e zero falso-negativo; o cabeçalho do arquivo traz a medição e o comando que a reproduz. Ponto-de-corte e mecanismo: [`_HOOKS-INDEX.md`](../../.claude/hooks/_HOOKS-INDEX.md), que é gerado, não escrito.

## Regra 3 — arquivo começando com `_` ou chamado `README` é pulado

A coleta recursiva descarta antes de indexar:

```php
if (str_starts_with($name, '_') || $name === 'README') continue;
```

Isso é intencional (templates e índices não devem poluir o recall), mas surpreende quem nomeia um doc de conteúdo como `_INDEX-ALGO.md` achando que ganha destaque. Ganha o oposto: some.

## Regra 4 — hoje NÃO há chunking; o que chega ao LLM são os primeiros ~400 chars

Esta é a regra que mais surpreende, e a que mais muda como se escreve.

O [`DocumentChunker`](../../Modules/Jana/Services/Memoria/Contextual/DocumentChunker.php) existe e quebra markdown por heading `##`/`###` em ~3200 chars com overlap zero. **Mas ele não roda.** Ele só é instanciado dentro de `aplicarContextualRetrieval()` (`IndexarMemoryGitParaDb.php:818`), e essa função retorna na primeira linha quando a flag está desligada (`:810`) — que é o estado em produção (Regra 5).

Consequência: **o documento é indexado inteiro, como uma unidade.** Não existe chunk.

O que efetivamente chega ao modelo é outra coisa: `renderFontes()` monta o contexto com `extrairExcerpt(content_md, 400)` — os **primeiros ~400 caracteres** do corpo, depois de remover o frontmatter. Um documento de 30 KB entra na resposta como título mais o primeiro parágrafo.

**O que fazer com isso:**

- Os **primeiros 400 chars depois do frontmatter** são o ativo mais valioso do documento. Se eles forem `# Título` seguido de `> Tela: | Module: | Charter:`, o modelo recebe metadado e nada mais.
- Abra o corpo com a **resposta**, não com preâmbulo: o que este doc afirma, para quem, e o que muda. Cabeçalho decorativo desperdiça o orçamento inteiro.
- Continue estruturando em `##` auto-suficientes — mas por dois outros motivos: o leitor humano, e o dia em que a flag da Regra 5 ligar. Hoje isso não é o que decide o que a IA vê.

## Regra 4-bis — as seções ainda precisam se sustentar sozinhas

Mesmo sem chunking ativo, escreva cada `##` como se ele viajasse isolado. Custo zero e três razões: a Regra 5 pode ser ligada a qualquer momento (e aí a estrutura passa a valer de imediato, sem reescrever nada); o humano que abre o doc pelo índice cai direto numa seção; e prosa que depende de "como vimos acima" é pior mesmo lida inteira.

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

⚠️ **Sobre o `description`, com uma ressalva medida.** O schema declara que ele é *"1 linha — usada pra decidir relevância no recall"*, e ele é útil para quem lê o índice e para as tools MCP. Mas `McpMemoryDocument::toSearchableArray()` **não o envia ao índice de busca** — o que vai é `title`, `content_md`, `content_excerpt`, `type`, `module`, `status` e `tags`. Escreva-o bem (é a vitrine do doc), sem contar com ele para ser encontrado: quem faz esse trabalho é o **título** e os **primeiros 400 chars do corpo** (Regra 4).

Famílias com schema validado por AJV no CI vivem em `scripts/memory-schemas/`. Para `memory/reference/`, o schema exige `name`, `description`, `type` (`reference|feedback|protocol|guide|index`) e `authority` (`canonical|generated`).

## Regra 6-bis — o campo `status` decide se o doc é ACHÁVEL, e hoje o vocabulário é só de ADR

Estar indexado não é o mesmo que ser encontrável. Os dois caminhos de retrieval filtram por `status`, **mas leem fontes diferentes — e é isso que separa o caminho são do doente**:

| caminho | de onde lê o `status` | efeito |
|---|---|---|
| **híbrido/Meilisearch** (primário, `docs_pipeline=true` em prod) | **coluna tipada** — `toSearchableArray()` manda `$this->status ?? 'aceito'` | valor desconhecido vira `NULL` na coluna → indexado como `aceito` → **passa** |
| **FULLTEXT** (fallback, quando o Meilisearch falha) | **`metadata->status` cru** — `scopePorStatusAtivo()` | valor fora do vocabulário de ADR → **descartado** |

Medido no banco de produção em 2026-07-28:

```
híbrido  : 1.958 de 2.015 visíveis (97,2%) — dos 57 fora, 47 são deprecated/rascunho/superseded (corretos) + 10 'proposto'
FULLTEXT :  1.727 de 2.012 visíveis        — 285 descartados
```

> ⚠️ **Errata da primeira redação desta seção.** Ela afirmava que "os dois caminhos" descartam e que **285 de 2.012 (14%)** eram invisíveis à busca, citando 85% dos SPECs. Esse número é do **fallback**, medido com uma query que reproduzia o `scopePorStatusAtivo` — e foi generalizado indevidamente para "a busca". No caminho que efetivamente atende, o descasamento custa **10 documentos**, e os 47 restantes estão fora por decisão correta. Fica registrado, não apagado: medir um caminho e concluir sobre o sistema é a mesma classe de erro que este documento cataloga no passo 6 da fórmula.

O descasamento **existe** e é real no fallback. Os schemas canônicos definem enums que não intersectam o vocabulário aceito:

| schema | enum de `status` | interseção com o filtro |
|---|---|---|
| `runbook.schema.json` | `rascunho, ativo, arquivado, historical` | **vazia** |
| `briefing.schema.json` | `producao, piloto, em-construcao, parcial, backlog, shared-infra, meta, deprecated` | **vazia** |
| `reference.schema.json` | *não define `status`* | — |

No fallback isso produz uma inversão perversa: **o documento que obedece ao schema do seu tipo é descartado; o que não declara `status` passa.** É parte do motivo de `memory/reference/` funcionar bem nos dois caminhos — o schema dele não tem o campo.

**Para quem escreve, hoje, a orientação é curta:** não se preocupe. O caminho primário normaliza, então `status: ativo` num RUNBOOK ou `status: producao` num BRIEFING **não** impede que o doc seja achado. Siga o schema do tipo.

**Para quem for consertar:** o defeito é do `scopePorStatusAtivo`, que lê `metadata->status` cru enquanto a coluna tipada — já normalizada, já preenchida, já usada pelo caminho híbrido — está ao lado. É troca de fonte no filtro, **não** normalização de documento. Backfill de frontmatter em massa aqui seria o big-bang de legado que o §5 de `proibicoes.md` já enterrou. Decisão do dono do módulo Jana (matriz §3 do `TEAM.md`).

## Regra 7 — o corpo passa por redação de PII antes de ser indexado

O indexador aplica redação e marca `has_pii` no registro. Isso é rede de segurança, **não** licença para escrever dado sensível: valores em R$ são proibidos em `memory/**` por decisão do dono (o time amplo tem acesso de leitura ao git), e CPF/CNPJ de cliente nunca entram.

## A fórmula, em ordem de execução

Antes de escrever qualquer doc que deva ser recuperável:

1. **Procure o dono do tema** — `git ls-files | grep -i <tema>` e leia `memory/reference/_INDEX.md`. Se já existe doc do assunto, **edite-o**. Dois docs do mesmo tema divergem e o RAG passa a servir a versão errada (aconteceu — ver Regra 9).
2. **Escolha o path pela Regra 1 e 2**, não pela estética da pasta.
3. **Escreva o frontmatter primeiro**, com `description` que funcione como resposta curta — e confira a Regra 6-bis antes de preencher `status`, porque esse campo pode tornar o doc inencontrável.
4. **Estruture em `##` auto-suficientes** — cada seção nomeia seu sujeito e cabe em ~3200 chars.
5. **Não restateie número que outro sistema sabe melhor** — aponte para o comando que o produz e, se precisar do valor, carimbe com data e diga qual sistema foi medido.
6. **Ao medir cobertura de um glob que vive em código, replique a semântica da linguagem daquele código — e meça no diretório certo.** Duas armadilhas independentes, que este documento já pisou nas duas:
   - `git ls-files 'a/*/*.md'` não é `glob("a/*/*.md")` do PHP: o pathspec do git deixa `*` atravessar `/`, o `glob()` não recursa. Use `:(glob)` para igualar.
   - `git ls-files` lista o **índice da branch daquele diretório**, não de `main`. Num projeto com worktrees paralelos, o repo principal costuma estar numa branch de outra sessão, dezenas ou centenas de commits atrás. Confira antes: `git branch --show-current` e `git rev-list --count HEAD..origin/main`.

   Nos dois casos o número errado sai **maior e plausível** — o pior tipo de erro, porque não parece erro.
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
