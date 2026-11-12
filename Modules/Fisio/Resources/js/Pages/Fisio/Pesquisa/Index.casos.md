---
id: modules-fisio-pages-fisio-pesquisa-index-casos
casos: Fisio · Área do estudo · /fisio/pesquisa
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do pesquisador + critério de aceite verificável (Dado/Quando/Então)
por_que: estudo observacional da clínica com a universidade (etapa 07); o comitê de ética exige consentimento por estudo, código sem identificação e registro de quem saiu.
owner: wagner
last_run: "2026-11-11"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL do Fisio rodar FisioEstudoTest e FisioPesquisaTelaTest."
related_us: []
---

# Casos de Uso & Aceite — Fisio · Área do estudo (`/fisio/pesquisa`)

> **Âncora:** etapa 07 do Fisio §4.2 e §4.3 (`memory/requisitos/Fisio/etapas/07-pesquisa-fibromialgia.md`), ADR 0437 §5 e §7, e o protótipo `Pesquisa.dc.html`. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FES-01 | só o pesquisador cria estudo; instrumento fora da lista é recusado | must | `FisioEstudoTest` |
| UC-FES-02 | código aleatório sem nome nem CPF; aceite do TCLE do estudo | must | `FisioEstudoTest` |
| UC-FES-03 | saída com tipo e motivo, uma vez, sem apagar | must | `FisioEstudoTest` |
| UC-FES-04 | pendência sai do momento, da entrada e da resposta na janela | must | `FisioEstudoTest` |
| UC-FES-05 | revogar pelo portal tira do estudo na data | must | `FisioEstudoTest` |
| UC-FES-06 | estudo, paciente e participante de outra clínica: 404 | must | `FisioEstudoTest` |
| UC-FES-07 | a tela abre só para `fisio.pesquisa`; nome só para quem atende | must | `FisioPesquisaTelaTest` |
| UC-FES-08 | a tela não lista estudo de outra clínica | must | `FisioPesquisaTelaTest` |
| UC-FES-09 | crise por mais de 48 h vira evento adverso | must | `FisioEventoAdversoTest` |
| UC-FES-10 | dor no limiar em vigor vira evento adverso, uma vez | must | `FisioEventoAdversoTest` |
| UC-FES-11 | revisão com nota; sem gravidade; outra clínica 404 | must | `FisioEventoAdversoTest` |
| UC-FES-12 | momentos em formato longo, sem identificação; não respondido é NA | must | `FisioExportacaoEstudoTest` |
| UC-FES-13 | série diária: dia sem check-in é NA, dor zero é 0 | must | `FisioExportacaoEstudoTest` |
| UC-FES-14 | só o pesquisador exporta; cada download no log; outra clínica 404 | must | `FisioExportacaoEstudoTest` |
| UC-FES-15 | travamento com motivo; depois, justificativa e exportação congelada | must | `FisioExportacaoEstudoTest` |
| UC-FES-16 | assistente desligado por padrão; só a clínica liga | must | `FisioAssistentePesquisaTest` |
| UC-FES-17 | o modelo recebe só dado sem identificação | must | `FisioAssistentePesquisaTest` |
| UC-FES-18 | sugestão nasce pendente; aceitar grava quem e quando | must | `FisioAssistentePesquisaTest` |
| UC-FES-19 | nada sai da área do estudo; outra clínica 404 | must | `FisioAssistentePesquisaTest` |
| UC-FES-20 | questionário aparece pelo nome curto, nunca pelo código interno | should | `FisioPesquisaTelaTest` |

---

## UC-FES-01 · Só o pesquisador cria estudo · `must`
- **Persona:** pesquisadora responsável cadastrando o estudo aprovado pelo CEP.
- **Aceite:** Sem `fisio.pesquisa`, criar dá 403. Com ela, o estudo nasce `ativo` na empresa da sessão, com os momentos na ordem enviada. Instrumento fora da lista liberada é recusado (422).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEstudoTest.php`
- **Status: 🧪**

## UC-FES-02 · Incluir com o TCLE do estudo `[T0]` · `must`
- **Aceite:** Sem marcar o TCLE assinado, 422. Incluir gera código `P-` + 6 caracteres aleatórios e grava o consentimento `pesquisa` com o estudo, a versão do TCLE do estudo e canal `clinica`. Incluir de novo é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEstudoTest.php`
- **Status: 🧪**

