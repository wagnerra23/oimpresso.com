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
| UC-FBP-04 | preencher grava só o que veio; matriz validada e pontuada; Sim/Não como booleano | must | US-FISIO-060 | `FisioLaudoBpsEscritaTest` |
| UC-FBP-05 | equipe: fisioterapeuta da clínica com nome e CREFITO do servidor; profissional de fora com nome, área e registro | must | US-FISIO-060 | `FisioLaudoBpsEscritaTest` |
| UC-FBP-06 | finalizar exige 30 itens, conclusão respondida, local e data, e dois profissionais de áreas distintas com médico | must | US-FISIO-060 | `FisioLaudoBpsEscritaTest` |
| UC-FBP-07 | nova versão copia formulário e equipe para rascunho que aponta o original | must | US-FISIO-060 | `FisioLaudoBpsEscritaTest` |
| UC-FBP-08 | sem `fisio.editar` 403; outra empresa 404; paciente bloqueado sem laudo novo | must | US-FISIO-060 | `FisioLaudoBpsEscritaTest` |
| UC-FBP-09 | o sistema não marca a conclusão, mesmo com pontuação abaixo de 500 | must | US-FISIO-060 | `FisioLaudoBpsEscritaTest` |
| UC-FBP-10 | apoio da fisioterapia: dor de 30 e 90 dias, crises, adesão e questionários com período e fonte | must | US-FISIO-060 | `FisioLaudoBpsApoioTest` |
| UC-FBP-11 | sem dado suficiente: nenhum número, aviso no lugar | must | US-FISIO-060 | `FisioLaudoBpsApoioTest` |
| UC-FBP-12 | o texto de apoio não conclui; leitura com `fisio.access`; outra empresa 404 | must | US-FISIO-060 | `FisioLaudoBpsApoioTest` |
| UC-FBP-13 | o PDF segue o formulário SES-SC; rascunho sai como documento de apoio; vazio sai em branco | must | US-FISIO-060 | `FisioLaudoBpsPdfTest` |
| UC-FBP-14 | cada PDF grava uma linha no `activity_log` só com ids; outra empresa 404 sem log | must | US-FISIO-060 | `FisioLaudoBpsPdfTest` |
| UC-FBP-15 | a ficha recebe o catálogo da matriz e os laudos deferidos com pontuação e equipe; a 99 não vê | must | US-FISIO-060 | `FisioLaudoBpsTelaTest` |

- [BACKLOG] conferência em staging com print de um laudo gerado com paciente de teste.

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

## UC-FBP-04 · Preencher o formulário `[must]`
- **Persona:** fisioterapeuta ou equipe preenchendo o laudo.
- **Aceite:** Cada envio grava só os blocos que vieram (identificação, matriz, fatores, conclusão,
  local, data); o resto fica. A matriz aceita só itens do modelo e valores 100/75/50/25; item
  enviado vazio sai da matriz; a resposta traz a pontuação (por domínio, o que falta e o total).
  As perguntas da conclusão chegam do formulário como "1"/"0" e são gravadas como Sim/Não.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php`
- **Status: 🧪**

## UC-FBP-05 · Equipe `[must]`
- **Aceite:** O fisioterapeuta da clínica entra com assinatura `sistema` só na área
  Fisioterapia/TO; nome é o do usuário e registro é o CREFITO do perfil, ignorando o que vier no
  corpo. Profissional de fora entra com `externa`, área, nome e registro obrigatórios; o PDF reserva
  espaço para a assinatura e o carimbo dele. Enquanto o laudo é rascunho, dá para tirar alguém.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php`
- **Status: 🧪**

## UC-FBP-06 · Finalizar `[must]`
- **Aceite:** Finalizar recusa, dizendo o motivo, quando falta item da matriz, quando alguma das três
  perguntas da conclusão está sem resposta, quando falta local ou data, e quando a equipe não tem
  dois profissionais de áreas distintas com um médico (dois médicos não bastam; fisioterapia e
  enfermagem sem médico não bastam). Com tudo certo, grava quem finalizou e quando, e devolve o
  total. Depois disso, preencher, mexer na equipe e finalizar de novo são recusados.
- **Âncora:** modelo SES-SC: "mínimo de dois profissionais de áreas distintas, sendo um deles o médico"; "Local e data: (obrigatório)".
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php`
- **Status: 🧪**

## UC-FBP-07 · Nova versão `[must]`
- **Aceite:** Só laudo finalizado abre nova versão; ela nasce em rascunho, com o formulário e a
  equipe copiados e `amends_id` no original, que fica intacto.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php`
- **Status: 🧪**

