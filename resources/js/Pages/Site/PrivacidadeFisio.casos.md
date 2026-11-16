---
casos: Site/PrivacidadeFisio — política do Portal do Paciente do Fisio e do app Uniafibro
irmaos: PrivacidadeFisio.charter.md (lei) · PrivacidadeFisio.tsx (código) · tests/Feature/Site/PrivacidadeFisioContratoTest.php (defesa)
tecnica: Caso de uso = o que a loja e o paciente precisam ver + critério de aceite verificável (Dado/Quando/Então)
por_que: o Google Play reprova o app sem URL de privacidade que cubra dado de saúde, e o texto não pode prometer o que a lei proíbe
owner: wagner
last_run: "2026-11-13"
---

# Casos de Uso & Aceite — Site/PrivacidadeFisio (`/fisio/privacidade`)

> **Fonte dos UC:** [charter](PrivacidadeFisio.charter.md) + [RUNBOOK](../../../../memory/requisitos/Site/RUNBOOK-privacidade.md) §5
> + inventário de `Modules/Fisio` (rotas do portal, `Consentimentos`, `TranscricaoDocumentos`, `SemExclusaoDeDadoClinico`). **Não** o `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente da lane · ⬜ não verificado · ❌ quebrou.
> Teste: `tests/Feature/Site/PrivacidadeFisioContratoTest.php`, lane sqlite per-PR.

## Rastreabilidade

| UC | Caso de uso | Prio | Status |
|----|-------------|------|--------|
| UC-PRVFIS-01 | Abre sem login, URL estável | must | 🧪 |
| UC-PRVFIS-02 | Nomeia a clínica como controladora e o oimpresso como operador | must | 🧪 |
| UC-PRVFIS-03 | Declara a guarda de 20 anos e não promete apagar dado clínico | must | 🧪 |
| UC-PRVFIS-04 | Declara a IA de transcrição como opcional e conferida pelo profissional | must | 🧪 |
| UC-PRVFIS-05 | Declara que o app não faz teleconsulta por vídeo | must | 🧪 |

## UC-PRVFIS-01 · Abre sem login, URL estável · `must`
- **Persona:** revisor do Google Play, sem conta no oimpresso.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/fisio/privacidade` · Então recebe 200 com a tela `Site/PrivacidadeFisio`, sem ir ao login.
- **Status:** 🧪

## UC-PRVFIS-02 · Controladora e operador · `must`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que a clínica é a controladora e o oimpresso o operador.
- **Status:** 🧪

## UC-PRVFIS-03 · Guarda de 20 anos · `must`
- **Fonte:** Lei 13.787/2018; `Modules/Fisio/Entities/Concerns/SemExclusaoDeDadoClinico.php`.
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que o prontuário é guardado por no mínimo 20 anos e que dado clínico não pode ser apagado antes disso, e não promete excluir o prontuário a pedido.
- **Status:** 🧪

## UC-PRVFIS-04 · IA de transcrição · `must`
- **Fonte:** `Modules/Fisio/Services/TranscricaoDocumentos.php` (desligada até a empresa ligar; provedores anthropic/openai; profissional confere).
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que a transcrição vem desligada, nomeia OpenAI e Anthropic e diz que o profissional confere antes do prontuário.
- **Status:** 🧪

## UC-PRVFIS-05 · Sem teleconsulta por vídeo · `must`
- **Aceite:** Dado a política publicada · Quando a leio · Então ela diz que o app não faz teleconsulta por vídeo.
- **Status:** 🧪
