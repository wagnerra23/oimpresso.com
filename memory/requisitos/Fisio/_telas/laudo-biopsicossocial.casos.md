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
| UC-FBP-03 | assinatura pelo sistema: autor, CREFITO e hora do servidor; sem CREFITO não assina; correção aponta a original | must | US-FISIO-060 | `FisioLaudoBpsAssinaturaTest` |
| UC-FBP-04 | seção externa: nome, conselho e número obrigatórios para registrar; seção sem texto não se registra | must | US-FISIO-060 | `FisioLaudoBpsAssinaturaTest` |
| UC-FBP-05 | sem `fisio.editar` 403; outra empresa 404; item fora dos quatro da LBI 404 | must | US-FISIO-060 | `FisioLaudoBpsAssinaturaTest` |
| UC-FBP-06 | paciente bloqueado não recebe laudo novo | should | US-FISIO-060 | `FisioLaudoBpsAssinaturaTest` |
| UC-FBP-07 | sugestão da fisioterapia: dor de 30 e 90 dias, crises, adesão e questionários com período e fonte | must | US-FISIO-060 | `FisioLaudoBpsSugestaoTest` |
| UC-FBP-08 | sem dado suficiente: nenhum número, aviso no lugar | must | US-FISIO-060 | `FisioLaudoBpsSugestaoTest` |
| UC-FBP-09 | o texto sugerido não conclui; leitura com `fisio.access`; outra empresa 404 | must | US-FISIO-060 | `FisioLaudoBpsSugestaoTest` |
| UC-FBP-10 | o PDF traz só seção registrada, na ordem da LBI; correção substitui; o que falta sai em branco para assinar | must | US-FISIO-060 | `FisioLaudoBpsPdfTest` |
| UC-FBP-11 | cada PDF grava uma linha no `activity_log` só com ids; outra empresa 404 sem log | must | US-FISIO-060 | `FisioLaudoBpsPdfTest` |
| UC-FBP-12 | a ficha recebe os laudos por prop deferida, por inciso, com seção registrada e rascunho; a 99 não vê | must | US-FISIO-060 | `FisioLaudoBpsTelaTest` |

As próximas partes da US entram como `[BACKLOG]` até ganharem teste:

- [BACKLOG] questionários de fibromialgia no pré-preenchimento (depende da etapa 07, P-03).

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

## UC-FBP-03 · Assinatura pelo sistema `[must]`
- **Persona:** fisioterapeuta da clínica, autor da seção de atividades.
- **Aceite:** Dado um rascunho com `assinatura=sistema` · Quando ele registra a seção · Então
  autor é o nome do usuário logado, conselho é CREFITO, número é o CREFITO do perfil, e
  `registrado_por`/`registrado_em` são o usuário e a hora do servidor. Nome, conselho e número
  mandados no corpo são ignorados. Sem CREFITO no perfil, a seção não é registrada. Salvar de novo
  um item já registrado cria rascunho novo com `amends_id` apontando a original, que fica intacta.
- **Regressão que defende:** laudo assinado em nome de outro profissional.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsAssinaturaTest.php`
- **Status: 🧪**

## UC-FBP-04 · Seção de autor de fora da clínica `[must]`
- **Persona:** recepção ou fisioterapeuta transcrevendo o parecer do médico, do psicólogo ou do assistente social.
- **Aceite:** Dado um rascunho com `assinatura=externa` · Quando falta nome, conselho ou número ·
  Então não registra. Com os três e com texto, registra; `registrado_por` é quem transcreveu.
  Seção sem texto não se registra.
- **Regressão que defende:** seção sem autor identificável no documento que vai à perícia.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsAssinaturaTest.php`
- **Status: 🧪**

## UC-FBP-05 · Permissão e isolamento nas rotas `[must]`
- **Aceite:** Sem `fisio.editar`, criar e salvar respondem 403. Usuário da 99 recebe 404 para o
  paciente e o laudo da 98. Item fora dos quatro da LBI responde 404. Nada da 99 entra no laudo da 98.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsAssinaturaTest.php`
- **Status: 🧪**

## UC-FBP-06 · Paciente bloqueado `[should]`
- **Aceite:** Paciente bloqueado (Lei 13.787/2018) não recebe laudo novo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsAssinaturaTest.php`
- **Status: 🧪**

