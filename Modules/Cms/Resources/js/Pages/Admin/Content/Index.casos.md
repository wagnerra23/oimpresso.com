---
id: modules-cms-pages-admin-content-index-casos
casos: Cms · Conteúdo do site · /cms/cms-page
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela que diz o que está no ar no site público. Página de sistema (layout home/contact) não pode ser excluída, e a ordem da lista é a ordem do site — errar qualquer uma quebra o site sem erro nenhum na tela.
owner: wagner
last_run: "2026-09-23"
last_run_ci: "_pendente_ — o trio nasce na thread Cms/01. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Cms · Conteúdo do site (`/cms/cms-page`)

> **Âncora:** UC-CMS-01/02/03 e regras R3/R6/A1 do F1 do Cowork
> (`prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md` §2/§3). UC-CMS-20/21 são
> desta fase (a lista) e derivam das mesmas regras. Os demais 15 UC do F1 (editor, exclusão no
> servidor, lote, demo, formulário público) entram com as fases 2–4 do RUNBOOK — não estão
> declarados aqui porque ainda não têm teste que os cite.

---

## UC-CMS-01 · A lista responde Inertia e segue a ordem do site · `must`

**Dado** que sou superadmin e há páginas com `priority` 1, 2 e vazio
**Quando** abro `/cms/cms-page?type=page`
**Então** recebo Inertia `Admin/Content/Index` e as linhas vêm 1, 2 e por último a sem ordem (R6).

Status: 🧪

---

## UC-CMS-02 · Quem não é superadmin é barrado · `must` `[T0]`

**Dado** um usuário comum de um negócio
**Quando** ele abre `/cms/cms-page`
**Então** recebe 403 — e o mesmo teste prova que o superadmin recebe 200.

Status: 🧪

---

## UC-CMS-03 · Visitante sem sessão vai para o login · `must`

**Dado** que não estou logado
**Quando** abro `/cms/cms-page`
**Então** sou redirecionado, sem nenhuma prop de conteúdo.

Status: 🧪

---

## UC-CMS-20 · A linha diz situação, sistema e descrição sem enum cru · `must`

**Dado** uma página de sistema (`layout=home`) sem descrição de busca e uma página livre em rascunho
**Quando** a lista carrega
**Então** a de sistema vem marcada como sistema e sem descrição, a livre como rascunho com o
endereço público `/c/page/<título-com-hífen>` (R3, R9, A1).

Status: 🧪

---

## UC-CMS-21 · Cada aba lista só o seu tipo · `must`

**Dado** uma publicação de blog
**Quando** abro a aba Blog e a aba Páginas
**Então** ela aparece só em Blog, com endereço `/c/blog/<slug>-<id>`; e um `?type=` fora do
domínio abre Páginas em vez de mostrar enum cru.

Status: 🧪
