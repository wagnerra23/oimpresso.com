---
id: modules-fisio-pages-fisio-painel-casos
casos: Fisio · Painel · /fisio
irmaos: Painel.charter.md (lei) · Painel.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: o painel decide quem o fisioterapeuta chama hoje. Número errado de adesão esconde quem vai abandonar; fila de atenção que perde dor alta ou sinal de alerta é risco clínico; paciente de outra clínica no painel é vazamento Tier 0.
owner: wagner
last_run: "2026-10-18"
last_run_ci: "_pendente_ — US-FISIO-010 (2026-10-18) liga FisioPainelTest, FisioMultiTenantTest e FisioModuloVisibilidadeTest na lane Verticais · MySQL; o veredito é da lane. UC-FPN-05 depende do portal (F::portalPronto → PortalTokenService, US-FISIO-007) e UC-FPN-07 do job de WhatsApp (US-FISIO-009)."
related_us: [US-FISIO-009]
---

# Casos de Uso & Aceite — Fisio · Painel (`/fisio`)

> **Âncora:** ADR 0437 (§4 a §8) e o handoff F1 "Constância" (T-02, T-09, T-10). Os UCs não derivam do `.tsx` (nasceram antes dele, com a suíte TDD).
>
> **Status:** ✅ passa (veredito no manifesto) · 🧪 teste cita o UC, sem veredito coletado · ⬜ não verificado · ❌ quebrou. **O veredito é da lane, nunca desta leitura** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPN-01 | menu e rotas só com o pacote `fisio_module` | must | `FisioModuloVisibilidadeTest`, `FisioModuloContratoTest` |
| UC-FPN-02 | adesão 7d bate com os check-ins | must | `FisioPainelTest`, `AdesaoCalculatorTest` |
| UC-FPN-03 | dor subiu ≥2 e persiste, ou dor ≥ limiar → atenção | must | `FisioPainelTest`, `AtencaoDetectorTest` |
| UC-FPN-04 | sem registro ≥4 dias, ou sinal de alerta → atenção na hora | must | `FisioPainelTest`, `FisioCheckinTest`, `AtencaoDetectorTest` |
| UC-FPN-05 | tabela com nível e sequência derivados | should | `FisioPainelTest` |
| UC-FPN-06 | painel não mistura clínicas | must | `FisioMultiTenantTest` |
| UC-FPN-07 | lembrete diário: 1 por paciente por dia | must | `FisioWhatsappJobTest` |
| UC-FPN-08 | lembrete respeita silêncio, teto por hora e canal caído | must | `FisioWhatsappJobTest`, `AgendaDeEnvioTest` |
| UC-FPN-09 | envio de WhatsApp que não chegou vira link `wa.me` no painel | must | `FisioPainelEnviosFalhosTest` |

---

## UC-FPN-01 · Só quem contratou vê o Fisio `[T0]` · `must`
- **Persona:** dono de uma clínica que não contratou o módulo.
- **Aceite:** Dado um business sem o pacote `fisio_module` · Quando o usuário abre a sidebar ou digita `/fisio` · Então o item "Fisio" não aparece e toda rota do módulo responde 403, sem gravar nada. Com o pacote e sem `fisio.access`, também 403. Desligar o pacote corta o acesso no request seguinte.
- **Regressão que defende:** menu de outro vertical aparecendo para cliente que não o contratou; liberação por `if ($business_id === N)`.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioModuloVisibilidadeTest.php` · `Modules/Fisio/Tests/Feature/FisioModuloContratoTest.php`
- **Status: 🧪**

## UC-FPN-02 · Adesão 7d honesta · `must`
- **Persona:** fisioterapeuta abrindo o painel de manhã.
- **Aceite:** Dado um paciente com meta de N sessões por semana · Quando o painel calcula a adesão · Então ela é o nº de dias, entre hoje-6 e hoje, com check-in que tem ≥1 exercício feito, dividido por N, arredondado, no máximo 100%. Check-in sem exercício não conta; sem programa ativo a adesão é vazia (não 0%) e não entra na média; meta 0 é proibida já no cadastro.
- **Regressão que defende:** adesão acima de 100% (painel que mente para cima); divisão por zero; paciente sem programa puxando a média para baixo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPainelTest.php` · `Modules/Fisio/Tests/Unit/AdesaoCalculatorTest.php`
- **Status: 🧪**

## UC-FPN-03 · Dor que piora chama atenção · `must`
- **Persona:** fisioterapeuta.
- **Aceite:** Dado os dois últimos registros com dor informada · Quando a dor subiu 2 pontos ou mais · Então o paciente entra em "precisa de atenção" com o motivo `dor_subiu`. Subir 1 não entra; se o último voltou a cair, não entra. Dor ≥ limiar do programa (padrão 7) no último registro → `dor_alta`.
- **Regressão que defende:** piora silenciosa entre consultas.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPainelTest.php` · `Modules/Fisio/Tests/Unit/AtencaoDetectorTest.php`
- **Status: 🧪**

## UC-FPN-04 · Quem sumiu ou deu sinal de alerta aparece já · `must`
- **Persona:** fisioterapeuta.
- **Aceite:** Dado um programa ativo · Quando passam 4 dias ou mais sem check-in (contando de `starts_at` se nunca registrou) · Então entra com `sem_registro`; 3 dias não; sem programa ativo, nunca. Quando o último check-in tem sinal de alerta · Então entra com `sinal_de_alerta` no mesmo instante, sem esperar job.
- **Regressão que defende:** abandono percebido só na consulta seguinte; sinal de alerta esperando o fim do dia.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPainelTest.php` · `Modules/Fisio/Tests/Feature/FisioCheckinTest.php` · `Modules/Fisio/Tests/Unit/AtencaoDetectorTest.php`
- **Status: 🧪**