## UC-FBP-08 · Permissão e isolamento `[must]`
- **Aceite:** Sem `fisio.editar`, criar e preencher dão 403. Usuário da 99 recebe 404 em tudo do
  laudo da 98 e nada entra nele. Paciente bloqueado (Lei 13.787/2018) não recebe laudo novo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php`
- **Status: 🧪**

## UC-FBP-09 · O sistema não conclui `[must]`
- **Aceite:** Com a matriz inteira em 25 (total 200, abaixo dos 500 do formulário), o laudo continua
  sem nenhuma resposta na conclusão: só a equipe marca as três perguntas.
- **Âncora:** ADI 5033647-81.2025.8.24.0000 (citada na Deliberação 500/CIB/2026) · ADR 0437 §5.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php`
- **Status: 🧪**

## UC-FBP-10 · Apoio da fisioterapia `[must]`
- **Persona:** fisioterapeuta preparando a descrição dos impedimentos e a pontuação da matriz.
- **Aceite:** Pedido o apoio · Então vêm, para 30 e 90 dias contados de hoje para trás, média (uma
  casa), mínima e máxima da dor do check-in (0 a 10) e os dias de crise; a adesão dos últimos 30
  dias (dias com exercício feito ÷ meta semanal × 30 ÷ 7, teto 100); e cada questionário com
  o nome do catálogo, pontuação e data — inclusive os de fibromialgia da etapa 07 (ACR 2016, FIQR,
  PGIC, sentar e levantar em 30 s). Cada número cita período e fonte. Nada é gravado: o profissional decide o que usar.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsApoioTest.php`
- **Status: 🧪**

## UC-FBP-11 · Sem dado, sem número `[must]`
- **Aceite:** Com menos de 7 dias de dor na janela, a média não é calculada e entra um aviso com a
  contagem. Sem programa ativo, a adesão não é calculada. Sem questionário, aviso.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsApoioTest.php`
- **Status: 🧪**

## UC-FBP-12 · O apoio não conclui `[must]`
- **Aceite:** Mesmo com dor alta todos os dias, o texto não usa "deficiência", "incapacidade",
  "grave", "severa" nem "invalidez". Quem só tem `fisio.access` lê o apoio; a outra empresa recebe 404.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsApoioTest.php`
- **Status: 🧪**

## UC-FBP-13 · PDF no layout oficial `[must]`
- **Persona:** paciente levando o laudo à unidade de saúde.
- **Aceite:** O PDF traz, na ordem do modelo SES-SC: identificação, matriz com as 30 linhas e as
  colunas 100/75/50/25, o menor valor de cada domínio e a pontuação total calculada, fatores
  contextuais, conclusão da equipe, as cinco áreas da equipe e local e data. O que não foi preenchido
  sai em branco, inclusive a conclusão; área sem profissional sai com a linha para preencher.
  Laudo em rascunho traz o aviso "Documento de apoio, não finalizado". O rodapé cita a deliberação e
  diz que a emissão do laudo cabe à APS e a da carteira, à FCEE.
- **Âncora:** Deliberação 500/CIB/2026, arts. 1º a 3º; modelo versão 1.0 de 26/10/2026.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsPdfTest.php`
- **Status: 🧪**

## UC-FBP-14 · Rastro da geração `[must]`
- **Aceite:** Cada PDF grava `laudo_bps_exportado` no `activity_log` com empresa, paciente, laudo,
  se está finalizado e o total — sem nome nem texto clínico. Quem só tem `fisio.access` gera o PDF.
  A outra empresa recebe 404 e nada é registrado.
- **Âncora:** LGPD art. 11 · mesmo rastro do laudo de evolução (UC-FLD-02).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsPdfTest.php`
- **Status: 🧪**

## UC-FBP-15 · O laudo na ficha do paciente `[must]`
- **Persona:** fisioterapeuta na ficha do paciente.
- **Aceite:** A ficha recebe o catálogo da matriz (8 domínios, 30 itens) junto da página e os laudos
  por prop deferida (grupo próprio), o mais recente primeiro, com formulário, pontuação e equipe. O
  cartão "Laudo biopsicossocial" (`Fisio/_components/LaudoBiopsicossocial.tsx`, protótipo
  `LaudoBiopsicossocial.dc.html`) mostra o aviso de que a emissão é da Atenção Primária e a carteira
  segue o fluxo da FCEE; abre um domínio da matriz por vez com 100/75/50/25; mostra o apoio da
  fisioterapia; deixa a conclusão sem resposta até a equipe marcar; monta a equipe; salva, finaliza,
  baixa o PDF e abre nova versão de laudo finalizado. Usuário da outra empresa recebe 404 na ficha.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsTelaTest.php`
- **Status: 🧪**
