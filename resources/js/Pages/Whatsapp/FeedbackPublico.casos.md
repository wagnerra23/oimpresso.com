---
casos: Canal público de sinal do cliente · /feedback
irmaos: FeedbackPublico.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: numa rota SEM auth o global scope é no-op — o isolamento do tenant depende do HMAC, e isso é comportamento durável que nenhum refactor pode perder.
owner: wagner
last_run: "2026-07-17"
---

# Casos de Uso & Aceite — Canal público de sinal do cliente

> US-INFRA-002 · [ADR 0334](../../../../memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md) (órgão sensor).
> UCs ancorados no `FeedbackPublicoSignedUrlTest` (Pest, rodado no CT 100).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Por que os UCs desta tela são quase todos Tier 0:** a rota não tem auth, então
> `ScopeByBusiness` é NO-OP (retorna cedo em `!auth()->check()`). Nada no Eloquent isola o
> tenant aqui — quem isola é o HMAC da URL assinada. Estes casos são o que impede o canal
> público de virar vazamento cross-tenant.

---

## UC-FBP-01 · Adulterar o business no link não vaza pro vizinho
- **Persona:** atacante (ou curioso) com um link legítimo de um business — troca `?biz=1` por `?biz=99` na barra de endereço.
- **Aceite:** Dado um link de feedback assinado para `biz=1` · Quando troco o parâmetro para `biz=99` · Então volta **403** e nada é lido ou gravado no business 99.
- **Teste:** `Modules/Whatsapp/Tests/Feature/FeedbackPublicoSignedUrlTest.php` — `003 · TIER 0 — adulterar ?biz=1 → 99 quebra a assinatura (403)`.
- **Regressão que defende:** o `business_id` numa rota pública **não pode** vir do input. Sem o HMAC amarrando o tenant à URL, `?biz` seria um seletor de tenant aberto — e o global scope não protege (é no-op sem auth). Controle-negativo executado: removendo `->middleware('signed')`, 5 dos 6 casos falham.
- **Status: 🧪** — Pest passa no CT 100 (4 rodadas consecutivas); ✅ quando `casos:results` regravar o manifesto.

---

## UC-FBP-02 · Escrita no tenant do vizinho é barrada
- **Persona:** atacante — tenta gravar um sinal no business de outro cliente usando um link adulterado.
- **Aceite:** Dado um link assinado para `biz=1` adulterado para `biz=99` · Quando faço **POST** com um sinal válido · Então volta **403** e nenhuma linha é criada em `clients_feedbacks`.
- **Teste:** `Modules/Whatsapp/Tests/Feature/FeedbackPublicoSignedUrlTest.php` — `006 · TIER 0 — POST com ?biz adulterado → 403 (não grava no tenant do vizinho)`.
- **Regressão que defende:** o par do UC-FBP-01 no caminho de **escrita**. Cobrir só o GET deixaria o vetor que importa (gravar) aberto.
- **Status: 🧪** — Pest passa no CT 100; ✅ com o manifesto regravado.

---

## UC-FBP-03 · O link não é adivinhável
- **Persona:** qualquer um que descubra a URL `/feedback` — tenta abrir sem o link que recebeu.
- **Aceite:** Dado que acesso `/feedback?biz=1` **sem** assinatura · Quando faço GET **ou** POST · Então volta **403** nos dois.
- **Teste:** `Modules/Whatsapp/Tests/Feature/FeedbackPublicoSignedUrlTest.php` — `002 · sem assinatura → 403` + `005 · POST sem assinatura → 403`.
- **Regressão que defende:** sem isso, `/feedback?biz=N` viraria um formulário aberto pra qualquer business só variando o número — spam e ruído no inbox de triagem de todo tenant.
- **Status: 🧪** — Pest passa no CT 100; ✅ com o manifesto regravado.

---

## UC-FBP-04 · O link caduca de verdade
- **Persona:** [W] — mandou o link há mais de 30 dias e assume que ele não vale mais.
- **Aceite:** Dado um link cuja validade já passou · Quando abro · Então volta **403**.
- **Teste:** `Modules/Whatsapp/Tests/Feature/FeedbackPublicoSignedUrlTest.php` — `004 · assinatura expirada → 403 (validade de 30d é real)`.
- **Regressão que defende:** a ADR 0105 especifica validade de 30d. Um link eterno é uma credencial que nunca é revogada — e ninguém lembraria de conferir se a expiração é real.
- **Status: 🧪** — Pest passa no CT 100; ✅ com o manifesto regravado.

---

## UC-FBP-05 · O link legítimo abre
- **Persona:** Larissa — recebeu o link e clica.
- **Aceite:** Dado um link assinado válido · Quando abro · Então **não** volta 403 (a assinatura é aceita).
- **Teste:** `Modules/Whatsapp/Tests/Feature/FeedbackPublicoSignedUrlTest.php` — `001 · URL assinada válida é aceita pelo middleware signed`.
- **Regressão que defende:** o **release** do par bite/release — sem ele, "403 em tudo" (ex: derrubar a rota) passaria como se fosse segurança. É o caso que continua verde no controle-negativo, provando que os outros 5 falham por causa da assinatura e não por a rota ter sumido.
- **Status: 🧪** — Pest passa no CT 100; ✅ com o manifesto regravado.

---

## Backlog (prosa honesta — sem UC até ganhar teste que o cite)

Estes comportamentos existem no código mas **ainda não têm teste**, então não viram UC (a
regra G-2 pune UC órfão de teste, e UC sem prova é afirmação, não contrato):

- [BACKLOG] Sinal válido cai em `clients_feedbacks` com `canal=web_form` e o `business_id` do link.
- [BACKLOG] Sinal repetido em 90d bumpa `recorrente_count` em vez de duplicar; 3+ acende `pattern_emergente`.
- [BACKLOG] `severity_self_reported` semeia `severity_nng` e **nunca** é sobrescrito na triagem.
- [BACKLOG] O sinal do form aparece em `/atendimento/feedback` junto do canal `whatsapp`.
- [BACKLOG] `feedback:link` gera URL que o `signed` aceita ponta-a-ponta.

> Os 5 exigem DB com schema real (a lane sqlite deste arquivo é DB-less de propósito — ver
> cabeçalho do teste). O lar deles é uma lane MySQL do Whatsapp, que **não existe hoje**: o
> `modules-pest.yml` cobre 6 módulos e Whatsapp não é um deles.
