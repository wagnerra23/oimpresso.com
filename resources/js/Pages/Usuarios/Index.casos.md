---
id: resources-js-pages-usuarios-index-casos
casos: Usuários · /users
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta de entrada de quem acessa o ERP; usuário de outro negócio na lista ou excluído por engano é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Usuários

> Thread `sistema/playbook/01`. Derivados do comportamento do `ManageUserController` (Blade
> `manage_user/*`, medido em 2026-10-07 pela baseline `UsuariosContratoTest`), do protótipo
> `usuarios-page.jsx` e das decisões de [W] de 2026-08-19 no `ACESSOS-F1` (D5) — não do `Index.tsx`.
> Testes: [`UsuariosIndexInertiaTest.php`](../../../../tests/Feature/Users/UsuariosIndexInertiaTest.php) ·
> [`UsuariosExcluirTest.php`](../../../../tests/Feature/Users/UsuariosExcluirTest.php), lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-USUA-01 · A tela React só entra pela chave da empresa
- **Persona:** quem administra os acessos do negócio.
- **Aceite:** Dado a chave `mwart.sistema_usuarios_index` desligada · Quando abro `/users` pela URL · Então
  vejo a Blade · E com `X-Inertia` vejo `Usuarios/Index` · E com a chave ligada só para a minha empresa a URL
  já abre a tela React, enquanto outra empresa continua na Blade · E o AJAX sem `X-Inertia` segue
  devolvendo o JSON da DataTable.
- **Status: 🧪**

## UC-USUA-02 · A lista é do meu negócio · `[T0]`
- **Aceite:** Dado um usuário meu, um comissionado meu e um usuário de outro negócio · Quando a lista carrega
  (prop deferida `usuarios`) · Então vejo o meu, com a função sem o `#negócio`, e não vejo os outros dois ·
  E a minha própria linha vem marcada como "você".
- **Status: 🧪**

## UC-USUA-03 · Cada ação respeita a permissão
- **Aceite:** Dado só `user.view` · Quando abro a tela · Então `pode` vem só com `ver` · E sem `user.view` nem
  `user.create` a tela responde 403.
- **Status: 🧪**

## UC-USUA-04 · Excluir pela tela usa o endpoint de sempre · `[T0]`
- **Aceite:** Dado um usuário meu · Quando a tela manda o pedido de excluir (POST com `_method=DELETE` e
  `X-Requested-With`) · Então ele é excluído (soft delete) e a resposta traz `success = true` · E o mesmo
  pedido para usuário de outro negócio devolve `success = false` sem excluir.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Excluir quem tem venda/OS no nome fica bloqueado com a contagem e oferece desativar (D5, [W]
  2026-08-19) — falta definir no backend o que conta como "no nome" (criador, vendedor, comissionado).
- [BACKLOG] Convite por e-mail e link de redefinição de senha — sem fluxo no legado.
- [BACKLOG] Cadastro e edição em drawer — hoje levam às telas Blade.

## Trilha do tempo
- 2026-10-07 · [CL] criado com a thread `sistema/playbook/01`.
