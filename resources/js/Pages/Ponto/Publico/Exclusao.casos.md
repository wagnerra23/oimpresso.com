---
casos: Ponto/Publico/Exclusao — pedido de exclusão de conta e dados do app de ponto
irmaos: Exclusao.charter.md (lei) · Exclusao.tsx (código) · Modules/Ponto/Tests/Feature/ExclusaoDadosPublicaContratoTest.php (defesa)
tecnica: Caso de uso = o que a loja e o colaborador precisam ver + critério de aceite verificável (Dado/Quando/Então)
por_que: o Google Play exige a URL, e prometer apagar marcação de ponto seria prometer o que a lei proíbe
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Ponto/Publico/Exclusao (`/privacidade/ponto/exclusao`)

> **Fonte dos UC:** [charter](Exclusao.charter.md) + Portaria MTP 671/2021 (append-only) +
> política do Google Play para exclusão de conta. **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Ponto/Tests/Feature/ExclusaoDadosPublicaContratoTest.php`, lane `ponto-pest`.

## Rastreabilidade

| UC | Caso de uso | Prio | Status |
|----|-------------|------|--------|
| UC-EXCL-01 | Abre sem login, URL estável | must | 🧪 |
| UC-EXCL-02 | Diz que a conta é gerida pelo empregador e oferece canal | must | 🧪 |
| UC-EXCL-03 | Explica retenção legal e não promete apagar marcação | must `[T0]` | 🧪 |
| UC-EXCL-04 | Não grava nada em GET | must | 🧪 |

## UC-EXCL-01 · Abre sem login · `must`
- **Aceite:** Dado um visitante sem sessão · Quando abre `/privacidade/ponto/exclusao` · Então recebe 200 e a tela `Ponto/Publico/Exclusao`.
- **Status:** 🧪

## UC-EXCL-02 · Conta do empregador + canal · `must`
- **Aceite:** Dado a página · Quando a leio · Então ela diz que a conta é gerida pelo empregador e traz um e-mail de contato.
- **Status:** 🧪

## UC-EXCL-03 · Retenção legal, sem promessa de apagar · `must` `[T0]`
- **Aceite:** Dado a página · Quando a leio · Então ela diz que marcações não podem ser apagadas nem alteradas e não promete descarte.
- **Status:** 🧪

## UC-EXCL-04 · Não grava nada em GET · `must`
- **Aceite:** Dado um visitante · Quando abre a página · Então nenhuma tabela `ponto_*` muda de contagem.
- **Status:** 🧪
