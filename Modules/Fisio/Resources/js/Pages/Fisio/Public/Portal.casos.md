---
id: modules-fisio-pages-fisio-public-portal-casos
casos: Fisio · Portal do paciente · /fisio/p/{token}
irmaos: Portal.charter.md (lei) · Portal.tsx (tela) · precedente resources/js/Pages/OficinaAuto/AprovacaoPublica.casos.md
tecnica: Caso de uso = narrativa do paciente + critério de aceite verificável (Dado/Quando/Então)
por_que: o portal é público na internet e mostra dado de saúde. Sem PIN/lockout é força bruta; sem consentimento é LGPD; sem telemonitoramento o check-in não vale como prontuário (Res. COFFITO 619/2025); conduta calculada pelo software vira dispositivo médico (Anvisa/SaMD); XP que premia exceder o prescrito incentiva lesão.
owner: wagner
last_run: "2026-10-19"
last_run_ci: "_pendente_ — o trio nasce com a suíte TDD da F1 (antes do código). O veredito entra quando a lane MySQL rodar FisioPortalAcessoTest, FisioCheckinTest, FisioJogoTest, FisioPortalSegurancaClinicaTest e os Unit SequenciaCalculatorTest/NivelCalculatorTest."
related_us: []
---

# Casos de Uso & Aceite — Fisio · Portal do paciente (`/fisio/p/{token}`)

> **Âncora:** ADR 0437 §4 a §8 e handoff F1 (T-07, T-08). Precedente copiado: link público de aprovação da OficinaAuto
> (`AprovacaoPublica.casos.md`, UC-OAP-01..07). Os UCs não derivam do `.tsx`.
>
> ⚖️ Público, mobile-first, **sem conta no sistema**: o business viaja assinado no token, nunca como parâmetro.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPP-01 | link válido pede PIN e não mostra dado | must | `FisioPortalAcessoTest` |
| UC-FPP-02 | adulterado, expirado, revogado: o mesmo estado vazio | must | `FisioPortalAcessoTest` |
| UC-FPP-03 | 5 PINs errados travam | must | `FisioPortalAcessoTest` |
| UC-FPP-04 | trava expira em 30 min | must | `FisioPortalAcessoTest` |
| UC-FPP-05 | consentimento de teleatendimento antes de qualquer dado | must | `FisioPortalAcessoTest` |
| UC-FPP-06 | consentimentos independentes; revogação pelo portal | must | `FisioPortalAcessoTest` |
| UC-FPP-07 | bloqueado ou de outra clínica: não abre | must | `FisioPortalAcessoTest` |
| UC-FPP-08 | check-in grava o dia | must | `FisioCheckinTest` |
| UC-FPP-09 | reenvio no mesmo dia atualiza, não duplica | must | `FisioCheckinTest` |
| UC-FPP-10 | cada check-in vira telemonitoramento no prontuário | must | `FisioCheckinTest` |
| UC-FPP-11 | dor 0–10 e esforço fechado | must | `FisioCheckinTest` |
| UC-FPP-12 | só itens do programa | must | `FisioCheckinTest` |
| UC-FPP-13 | sinal de alerta avisa o profissional | must | `FisioCheckinTest` |
| UC-FPP-14 | XP por fazer o prescrito e por registrar | must | `FisioJogoTest` |
| UC-FPP-15 | nada por exceder ou por item fora | must | `FisioJogoTest` |
| UC-FPP-16 | reenvio não duplica XP; dor alta também pontua o registro | must | `FisioJogoTest` |
| UC-FPP-17 | nível derivado do XP, sem ranking | should | `FisioJogoTest`, `NivelCalculatorTest` |
| UC-FPP-18 | sequência por dias prescritos; pausa não quebra | must | `FisioJogoTest`, `SequenciaCalculatorTest` |
| UC-FPP-19 | dor alta ou alerta: só a instrução do profissional | must | `FisioPortalSegurancaClinicaTest` |
| UC-FPP-20 | portal não expõe série de dor | must | `FisioPortalSegurancaClinicaTest` |
| UC-FPP-21 | WhatsApp e câmera: opcionais, independentes, carimbados; sem PIN nada se grava | must | `FisioPortalLinkTest` |

