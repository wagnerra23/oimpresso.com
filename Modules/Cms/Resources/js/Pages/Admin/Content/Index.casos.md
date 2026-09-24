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
> da fase 1 (a lista); UC-CMS-04/05/22/23 são da fase 2 (o editor), sobre R2/R5/R7/A2; UC-CMS-08/24/25 da fase 2b (destaques da home, R4). Os demais 15 UC do F1 (segmentos `industry`,
> exclusão no servidor, lote, demo, formulário público) entram com as fases 2b–4 do RUNBOOK — não estão
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

---

## UC-CMS-04 · Criar sem título não grava nada · `must`

**Dado** o drawer de criação aberto
**Quando** salvo sem título
**Então** o erro aparece no campo título e nenhuma linha é criada.

Status: 🧪

---

## UC-CMS-05 · Descrição vazia vem do conteúdo · `must`

**Dado** um conteúdo HTML longo e a descrição para buscadores vazia
**Quando** salvo
**Então** a descrição gravada são os 160 primeiros caracteres do conteúdo em texto puro — sem tag
e sem o corpo de `<script>` (R7).

Status: 🧪

---

## UC-CMS-22 · O drawer recebe a linha pedida, e só quando pede · `must`

**Dado** uma página de sistema em rascunho
**Quando** clico em Editar
**Então** o drawer recebe conteúdo, layout e situação dela — e a carga normal da lista não traz o editor.

Status: 🧪

---

## UC-CMS-23 · Editar sem mexer na descrição preserva a digitada · `must`

**Dado** uma página com descrição escrita à mão
**Quando** ela é salva por um caminho que não envia o campo (a tela anterior)
**Então** a descrição continua a mesma; se o campo vier vazio de propósito, é derivada do conteúdo.

Status: 🧪

---

## UC-CMS-08 · Só a página inicial tem destaques · `must`

**Dado** a página inicial e uma página livre
**Quando** abro cada uma no drawer
**Então** a inicial traz a seção de destaques (título, texto e itens) e a livre não (R4).

Status: 🧪

---

## UC-CMS-24 · Salvar os destaques muda a home pública · `must`

**Dado** a página inicial aberta no drawer
**Quando** mudo um destaque e salvo
**Então** o registro `feature` que a home de `/` lê é regravado — sem criar um segundo registro.

Status: 🧪

---

## UC-CMS-25 · A virada não troca o site nem apaga edição · `must` `[T0]`

**Dado** o registro de destaques ainda com o conteúdo de instalação em inglês
**Quando** a migration da fase 2b roda
**Então** ele passa a ser o texto que o site já mostrava; e se alguém já tinha editado, nada muda.

Status: 🧪
