---
thread: "28 · Rota própria — 5 ondas de build [CC]"
dono: "[CC]"
estado: feito
base_lida: "wagnerra23/oimpresso.com@main 7e4a6ebfda (2026-10-07)"
prefixo_tocado: "prototipo-ui/cowork/Wagner/ponto-page.jsx · prototipo-ui/cowork/Wagner/ponto-telas.jsx (via import do handoff 43)"
veredito: "entregue no build do Cowork em 2026-09-29, descido ao espelho pelo #8194 — recibo RETROATIVO, escrito por uma sessão que não fez o trabalho"
---

# _saida-28 · Rota própria do Ponto

Recibo retroativo, escrito em 2026-10-07. Quem executou foi o [CC] no build do Cowork; o trabalho
chegou ao `main` só pelo import do handoff. Nunca existiu um `_saida-28.md` para restaurar:
`git log --all -- '*_saida-28*'` sai vazio, em clone completo (`git rev-parse --is-shallow-repository` = `false`).

## 1 · Feito

### PR que trouxe o trabalho

| PR | estado | base | merge (UTC) | conteúdo |
|---|---|---|---|---|
| #8194 | MERGED | main | 2026-09-29 19:59 | `chore(design-sync): importa handoff (43) do Cowork` — o corpo diz *"Traz: Ponto thread 28 (rotas 1:1 com routes.php, drawer removido)"*; mexe `ponto-page.jsx` (+38) e `ponto-telas.jsx` (+208) e re-ancora os `.map.json` das faixas que a thread reescreveu |

Estado pedido com `gh pr view 8194 --json state,mergedAt,baseRefName`. Não houve PR de código: a
thread é `[CC]` e o próprio índice diz *"Sem mudança no main"* fora do espelho.

### O padrão é do #8194, não anterior a ele

O `daRota` já existia desde o #7272 (2026-09-14), mas só reconhecia `espelho-<id>`. As rotas da
thread nasceram no #8194:

| conferência | resultado |
|---|---|
| `git log -S'"pt-intercorrencias"' -- ponto-page.jsx` | só `107c7151dc` (#8194) |
| `git log -S'pt29rotareal'` e `-S'pt32rotareal'` em `oimpresso.com.html` | só `107c7151dc` (#8194) |
| linhas com `intercorrencias-` ou `importacoes\|escalas` no `ponto-page.jsx` | 0 no pai do #8194 · 2 no #8194 |
| ocorrências de `Drawer` no `ponto-telas.jsx` | 5 no pai do #8194 · 0 hoje |

A data do índice (29/09) bate com o merge. O único commit posterior nos dois arquivos é o #8806
(handoff 48), que mexe 2 linhas do `ponto-telas.jsx` e mantém as rotas.

### As 10 rotas, conferidas contra o router de hoje

Rota do protótipo = `pt-` + caminho de `Modules/Ponto/Http/routes.php` com `/` → `-`.

| rota do protótipo | rota real (`routes.php`, lido em `7e4a6ebfda`) |
|---|---|
| `pt-intercorrencias-<uuid>` · `-create` · `-<uuid>-edit` | `Route::resource('/intercorrencias')` (linha 57) |
| `pt-importacoes-<id>` · `-novo` | `/importacoes/{id}` (104) · `/importacoes/novo` (102) |
| `pt-escalas-create` · `-<id>-edit` | `Route::resource('/escalas')->except('show')` (86) |
| `pt-banco-horas-<colaborador>` | `/banco-horas/{colaborador}` (78) |
| `pt-colaboradores-<id>-editar` | `/colaboradores/{id}/editar` (124) |
| `pt-configuracoes-reps` | `/configuracoes/reps` (129) |

No `ponto-telas.jsx` de hoje cada tela lê o `sub` que o `daRota` entrega: Escalas (`create` ·
`<id>-edit`, linha 470), Colaboradores (`<id>-editar`, 597), Importações (`novo` · `<id>`, 751-753),
Configurações (`reps`, 984), Intercorrências (`foco === "create"` · `editar`, 217-219).

## 2 · Não medido

- **O render.** A `nota_provas` do índice diz que a prova é `__go('pt-intercorrencias-<id>')` abrir a
  página e não o drawer. Não abri o protótipo no navegador. O que está acima é leitura do código e
  da história, não render.
- **Os 4 sufixos corrigidos no dia.** O índice diz que `novo/nova/-editar/-config` da tabela da
  thread foram trocados para bater com o router. Conferi o resultado (a tabela acima), não a troca.

## 3 · Prefixo tocado por este recibo

Só este arquivo.
