---
id: requisitos-fisio-telas-laudo-biopsicossocial-casos
casos: Laudo biopsicossocial integrado — quatro seções da LBI, cada uma com autor e assinatura própria · fisio_laudos_bps + fisio_laudos_bps_secoes
irmaos: ../SPEC.md (US-FISIO-060) · ../protocolos/11-fibromialgia.md · ../../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md (UC-FLD-01..03, o laudo de evolução, outro documento)
tecnica: Caso de uso = narrativa do profissional + critério de aceite verificável (Dado/Quando/Então)
por_que: o laudo vai para a perícia que decide a equiparação à pessoa com deficiência. Texto alterado depois de assinado, assinatura de quem não escreveu ou conclusão gerada pelo software tiram o valor do documento.
owner: wagner
last_run: "2026-11-11"
last_run_ci: "0 UC executado em MySQL nesta sessão (ADR 0358: MySQL só no CT 100/CI). Veredito: lane PHP / Pest (Verticais · MySQL)."
related_us: [US-FISIO-060]
---

# Casos de Uso & Aceite — Laudo biopsicossocial (US-FISIO-060)

> **Casa do contrato** (`memory/requisitos/<Mod>/_telas/`): o laudo nasce na ficha do paciente,
> mas é documento próprio, com tabelas próprias. Este arquivo é o contrato dele; o `Show.casos.md`
> segue dono da ficha.
>
> **Âncora:** Lei 15.176/2025 (art. 1º-C da Lei 14.705/2023) · Lei 13.146/2015, art. 2º, §1º
> (os quatro itens da avaliação) · ADR 0437 §5 (o software não conclui; quem enquadra é a perícia) ·
> Res. COFFITO 414/2012 (registro assinado não muda; correção por adendo).
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Verticais · MySQL)` — **ADVISORY** (não está em
> `governance/required-checks-baseline.json`).
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-FBP-01 | seção registrada não muda nem se apaga; correção é seção nova | must | US-FISIO-060 | `FisioLaudoBpsEsquemaTest` |
| UC-FBP-02 | a empresa 99 não vê o laudo da 98 | must | US-FISIO-060 | `FisioLaudoBpsEsquemaTest` |

As próximas partes da US entram como `[BACKLOG]` até ganharem teste:

- [BACKLOG] o fisioterapeuta assina a seção de atividades com o CREFITO do perfil; carimbo com usuário e hora do servidor; sem `fisio.editar` 403; paciente de outra empresa 404.
- [BACKLOG] seção de autor de fora da clínica: a clínica transcreve texto, nome, conselho e número; o PDF sai com espaço para a assinatura à mão ou por certificado externo.
- [BACKLOG] seção da fisioterapia pré-preenchida com dor média e variação de 30 e 90 dias, crises e adesão, cada número com data e fonte; sem dado suficiente, campo vazio com aviso.
- [BACKLOG] questionários de fibromialgia no pré-preenchimento (depende da etapa 07, P-03).
- [BACKLOG] PDF com as quatro seções; seção não registrada sai em branco com espaço de assinatura; uma linha no `activity_log` só com ids.
- [BACKLOG] nenhum texto gerado pelo sistema conclui que o paciente é pessoa com deficiência.

---

## UC-FBP-01 · Seção registrada não muda `[must]`
- **Persona:** fisioterapeuta que assinou a seção de atividades.
- **Aceite:** Dada uma seção com `registrado_em` preenchido · Quando alguém tenta UPDATE ou
  DELETE pelo banco ou pelo model · Então o banco recusa (trigger) e o model lança
  `DadoClinicoImutavelException`; o texto fica como estava. A correção é uma seção nova com
  `amends_id` apontando a original. Seção ainda em rascunho aceita edição.
- **Regressão que defende:** laudo alterado depois de entregue à perícia.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEsquemaTest.php`
- **Status: 🧪**

## UC-FBP-02 · Isolamento entre clínicas `[must]`
- **Aceite:** Dado um laudo da empresa 98 · Quando um usuário da 99 consulta laudos e seções ·
  Então não acha nenhum; o usuário da 98 acha o laudo e a seção.
- **Regressão que defende:** dado de saúde de uma clínica aparecendo para outra (ADR 0093).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEsquemaTest.php`
- **Status: 🧪**
