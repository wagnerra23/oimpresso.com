---
sessao: "16b"
titulo: Saída da thread 16 (2ª passada) — decisão [W] do grão das devoluções; a 16 passa a depender do drawer do bem e da 18
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 0051a3950 (origin/main fresco)
arquivos_de_producao_tocados: 0
complementa: "_saida-16.md (2026-09-24), que parou no §4-ter DADO e pediu a decisão (a)/(b)"
---

# 16b · A decisão que faltava foi tomada, e ela muda de quem é o trabalho

## O que esta passada fez
Revalidou a premissa da `_saida-16` no `main` de hoje e levou a decisão ao [W]. **Nenhum arquivo de produção foi tocado.**

- `RevokeAllocatedAssetController.php:108` **ainda** devolve `view('assetmanagement::asset_revocation.index')`. A lacuna 2 de 09/09 segue aberta.
- O defeito de grão continua: a lista de revogações tem **uma linha por devolução**, a aba Alocações tem **uma linha por alocação**, e a relação é **1 : N**. Os 5 campos da devolução (código, quantidade, data, autor, motivo) e a ação Excluir seguem ausentes da Page.
- **D-FORMS foi respondida** depois da `_saida-16`: os formulários do Patrimônio migram para drawers React PT-02, fonte `patrimonio-forms.jsx` ([W] 2026-09-24, ADR 0414). Isso dá destino à ação Excluir, que a `_saida-16` não tinha.

## Decisão [W] 2026-09-30
Entre as duas saídas que a `_saida-16` nomeou, [W] escolheu a **(b)**: a aba Alocações fica no grão da alocação, e o histórico de devoluções vai para o **drawer do BEM**, aba Alocações, **como no protótipo** (`patrimonio-page.jsx` `BemDrawer`, `:714-735`: cada alocação com `REV-` e data de revogação).

⚠️ **Correção de rota registrada, para não virar descoberta futura.** A primeira pergunta desta sessão ao [W] descrevia a opção como "drawer da **alocação**". O protótipo não tem drawer de alocação; o `REV-` mora no drawer do **bem**. A pergunta foi refeita com a leitura certa, e a resposta final é a do drawer do bem.

## Por que a 16 não executa agora
A produção **não tem drawer de detalhe do bem**. O que existe é `_shared/CadastroBemDrawer.tsx`, formulário de criar/editar, e `_shared/**` é `nao_toca` desta thread. O drawer com abas (Identificação · Alocações · Manutenção · …) **não pertence hoje a nenhuma thread**: o `show` do bem, que a thread 17 listava, foi resolvido por **remoção** — o [#7904](https://github.com/wagnerra23/oimpresso.com/pull/7904) (thread 15) tirou do ar as rotas `show` que apontavam view inexistente. ⚠️ A 1ª redação deste arquivo dizia que o drawer era "o `show` da thread 17"; estava errado, conferido no `main` antes do merge.

## Nova ordem (decisão de sequência, não de produto)
1. **Drawer de detalhe do bem** (sem thread dona hoje; o Cowork precisa emitir uma, ou ampliar a 17) — com a aba Alocações. O histórico de devoluções por alocação entra nela, com **N** devoluções por alocação (o protótipo modela 1 : 1; o nosso modelo é 1 : N, ver `_saida-16` §defeito de premissa).
2. **Thread 18** — drawer de revogação, incluindo **Excluir devolução**. É escrita de quantidade: exige a REGRA MESTRE (prova por dois caminhos + antes→depois ao [W]).
3. **Só então a 16** — `/asset/revocation` redireciona para `/asset/allocation`. Isso **funde a rota**, que o §B da thread reservava ao [W]; a escolha da (b) é essa autorização. Redirecionar antes de 1 e 2 apagaria o único caminho de UI que desfaz uma devolução errada.

## Tier 0 a carregar para a thread do drawer do bem (não corrigido aqui)
A soma de devoluções da Page (`AssetAllocationController`, `leftJoin asset_transactions as PT`) **não filtra `PT.business_id`** — o próprio charter de Alocações já declara esse resíduo. A lista de devoluções por alocação que essa thread montar tem de escopar `business_id` na **devolução**, não só na alocação. Cobrar com teste cross-tenant biz 98 × biz 2.

## O que o índice precisa refletir (do Cowork, não editado aqui)
- thread 16: `depende_threads` passa de `["15"]` para `["15","18",<thread do drawer do bem>]`, e o §B/§4-ter deixa de dizer "a rota NÃO funde" / "prop opcional em `Alocacoes.tsx`";
- **uma thread nova (ou a 17 ampliada)** para o drawer de detalhe do bem, com a aba Alocações e o histórico de devoluções 1 : N;
- thread 17: hoje ela depende da **16**, e a 16 passa a depender do drawer do bem — se o drawer ficar na 17, o índice tem um **ciclo 16 ↔ 17** a desfazer.
