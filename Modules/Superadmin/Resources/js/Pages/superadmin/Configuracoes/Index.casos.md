---
id: modules-superadmin-pages-superadmin-configuracoes-index-casos
casos: Superadmin · Configurações · /superadmin/settings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela que guarda as senhas do SMTP e dos gateways da plataforma inteira. Segredo vazando nas props sai no HTML de qualquer superadmin; segredo vazio tratado como "apagar" derruba o e-mail e a cobrança de todos os negócios num clique de Salvar.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Superadmin · Configurações (`/superadmin/settings`)

> **Âncora:** US-SUPER-008 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md)
> (editar settings globais: SMTP, gateways, cron, backup) + o charter ao lado. Os UCs derivam
> disso, nunca do `Index.tsx`.

## UC-SACFG-01 · A tela responde em Inertia, não em Blade · `must`

**Dado** que sou superadmin **Quando** abro `/superadmin/settings` **Então** recebo Inertia com o
componente `superadmin/Configuracoes/Index`, não a view `superadmin::superadmin_settings.edit`.

Status: 🧪

## UC-SACFG-02 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um usuário comum **Quando** acessa a tela **Então** é barrado, e o mesmo teste prova que
o superadmin recebe 200.

Status: 🧪

## UC-SACFG-03 · Segredo nunca sai nas props, só o "definido" · `must` `[T0]`

**Dado** uma senha SMTP gravada **Quando** o superadmin abre a tela **Então** o payload não contém
o valor em lugar nenhum, `segredos.MAIL_PASSWORD` é `true` e `valores` não tem a chave.

Status: 🧪

## UC-SACFG-04 · Segredo em branco mantém o valor gravado · `must` `[T0]`

**Dado** uma senha SMTP gravada **Quando** salvo com o campo de senha vazio e outro campo alterado
**Então** o outro campo é regravado e a senha continua a mesma.

Status: 🧪

## UC-SACFG-05 · Segredo preenchido é regravado · `must`

**Dado** uma senha SMTP gravada **Quando** salvo com uma senha nova **Então** a senha nova é a gravada.

Status: 🧪

## UC-SACFG-06 · Quebra de linha num valor não abre linha nova no `.env` · `must` `[T0]`

**Dado** um valor com quebra de linha seguida de `CHAVE=valor` **Quando** salvo **Então** nenhuma
linha nova com essa chave aparece no arquivo.

Status: 🧪
