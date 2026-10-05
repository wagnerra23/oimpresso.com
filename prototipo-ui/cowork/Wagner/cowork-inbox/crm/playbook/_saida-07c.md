---
sessao: "_saida-07c"
thread: "07 · Acompanhamentos: escrita em Inertia — PR-c2 (tela do antecipado)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main + PR-c1 (#8734)
complementa: "_saida-07.md (pendente 1) e _saida-07b.md (PR-c1, Tier 0)"
---
# _saida-07c · "Acompanhamento antecipado" sai da Blade

## Entregue (PR-c2)
- `ScheduleController::getFollowUpGroups`: responde JSON quando o pedido aceita JSON
  (`{grupos: [{contact_id, cliente, faturas[], atribuido}]}`), nos três critérios (status do
  pagamento, pedidos, nome). A tela clássica pede sem `Accept: application/json` e segue recebendo os
  partials Blade.
- `Acompanhamentos/_components/FormAntecipado.tsx` (novo): modal com os três blocos do protótipo
  (`crm-blade-forms.jsx` → AntecipadoForm): base (categoria, critério, faturas ou clientes, dias),
  "Quem vai receber" (atribuído por linha, remover) e conteúdo (título com as etiquetas que o
  backend troca, status, início, fim, descrição, tipo). Faturas vêm de `GET /crm/get-invoices`,
  que já respondia JSON. Grava em `POST /crm/follow-ups` com `follow_ups`, validado no PR-c1.
- `Index.tsx`: o botão "Acompanhamento antecipado" abre o modal em vez de levar a `?classico=1`.
- UC-CRMACO-19 `[T0]` e teste em `CrmAcompanhamentosContratoTest.php` (lane `verticais-pest.yml`):
  JSON só com contatos do meu negócio (id alheio descartado), atribuído padrão, critério "sem
  compra", e a Blade seguindo com HTML.

## Prova da ficha
*"os botões da toolbar deixam de levar a `?classico=1`"*: com este PR, os 3 (Adicionar, Recorrente,
Antecipado) abrem modal na própria tela. ✅ no branch.

## Diferenças declaradas
- **Etiquetas:** o protótipo lista `{invoice_no}`, `{due_amount}`, `{contact_name}`; o modal mostra
  as que o `replaceAdvFollowUpTags` de fato troca: `{customer_name}`, `{customer_business_name}`,
  `{invoice_numbers}`, `{days}`.
- **Notificação:** o formulário Blade tinha "enviar notificação"; o modal grava com ela desligada.
  Fica para o próximo PR se for usada.

## Pendente
1. Rodapé por status/tipo e drawer de detalhe com a lista de registros (pendente 2 do `_saida-03`).
2. Notificação no antecipado (acima).
3. Smoke em produção depois do merge.

## Placar
`07`: com o c1 e o c2, os UCs de escrita do pedido saem em Inertia; restam o rodapé e o drawer de
detalhe. O `00-INDICE.md` não foi editado.