## UC-FES-03 · Saída registrada, nunca apagada · `must`
- **Aceite:** Sem motivo, 422. Com tipo e motivo, a saída grava data, tipo e motivo; segunda saída é recusada; apagar a linha lança erro.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEstudoTest.php`
- **Status: 🧪**

## UC-FES-04 · Pendência derivada · `must`
- **Aceite:** Dado momento no dia 28 ± 7 com EVA e RMDQ · Quando só a EVA foi respondida na janela · Então a pendência lista RMDQ, `aberto` na janela e `vencido` depois dela; resposta fora da janela não conta. Quem saiu não tem pendência.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEstudoTest.php`
- **Status: 🧪**

## UC-FES-05 · Revogar pelo portal tira do estudo · `must`
- **Aceite:** O paciente revoga a pesquisa no portal · Então o participante sai naquela data com tipo `revogacao` e some das pendências.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEstudoTest.php`
- **Status: 🧪**

## UC-FES-06 · Outra clínica não existe `[T0]` · `must`
- **Aceite:** Paciente da 99 não entra em estudo da 98; pesquisador da 98 não inclui em estudo da 99 nem registra saída de participante da 99 (404); pendências da 98 não trazem paciente da 99.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEstudoTest.php`
- **Status: 🧪**

## UC-FES-07 · Quem vê o quê `[T0]` · `must`
- **Aceite:** Sem `fisio.pesquisa`, a tela dá 403. Com `fisio.pesquisa` sem `fisio.editar`, os participantes vêm só com código (`nome` nulo) e a lista de pacientes para incluir vem vazia. Com as duas, o nome aparece.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPesquisaTelaTest.php`
- **Status: 🧪**

## UC-FES-08 · Lista só da clínica `[T0]` · `must`
- **Aceite:** A 98 não vê estudo da 99 na lista, e abrir o estudo da 99 pela URL dá 404.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPesquisaTelaTest.php`
- **Status: 🧪**

## UC-FES-09 · Crise por mais de 48 h · `must`
- **Persona:** pesquisadora acompanhando a segurança do estudo (etapa 07 §4.2, P-06).
- **Aceite:** Dado participante com crise no check-in em 3 dias seguidos · Então nasce um evento `crise_48h` que começa no 1º dia; se a crise continua, o fim acompanha e não nasce outro. Com 2 dias, nada. Paciente fora do estudo não gera evento.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEventoAdversoTest.php`
- **Status: 🧪**

## UC-FES-10 · Alerta de dor · `must`
- **Aceite:** Dor no limiar em vigor do programa (a mesma regra que tira o jogo da tela) vira um evento `alerta_dor` com a dor e o limiar; reenviar o check-in não duplica; dor abaixo não gera nada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEventoAdversoTest.php`
- **Status: 🧪**

## UC-FES-11 · Revisão é da pesquisadora `[T0]` · `must`
- **Aceite:** O registro não tem gravidade. A pesquisadora revisa com nota obrigatória (quem e quando ficam gravados). Evento de estudo da 99 dá 404 para a 98 e não aparece na lista dela.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioEventoAdversoTest.php`
- **Status: 🧪**

## UC-FES-12 · Momentos sem identificação `[T0]` · `must`
- **Persona:** universidade recebendo o arquivo do estudo (etapa 07 §4.4, P-07).
- **Aceite:** O CSV de momentos tem uma linha por participante × momento × questionário, com o código, a versão do instrumento, o escore e o dia do estudo da resposta (a mais próxima do dia alvo na janela). Não respondido sai `NA`. O arquivo não tem nome, telefone nem data do calendário.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioExportacaoEstudoTest.php`
- **Status: 🧪**

