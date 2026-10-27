---
id: modules-superadmin-pages-superadmin-pagar-index-casos
casos: Superadmin · Pagar assinatura · /subscription/{id}/pay
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do dono do negócio + critério de aceite verificável (Dado/Quando/Então)
por_que: é onde o cliente paga. Preço diferente do mostrado em Minha assinatura confunde a cobrança, e um gateway que saiu da tela não pode continuar cobrando por POST direto.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Superadmin · Pagar assinatura

> US-SUPER-003 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md) (assinaturas) ·
> decisão [W] 2026-10-27 (só Pix automático e pagamento offline; antes→depois de valor aprovado) ·
> ADR 0093 (multi-tenant Tier 0).
>
> UCs derivados do SPEC, da decisão do [W] e do **charter**; o `SubscriptionController` só confirma.
> **Não derivados do `.tsx`.**
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Onde roda:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php`, lane
> `verticais-pest.yml` (MySQL), tenant 98 com o 99 de adversário. Em SQLite o teste pula — skip
> não é verde.

---

## UC-SAPGR-01 · Pagar e register-pay abrem a tela nova
- **Persona:** dono do negócio clicando em "Pagar e assinar" num pacote pago, ou empresa nova vinda do cadastro.
- **Aceite:** Dado um usuário com `superadmin.access_package_subscriptions` e um pacote pago · Quando abre `/subscription/{id}/pay` ou `/subscription/{id}/register-pay` · Então recebe Inertia `superadmin/Pagar/Index`.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php` — `UC-SAPGR-01 · pagar e register-pay respondem Inertia com a tela nova`.
- **Regressão que defende:** MWART F5 (a Blade `subscription.pay` saiu); o cadastro de empresa nova segue chegando por `register-pay`.
- **Status: ⬜** — teste novo; espera o primeiro run da lane.

---

## UC-SAPGR-02 · Sem a permissão é barrado ENQUANTO quem tem passa · `[T0]`
- **Persona:** usuário de um negócio sem `superadmin.access_package_subscriptions`.
- **Aceite:** Dado um usuário sem a permissão · Quando abre a tela · Então recebe 403; Dado quem tem a permissão · Então recebe 200 — no mesmo teste.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php` — `UC-SAPGR-02 · sem a permissão é barrado enquanto quem tem passa`.
- **Regressão que defende:** charter Mission (tela do dono do negócio, não de qualquer usuário).
- **Status: ⬜** — teste novo; espera o primeiro run da lane.

---

## UC-SAPGR-03 · Só Pix automático e offline, com o preço no texto da Blade
- **Persona:** dono do negócio conferindo quanto vai pagar.
- **Aceite:** Dado o offline ligado e um pacote com preço fracionado · Quando a tela carrega · Então as formas são só as de `GATEWAYS_DA_TELA`, o offline traz os dados cadastrados, e o preço é o mesmo texto que a Blade mostrava (moeda do sistema, precisão e posição do negócio), conferido contra um texto montado à mão.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php` — `UC-SAPGR-03 · só Pix automático e offline aparecem, e o preço é o texto da Blade`.
- **Regressão que defende:** decisão [W] 2026-10-27 e a regra mestre de valor (o preço mostrado não pode mudar na migração).
- **Status: ⬜** — teste novo; espera o primeiro run da lane.

---

## UC-SAPGR-04 · Gateway que saiu não cobra nem cria assinatura · `[T0]`
- **Persona:** formulário antigo ou POST direto com `gateway=stripe`.
- **Aceite:** Dado um POST em `confirm()` com `gateway=stripe` · Então volta para `/subscription` com erro e nenhuma assinatura é criada.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php` — `UC-SAPGR-04 · confirmar por um gateway que saiu não cria assinatura`.
- **Regressão que defende:** charter Anti-hook "não religar outro gateway nem tirar a recusa em `confirm()`".
- **Status: ⬜** — teste novo; espera o primeiro run da lane.

---

## UC-SAPGR-05 · Offline cria a assinatura aguardando, com o preço do pacote
- **Persona:** dono do negócio que paga por transferência e avisa pela tela.
- **Aceite:** Dado o offline ligado · Quando confirmo offline · Então existe uma assinatura `waiting` do meu negócio para o pacote, com `package_price` igual ao preço do pacote.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php` — `UC-SAPGR-05 · confirmar offline cria a assinatura aguardando, com o preço do pacote`.
- **Regressão que defende:** regra mestre de valor (o valor gravado é o do pacote, lido no servidor).
- **Status: ⬜** — teste novo; espera o primeiro run da lane.

---

## UC-SAPGR-06 · A assinatura gravada é só do meu negócio; o 99 fica intacto · `[T0]`
- **Persona:** dono do negócio 98 confirmando o pagamento offline.
- **Aceite:** Dado o negócio 99 existindo · Quando o 98 confirma offline · Então a assinatura nova é do 98 e a contagem de assinaturas do 99 não muda.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php` — `UC-SAPGR-06 · confirmar offline grava a assinatura só do meu negócio; o 99 fica intacto`.
- **Regressão que defende:** ADR 0093 (o negócio vem da sessão, nunca do formulário).
- **Status: ⬜** — teste novo; espera o primeiro run da lane.
