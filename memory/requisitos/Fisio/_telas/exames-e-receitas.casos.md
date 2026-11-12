---
id: requisitos-fisio-telas-exames-e-receitas-casos
casos: Exames e receitas do paciente — anexos do prontuário, medicamentos, transcrição sugerida pela IA e envio pelo portal · fisio_patient_documents
irmaos: ../SPEC.md (US-FISIO-063) · ../../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md (ficha) · ../../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md (portal)
tecnica: Caso de uso = narrativa do profissional + critério de aceite verificável (Dado/Quando/Então)
por_que: exame e receita que o paciente traz são parte do prontuário. Arquivo que some, que outra clínica abre ou que a IA "interpreta" tira o valor do registro e muda o enquadramento do software na Anvisa.
owner: wagner
last_run: "2026-11-11"
last_run_ci: "0 UC executado em MySQL nesta sessão (ADR 0358: MySQL só no CT 100/CI). Veredito: lane PHP / Pest (Verticais · MySQL)."
related_us: [US-FISIO-063]
---

# Casos de Uso & Aceite — Exames e receitas (US-FISIO-063)

> **Casa do contrato** (`memory/requisitos/<Mod>/_telas/`): a seção nasce na ficha do paciente, mas
> tem tabela, rotas e regras próprias. O `Show.casos.md` segue dono da ficha.
>
> **Âncora:** Res. COFFITO 414/2012 (prontuário) · Lei 13.787/2018 (guarda de 20 anos) · ADR 0123
> (`Modules/Arquivos`) · ADR 0093 (multi-tenant) · ADR 0437 §5 e §7 (o software não interpreta dado
> clínico; consentimentos separados) · proposta da ADR 0450.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Verticais · MySQL)` — **ADVISORY** (não está em
> `governance/required-checks-baseline.json`).
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-FER-01 | anexar grava o documento na empresa do paciente e o arquivo sob `biz-{id}/`, ligado ao documento | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |
| UC-FER-02 | sem arquivo, tipo fora da lista, formato errado, data futura ou observação longa: 422 e nada gravado | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |
| UC-FER-03 | abrir: a empresa dona recebe o arquivo com `nosniff` e a abertura vai ao `activity_log`; outra empresa 404 sem log | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |
| UC-FER-04 | tirar da lista exige motivo, grava quem e quando, mantém documento e arquivo; repetir não muda nada | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |
| UC-FER-05 | documento não se apaga, nem pelo model nem pelo banco | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |
| UC-FER-06 | sem `fisio.editar` 403; outra empresa 404; paciente bloqueado não recebe documento | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |
| UC-FER-07 | a ficha recebe os tipos junto e os documentos deferidos, sem caminho de disco e sem os tirados | must | US-FISIO-063 | `FisioDocumentosPacienteTest` |

- [BACKLOG] lista de medicamentos estruturada (parte 2 da US).
- [BACKLOG] transcrição sugerida pela IA e conferida pelo profissional (parte 3 da US).
- [BACKLOG] envio pelo portal com consentimento próprio, pendente de conferência (parte 4 da US).

---

## UC-FER-01 · Anexar foto ou PDF ao prontuário `[must]`
- **Persona:** fisioterapeuta com a receita que o paciente trouxe.
- **Aceite:** Dado um paciente da empresa · Quando anexo uma foto ou um PDF com tipo, data e
  observação · Então o documento é gravado na empresa do paciente, com quem anexou, origem
  `clinica` e situação `prontuario`, e o arquivo fica no `Modules/Arquivos` sob `biz-{id}/`,
  ligado ao documento (não ao paciente direto).
- **Status:** 🧪

## UC-FER-02 · Validação do envio `[must]`
- **Aceite:** Dado o formulário · Quando falta o arquivo, o tipo não é da lista fechada, o arquivo
  não é JPG, PNG, WebP ou PDF, a data é depois de hoje ou a observação passa de 500 caracteres ·
  Então a resposta é 422 com o campo e nada é gravado.
- **Status:** 🧪

## UC-FER-03 · Abrir o arquivo `[must]`
- **Aceite:** Dado um documento da empresa 98 · Quando alguém da 98 com `fisio.access` abre ·
  Então recebe o arquivo com o tipo certo, `X-Content-Type-Options: nosniff` e cache `private`, e
  a abertura grava uma linha no `activity_log` só com ids · Quando alguém da 99 pede o mesmo
  endereço · Então recebe 404 e nada é logado.
- **Status:** 🧪

## UC-FER-04 · Tirar da lista sem apagar `[must]`
- **Aceite:** Dado um documento na lista · Quando tiro sem motivo · Então 422 · Quando tiro com
  motivo · Então ficam gravados quem, quando e o motivo, uma linha no `activity_log`, e o
  documento e o arquivo continuam (o arquivo ainda abre) · Quando outra pessoa tira de novo ·
  Então nada muda.
- **Status:** 🧪

## UC-FER-05 · Documento não se apaga `[must]`
- **Aceite:** Dado um documento · Quando o model ou o banco tentam apagar · Então a exclusão é
  recusada (exceção no model, trigger no MySQL) e a linha continua.
- **Status:** 🧪

## UC-FER-06 · Permissão e empresa `[must]`
- **Aceite:** Sem `fisio.editar`, anexar e tirar respondem 403. Usuário da 99 anexando no paciente
  da 98 ou tirando documento da 98 recebe 404 e nada muda. Paciente bloqueado não recebe documento
  novo (422).
- **Status:** 🧪

## UC-FER-07 · O que a ficha recebe `[must]`
- **Aceite:** Dada a ficha · Quando ela abre · Então a lista de tipos vem junto e os documentos
  chegam por prop deferida (`documentos`), cada um com tipo, data, observação, formato e o
  endereço da rota que serve; nunca caminho de disco nem nome original do arquivo; documentos
  tirados da lista não aparecem.
- **Status:** 🧪
