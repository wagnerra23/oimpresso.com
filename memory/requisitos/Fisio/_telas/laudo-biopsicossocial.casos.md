---
id: requisitos-fisio-telas-laudo-biopsicossocial-casos
casos: Laudo de avaliação biopsicossocial para fibromialgia — modelo SES-SC (Deliberação 500/CIB/2026) · fisio_laudos_bps + fisio_laudos_bps_equipe
irmaos: ../SPEC.md (US-FISIO-060) · ../protocolos/11-fibromialgia.md · ../../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md (UC-FLD-01..03, o laudo de evolução, outro documento)
tecnica: Caso de uso = narrativa do profissional + critério de aceite verificável (Dado/Quando/Então)
por_que: o laudo vai para a Atenção Primária e a FCEE decidirem a equiparação à pessoa com deficiência. Pontuação errada, laudo alterado depois de assinado ou conclusão preenchida pelo software tiram o valor do documento.
owner: wagner
last_run: "2026-11-11"
last_run_ci: "0 UC executado em MySQL nesta sessão (ADR 0358: MySQL só no CT 100/CI). Veredito: lane PHP / Pest (Verticais · MySQL)."
related_us: [US-FISIO-060]
---

# Casos de Uso & Aceite — Laudo biopsicossocial (US-FISIO-060)

> **Casa do contrato** (`memory/requisitos/<Mod>/_telas/`): o laudo nasce na ficha do paciente,
> mas é documento próprio, com tabelas próprias. O `Show.casos.md` segue dono da ficha.
>
> **Âncora:** Lei 15.176/2025 (art. 1º-C da Lei 14.705/2023) · em SC, o modelo aprovado pela
> Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026), versão 1.0 de
> 26/10/2026: identificação, matriz IFBr-M/CIF com 8 domínios e 30 itens, fatores contextuais,
> conclusão da equipe e equipe multiprofissional (mínimo de dois profissionais de áreas distintas,
> um deles médico) · ADI 5033647-81.2025.8.24.0000 (sem reconhecimento automático pelo CID) ·
> ADR 0437 §5 (o software não conclui).
>
> **Fluxo oficial:** em SC o laudo é emitido pela Atenção Primária do município (art. 2º da
> deliberação) e a carteira segue o fluxo da FCEE (art. 3º). O Fisio gera o laudo no layout oficial
> com o que a clínica avaliou; ele não emite a carteira.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Verticais · MySQL)` — **ADVISORY** (não está em
> `governance/required-checks-baseline.json`).
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-FBP-01 | matriz com os 8 domínios e 30 itens do modelo; total = soma do menor valor de cada domínio | must | US-FISIO-060 | `MatrizIfbrmTest` |
| UC-FBP-02 | laudo finalizado e sua equipe não mudam; correção é laudo novo | must | US-FISIO-060 | `FisioLaudoBpsEsquemaTest` |
| UC-FBP-03 | a empresa 99 não vê o laudo da 98 | must | US-FISIO-060 | `FisioLaudoBpsEsquemaTest` |

As próximas partes da US entram como `[BACKLOG]` até ganharem teste:

- [BACKLOG] preencher o formulário por rota, com `fisio.editar`; outra empresa 404; paciente bloqueado não recebe laudo novo.
- [BACKLOG] incluir e tirar profissional da equipe; o fisioterapeuta da clínica assina com o CREFITO do perfil; profissional de fora entra com nome, área e registro.
- [BACKLOG] finalizar só com os 30 itens pontuados, as três perguntas da conclusão respondidas pela equipe, local e data, e equipe com dois profissionais de áreas distintas, um deles médico.
- [BACKLOG] sugestão de apoio ao texto com dor, crises e adesão, cada número com período e fonte; sem dado suficiente, aviso.
- [BACKLOG] PDF no layout oficial; antes de finalizar sai marcado como documento de apoio; cada geração grava uma linha no `activity_log` só com ids.
- [BACKLOG] o software nunca marca a conclusão nem escreve que o paciente é pessoa com deficiência.

---

## UC-FBP-01 · Matriz IFBr-M e pontuação `[must]`
- **Persona:** fisioterapeuta pontuando a matriz.
- **Aceite:** Dada a matriz do modelo (8 domínios, 30 itens, valores 100/75/50/25) · Quando os 30
  itens estão pontuados · Então o total é a soma do menor valor de cada domínio: todos 100 dá 800,
  todos 25 dá 200, quatro domínios em 25 e quatro em 100 dá 500. Faltando item, não há total e o
  sistema aponta quais faltam. Item fora do modelo ou valor fora da escala é recusado.
- **Âncora:** texto do formulário: "somatória da linha de menor valor dentro de cada um dos itens de 1 a 8".
- **Regressão que defende:** total calculado por média ou soma dos itens, que muda o resultado da perícia.
- **Teste:** `Modules/Fisio/Tests/Unit/MatrizIfbrmTest.php`
- **Status: 🧪**

## UC-FBP-02 · Laudo finalizado não muda `[must]`
- **Aceite:** Dado um laudo em rascunho · Então o formulário e a equipe aceitam alteração. Depois de
  finalizado (`finalizado_em`), UPDATE e DELETE no laudo e INSERT, UPDATE e DELETE na equipe dele
  são recusados pelo banco (trigger), e o model lança `DadoClinicoImutavelException`. Correção é
  laudo novo com `amends_id` apontando o anterior.
- **Regressão que defende:** laudo alterado depois de entregue à Atenção Primária.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEsquemaTest.php`
- **Status: 🧪**

## UC-FBP-03 · Isolamento entre clínicas `[must]`
- **Aceite:** Dado um laudo da empresa 98 · Quando um usuário da 99 consulta laudos e equipe ·
  Então não acha nada; o usuário da 98 acha.
- **Regressão que defende:** dado de saúde de uma clínica aparecendo para outra (ADR 0093).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEsquemaTest.php`
- **Status: 🧪**
