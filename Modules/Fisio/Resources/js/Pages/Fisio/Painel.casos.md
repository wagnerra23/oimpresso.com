---
id: modules-fisio-pages-fisio-painel-casos
casos: Fisio · Painel · /fisio
irmaos: Painel.charter.md (lei) · Painel.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: o painel decide quem o fisioterapeuta chama hoje. Número errado de adesão esconde quem vai abandonar; fila de atenção que perde dor alta ou sinal de alerta é risco clínico; paciente de outra clínica no painel é vazamento Tier 0.
owner: wagner
last_run: "2026-10-17"
last_run_ci: "_pendente_ — US-FISIO-010 (2026-10-18) liga FisioPainelTest, FisioMultiTenantTest e FisioModuloVisibilidadeTest na lane Verticais · MySQL; o veredito é da lane. UC-FPN-05 depende do portal (F::portalPronto → PortalTokenService, US-FISIO-007) e UC-FPN-07 do job de WhatsApp (US-FISIO-009)."
related_us: []
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

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Navegação por teclado na tabela (J/K) e na fila — sem teste de comportamento em navegador.
- `[BACKLOG]` Painel responde em ≤ 800 ms p50 com 300 pacientes — sem medição no CI.

## Trilha do tempo
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código, junto com os testes que os citam.
- 2026-10-18 · US-FISIO-010 — tela `Painel.tsx`, rota `GET /fisio` (`fisio.painel`) e charter. Fila separada em Alerta (`dor_subiu`, `dor_alta`, `sinal_de_alerta`) e Risco de abandono (`sem_registro`), via `AtencaoDetector::separar`. Status dos UCs segue 🧪 até o veredito da lane.
