---
slug: 0378-execucao-mcp-jana-para-forja-ondas
number: 378
title: "Execução do item 4 da 0366 — a plataforma MCP sai da Jana para a Forja em ondas, começando pelo schema"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-13"
module: governance
tags: [fronteira, mcp, jana, forja, migracao, ondas, schema]
supersedes: []
superseded_by: []
related:
  - 0366-fronteira-jana-forja-governance-kb
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Execução do item 4 da 0366 — o MCP sai da Jana em ondas, começando pelo schema

> **Nasce `proposto`; a ratificação é PR de flip próprio** — `status: proposto → aceito`,
> mudando SÓ essa linha, com o índice regenerado e o label `adr-metadata-normalization`
> ([receita canônica](README.md#como-ratificar-uma-adr-proposta-flip-proposto--aceito),
> convenção [ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md)). O merge daquele PR
> é o ato ([W], R10). `decided_by`/`decided_at` já vêm preenchidos porque a **decisão** de
> [W] é de 2026-08-13 e está datada — o que falta é o **ato formal**, não a decisão.
>
> A decisão de DESTINO já era canon desde a [0366](0366-fronteira-jana-forja-governance-kb.md)
> (aceita 2026-08-03); esta ADR não a redecide — ela autoriza e desenha a **execução**, que a
> 0366 deixou explicitamente pendente.

## Contexto

A [ADR 0366](0366-fronteira-jana-forja-governance-kb.md) decidiu, pelo critério *"que pergunta
o módulo responde"*, que o dono da plataforma MCP é o **Forja** (Corolário 1). Ela também
decidiu **não mover nada**: o §D-C lista 4 itens de execução e diz do item 4 —
*"30 `Mcp*` + 59 migrations + tools MCP → Forja"* — que ele é **alto risco** e *"não está
autorizado por esta ADR … exige ADR própria + janela"*.

Passaram-se 9 dias sem que nenhuma máquina cobrasse a pendência, e a razão é instrutiva:

- o `catalog-graph` **via** a aresta `Forja → Jana` (94 imports), mas a contava como
  **declarada** — e `declarado` é binário POR PAR, então duas delegações verdadeiras do
  `not_contains` do Forja (*"Skills governance"*, *"Chat IA"*, ~11 imports) absolviam os
  **81** imports `Mcp*` do tema oposto. A maior aresta do repo ficou fora da lista de dívida;
- o slot `drift_alerts` do `SCOPE.md` é o desenhado para isso e estava **vazio** para este tema
  (escrito à mão ⇒ apodrece, [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md));
- o `adr-proposto-parado.mjs` vigia ADR **proposta** parada; **ADR aceita não executada** não é
  nenhum dos 3 checks dele.

O conserto do primeiro já landou ([#5720](https://github.com/wagnerra23/oimpresso.com/pull/5720)):
o report passou a imprimir também os pares **declarados**, com volume e grupo dominante.

## Decisão

**D-1 — O item 4 está AUTORIZADO, e a execução é em ONDAS, não em um PR.** A unidade de onda é
a camada, não o arquivo: cada onda tem que deixar a árvore consistente sozinha.

| onda | conteúdo | estado |
|---|---|---|
| **1 — schema** | 61 migrations `mcp_*` | **feita** ([#5722](https://github.com/wagnerra23/oimpresso.com/pull/5722)) |
| 2 — dado | 30 `Entities/Mcp/` + os 81 imports `Mcp*` do Forja, que viram internos | pendente |
| 3 — servidor | `Mcp/` (servidor JSON-RPC + 40 tools + prompts/resources) | pendente |
| 4 — serviço | 5 `Services/Mcp/` + 10 `Console/Commands/Mcp*` + `McpAuthMiddleware` | pendente |
| 5 — teste | 44 arquivos de teste | pendente |

**D-2 — A ordem é schema → dado → servidor → serviço → teste, e ela não é arbitrária.** O schema
vai primeiro porque é a única camada que move **sem tocar namespace**: migration não é
namespaced e a tabela `migrations` do Laravel casa por **nome de arquivo**, então mover
preservando o nome não re-roda nada em produção. As camadas seguintes trocam FQCN e exigem
relink de todos os consumidores — começar por elas seria mover o risco para a frente.

**D-3 — A janela.** A onda 1 **não tem janela de risco de runtime** e por isso pôde ir direto:
nome preservado ⇒ zero re-execução; o deploy roda `migrate --force` global. As ondas 2-5 **têm**,
porque trocam namespace de classe consumida por outros módulos, e ficam sujeitas a:

- **uma onda por PR**, sem empacotar duas;
- **CI verde inteiro** antes do merge, incluindo os gates de fronteira (`catalog-graph --check`,
  as duas catracas de acoplamento) — a régua tem que mostrar a aresta ENCOLHENDO;
- **smoke real da tool MCP** após o deploy de cada onda (a plataforma serve o time; quebrar o
  `brief-fetch` ou o `tasks-*` é incidente, não regressão silenciosa);
- **rollback declarado no PR** — para as ondas de código o rollback é `git revert`, e ele é
  seguro justamente porque o schema já está do lado certo desde a onda 1.

**D-4 — O que NÃO muda.** URLs, nomes de tool MCP, nomes de rota e permissions `jana.*`/`mcp.*`
permanecem **inalterados** em todas as ondas. Muda o dono do código, não o contrato — mesma
regra que a 0366 aplicou às telas e que a [ADR 0087](0087-drift-resolution-sem-mover-url.md)
fixou: renomear revogaria acesso em silêncio.

**D-5 — Reconciliação de contagem.** A 0366 fala em **59** migrations; moveram **61**. As duas
extras (`2026_08_04_100000` e `2026_08_04_190000`) nasceram **depois** da ADR, que é de 08-03.
Não é divergência de escopo.

## Consequências

**Positivas.** A Jana volta a ser o que o README sempre disse que era — produto de IA — e o
ratio negócio/governança do módulo passa a medir uma coisa só ([ADR 0334](0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)).
Medido na onda 1: a dívida de acoplamento por tabela caiu de **20 para 18** pares, com
`Forja>Jana`, `Governance>Jana` e `Superadmin>Jana` **curados**.

**Negativas, e elas são reais enquanto as ondas 2-5 não saírem:**

1. **`module:migrate Jana` deixou de provisionar as 61 tabelas.** O `InstallController` da Jana
   estende `BaseModuleInstallController` ([:128](../../app/Http/Controllers/BaseModuleInstallController.php)),
   que chama `module:migrate` com o nome do módulo, e a rota `/ia/install` está viva. Com o
   schema na Forja e o código MCP ainda na Jana, instalação **por módulo** não cria as tabelas
   que as 30 Entities, 40 tools, 5 Services e 10 comandos daqui consomem. Em produção isso é
   **mascarado** pelo `migrate --force` global do deploy. Fecha na onda 4.
2. **`Jana>Forja` cresceu de 4 para 54 queries** no eixo tabela. É **transitório por
   construção** — o código ainda mora na Jana e o schema já é do Forja. Some quando o código
   seguir. Fica congelado na baseline até lá, e **não** deve ser "curado" declarando norma.
3. **`Superadmin>Forja`** entra na baseline. Não é acoplamento novo: é `Superadmin>Jana`
   re-apontado. A norma do par segue indecidida na
   [mesa de fronteiras](proposals/2026-08-12-fronteiras-de-modulo-norma-por-par.md).

**Neutras.** 3 migrations já aplicadas em produção tiveram o arquivo editado (docblock apenas).
O Laravel não faz checksum de migration, então o risco de runtime é nulo — registrado por serem
artefatos já executados.

## Alternativas consideradas

- **Mover tudo num PR só** — descartado: 200 arquivos, 7 áreas, Tier 0. É o tamanho que fez a
  própria 0366 recusar o item 4.
- **Mover o código antes do schema** — descartado por D-2: colocaria a troca de namespace na
  frente, com o schema ainda do lado errado, e o rollback deixaria de ser trivial.
- **Não mover e declarar a fronteira como norma** (`depends_on`/`allowlist`) — descartado: faria
  acoplamento **vivo** parecer dívida **resolvida**, que é o vetor que a errata da mesa de
  fronteiras mediu por experimento e recusou.

## Riscos e revisão

`review_triggers`: (a) qualquer onda de 2 a 5 quebrar tool MCP em produção; (b) a aresta
`Forja → Jana` **não** encolher no `catalog-graph --acoplamento` depois da onda 2 — sinal de que
a decomposição por camada está errada; (c) `Jana>Forja` no eixo tabela **não** zerar ao fim da
onda 4. Qualquer um reabre esta ADR por sucessora, nunca por edição (append-only).