## UC-FES-13 · Ausente é ausente · `must`
- **Aceite:** Na série diária, dia sem check-in sai `NA` em todas as colunas; dor zero sai `0`. Itens feitos e prescritos saem do check-in e do programa do dia.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioExportacaoEstudoTest.php`
- **Status: 🧪**

## UC-FES-14 · Quem exporta e o rastro `[T0]` · `must`
- **Aceite:** Sem `fisio.pesquisa`, 403. Cada download grava `estudo_exportado` no activity_log com o estudo e o arquivo. Estudo da 99 dá 404 para a 98.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioExportacaoEstudoTest.php`
- **Status: 🧪**

## UC-FES-15 · Travamento · `must`
- **Aceite:** Travar exige motivo e é uma vez só. Depois, incluir, registrar saída ou revisar evento sem `justificativa` dá 422; com ela, a mudança passa e o activity_log grava `estudo_alterado_apos_travamento`. Dado gravado depois do travamento não entra na exportação.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioExportacaoEstudoTest.php`
- **Status: 🧪**

## UC-FES-16 · Desligado até a clínica ligar · `must`
- **Persona:** pesquisadora que quer ajuda para conferir o dado (etapa 07 P-08; decisão [W] 2026-11-11: "seria um auxílio para pesquisador, ele confirma se quiser").
- **Aceite:** Sem ligar, pedir sugestões dá 422 e o modelo não é chamado. Só quem tem `fisio.pesquisa` e `fisio.editar` liga (o pesquisador externo leva 403). Ligar na 98 não liga na 99.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAssistentePesquisaTest.php`
- **Status: 🧪**

## UC-FES-17 · Sem identificação para o modelo `[T0]` · `must`
- **Aceite:** O que vai ao modelo são os mesmos CSVs da exportação: código do participante e dia do estudo; nem nome, nem telefone, nem data.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAssistentePesquisaTest.php`
- **Status: 🧪**

## UC-FES-18 · A pesquisadora decide · `must`
- **Aceite:** Cada sugestão (qualidade, resumo, padrão) nasce `pendente`. Aceitar ou descartar grava quem e quando, uma vez só. Tipo fora da lista é ignorado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAssistentePesquisaTest.php`
- **Status: 🧪**

## UC-FES-19 · Fica na área do estudo `[T0]` · `must`
- **Aceite:** A sugestão não aparece na ficha do paciente nem no portal (RDC 657/2022; ADR 0437 §5). Pedir sugestões em estudo da 99 dá 404 para a 98.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAssistentePesquisaTest.php`
- **Status: 🧪**

## UC-FES-20 · Nome do questionário, não o código · `should`
- **Aceite:** No diálogo Novo estudo e na aba Pendências cada questionário aparece pelo nome curto (o trecho antes do " — " no catálogo): "Sentar e levantar em 30 segundos", "FIQR", "EVA/END". Nenhum rótulo é vazio ou tem cara de código (`_`).
- **Regressão que defende:** `SENTAR_LEVANTAR_30S` na tela (smoke de staging de 2026-11-11).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPesquisaTelaTest.php`
- **Status: 🧪**

## Trilha do tempo
- 2026-11-11 · UC-FES-20 — rótulo curto do questionário no Novo estudo e nas Pendências (achado do smoke de staging).
- 2026-11-11 · etapa 07 P-08 — aba Assistente: sugestões de qualidade do dado, resumo descritivo e padrões antes da crise, sobre o dado sem identificação; a pesquisadora aceita ou descarta. Desligado por padrão.
- 2026-11-11 · etapa 07 P-07 — aba Exportação: 4 CSV (momentos, série diária, participantes, dicionário), `NA` para ausente, dia do estudo no lugar da data, travamento com justificativa, tudo no activity_log.
- 2026-11-11 · etapa 07 P-06 — aba Eventos adversos: crise por mais de 48 h e alerta de dor, registrados a partir do check-in. O software não classifica gravidade.
- 2026-11-11 · etapa 07 P-04 — tela nasce com participantes e pendências. Eventos adversos (P-06), exportação (P-07) e assistente (P-08) entram com as abas do protótipo.
