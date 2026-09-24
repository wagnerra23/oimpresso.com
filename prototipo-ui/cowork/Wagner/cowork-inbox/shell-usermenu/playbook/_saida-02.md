---
sessao: "_saida-02"
thread: "02 · Sair: confirmar antes de encerrar"
dono: "[CL]"
data: 2026-09-23
prefixo_tocado: resources/js/Components/cockpit/Sidebar.tsx · tests/sidebarSair.spec.tsx
base_lida: wagnerra23/oimpresso.com@main 66677a491
---
# _saida-02

## 0 · A premissa do playbook estava ERRADA — medido antes de editar

O playbook diz *"o item Sair não tem handler"*. **Falso no vivo**: medido em produção, logado,
2026-09-23, o item é `<a href="/logout" class="um-item">`. A rota existe (`routes/web.php`
`Route::get('/logout', LoginController@logout)` → `session()->flush()` + `Auth::logout()` +
`redirect('/login')`), e é a mesma que o layout legado usa (`header.blade.php`). O Sair
**encerrava**. Só encerrava **sem confirmar**.

A condição de parada do playbook (*"não houver rota de logout evidente no layout legado"*) não se
aplica: a rota é evidente e foi reusada. Nenhum endpoint novo.

## 1 · Feito

- **Sair** virou `<button>`: clicar abre a pergunta **inline no próprio menu**, `Encerrar a sessão?`
  (`role=group`), sem modal.
- **Encerrar** = `<a href="/logout">`, o logout real, e funciona sem JS. **Não** o
  `window.location.reload()` do protótipo, que é stand-in declarado.
- **Cancelar** volta ao menu sem efeito. Fechar o menu (clique fora) descarta a pergunta, então
  reabrir volta ao Sair.
- Estilo inline com os tokens da sidebar (`--sb-border`, `--sb-text-dim`), porque o prefixo da
  thread é só o `Sidebar.tsx` e não há CSS novo. As medidas espelham `.um-sair*` do protótipo.

## 2 · Provas

- `npx vitest run tests/sidebarSair.spec.tsx tests/sidebarMenuSemantics.spec.tsx`: **14/14**.
- **Mordida:** M1 (`Sidebar.tsx` do main) → **4 de 4 caem**; M2 (Cancelar não cancela) → **1 cai**;
  M3 (fechar o menu não descarta a pergunta) → **1 cai**. Restauração conferida por hash.
- **Controle negativo** do playbook: com a pergunta aberta, clicar fora fecha o menu, e nenhum link
  de logout sobra no DOM.
- `npm run lint && npx tsc --noEmit` **não sai exit 0 nem no main**. Delta nos arquivos tocados:
  **0** erros de lint; tsc com os **mesmos 2** erros pré-existentes do `Sidebar.tsx` (`:434`, `:720`).

## 3 · O que NÃO foi provado

- **Clicar Encerrar em produção** não foi feito: encerraria a sessão do Wagner. O logout real é
  provado por construção (mesma rota e mesmo verbo que o legado e o main já usavam) e pelo `href`
  asserido no teste, não por um clique vivo.
- **Runtime do código novo em produção:** só depois do deploy.
- Os specs do Sidebar **não rodam em nenhuma lane de CI** (resíduo fora do prefixo, igual ao
  `_saida-01`).
