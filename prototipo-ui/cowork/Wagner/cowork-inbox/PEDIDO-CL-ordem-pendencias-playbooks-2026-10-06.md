# Pedido pro Code — ordem das pendências dos playbooks (2026-10-06)

> De [CC] para [CL]. Base lida no `main` @1ab4ab51b13a. **Não commitei nada.** Este pedido é ordem de trabalho, não retrato: o estado de cada thread **é o placar**, não esta folha. Onde ela e o placar divergirem, o placar vence.

## 0 · Antes de tudo (portão)
1. Importar o handoff do Cowork que leva este arquivo. Ele traz **índices novos** (`modulos`, `modulos-faltantes`, `app-lojas`) e o **`venda-menu` corrigido** (a thread fantasma `C0` agora existe, com C0–C6).
2. `node scripts/qa/placar.mjs --todos` — tem de sair **sem NÃO MEDI** em nenhum módulo. Se algum sair, **pare** e devolva a linha no recibo: é dado da fonte (Cowork), não se corrige no espelho.

## 0-bis · Regra nova ([W] 2026-10-07) — o design manda na forma
Melhoria de tela que você fizer **volta primeiro ao Cowork** (recibo + `pendentes-cowork.mjs --plano`) antes da próxima onda dela. Não continue uma tela a partir de um build do Cowork que esteja atrás do que você entregou — pare e devolva.

## 1 · Recibos que faltam (barato, destrava o placar)
Pegar **todas** as threads que o placar mostrar como `sem recibo` (provas verdes, sem `_saida`). Para cada uma, seguir a regra do `/onda`: achar o PR que entregou (`git log -S`), restaurar recibo apagado byte a byte, só na falta escrever retroativo citando o PR. **Desconfiar da prova** se o padrão já existia antes da data do índice.
- Caso conhecido: **`venda-menu/C0`** — `VendasMwartCutoverTest.php`, `app/Support/Mwart.php` e as 6 chaves `vendas_*` já estão no `main`; falta `_saida-C0.md`.

## 2 · Pesquisar antes de executar (módulos com playbook e nenhum recibo)
Estes têm threads e zero `_saida` no `main`. Antes de abrir qualquer onda, **medir se já foram entregues sem recibo** (mesmo caso do HRM/Patrimônio, que estavam em produção e o placar mostrava como a fazer):
`sistema` (01–07) · `tema-escuro` (01–04) · `recorrente` (01, A1) · `notificacoes` (01) · `acessos` (01).
Saída por módulo: lista "entregue (PR #) / não entregue / prova errada no índice". Prova errada **não se edita no espelho** — vai no recibo e volta pro Cowork corrigir.

## 3 · Executar — nesta ordem, uma thread = uma sessão = um PR
Só threads que o placar der como `proximo`. Prioridade por onde o dinheiro passa:
1. **venda-menu** — C1 (liga biz=1, depende do C0 com recibo) · Q1 (`Quotations.casos.md`) · **Q3** (converter cotação em venda, reusando `SellPosController@convertToInvoice`) · Q2 (`vendas_cotacoes`) **só depois da Q3**. C3–C5 são do [W] (observação e aviso à ROTA LIVRE): não executar.
2. **modulos/01** — install falho volta a inativo + "Com erro" acende, com `ModuleErroFixtureTest`. Depois **A1** (alvo). A 04 (apagar o legado) só com 01 `feito`, lane verde e smoke 1280/1440 do [W2].
3. **modulos-faltantes/01** — contratos de CV, Suporte e Vestuário em `governance/design/contracts/` (advisory primeiro). Depois **02** (Suporte Log).
4. O que sobrar como `proximo` nos módulos parcialmente sincronizados (cliente, estoque, essenciais, crm, hrm, ds-atomos, atendimento, officeimpresso, produto, telas-soltas) — pela ordem que o placar listar.

5. **telas-soltas** (D1 mudou em 07/10: entram em produção) — 04 Planilhas (Index primeiro) · 05 Voz (= modulos-faltantes/03, agora destravada por PERM-VOZ = `vozdocliente.triar`) · **06 OS: só pesquisa**, sem Page.
6. **patrimonio** — 05a (ADR sucessora da lápide 27/07 + 4 chaves mortas do `retention.php`) → 05 (comando nasce desligado). Merge da ADR = [W].

## 4 · Decisões — todas respondidas em 2026-10-07
[W] pediu recomendação e o [CC] decidiu (registrado em cada índice como "recomendação [CC]"). Efeitos: `modulos/02` (fila) **cancelada** · `modulos` D1/D5 entram na 01/04 · `modulos-faltantes/04` Catálogo QR destravada (`product.view`) · VEST-D1 liga o bloqueio **depois de 1 semana só logando** · comissões ficam no legado e **a regra de cálculo não muda**. Se algo disso contrariar código que você já mediu, **pare e devolva no recibo** — não ajuste a decisão no espelho.

> `venda-menu` D-ORC-1 e D-ORC-2 foram respondidas em 2026-10-06 (ver §6-ter do índice) — Q3 está liberada; Q-CC já tem recibo.

## 5 · Leis que não afrouxam
1 thread = 1 prefixo = 1 PR ≤300 linhas · estado só em `_saida-NN.md` · `nao_toca` do índice é lei · **não editar `00-INDICE.md` no espelho** (derruba o check required de todos os PRs) · retorno sobe ao Cowork na mesma sessão (`pendentes-cowork.mjs --plano`).

## 6 · Recibo deste pedido
`_saida-ordem-2026-10-06.md` em `cowork-inbox/placar/playbook/`, com: placar antes/depois (`entregue X de Y` por módulo), recibos restaurados/escritos, resultado da §2 por módulo, PRs abertos.
