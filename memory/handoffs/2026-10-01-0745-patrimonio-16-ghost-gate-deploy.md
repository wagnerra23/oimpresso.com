---
date: "2026-10-01"
time: "07:45 BRT"
slug: patrimonio-16-ghost-gate-deploy
tldr: "Placar cita dependência; Patrimônio thread 16 (revocation redireciona) e ghost Devoluções fora do sub-menu; gate de bundle do deploy passou a comparar o manifest do build e destravou a esteira; lição na LC-11. Pendente: o Cowork trocar a prova 1 da thread 16 no índice."
prs: [8265, 8286, 8296, 8297, 8309, 8318]
decided_by: [W]
next_steps:
  - "Cowork: trocar a prova 1 da thread 16 no 00-INDICE do Patrimônio pela do redirecionamento (pedido em _saida-16c.md, já no projeto w)"
  - "Apagar as 2 views Blade órfãs de devoluções (asset_revocation/index e create): 0 chamadores em PHP"
---

# Patrimônio 16 + ghost Devoluções + gate de bundle do deploy

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI. `my-work`: sem tasks ativas para @wr23.
- `whats-active` (6h): 10 sessões, nenhuma no que ficou pendente aqui. As threads 18, 19 e 20 do Patrimônio mergearam (#8262, #8260, #8272).

## O que aconteceu
1. **#8265** — o `motivo` do placar passou a citar `depende de NN (não feita)`. Antes, uma thread travada só pela dependência saía "sem pendência legível".
2. **#8286 — thread 16:** `/asset/revocation` redireciona para `/asset/allocation` (decisão [W], opção b da `_saida-16b`). Saiu o ramo `ajax()` do DataTables, que devolvia JSON cru a quem entrava pelo menu, e o botão Devoluções do cabeçalho. O teste subiu sozinho antes do conserto e mordeu de verdade: o 1º run deu 409 no middleware, foi corrigido, e o 2º deu 200 onde se esperava 302.
3. **#8296** — `_saida-16c` e `_saida-13b` subiram ao Cowork (projeto w) por DesignSync e foram conferidas byte a byte.
4. **#8297** — o ghost "Devoluções" saiu do sub-menu; a Auditoria passou do "⋯" para a faixa. Prova de mordida: run 36756682263.
5. **#8309** — o deploy de #8297 travou a esteira: o smoke reprovava "front mudou e hash igual", e o comentário-só gerou build idêntico (3 deploys vermelhos com o bundle certo no ar). O gate agora compara o manifest do build com os assets servidos (`scripts/deploy/smoke-bundle.mjs`). O deploy seguinte saiu verde.
6. **#8318** — lápide §5 2026-09-30 + rec na LC-11, revisadas pelo `ciclo-adversary` (REJECT, 5 correções aplicadas).

## Persistência
- git: os 6 PRs mergeados. Prod: smoke DOM em `/asset/allocation` (redirect ok, cabeçalho só com "Alocar recurso", faixa sem Devoluções e com Auditoria).
- Cowork: `_saida-16c` e `_saida-13b` no projeto w.
- BRIEFING do AssetManagement **não** foi atualizado nesta sessão.

## Próximos passos pra retomar
- **Placar:** `node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/00-INDICE.md` dá 13 de 19. As threads 16 a 20 só esperam o **Cowork** trocar a prova 1 da 16; o código delas já está no ar. Isso não é trabalho de Code: o índice é do Cowork.
- **Limpeza:** `Modules/AssetManagement/Resources/views/asset_revocation/{index,create}.blade.php` estão órfãs (0 chamadores em PHP).

## Lições catalogadas
- Gate que quer provar resultado publicado compara artefato produzido com artefato servido, nunca "o insumo mudou" (§5 2026-09-30, LC-11).
- Primeiro vermelho de bite-test não vale sem ler o motivo: aqui era 409 do middleware, não o controller.
- Sessão grande demais: 6 intents distintos numa sessão só. O certo era uma sessão por thread ou intent, como as 18, 19 e 20 foram feitas.

## Pointers
- `_saida-16c.md` em `prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/`
- Lápide: `memory/licoes-rejeitadas.md` §5 2026-09-30 (gate de bundle)
