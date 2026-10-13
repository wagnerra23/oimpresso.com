---
id: resources-js-pages-funcoes-index-casos
casos: Funções e permissões · /roles
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: função de outro negócio na lista, ou contagem de usuários alheia, expõe quem acessa o ERP de outra empresa.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Funções e permissões

> Thread `sistema/playbook/02`. Derivados do comportamento do `RoleController` e da Blade `role/*`
> (RUNBOOK-funcoes, `funcoes-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Roles/FuncoesContratoTest.php`](../../../../tests/Feature/Roles/FuncoesContratoTest.php),
> lane `acessos-pest.yml` (MySQL). O comportamento da Blade está travado em `FuncoesBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-FUNC-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem administra o que cada usuário pode fazer.
- **Aceite:** Dado `roles.view` e a flag `useV2SistemaFuncoes` ligada · Quando faço `GET /roles` como o browser faz
  (`X-Inertia` **e** `X-Requested-With`) · Então renderiza Inertia **`Funcoes/Index`** — não o JSON da DataTable —
  com `pode` refletindo `roles.create|update|delete`.
- **Status: 🧪**

## UC-FUNC-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /roles` · Então a resposta é a Blade `role.index`.
- **Status: 🧪**

## UC-FUNC-03 · A lista é só do meu negócio · `[T0]`
- **Aceite:** Dado uma função minha e uma de outro negócio, cada uma com um usuário · Quando a lista carrega (prop
  deferida `funcoes`) · Então vejo a minha, sem o sufixo `#negócio` e com 1 usuário, e não vejo a alheia.
- **Status: 🧪**

## UC-FUNC-04 · Função padrão não se edita nem se exclui
- **Aceite:** Dado uma função padrão e uma comum · Quando a lista carrega · Então a padrão vem `editavel: false` e a
  comum `editavel: true`.
- **Status: 🧪**

## UC-FUNC-05 · O drawer oferece o mesmo formulário da Blade
- **Persona:** quem administra o que cada usuário pode fazer.
- **Aceite:** Dado uma função minha com permissões · Quando abro "Editar permissões" (partial reload da prop `editor`
  com `?funcao=<id>`) · Então recebo os blocos e controles que a Blade `role/edit` renderiza para o meu negócio, com o
  rótulo da Blade, o nome sem `#negócio`, as caixas e os escopos (rádio) que a função já tem marcados.
- **Status: 🧪**

## UC-FUNC-06 · Função de outro negócio não abre no drawer · `[T0]`
- **Aceite:** Dado uma função de outro negócio · Quando peço o editor dela · Então 404, sem o nome dela na resposta;
  a minha abre.
- **Status: 🧪**

## UC-FUNC-07 · Salvar sem mexer não muda nada
- **Aceite:** Dado uma função com caixas e escopos marcados · Quando salvo pelo drawer sem alterar nada (mesmo corpo
  que o `<form>` da Blade mandaria: `permissions[]`, `spg_permissions[]`, `radio_option[]`, `is_service_staff`) ·
  Então as permissões da função continuam as mesmas; desmarcar uma revoga só aquela.
- **Status: 🧪**

## UC-FUNC-08 · Nova função e função padrão
- **Aceite:** Dado `roles.create` · Quando abro "Nova função" · Então o editor vem sem id e sem nome, com os mesmos
  controles. Sem `roles.create`, 403. Função padrão (fora `Cashier`) não abre no editor: 403.
- **Status: 🧪**

## Trilha do tempo
- 2026-10-08 · [CL] criado com a F3-1 da thread `sistema/playbook/02`.
- 2026-10-13 · [CL] UC-FUNC-05..08 com a F3-2 (drawer de cadastrar/editar).