---

## UC-FPP-01 · O link abre o PIN, não o prontuário · `must`
- **Persona:** paciente que recebeu o link no WhatsApp.
- **Aceite:** Dado um link válido · Quando é aberto · Então a tela pede o PIN (5 tentativas) e não traz nenhum dado clínico. No banco, só hash do token e do PIN.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-02 · Link ruim é sempre o mesmo link ruim `[must]`
- **Aceite:** Token malformado, com assinatura adulterada, desconhecido, expirado, revogado, ou substituído por um link mais novo · Então a tela mostra o mesmo estado vazio (`link_invalido`), sem dizer qual condição quebrou.
- **Regressão que defende:** oráculo que permite enumerar pacientes.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-03 · Cinco chutes travam `[must]`
- **Aceite:** PIN errado (ou fora do formato de 4 dígitos) conta tentativa e diz quantas restam · na 5ª trava por 30 min (423), e travado recusa até o PIN certo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-04 · A trava acaba · `must`
- **Aceite:** Aos 29 min ainda travado; depois de 30 min o PIN certo entra e o contador zera.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-05 · Consentimento antes de qualquer dado `[must]`
- **Persona:** paciente no primeiro acesso.
- **Aceite:** Dado PIN certo e sem consentimento de teleatendimento na versão vigente · Então a tela pede o consentimento e não mostra queixa, exercícios nem jogo; o check-in é recusado no servidor. Ao aceitar, grava `fisio_consents` com purpose, versão da config (não do corpo), IP e canal `portal`; aí o "Hoje" aparece. Versão antiga não vale.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-06 · Cada consentimento por si · `must`
- **Aceite:** WhatsApp, câmera, imagem, marketing e pesquisa são independentes do teleatendimento; purpose fora da lista é recusado. Revogar WhatsApp não fecha o portal; revogar teleatendimento fecha (o registro fica com `revoked_at`).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-07 · Bloqueado não entra; outra clínica não vaza `[T0]` · `must`
- **Aceite:** Paciente bloqueado vê o estado vazio mesmo com token válido e PIN certo, e não registra check-in. Token da 98 nunca resolve paciente da 99.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php`
- **Status: 🧪**

## UC-FPP-08 · Registrar o dia · `must`
- **Aceite:** Quando o paciente envia "Como foi hoje" · Então grava check-in do dia com business, programa, itens feitos, dor e esforço.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`
- **Status: 🧪**

## UC-FPP-09 · Mandar de novo não duplica · `must`
- **Aceite:** Reenvio no mesmo dia atualiza o check-in (201 → 200); no dia seguinte é outro. A unicidade paciente+data é do banco.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`
- **Status: 🧪**

## UC-FPP-10 · Check-in é telemonitoramento no prontuário · `must`
- **Persona:** invariante legal (Res. COFFITO 619/2025).
- **Aceite:** Cada check-in gera 1 `fisio_records` `kind=telemonitoramento`, `modality=assincrona`, `channel=portal`, assinado. **Decisão:** reenvio idêntico não cria nada; reenvio com relato diferente cria 1 `adendo` com `amends_id` para o telemonitoramento do dia, que fica intacto.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`
- **Status: 🧪**

## UC-FPP-11 · Dor e esforço válidos · `must`
- **Aceite:** Dor fora de 0–10 (−1, 11, 3,5, texto) é recusada e nada é gravado (nem check-in, nem prontuário, nem XP); 0, 10 e ausente são aceitos. Esforço só `facil`, `adequado` ou `dificil`.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`
- **Status: 🧪**

## UC-FPP-12 · Só o que foi prescrito · `must`
- **Aceite:** Item de outro programa ou inexistente → 422, nada gravado; item repetido conta uma vez.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`
- **Status: 🧪**

## UC-FPP-13 · Sinal de alerta chega ao profissional · `must`
- **Aceite:** Qualquer sinal marcado (lista fechada) notifica o profissional do programa no envio e coloca o paciente na fila de atenção na hora (UC-FPN-04). Sinal fora da lista é recusado; sem sinal, ninguém é notificado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`
- **Status: 🧪**

## UC-FPP-14 · XP por cumprir e por registrar · `must`
- **Aceite:** Cada vez feita de um item (até o `times_per_day`) vale o XP de exercício; registrar o dia vale o XP de check-in, 1 vez por dia. O ledger é append-only e a razão é enum fechado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoTest.php`
- **Status: 🧪**

