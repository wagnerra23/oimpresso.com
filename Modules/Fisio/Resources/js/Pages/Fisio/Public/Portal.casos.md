---
id: modules-fisio-pages-fisio-public-portal-casos
casos: Fisio · Portal do paciente · /fisio/p/{token}
irmaos: Portal.charter.md (lei) · Portal.tsx (tela) + _components/Entrada.tsx, Hoje.tsx, Checkin.tsx · precedente resources/js/Pages/OficinaAuto/AprovacaoPublica.casos.md
tecnica: Caso de uso = narrativa do paciente + critério de aceite verificável (Dado/Quando/Então)
por_que: o portal é público na internet e mostra dado de saúde. Sem PIN/lockout é força bruta; sem consentimento é LGPD; sem telemonitoramento o check-in não vale como prontuário (Res. COFFITO 619/2025); conduta calculada pelo software vira dispositivo médico (Anvisa/SaMD); XP que premia exceder o prescrito incentiva lesão.
owner: wagner
last_run: "2026-10-19"
last_run_ci: "_pendente_ — a US-FISIO-007b põe FisioCheckinTest, FisioJogoTest, FisioPortalSegurancaClinicaTest e FisioPortalAparelhoTest na lane Verticais (MySQL); o veredito por UC entra quando o manifesto da lane for regenerado."
related_us: [US-FISIO-007, US-FISIO-008]
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
| UC-FPP-22 | "lembrar este aparelho" dispensa o PIN; só hash no banco | must | `FisioPortalAparelhoTest` |
| UC-FPP-23 | aparelho lembrado cai com outro link, prazo vencido, revogação ou link novo | must | `FisioPortalAparelhoTest` |
| UC-FPP-24 | dia de crise só com versão escrita pelo profissional; conta para a sequência | must | `FisioPortalAparelhoTest` |
| UC-FPP-25 | Hoje: dose, cuidados, jogo e só o relato de hoje; nada de outra clínica | must | `FisioPortalAparelhoTest` |
| UC-FPP-26 | app instalável: manifesto por link, sem o nome da clínica | must | `FisioPortalAppTest` |
| UC-FPP-27 | programa abre sem rede só em aparelho lembrado | must | `FisioPortalAppTest` |
| UC-FPP-28 | service worker nunca guarda POST nem cookie e apaga link que deixou de abrir | must | `FisioPortalAppTest` |
| UC-FPP-29 | app da Google Play abre em `/fisio/app`, que só pede o link da clínica | must | `FisioPortalAppTest` |

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

## UC-FPP-22 · Lembrar este aparelho · `must`
- **Persona:** paciente que abre o lembrete todo dia e não quer digitar o PIN de novo (etapa 02 T-07a).
- **Aceite:** Dado o PIN certo com "Lembrar este aparelho" marcado · Então o servidor grava em `fisio_portal_devices` só o sha256 de um segredo aleatório, amarrado à linha do token e ao business, válido por `config('fisio.portal.lembrar_aparelho_dias')` (30), e devolve o segredo num cookie httpOnly cifrado. Num aparelho sem sessão, esse cookie abre direto o Hoje. Sem cookie, ou com cookie forjado, o PIN volta. Sem marcar, nada é gravado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`
- **Status: 🧪**

## UC-FPP-23 · O aparelho lembrado cai quando deve `[T0]` · `must`
- **Aceite:** O cookie de um link não abre o link de outro paciente. Passado o prazo, o PIN volta mesmo com o link ainda válido. O profissional revogar o link, ou gerar outro, revoga os aparelhos lembrados (`revoked_at`); o link antigo cai no estado vazio.
- **Regressão que defende:** celular perdido ou emprestado continuar com acesso depois de a clínica trocar o link.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`
- **Status: 🧪**

## UC-FPP-24 · Hoje estou em crise · `must`
- **Persona:** paciente com dor crônica (protocolo 11) num dia ruim.
- **Aceite:** O botão só aparece quando o profissional escreveu a versão de crise no programa (`crisis_items`). Sem ela, `crisis=true` é recusado (422) e nada é gravado. Com ela, o check-in grava `crisis=true` e o dia conta para a sequência. A versão de crise é texto do profissional, e o software não a escolhe.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`
- **Status: 🧪**

## UC-FPP-25 · A tela Hoje mostra o que o paciente precisa, e só isso `[T0]` · `must`
- **Aceite:** Com consentimento: itens do programa ativo com séries, repetições, tempo, vezes por dia e os cuidados da biblioteca; estado do jogo (`xp`, `nivel`, `xp_no_nivel`, `xp_para_proximo`, `sequencia`, `em_pausa`, `dias_cumpridos`, sem ranking); `relato` só do dia corrente, para corrigir. O token da 98 não grava check-in em paciente da 99, mesmo com `business_id`/`patient_id` no corpo, e nada da 99 aparece no payload.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`
- **Status: 🧪**

## UC-FPP-26 · O portal vira app na tela inicial · `must`
- **Persona:** paciente que abre o programa todo dia pelo celular.
- **Aceite:** `GET /fisio/p/{token}/manifest.webmanifest` devolve um manifesto com `start_url` igual ao link do paciente, `scope` `/fisio/p/`, `display` `standalone`, ícones 192 e 512 (com um `maskable`) que existem em `public/`. O manifesto é público e vem antes do PIN: não leva o nome da clínica nem dado do paciente.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAppTest.php`
- **Status: 🧪**

## UC-FPP-27 · Sem internet, o programa abre só em aparelho lembrado `[T0]` · `must`
- **Aceite:** Na tela Hoje de aparelho lembrado, a resposta traz `X-Fisio-Offline: guardar` e o service worker guarda a tela para abrir sem rede. Com o PIN só na sessão vem `nao-guardar`; antes do PIN, no consentimento e com link inválido o cabeçalho não vem. Sem `guardar`, o service worker apaga a cópia daquele link: a cópia nunca abre sem PIN depois de a sessão acabar nem depois de a clínica revogar o link.
- **Regressão que defende:** celular emprestado continuar mostrando o programa de outra pessoa sem PIN.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAppTest.php`
- **Status: 🧪**

