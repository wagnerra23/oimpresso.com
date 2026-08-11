---
date: "2026-08-11"
hour: "03:00 BRT"
topic: "Venda V3 — as 6 ondas do handoff de design portadas, e a âncora que não existia"
authors: [C, W]
prs: [5560, 5561, 5562, 5563, 5564]
us: [US-SELL-058]
tldr: "6 ondas em prod + fonte de design versionada. O charter apontava pra um path que não existia; 70 testes novos onde havia zero."
outcomes:
  - "5 PRs mergeados — ondas 2 a 6 do preview /sells/create-v3, todas verificadas em prod"
  - "Fonte de design versionada em prototipo-ui/cowork/venda-v3 — a âncora do charter era fantasma"
  - "70 testes em tests/js/ (antes: zero em resources/js)"
  - "3 chips abertos pro que resta"
---

# Venda V3 — as 6 ondas, e a âncora que não existia

## O que entrou

| PR | onda | prova |
|---|---|---|
| [#5560](https://github.com/wagnerra23/oimpresso.com/pull/5560) | fonte versionada + **2** entrega e frete | 118 checks |
| [#5561](https://github.com/wagnerra23/oimpresso.com/pull/5561) | **3** parcelas | 15 testes |
| [#5562](https://github.com/wagnerra23/oimpresso.com/pull/5562) | **4** detalhe do item, 7 abas | 18 testes |
| [#5563](https://github.com/wagnerra23/oimpresso.com/pull/5563) | **5** comissão | 16 testes |
| [#5564](https://github.com/wagnerra23/oimpresso.com/pull/5564) | **6** colunas do grid | 21 testes |

## Os achados

**A âncora de design não existia.** O charter declarava `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` — confirmado ausente por quatro oráculos (`ancora.mjs`, `git ls-files`, `ls`, `git check-ignore`). As 12 fontes viviam só dentro do zip do handoff. Foram versionadas em `prototipo-ui/cowork/venda-v3/`, que é **onde o gate required resolve** (`anchor-content-check::anchorRelPath` monta o path dentro de `cowork/`; fora dali vira `MISSING`).

**A onda 2 era a dívida D-6, não porte novo.** O `SDD-tela-venda-v1.0.md` marca `CU-SELL-11` como `[must]` e parcial porque o **PR #2104 foi revertido** (#2107) por regressão em cliente, ~30 min após o merge. As três causas-raiz são estruturalmente impossíveis no preview: não há fetch (cena estática), não há persistência (Non-Goal) e não é a tela viva. A lição do incidente foi *"refazer só após smoke real"* — o preview é onde esse ensaio cabe.

**A onda 4 revelou duas famílias de validação.** Formato (NCM 8 dígitos…) e **coerência**: CST 40 com alíquota 18% passa em qualquer validação campo-a-campo e é rejeitado pela SEFAZ. É a segunda que paga a onda.

**A onda 5 mostrou que base de comissão é decisão de incentivo** — sobre o bruto, a empresa paga o vendedor para dar desconto. E a faixa é por valor total, não progressiva por fatia (o teste calcula 950 × 1.200 e assere qual está implementado).

## Erros meus, registrados

1. Afirmei que o bundle *"carrega sem cor"* — não carrega nada, tela branca. Inferência de leitura em vez de medição.
2. Disse que `sells-roteiro.jsx` era tag órfã — define `CuDrawer`, sem ele o app não monta. Grepei o nome do **arquivo**, não o símbolo.
3. O teste de datas pegou minha aritmética errada (31/05 + 30 dias) num caso que nem contrastava.
4. Hand-rolei `role="tablist"` onde o canon é `<SubNav>`.
5. Reconstruí a base da comissão dividindo o resultado por 0,01.
6. **No smoke**: diagnostiquei "modal não abre" quando havia **dois** botões "Consultar cadastro… F2" e meu `find` pegava o inerte. O que me salvou foi o controle — outros drawers usam o mesmo `<Dialog>` e abriam.

Todos corrigidos, nenhum apagado. Três catracas morderam (`lint:baseline`, `layout:check`, `cowork-ssot-guard`) e as três estavam certas — consertei, nunca regenerei baseline.

## Smoke em prod (feito)

Autenticado, 2026-08-11: gaveta de entrega 46→755px com as 5 transportadoras e peso 7,550 kg derivado; 7 abas e 9 impostos com IBS/CBS; NCM `3919` acusa "faltam 4" e desabilita o Confirmar. `/sells/create-v3` dá **302→/login** e rota inexistente dá **404** — o teste HTTP **distingue**, ao contrário do que o mapa das ondas afirmava.

⚠️ O deploy da onda 5 **falhou** por SSH timeout (o flaky do Hostinger já catalogado). O de 02:44 (onda 6) levou tudo — provado: a onda 5 é ancestral daquele commit e seus 3 arquivos estão na árvore dele.

## O que fica

Três chips abertos: **Consulta de clientes** (último `AindaNao`), **promover os ~40 `[BACKLOG]` a UC com teste**, e **certificado digital vencido em prod** (achado do smoke, não relacionado).

Em aberto para [W]: o Non-Goal *"não calcula"* do charter, com o conflito registrado lado a lado por escolha dele. E o `sells-roteiro.jsx`, que não veio no handoff nem está no DesignSync — com ele, o `index.html` versionado roda direto.

## Estado MCP no fechamento

Não consultado nesta sessão — o trabalho entrou pelo chat com o zip do handoff, e a rastreabilidade ficou nos 5 PRs + `US-SELL-058` no SPEC. Fica declarado em vez de omitido.