## UC-FPN-05 · Nível e sequência na tabela · `should`
- **Persona:** fisioterapeuta.
- **Aceite:** Dado um paciente com check-ins · Quando o painel lista · Então nível e sequência são calculados do ledger e dos check-ins (os mesmos números do portal) e paciente bloqueado não aparece.
- **Regressão que defende:** coluna de nível editável que diverge do que o paciente vê.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPainelTest.php`
- **Status: 🧪**

## UC-FPN-06 · Painel de uma clínica não conta paciente de outra `[T0]` · `must`
- **Persona:** invariante multi-tenant (ADR 0093).
- **Aceite:** Dado pacientes e check-ins nas empresas 98 e 99 · Quando a 98 abre o painel · Então KPIs, fila e tabela contam só a 98.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioMultiTenantTest.php`
- **Status: 🧪**

## UC-FPN-07 · Um lembrete por dia, no máximo · `must`
- **Persona:** paciente com programa ativo que ainda não registrou o dia.
- **Aceite:** Dado consentimento de WhatsApp vigente · Quando o lembrete diário roda (inclusive mais de uma vez) · Então sai no máximo 1 mensagem por paciente por dia; quem já registrou o dia, quem não tem programa ativo, quem revogou o WhatsApp e business sem módulo não recebem.
- **Regressão que defende:** spam de lembrete por retry de fila; mensagem para quem revogou (LGPD).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioWhatsappJobTest.php`
- **Status: 🧪**

## UC-FPN-08 · O lembrete não acorda ninguém nem queima o número · `must`
- **Persona:** paciente (horário de silêncio) e clínica (número de WhatsApp sem banimento; ADR 0437 §13.1 (c) e (e)).
- **Aceite:** Dado o horário de silêncio de `config('fisio.whatsapp.silencio')` (padrão 21:00–07:00, fuso da clínica) · Quando o lembrete roda dentro dele · Então a mensagem é agendada para o fim do silêncio, não enviada na hora. No mesmo número, nunca mais que `teto_por_hora` envios numa janela de 60 min, com intervalo sorteado entre `intervalo_min_segundos` e `intervalo_max_segundos` entre mensagens. Canal desconectado ou banido no módulo `Whatsapp` → nenhum envio automático (o alerta de canal caído é do módulo Whatsapp).
- **Regressão que defende:** rajada de mensagens com cadência fixa (padrão de automação que leva a banimento); lembrete de madrugada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioWhatsappJobTest.php` · `Modules/Fisio/Tests/Unit/AgendaDeEnvioTest.php`
- **Status: 🧪**

## UC-FPN-09 · O WhatsApp que não chegou vira envio à mão `[T0]` · `must`
- **Persona:** recepção da clínica abrindo o painel; paciente que ficaria sem lembrete.
- **Aceite:** Dado um lembrete ou envio de programa reservado hoje · Quando o módulo `Whatsapp` gravou só tentativas `failed` da mensagem (ou ela ficou na fila mais de 30 min depois do horário previsto), ou o número da clínica está desconectado/banido depois do horário do lembrete · Então o painel lista o paciente, o tipo e o motivo, com link `wa.me` pré-preenchido com o mesmo texto. Mensagem que chegou (`sent`/`delivered`/`read` em alguma tentativa) não aparece. Paciente de outra clínica não aparece. Paciente sem consentimento de WhatsApp vigente aparece sem link. Quem não tem `fisio.editar` vê a lista sem link (o programa leva o código do portal).
- **Regressão que defende:** paciente sem lembrete em silêncio quando o canal falha; envio à mão para quem revogou o WhatsApp (LGPD); falha de outra clínica no painel (Tier 0).
- **Fora:** lembrete que não saiu porque o canal caiu e voltou antes da consulta ao painel (não há registro sem gravar linha nova); programa com canal caído já recebe o `wa.me` no clique.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPainelEnviosFalhosTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Navegação por teclado na tabela (J/K) e na fila — sem teste de comportamento em navegador.
- `[BACKLOG]` Painel responde em ≤ 800 ms p50 com 300 pacientes — sem medição no CI.

## Trilha do tempo
- 2026-10-19 · etapa 02 T-09e — fallback humano no painel, lido de `activity_log` (reserva do Fisio) e `whatsapp_messages` (módulo Whatsapp), sem tabela própria; UC-FPN-09 nasce com o teste.
- 2026-10-19 · US-FISIO-009 — lembrete diário pelo módulo `Whatsapp` existente; UC-FPN-08 (silêncio, teto por hora, intervalo aleatório, canal caído) nasce com o teste.
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código, junto com os testes que os citam.
- 2026-10-18 · US-FISIO-010 — tela `Painel.tsx`, rota `GET /fisio` (`fisio.painel`) e charter. Fila separada em Alerta (`dor_subiu`, `dor_alta`, `sinal_de_alerta`) e Risco de abandono (`sem_registro`), via `AtencaoDetector::separar`. Status dos UCs segue 🧪 até o veredito da lane.