## UC-FPP-28 · O service worker obedece o servidor · `must`
- **Aceite:** Só GET passa pelo cache; PIN, consentimento e check-in vão direto à rede. O `set-cookie` é removido antes de guardar. O registro tem escopo `/fisio/p/`.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAppTest.php`
- **Status: 🧪**


## UC-FPP-29 · O app da loja abre sem link e só orienta · `must`
- **Persona:** paciente que instalou o app pela Google Play e tocou no ícone.
- **Aceite:** `GET /fisio/app` (sem token) devolve a página do portal com `erro: sem_link`: só o texto pedindo para abrir o link que a clínica mandou pelo WhatsApp. Não consulta o banco e não manda dado de paciente, de clínica nem do jogo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAppTest.php`
- **Status: 🧪**

## UC-FPP-30 · Reenvio da fila com a mesma chave não grava duas vezes `[T0]` · `must`
- **Persona:** paciente que fez o check-in em casa, com a rede caindo.
- **Aceite:** Cada envio do check-in leva uma `chave`. Se a mesma chave volta (a fila reenviou porque a resposta se perdeu), o servidor responde 200 com o check-in do dia e não grava nada de novo: continua 1 linha em `fisio_checkins`, 1 telemonitoramento, nenhum adendo e nenhum XP a mais. Chave nova no mesmo dia segue a regra de sempre (atualiza o dia).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalFilaOfflineTest.php`
- **Status: 🧪**

## UC-FPP-31 · Só aparelho lembrado guarda check-in no aparelho `[T0]` · `must`
- **Aceite:** A tela Hoje traz `guardar_offline: true` só em aparelho lembrado; com o PIN só na sessão vem `false` e o portal não guarda o check-in sem rede (mostra só o aviso de sem internet). Um envio marcado `offline: true` vindo de aparelho não lembrado é recusado (403 `fila_so_aparelho_lembrado`) e nada é gravado; a fila apaga o item.
- **Regressão que defende:** dado clínico (dor, sinal de alerta) ficando num celular emprestado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalFilaOfflineTest.php`
- **Status: 🧪**

## UC-FPP-32 · Check-in guardado de outro dia não cai no dia errado `[T0]` · `must`
- **Aceite:** O envio leva o `dia` em que o paciente fez o check-in. Se não for o dia de hoje no fuso da clínica, o servidor recusa (422 `dia_passou`) e não grava nada: o relato de ontem nunca vira o check-in de hoje. A fila apaga o item.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalFilaOfflineTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Missões e medalhas da Jornada (T-08) — regra ainda não fixada na ADR em forma testável.
- `[BACKLOG]` A tela funciona sem zoom em 360 px e com leitor de tela — sem prova automatizada.
- `[BACKLOG]` Rate limit por IP na rota pública: `throttle:60,1` ligado na US-FISIO-007a (como o Oficina), ainda sem teste que o cite.
- `[BACKLOG]` Check-in feito offline num dia e enviado só no dia seguinte: hoje é recusado (UC-FPP-32) e se perde. Aceitar "ontem" mexe na sequência do jogo (dá para salvar um dia esquecido) — decisão [W].
- `[BACKLOG]` Convite "Adicionar à tela inicial" a partir do 3º dia cumprido (Android pelo convite do navegador; iPhone com passo a passo) — no código (`_components/Instalar.tsx`), sem prova automatizada.
- `[BACKLOG]` Aviso por push para quem instalou (iOS 16.4+) — exige chave VAPID e biblioteca nova (ADR).
- `[BACKLOG]` Aviso fixo "Se for uma emergência, ligue 192" no dia de pausa — segurado pela ADR 0437 §14 nº 9 até parecer regulatório.
- `[BACKLOG]` Crise por mais de 3 dias seguidos avisar o profissional (protocolo 11) — sem regra testável no AtencaoDetector ainda.

## Trilha do tempo
- 2026-10-19 · T-13 (parte 2) — fila offline do check-in no aparelho lembrado (`portal.ts`), reenvio ao abrir a tela e quando a internet volta, chave por envio (`fisio_checkins.client_key`). UC-FPP-30..32 entram com `FisioPortalFilaOfflineTest`.
- 2026-10-19 · T-13 (parte 1) — app instalável: manifesto por link, ícones, service worker que guarda a tela do dia só em aparelho lembrado, convite de instalação. UC-FPP-26..28 entram com `FisioPortalAppTest`.
- 2026-10-19 · US-FISIO-007b — check-in grava o dia, telemonitoramento/adendo, sinal de alerta notifica, modo pausa só com a instrução do profissional; tela desenhada (Entrada, Hoje, Check-in, Crise) a partir do canvas Prescreve Pro; "lembrar este aparelho" (`fisio_portal_devices`). UC-FPP-22..25 entram com `FisioPortalAparelhoTest`.
- 2026-10-18 · US-FISIO-007a — a tela nasce mínima (`Portal.tsx` + `Portal.charter.md`): link, PIN, consentimentos e lista do dia; check-in, jogo e modo pausa ficam com a US-FISIO-007b/008. UC-FPP-21 (consentimentos opcionais) entra com teste.
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código; consentimentos, telemonitoramento, jogo, sinais de alerta e modo pausa entram pela atualização de escopo do mesmo dia.