## UC-FPP-15 · Nada por exceder `[must]`
- **Aceite:** Vezes acima do prescrito não dão XP; item fora do programa é recusado e não pontua.
- **Regressão que defende:** jogo premiando sobrecarga.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoTest.php`
- **Status: 🧪**

## UC-FPP-16 · Reenvio não pontua de novo · `must`
- **Aceite:** Reenvio igual não lança nada; reenvio com mais itens lança só a diferença; desmarcar não estorna. Registrar o dia com dor alta também dá o XP do registro.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoTest.php`
- **Status: 🧪**

## UC-FPP-17 · Nível vem do XP · `should`
- **Aceite:** O nível é o maior limiar de `config('fisio.niveis')` alcançado pelo XP total do ledger; não existe coluna editável; não há ranking entre pacientes.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoTest.php` · `Modules/Fisio/Tests/Unit/NivelCalculatorTest.php`
- **Status: 🧪**

## UC-FPP-18 · Sequência por dias prescritos · `must`
- **Aceite:** Folga = ceil(7 ÷ sessões por semana) − 1 dias seguidos sem registro. Dia de pausa (dor ≥ limiar ou sinal de alerta) não soma e não quebra; buraco maior que a folga zera.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioJogoTest.php` · `Modules/Fisio/Tests/Unit/SequenciaCalculatorTest.php`
- **Status: 🧪**

## UC-FPP-19 · Em dia ruim, só a palavra do profissional `[must]`
- **Persona:** invariante Anvisa (não virar SaMD).
- **Aceite:** Dado dor ≥ limiar do programa (padrão 7) ou sinal de alerta · Então a resposta e a tela trazem só `modo=pausa` e a `stop_instruction` escrita pelo profissional — nenhuma conduta calculada, nenhum exercício do dia. Abaixo do limiar e sem sinal, modo normal.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalSegurancaClinicaTest.php`
- **Status: 🧪**

## UC-FPP-20 · Gráfico de dor é do profissional · `must`
- **Aceite:** O payload do portal não contém série histórica de dor (nem chaves, nem datas antigas); a ficha do profissional contém.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalSegurancaClinicaTest.php`
- **Status: 🧪**

## UC-FPP-21 · Consentimentos opcionais, cada um com carimbo · `must`
- **Persona:** paciente no primeiro acesso, escolhendo o que autoriza além do tratamento.
- **Aceite:** Sem PIN aceito na sessão, nenhum consentimento é gravado (403). Com PIN: `camera` e `whatsapp` são aceitos à parte do teleatendimento, cada um com versão da config, canal `portal`, IP e data; aceitar de novo não duplica (200); `aceito` falso é recusado (422). Revogar a câmera não fecha o portal.
- **Regressão que defende:** consentimento "em pacote" (um aceite valendo para tudo); gravação sem sessão do paciente.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalLinkTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Missões e medalhas da Jornada (T-08) — regra ainda não fixada na ADR em forma testável.
- `[BACKLOG]` A tela funciona sem zoom em 360 px e com leitor de tela — sem prova automatizada.
- `[BACKLOG]` Rate limit por IP na rota pública: `throttle:60,1` ligado na US-FISIO-007a (como o Oficina), ainda sem teste que o cite.
- `[BACKLOG]` "Lembrar este aparelho por 30 dias" (etapa 02 T-07a, `fisio_portal_devices`) — fora do corte da US-FISIO-007a.

## Trilha do tempo
- 2026-10-18 · US-FISIO-007a — a tela nasce mínima (`Portal.tsx` + `Portal.charter.md`): link, PIN, consentimentos e lista do dia; check-in, jogo e modo pausa ficam com a US-FISIO-007b/008. UC-FPP-21 (consentimentos opcionais) entra com teste.
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código; consentimentos, telemonitoramento, jogo, sinais de alerta e modo pausa entram pela atualização de escopo do mesmo dia.
