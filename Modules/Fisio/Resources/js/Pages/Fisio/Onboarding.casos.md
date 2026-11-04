---
id: modules-fisio-pages-fisio-onboarding-casos
casos: Fisio · Primeira configuração · /fisio/onboarding
irmaos: Onboarding.charter.md (lei) · Onboarding.tsx (tela)
tecnica: Caso de uso = narrativa da clínica + critério de aceite verificável (Dado/Quando/Então)
por_que: o onboarding grava o canal do WhatsApp e o perfil que vai em todo programa. Canal QR sem aceite de risco, CREFITO de um profissional aparecendo como de outro, ou configuração de uma clínica lida por outra são os erros que estes casos barram.
owner: wagner
last_run: "2026-11-04"
last_run_ci: "_pendente_ — FisioOnboardingTest entra na lane Verticais · MySQL neste PR; o veredito é da lane."
related_us: [US-FISIO-050]
---

# Casos de Uso & Aceite — Fisio · Primeira configuração (`/fisio/onboarding`)

> **Âncora:** SPEC US-FISIO-050 + protótipo `Onboarding.dc.html` + regra de canal da US-FISIO-009. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (veredito no manifesto) · 🧪 teste cita o UC, sem veredito coletado · ⬜ não verificado · ❌ quebrou. **O veredito é da lane, nunca desta leitura** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FOB-01 | tela abre com os protocolos da clínica e os modelos | should | `FisioOnboardingTest` |
| UC-FOB-02 | perfil é do profissional; PF/PJ é da clínica | must | `FisioOnboardingTest` |
| UC-FOB-03 | QR só com aceite de risco | must | `FisioOnboardingTest` |
| UC-FOB-04 | protocolo de outra empresa não entra | must | `FisioOnboardingTest` |
| UC-FOB-05 | concluir grava; só `fisio.editar` grava | must | `FisioOnboardingTest` |
| UC-FOB-06 | uma clínica não lê a configuração da outra | must | `FisioOnboardingTest` |

---

## UC-FOB-01 · Os protocolos certos · `should`
- **Aceite:** Dado um protocolo da 98, um da 99 e os modelos do produto · Quando a 98 abre o onboarding · Então vê os dela e os modelos, nunca os da 99.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`
- **Status: 🧪**

## UC-FOB-02 · Perfil de quem atende · `must`
- **Aceite:** Quando o profissional grava nome e CREFITO · Então ficam ligados a ele (outro profissional da clínica não herda). PF/PJ fica na clínica; valor fora de `pf`/`pj` é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`
- **Status: 🧪**

## UC-FOB-03 · QR só com o risco aceito `[T0]` · `must`
- **Aceite:** Quando a clínica escolhe QR sem marcar o aviso · Então nada é gravado e a tela pede o aceite. Marcando, grava o canal e o aceite com o usuário.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`
- **Status: 🧪**

## UC-FOB-04 · Protocolo alheio não entra `[T0]` · `must`
- **Aceite:** Dado um id de protocolo da 99 no corpo · Quando a 98 grava os protocolos · Então só ficam os modelos e os da 98.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`
- **Status: 🧪**

## UC-FOB-05 · Concluir e quem pode · `must`
- **Aceite:** Sem `fisio.editar` gravar responde 403 e nada muda; passo fora da lista é 404; concluir grava a data.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`
- **Status: 🧪**

## UC-FOB-06 · Cada clínica com a sua `[T0]` · `must`
- **Aceite:** Dado que a 98 gravou PJ e concluiu · Quando a 99 abre o onboarding · Então vê tudo vazio.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`
- **Status: 🧪**
