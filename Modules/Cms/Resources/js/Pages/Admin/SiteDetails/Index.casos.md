---
id: modules-cms-pages-admin-sitedetails-index-casos
casos: Cms · Detalhes do site · /cms/site-details
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela de onde o site público tira contatos, redes e códigos de medição. Salvar uma seção não pode apagar as outras — e até 2026-09-23 salvar nem funcionava, porque a validação recusava o formato que o próprio formulário mandava.
owner: wagner
last_run: "2026-09-25"
last_run_ci: "_pendente_ — o trio nasce na fase 4a da thread Cms/01. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Cms · Detalhes do site (`/cms/site-details`)

> **Âncora:** UC-CMSD-01/02/07 do [CC] (`prototipo-ui/cowork/Wagner/cowork-inbox/cms/SiteDetails.casos.md`)
> e a regra S1 do charter dele (uma linha por chave). UC-CMSD-09 é desta fase: o formato de envio.
> Os demais UC do [CC] (03 rede vazia, 04 e-mail dos leads, 05 logo no site, 06 rastro, 08 medição
> fora do painel) entram com a fase 4b ou dependem do site público.

---

## UC-CMSD-01 · Abrir os detalhes do site · `must`

**Dado** que sou superadmin
**Quando** abro `/cms/site-details`
**Então** recebo a tela nova; e a tela anterior continua em `?legado=1` enquanto a fase 4b não entra.

Status: 🧪

---

## UC-CMSD-02 · Salvar estas seções não apaga as outras · `must` `[T0]`

**Dado** perguntas frequentes já gravadas pela tela anterior
**Quando** salvo só o e-mail de aviso e o CSS nesta tela
**Então** as perguntas continuam lá, e o que eu mudei foi gravado (S1).

Status: 🧪

---

## UC-CMSD-07 · Usuário comum não entra · `must`

**Dado** um usuário comum de um negócio
**Quando** ele abre `/cms/site-details`
**Então** é barrado — e o mesmo teste prova que o superadmin entra.

Status: 🧪

---

## UC-CMSD-09 · O formato que o formulário manda é aceito · `must`

**Dado** telefones, e-mails, redes, estatísticas e perguntas no formato de lista que o formulário
sempre enviou
**Quando** salvo
**Então** nada é recusado pela validação e cada chave é gravada. (Até 2026-09-23 a validação exigia
texto nessas chaves e recusava o formulário inteiro.)

Status: 🧪

- **[BACKLOG] Navegação entre as telas do Cms** — as abas *Páginas · Blog · Depoimentos · Detalhes do site* (`Admin/_shared/CmsAbas.tsx`) são o único caminho até `/cms/site-details`: o item CMS mora na cascata Superadmin do rodapé, que não mostra sub-telas. É comportamento do cliente (link Inertia), sem teste que o cite — vira UC quando um E2E abrir a aba.
