---
casos: Ponto/Publico/Privacidade — política de privacidade do app de ponto
irmaos: Privacidade.charter.md (lei) · Privacidade.tsx (código) · Modules/Ponto/Tests/Feature/PrivacidadePublicaContratoTest.php (defesa)
tecnica: Caso de uso = o que a loja e o colaborador precisam ver + critério de aceite verificável (Dado/Quando/Então)
por_que: a loja reprova o app sem URL de privacidade estável, e o texto não pode prometer o que a lei proíbe
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Ponto/Publico/Privacidade (`/privacidade/ponto`)

> **Fonte dos UC:** [charter](Privacidade.charter.md) + [ADR 0383](../../../../../memory/decisions/0383-ponto-interno-nao-coleta-biometria.md)
> + inventário de `MobileMarcacaoService` + Portaria MTP 671/2021 (append-only). **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `Modules/Ponto/Tests/Feature/PrivacidadePublicaContratoTest.php`, lane `ponto-pest`.

## Rastreabilidade

| UC | Caso de uso | Prio | Status |
|----|-------------|------|--------|
| UC-PRIV-01 | Abre sem login, URL estável | must | 🧪 |
| UC-PRIV-02 | Declara que não coleta biometria nem imagem | must `[T0]` | 🧪 |
| UC-PRIV-03 | Não promete apagar marcação (append-only por lei) | must | 🧪 |
| UC-PRIV-04 | Não grava nada em GET | must | 🧪 |

## UC-PRIV-01 · Abre sem login, URL estável · `must`
- **Persona:** revisor da loja de aplicativo, sem conta no oimpresso.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/privacidade/ponto` · Então recebe 200 e a tela `Ponto/Publico/Privacidade`, sem ir ao login.
- **Status:** 🧪

## UC-PRIV-02 · Declara que não coleta biometria nem imagem · `must` `[T0]`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que o app não coleta biometria nem imagem (ADR 0383).
- **Status:** 🧪

## UC-PRIV-03 · Não promete apagar marcação · `must`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que marcações não podem ser apagadas nem alteradas, e não promete exclusão delas.
- **Status:** 🧪

## UC-PRIV-04 · Não grava nada em GET · `must`
- **Aceite:** Dado um visitante · Quando abre a página · Então nenhuma tabela `ponto_*` muda de contagem.
- **Status:** 🧪