## UC-FBP-07 · Sugestão da seção de atividades `[must]`
- **Persona:** fisioterapeuta abrindo a seção de atividades do laudo.
- **Aceite:** Dado um paciente com check-ins, programa ativo e questionário · Quando ele pede a
  sugestão · Então recebe, para 30 e 90 dias contados de hoje para trás, média (uma casa),
  mínima e máxima da dor (escala 0 a 10) e o número de dias de crise; a adesão dos últimos 30 dias
  (dias com exercício feito ÷ meta semanal × 30 ÷ 7, teto 100); e cada questionário com pontuação
  e data. O texto cita o período de cada número e a fonte. Nada é gravado: a sugestão vira rascunho
  só quando o profissional salva, depois de revisar.
- **Âncora:** LBI art. 2º, §1º, II (limitação no desempenho de atividades).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsSugestaoTest.php`
- **Status: 🧪**

## UC-FBP-08 · Sem dado, sem número `[must]`
- **Aceite:** Com menos de 7 dias de dor registrada na janela, a média não é calculada e entra
  um aviso dizendo quantos registros há. Sem programa ativo, a adesão não é calculada. Sem
  questionário, aviso. O texto não traz número que não foi medido.
- **Regressão que defende:** média de 2 dias apresentada à perícia como se fosse o mês.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsSugestaoTest.php`
- **Status: 🧪**

## UC-FBP-09 · O software descreve, não enquadra `[must]`
- **Aceite:** Mesmo com dor alta todos os dias, o texto sugerido não usa "deficiência",
  "incapacidade", "grave", "severa" nem "invalidez". Quem só tem `fisio.access` vê a sugestão;
  usuário da outra empresa recebe 404.
- **Âncora:** ADR 0437 §5 (linha da Anvisa: o software não conclui).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsSugestaoTest.php`
- **Status: 🧪**

## UC-FBP-10 · O PDF `[must]`
- **Persona:** paciente levando o laudo à perícia.
- **Aceite:** Dado um laudo com seções registradas, uma corrigida e uma em rascunho · Quando o
  PDF é gerado · Então as quatro seções saem na ordem dos incisos I a IV do art. 2º, §1º, da LBI;
  cada seção registrada traz o texto, autor, conselho e número; a corrigida mostra a versão mais
  recente e avisa que corrige outra; a seção externa traz espaço para a assinatura manuscrita ou
  por certificado digital; seção sem registro (rascunho incluído) sai em branco com campos de nome,
  conselho, data e assinatura. O rodapé diz que o documento descreve e que o enquadramento cabe à perícia.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsPdfTest.php`
- **Status: 🧪**

## UC-FBP-11 · Rastro da geração `[must]`
- **Aceite:** Cada PDF gerado grava uma linha `laudo_bps_exportado` no `activity_log` com
  empresa, paciente, laudo e quantas seções estavam registradas — sem nome nem texto clínico.
  Quem só tem `fisio.access` gera o PDF. Usuário da outra empresa recebe 404 e nada é registrado.
- **Âncora:** LGPD art. 11 (dado de saúde), mesmo rastro do laudo de evolução (UC-FLD-02).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsPdfTest.php`
- **Status: 🧪**

## UC-FBP-12 · O laudo na ficha do paciente `[must]`
- **Persona:** fisioterapeuta na ficha do paciente.
- **Aceite:** Dada a ficha · Quando ela carrega · Então `laudos_bps` chega como prop deferida
  (grupo próprio) com os laudos do paciente, o mais recente primeiro; cada laudo traz os quatro
  incisos na ordem da LBI, e cada inciso traz a seção registrada mais recente e o rascunho aberto.
  O cartão "Laudo biopsicossocial" (`Fisio/_components/LaudoBiopsicossocial.tsx`, protótipo
  `LaudoBiopsicossocial.dc.html`) mostra o estado de cada seção, abre o editor de uma por vez,
  mostra a sugestão do Fisio na seção III e baixa o PDF. Usuário da outra empresa recebe 404 na ficha.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioLaudoBpsTelaTest.php`
- **Status: 🧪**
