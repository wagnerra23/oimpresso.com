---
casos: Site/PrivacidadeApp — política de privacidade do app oimpresso (ERP + ponto)
irmaos: PrivacidadeApp.charter.md (lei) · PrivacidadeApp.tsx (código) · tests/Feature/Site/PrivacidadeAppContratoTest.php (defesa)
tecnica: Caso de uso = o que a loja e quem usa o app precisam ver + critério de aceite verificável (Dado/Quando/Então)
por_que: a loja reprova o app sem URL de privacidade estável, e o texto não pode prometer o que a lei proíbe
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Site/PrivacidadeApp (`/privacidade`)

> **Fonte dos UC:** [charter](PrivacidadeApp.charter.md) + [RUNBOOK](../../../../memory/requisitos/Site/RUNBOOK-privacidade.md)
> + inventário de `docs/lojas-app/textos/privacidade-lojas.md` + ADR 0383 + Portaria MTP 671/2021. **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `tests/Feature/Site/PrivacidadeAppContratoTest.php`, lane sqlite per-PR.

## Rastreabilidade

| UC | Caso de uso | Prio | Status |
|----|-------------|------|--------|
| UC-PRVAPP-01 | Abre sem login, URL estável | must | 🧪 |
| UC-PRVAPP-02 | Cobre o ERP além do ponto e aponta para a política do ponto | must | 🧪 |
| UC-PRVAPP-03 | Não promete apagar marcação de ponto | must | 🧪 |
| UC-PRVAPP-04 | Declara que não usa localização em segundo plano | must | 🧪 |

## UC-PRVAPP-01 · Abre sem login, URL estável · `must`
- **Persona:** revisor da loja de aplicativo, sem conta no oimpresso.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/privacidade` · Então recebe 200 com a tela `Site/PrivacidadeApp`, sem ir ao login.
- **Status:** 🧪

## UC-PRVAPP-02 · Cobre o ERP e aponta para o ponto · `must`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela trata dos dados de clientes e fornecedores cadastrados e linka `/privacidade/ponto`.
- **Status:** 🧪

## UC-PRVAPP-03 · Não promete apagar marcação · `must`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que marcações de ponto não podem ser apagadas nem alteradas.
- **Status:** 🧪

## UC-PRVAPP-04 · Sem localização em segundo plano · `must`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que o app não usa a localização em segundo plano (o Data Safety declara o mesmo).
- **Status:** 🧪
