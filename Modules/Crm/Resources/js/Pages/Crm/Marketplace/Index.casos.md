---
id: modules-crm-pages-crm-marketplace-index-casos
casos: Crm · Marketplace B2B · /crm/b2b-marketplace
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela guarda a credencial de uma integração externa e cria leads atribuídos a usuários. Errar o escopo mostra a credencial de outra empresa ou atribui lead a usuário de outra empresa.
owner: felipe
last_run: "2026-11-27"
last_run_ci: "_pendente_ — o trio nasce na Onda 1 do RUNBOOK-marketplace. O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Marketplace B2B (`/crm/b2b-marketplace`)

> **Âncora:** o [charter](Index.charter.md) e o RUNBOOK-marketplace (§10 paridade, §11 endurecimento). Os UCs não
> derivam do `.tsx`.

---

## UC-CRMMKT-01 · Admin abre a tela React; ?classico=1 abre a Blade · `must`

**Dado** a integração ligada e a flag `useV2CrmMarketplace` ligada
**Quando** um Admin do negócio abre `/crm/b2b-marketplace`
**Então** recebe Inertia `Crm/Marketplace/Index`; com `?classico=1`, a view `crm::marketplace.index`.

Status: 🧪

---

## UC-CRMMKT-02 · Quem não é Admin recebe 403 · `must`

**Dado** um usuário do negócio sem o papel de Admin
**Quando** abre a tela
**Então** recebe 403.

Status: 🧪

---

## UC-CRMMKT-03 · A configuração exibida é a da minha empresa · `must`

**Dado** uma configuração salva na minha empresa e outra numa empresa vizinha
**Quando** abro a tela
**Então** a prop `config.chave` é a minha.

Status: 🧪

---

## UC-CRMMKT-04 · Salvar recusa usuário ou fonte de outra empresa · `must`

**Dado** um usuário e uma fonte que pertencem a uma empresa vizinha
**Quando** salvo a configuração escolhendo esse usuário ou essa fonte
**Então** recebo erro de validação no campo e nada é gravado.

Status: 🧪

---

## UC-CRMMKT-05 · Salvar com dados da minha empresa grava e avisa · `must`

**Dado** um usuário e uma fonte da minha empresa
**Quando** salvo chave, e-mail, o usuário e a fonte
**Então** a configuração fica gravada no meu negócio e volto com o aviso de sucesso.

Status: 🧪
