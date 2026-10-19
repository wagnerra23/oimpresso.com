---
id: requisitos-fisio-telas-jogo-constancia-casos
casos: Jogo de constância — XP, nível, sequência e pausa por dor (fluxo SEM tela própria) · evento CheckinRegistrado → JogoService → fisio_game_ledger
irmaos: ../SPEC.md (US-FISIO-008) · ../etapas/02-f1-constancia.md §3.4 · ../protocolos/11-fibromialgia.md · ../../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md (UC-FPP-14..18, o mesmo jogo visto pelo portal)
tecnica: Caso de uso = narrativa do paciente/profissional + critério de aceite verificável (Dado/Quando/Então)
por_que: o jogo mexe com comportamento de paciente com dor. XP que premia exceder o prescrito incentiva lesão; jogo que pune dia de dor esconde dado clínico; ledger editável deixa XP e nível sem prova.
owner: wagner
last_run: "2026-10-18"
last_run_ci: "0 UC executado em MySQL nesta sessão (ADR 0358: MySQL só no CT 100/CI). Unit puros rodados localmente: XpDoDiaCalculatorTest, LimiarDorCalculatorTest, SequenciaCalculatorTest verdes. Veredito do Feature: lane PHP / Pest (Verticais · MySQL)."
related_us: [US-FISIO-008]
---

# Casos de Uso & Aceite — Jogo de constância (US-FISIO-008)

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): o jogo **não tem tela própria** —
> é a regra que o portal (US-FISIO-007) e o painel (US-FISIO-010) mostram. O ponto de entrada é
> o evento `Modules\Fisio\Events\CheckinRegistrado`, ouvido por `LancarXpDoCheckin`, que chama
> `JogoService::registrarCheckin()`. O portal vê o mesmo jogo pelos UC-FPP-14..18.
>
> **Âncora:** ADR 0437 §6 e §13.3 · números de `etapas/02-f1-constancia.md` §3.4 (70/30,
> normalização 4 ÷ meta, teto semanal 500, 10 níveis) em `config('fisio.jogo')` · protocolo 11
> (dia de crise conta; alerta relativo à dor de base da paciente).
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Verticais · MySQL)` — **ADVISORY** (não está em
> `governance/required-checks-baseline.json`). Os Unit puros rodam na mesma lane.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-FJG-01 | XP só pelo prescrito: 70 × feito ÷ prescrito + 30 do registro, × 4 ÷ meta | must | US-FISIO-008 | `FisioJogoServicoTest`, `XpDoDiaCalculatorTest` |
| UC-FJG-02 | reenvio lança só a diferença; nada estorna; teto semanal | must | US-FISIO-008 | `FisioJogoServicoTest`, `XpDoDiaCalculatorTest` |
| UC-FJG-03 | dor alta pausa sem punir; dia de crise conta para a sequência | must | US-FISIO-008 | `FisioJogoServicoTest`, `SequenciaCalculatorTest` |
| UC-FJG-04 | alerta relativo à dor de base da própria paciente (`sobre_basal`) | must | US-FISIO-008 | `FisioJogoServicoTest`, `LimiarDorCalculatorTest` |
| UC-FJG-05 | nível derivado do ledger; isolamento por clínica; bloqueado não pontua | must | US-FISIO-008 | `FisioJogoServicoTest` |

---

## UC-FJG-01 · XP só por cumprir o prescrito `[must]`
- **Persona:** paciente fazendo o programa em casa.
- **Aceite:** Dado um programa com itens e `times_per_day` · Quando o check-in do dia é gravado ·
  Então o ledger recebe `exercicio_concluido` = `xp.exercicio_concluido` × (vezes feitas ÷ vezes
  prescritas, cada item contando no máximo `times_per_day`) e `checkin` = `xp.checkin`, ambos ×
  `normalizacao_dias_base` ÷ `sessions_per_week`, arredondados meio-para-cima. Vez a mais e item
  fora do programa valem 0. Registrar o dia sem fazer nada, inclusive com dor 9, dá o XP do registro.
- **Regressão que defende:** jogo premiando sobrecarga; quem tem 7x/semana subindo de nível 2× mais rápido.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php` · `Modules/Fisio/Tests/Unit/XpDoDiaCalculatorTest.php`
- **Status: 🧪**

## UC-FJG-02 · Reenvio não pontua de novo; teto semanal `[must]`
- **Aceite:** Reenvio com o mesmo relato não lança nada; com mais itens, lança só a diferença
  positiva do dia; desmarcar não estorna (o ledger é append-only, nenhum lançamento negativo). O
  XP da semana (segunda a domingo) não passa de `teto_semanal`; o registro do dia entra antes do
  exercício quando o teto aperta; a semana seguinte recomeça.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php` · `Modules/Fisio/Tests/Unit/XpDoDiaCalculatorTest.php`
- **Status: 🧪**

## UC-FJG-03 · Dia ruim pausa, não pune; dia de crise conta `[must]`
- **Persona:** paciente com dor alta ou em crise (protocolo 11).
- **Aceite:** Dado dor ≥ limiar ou sinal de alerta · Então o jogo fica `em_pausa`, a sequência não
  quebra e o XP do registro entra; o paciente aparece na fila de atenção do painel (`dor_alta`).
  Dado que o paciente escolheu a versão de crise (`fisio_checkins.crisis`) · Então o dia soma na
  sequência e no total de dias cumpridos, mesmo sem exercício do programa normal. O total de dias
  cumpridos nunca zera.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php` · `Modules/Fisio/Tests/Unit/SequenciaCalculatorTest.php`
- **Status: 🧪**

## UC-FJG-04 · Alerta pela dor de base da própria paciente `[must]`
- **Persona:** paciente com dor crônica; fisioterapeuta que escolheu `pain_alert_mode = sobre_basal`.
- **Aceite:** A dor de base é a média das dores informadas nos primeiros `dor_de_base.janela_dias`
  do programa (pelo menos `minimo_registros`). O limiar vira base + `margem`, arredondado para cima
  e limitado a 10; sem base ainda, vale o limiar do programa. O mesmo número pausa o jogo e põe o
  paciente na fila de atenção. Controle: o mesmo valor num programa `absoluto` pausa pelo limiar fixo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php` · `Modules/Fisio/Tests/Unit/LimiarDorCalculatorTest.php`
- **Status: 🧪**

## UC-FJG-05 · Nível vem do ledger; cada clínica no seu `[must]` `[T0]`
- **Aceite:** XP total é a soma do ledger e o nível sai de `config('fisio.jogo.niveis')`; não há
  coluna de XP nem de nível no paciente, nem ranking. Evento com `business_id` de outra clínica não
  acha o check-in e não lança nada; paciente bloqueado não pontua.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Escudos de sequência (1 a cada 7 dias, estoque 2) — a suíte fixou a folga `ceil(7/meta) − 1` no lugar; decisão em aberto com [W].
- `[BACKLOG]` Missões diárias, desafio semanal e medalhas (T-08c) — os valores já estão em `config('fisio.jogo.xp')`, o código não.
- `[BACKLOG]` Pausa protegida que expira em 7 dias ou com reativação pelo profissional.
- `[BACKLOG]` Modo manutenção na alta (programa `encerrado`).

## Trilha do tempo
- 2026-10-18 · nasce com a US-FISIO-008 (calculadoras puras, `JogoService`, evento `CheckinRegistrado`).