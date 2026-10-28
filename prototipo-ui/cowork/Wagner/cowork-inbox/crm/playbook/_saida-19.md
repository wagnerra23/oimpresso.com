---
sessao: "_saida-19"
thread: "19 · Portal do contato · Perfil (+ senha) (ManageProfileController) → Inertia"
dono: "[CL]"
data: 2026-10-27
tipo: recibo
entregue_em: "PR da branch claude/mig-crm-portal-perfil, empilhado sobre o #10023 (thread 18, casca do contato) — aberto em 27/10, não mergeado"
base_lida: wagnerra23/oimpresso.com@main 7806b8ec7c
---
# _saida-19

## Entregue: Perfil do portal do contato em React, atrás da flag OFF
`ManageProfileController@getProfile` responde Inertia `Crm/Portal/Perfil/Index` com a flag `useV2CrmPortalPerfil` ligada; sem ela, ou com
`?classico=1`, segue a Blade `crm::profile.edit`. Os dois POST de hoje (`contact-profile-update` e `contact-password-update`) continuam
servindo a Blade como antes, e ganham um caminho para a visita Inertia. Ligar é o cutover (F5), decisão [W].

A tela usa a casca do contato da thread 18. A montagem da casca saiu do `DashboardController` para o `PortalContatoMenu::casca()`, que as
duas telas usam.

## Mudança de comportamento — registrar no Cowork
A Blade de hoje edita o **usuário** de login (nome, sobrenome, e-mail, idioma, foto). A tela React edita o **cadastro do contato** —
pessoa de contato (`contato`), telefone (`mobile`), e-mail (`email`) e endereço (`address_line_1`) —, que é o que o protótipo desenha e o
que o charter do portal pede nos Goals. Nome, CNPJ/CPF, tipo e as chaves ficam de fora por lista permitida no controller.

## Placar
entregue 2 de 2 UCs.

| UC | estado |
|---|---|
| UC-CRMPO-04 cadastro fiscal não muda `[T0]` | teste escrito: posta `name`, `tax_number`, `contact_id`, `business_id`, `type` e `id` de B junto com os 4 campos; os 5 protegidos ficam iguais, os 4 mudam, B e o A' do negócio 99 ficam intactos |
| UC-CRMPO-05 troco a minha senha, e só a minha | teste escrito: atual errada → erro no campo e hash igual; atual certa com `user_id` de B no corpo → muda só a de A |

## Testes
`Modules/Crm/Tests/Feature/CrmPortalPerfilContratoTest.php`, registrado na lane `verticais-pest.yml` (lista + `ManageProfileController` nos
dois gatilhos). **Não rodei Pest** (CT 100 fora; Pest local proibido). O veredito sai do CI do PR.

## Forma
**Não medida** — `design-diff` contra `crm--portal-perfil--index` exige a tela rodando com contato logado e flag ligada. Fica para a F4.

## Divergências declaradas
- O caso UC-CRMPO-05 diz `422` para a senha errada. Em formulário Inertia a recusa é redirect com erro de validação na sessão; o teste
  prova o erro no campo e o hash intacto.
- Idioma e foto do usuário não entram na tela React (o protótipo não os tem); seguem na Blade.
- Endereço é um campo só, como no protótipo (`address_line_1`); cidade, UF e CEP seguem pelo balcão.

## Pendente
- CI verde do PR.
- O #10023 (thread 18) mergear antes deste: este PR é empilhado sobre ele.
- F4: smoke com a flag ligada num negócio de teste + design-diff. F5: decisão [W].
