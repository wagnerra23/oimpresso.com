---
sessao: "_saida-07b"
thread: "07 · Acompanhamentos: escrita em Inertia — PR-c1 (Tier 0 do antecipado)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main (fetch em 2026-10-05)
complementa: "_saida-07.md, pendente 2 (Tier 0 a olhar no PR-c)"
---
# _saida-07b · O antecipado aceitava contato e fatura de outro negócio

## Por que vem antes da tela
O PR-c leva o "Acompanhamento antecipado" para Inertia. O `_saida-07` deixou registrado, como
pendente 2, que o `addAdvanceFollowUp` usava as chaves de `follow_ups` como `contact_id` e
sincronizava `invoices` sem validar o negócio. Medido no `main`: nada no `StoreScheduleRequest`
cobria `follow_ups`, e nem `CrmContact` nem `Transaction` filtram o negócio sozinhos. Uma requisição
montada com o id de um contato de outro negócio levava o nome dele (`{customer_name}`) e os números
das faturas dele (`{invoice_numbers}`) para o título do acompanhamento, e vinculava essas faturas.
Construir a tela por cima disso seria publicar o furo; ele sai primeiro, em PR próprio.

## Entregue (PR-c1)
- `StoreScheduleRequest`: as chaves de `follow_ups` precisam ser contatos do negócio da sessão;
  `follow_ups.*.user_id.*` e `follow_ups.*.invoices.*` também.
- `CrmUtil::addAdvanceFollowUp` / `replaceAdvFollowUpTags`: contato e faturas lidos e vinculados só
  do negócio do acompanhamento (2ª linha, que vale também para `pos:createRecursiveFollowup`, cujo
  input não traz `business_id` — usa o do usuário).
- UC-CRMACO-18 `[T0]` e teste em `CrmAcompanhamentosContratoTest.php` (lane `verticais-pest.yml`):
  contato, usuário e fatura alheios voltam como erro do campo sem gravar nada; o caminho certo grava
  no meu negócio com o nome do meu contato no título.

## Achado lateral, não consertado aqui
O comando recorrente monta o input sem `business_id`, e `addFollowUp` não o preenche. Se o
`Schedule` não tiver default, os acompanhamentos criados pelo comando ficam sem negócio. Não medi.

## Pendente (PR-c2 e seguintes)
1. Tela do antecipado em Inertia: o `getFollowUpGroups` devolve HTML (partials Blade) e precisa de
   uma resposta JSON; o botão da toolbar segue em `?classico=1` até lá.
2. Rodapé por status/tipo e drawer de detalhe com a lista de registros.
3. Smoke em produção depois do merge.

## Placar
`07` segue `em curso`: entregue a parte Tier 0 do PR-c; a tela do antecipado é o PR-c2. O
`00-INDICE.md` não foi editado.
