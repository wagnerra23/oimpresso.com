---
id: modules-crm-pages-crm-acompanhamentos-index-casos
casos: Crm · Acompanhamentos · /crm/follow-ups
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a agenda comercial mostra compromissos de clientes e leads. Errar o escopo vaza agenda de outro negócio ou de colega que não devia ver; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela.
owner: wagner
last_run: "2026-10-05"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/03. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Acompanhamentos (`/crm/follow-ups`)

> **Âncora:** o [charter](Index.charter.md) (Goals e Anti-hooks), o SPEC do Crm §0 ("Follow-up
> agendado — funcional") e as permissões que a tela Blade já exigia
> (`crm.access_all_schedule` / `crm.access_own_schedule`). Os UCs não derivam do `.tsx`.

---

## UC-CRMACO-01 · A lista responde em Inertia · `must`

**Dado** que tenho `crm.access_all_schedule`
**Quando** abro `/crm/follow-ups`
**Então** recebo Inertia `Crm/Acompanhamentos/Index`, não a view `crm::schedule.index`.

Status: 🧪

---

## UC-CRMACO-02 · Visita Inertia não vira JSON do DataTables · `must`

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede a lista
**Então** recebe a prop `acompanhamentos` — e um ajax **sem** `X-Inertia` (o da tela clássica)
continua recebendo o JSON do DataTables.

Status: 🧪

---

## UC-CRMACO-03 · Acompanhamento de outro negócio não aparece · `must` `[T0]`

**Dado** um acompanhamento no meu negócio e outro em outro negócio
**Quando** abro a lista
**Então** vejo o meu e não vejo o do outro negócio.

Status: 🧪

---

## UC-CRMACO-04 · Quem só vê os próprios vê só os atribuídos a ele · `must`

**Dado** que tenho só `crm.access_own_schedule`
**Quando** abro a lista
**Então** vejo os acompanhamentos atribuídos a mim e não os do colega — e quem tem
`crm.access_all_schedule` vê o do colega.

Status: 🧪

---

## UC-CRMACO-05 · Sem permissão é barrado · `must`

**Dado** um usuário sem nenhuma das duas permissões
**Quando** abre `/crm/follow-ups`
**Então** recebe 403 — e o mesmo teste prova que quem tem a permissão recebe 200.

Status: 🧪

---

## UC-CRMACO-06 · A aba recorrente lista só os recorrentes · `should`

**Dado** um acompanhamento avulso e um recorrente
**Quando** abro a aba "Acompanhamento recorrente"
**Então** vejo só o recorrente, e a aba principal não mostra o recorrente.

Status: 🧪

---

## UC-CRMACO-07 · `?classico=1` devolve a tela Blade · `should`

**Dado** que a escrita ainda vive nos modais da Blade
**Quando** abro `/crm/follow-ups?classico=1`
**Então** recebo a view `crm::schedule.index`.

Status: 🧪

---

## Escrita (thread Crm/07, PR-a)

> **Âncora:** SPEC do Crm §0 ("Follow-up agendado — funcional"), o modal "Adicionar
> acompanhamento" do protótipo (`crm-blade.jsx` → `TelaAcompanhamentos`) e as regras que o
> `ScheduleController` já aplicava na Blade (escopo por `business_id`, "só os meus").
> O modal grava pelas MESMAS rotas (`store`/`update`/`destroy`); nenhum endpoint novo.

## UC-CRMACO-08 · Adicionar grava no meu negócio · `must`

**Dado** que tenho `crm.access_all_schedule` e um contato do meu negócio
**Quando** salvo o modal "Adicionar acompanhamento" com as datas do campo de data e hora
**Então** o acompanhamento fica gravado no meu negócio, com o início que escolhi e atribuído a quem marquei.

Status: 🧪

---

## UC-CRMACO-09 · Editar altera, mas não troca o negócio · `must` `[T0]`

**Dado** um acompanhamento do meu negócio
**Quando** salvo o modal "Editar acompanhamento" — mesmo que o pedido traga outro `business_id`
**Então** o título muda e o acompanhamento continua no meu negócio.

Status: 🧪

---

## UC-CRMACO-10 · Acompanhamento de outro negócio não se edita nem se exclui · `must` `[T0]`

**Dado** um acompanhamento de outro negócio
**Quando** tento editar ou excluir pelo id dele
**Então** recebo 404 nas duas ações e ele continua lá, sem alteração.

Status: 🧪

---

## UC-CRMACO-11 · Excluir remove o acompanhamento · `must`

**Dado** um acompanhamento do meu negócio
**Quando** confirmo "Excluir"
**Então** ele sai do banco e da lista.

Status: 🧪

---

## UC-CRMACO-12 · Contato de outro negócio é recusado · `must` `[T0]`

**Dado** um contato que pertence a outro negócio
**Quando** tento adicionar um acompanhamento para ele
**Então** recebo erro de validação em `contact_id` e nada é gravado — e o mesmo vale para atribuir a um usuário de outro negócio (`user_id`).

Status: 🧪

---

## UC-CRMACO-13 · A linha traz os valores do modal de edição · `should`

**Dado** um acompanhamento na lista
**Quando** abro "Editar"
**Então** o modal já vem com atribuídos, tipo e datas (início em formato de campo de data e hora).

Status: 🧪

---

## Escrita (thread Crm/07, PR-b)

> **Âncora:** SPEC do Crm §0 ("Follow-up agendado — funcional") e as telas Blade
> `crm::schedule.create_recursive_follow_up` e `crm::schedule_log.create`, com as regras que o
> `store`/`update` do `ScheduleController` e o `store` do `ScheduleLogController` já aplicavam
> (escopo por `business_id`). Nenhum endpoint novo.

## UC-CRMACO-14 · Adicionar recorrente grava no meu negócio · `must`

**Dado** que tenho `crm.access_all_schedule`
**Quando** salvo o modal "Adicionar acompanhamento recorrente" com "acompanhamento por" e "em dias"
**Então** o acompanhamento fica gravado no meu negócio, marcado como recorrente, com os dias e o critério escolhidos e sem datas.

Status: 🧪

---

## UC-CRMACO-15 · Editar recorrente altera e não troca o negócio · `must` `[T0]`

**Dado** um acompanhamento recorrente do meu negócio
**Quando** salvo o modal "Editar acompanhamento recorrente" — mesmo que o pedido traga outro `business_id`
**Então** o título e os dias mudam, ele continua recorrente e continua no meu negócio.

Status: 🧪

---

## UC-CRMACO-16 · Adicionar registro grava o log e o status · `must`

**Dado** um acompanhamento do meu negócio
**Quando** salvo "Adicionar registro" com as datas do campo de data e hora e um status
**Então** o registro fica gravado com o início que escolhi, e o acompanhamento passa ao status escolhido.

Status: 🧪

---

## UC-CRMACO-17 · Registro em acompanhamento de outro negócio é recusado · `must` `[T0]`

**Dado** um acompanhamento de outro negócio
**Quando** tento adicionar um registro pelo id dele
**Então** recebo 404 (como em editar e excluir), nenhum registro é gravado e o status dele não muda.

Status: 🧪

## UC-CRMACO-18 · Antecipado só aceita contato, usuário e fatura do negócio · `must` `[T0]`

**Dado** um acompanhamento antecipado (`follow_ups[<contato>][user_id][]`, `[invoices][]`)
**Quando** algum contato, usuário atribuído ou fatura é de outro negócio
**Então** o servidor recusa com erro no campo e nada é gravado. Com os do meu negócio, grava no meu
negócio e troca `{customer_name}` pelo nome do **meu** contato.

> Antes, nada disso era validado: o contato de outro negócio entrava no título pelas etiquetas
> `{customer_name}`/`{invoice_numbers}`, e as faturas dele eram vinculadas ao acompanhamento
> (pendente 2 do `_saida-07`). O `CrmUtil` também filtra contato e faturas pelo negócio, como
> segunda linha que vale para o comando recorrente.

Status: 🧪

## UC-CRMACO-19 · O antecipado monta "Quem vai receber" na própria tela · `must` `[T0]`

**Dado** a tela de acompanhamentos
**Quando** clico em **Acompanhamento antecipado**, escolho o critério (status do pagamento, pedidos
ou nome) e peço **Próximo**
**Então** a lista "Quem vai receber" vem do `getFollowUpGroups` em JSON — uma linha por contato, com
as faturas dele e o atribuído padrão — e **só com contatos do meu negócio**. Salvar cria um
acompanhamento por linha, pelo mesmo `store`. A tela clássica segue recebendo o partial Blade.

> Âncora: protótipo `crm-blade-forms.jsx` → AntecipadoForm. A notificação do formulário Blade
> ficou fora deste modal (vai desligada) — registrado no `_saida-07c`.

Status: 🧪
