---
id: modules-whatsapp-resources-js-pages-atendimento-caixa-unificada-index-casos
casos: Caixa Unificada V4 — atendimento omnichannel · /atendimento/caixa-unificada
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do atendente + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela concentra a conversa com o cliente de TODOS os canais — se o isolamento por business ou o ACL canal=fila falhar, um tenant lê a conversa do outro. É o comportamento durável que nenhum refactor pode perder.
owner: wagner
last_run: "2026-08-24"
---

# Casos de Uso & Aceite — Caixa Unificada V4

> US-WA-095 (mãe) · US-WA-069 (ACL canal=fila) · US-WA-301/302/303/304/305/306/307/308
> · ADR 0093 (multi-tenant Tier 0) · ADR 0135 (arquitetura omnichannel)
> · ADR 0267 (filas persistidas) · ADR 0268 (broadcast em fases)
>
> UCs derivados do **charter** (`Index.charter.md` — Goals · Non-Goals · Multi-tenant Tier 0 ·
> Automation Anti-hooks) e das US do `memory/requisitos/Whatsapp/SPEC.md`. **Não derivados do
> `.tsx`** — teste que nasce do código é tautológico (lápide §5 2026-06-05).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Onde estes testes rodam.** Toda a suíte é da lane **sqlite** do CI
> (`.github/ci-sqlite-pest.list:110`): o `beforeEach` pula por desenho quando o driver não é
> sqlite (`era-sqlite`, quarentena Onda 2 SDD floor). Medido em 2026-08-24 no CT 100 nas duas
> lanes: em MySQL dá **16 skipped (0 assertions)** — que NÃO é verde, é ausência de medição
> (LC-13) — e em sqlite dá **16 passed (129 assertions)**. O veredito abaixo é o da lane sqlite.
>
> **Por que quase todo UC aqui é Tier 0:** a Caixa lê `conversations`/`messages` de todos os
> canais do business. O isolamento vem do global scope `business_id` (ADR 0093) MAIS o ACL
> canal=fila (US-WA-069) — os dois têm que valer juntos, porque um atendente legítimo do
> business ainda assim não pode ler a fila de um canal que não é dele.

---

## UC-CXU-01 · A caixa abre com as conversas da empresa e a fila já derivada
- **Persona:** atendente do business — abre `/atendimento/caixa-unificada` no começo do turno.
- **Aceite:** Dado um business com canal ativo e conversas · Quando abro a tela · Então o Inertia responde com a lista de conversas do business e cada conversa já vem com a fila derivada (heurística tag→fila), sem eu precisar escolher nada.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-01 · R-WA-CAIXA-UNIF-001 — happy path render com props básicas + queue derivada`.
- **Regressão que defende:** é o **release** do par bite/release desta suíte — sem ele, "403/vazio em tudo" (ex.: derrubar a rota ou o scope) passaria como se fosse segurança. É o caso que continua verde quando os de isolamento falham, provando que eles falham pelo isolamento e não por a tela ter sumido.
- **Status: 🧪** — passa no CT 100 na lane sqlite (16/16, 129 assertions, 2026-08-24); ✅ quando `casos:results` regravar o manifesto.

---

## UC-CXU-02 · A conversa de outro cliente nunca aparece na minha caixa
- **Persona:** atendente do business 1 — outro tenant (business 99) atende os clientes dele na mesma instalação.
- **Aceite:** Dado que existem conversas no business 99 · Quando abro a caixa logado no business 1 · Então nenhuma conversa do 99 aparece na lista, nos contadores ou nos payloads.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-02 · R-WA-CAIXA-UNIF-002 — cross-tenant biz=99 invisível pra biz=1 (Tier 0)`.
- **Regressão que defende:** **Tier 0 IRREVOGÁVEL** (ADR 0093). Uma query desta tela que esqueça o global scope não devolve erro — devolve a conversa do vizinho, com o telefone e o texto do cliente dele. Falha silenciosa e irreversível: o dado já foi lido.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-03 · Atendente sem acesso ao canal não lê a fila daquele canal
- **Persona:** atendente do Suporte — existe também um canal do Financeiro no mesmo business, que não é dele.
- **Aceite:** Dado um atendente SEM `channel_user_access` ativo no canal do Financeiro · Quando abro a caixa · Então as conversas daquele canal não aparecem; e Quando forço `?account_id=` do canal proibido · Então volta **403** (fail-loud, não lista vazia).
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-03 · R-WA-CAIXA-UNIF-003 — user sem ACL no canal NÃO vê convs daquele canal`.
- **Regressão que defende:** US-WA-069. O `business_id` sozinho **não** basta: dentro do mesmo tenant, canal é fila e fila tem dono. Sem este caso, "estou logado no business certo" viraria licença pra ler o atendimento de qualquer departamento. O 403 explícito (em vez de lista vazia) é o que impede o vazamento de virar silêncio.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-04 · A lista de quem pode receber a conversa só traz gente da própria empresa
- **Persona:** atendente — vai passar a conversa adiante e abre o seletor de responsável.
- **Aceite:** Dado operadores cadastrados no business 1 e no business 99 · Quando abro o picker de atribuição · Então só aparecem os operadores do business 1 (com grant ativo OU `whatsapp.access`/`whatsapp.send`).
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-04 · R-WA-CAIXA-UNIF-004 — availableAssignees só lista operadores do business atual (Tier 0)`.
- **Regressão que defende:** US-WA-302 + Tier 0. Vazar o payload de operadores é vazar o **quadro de pessoal** do vizinho (nome de quem trabalha lá) sem nem precisar abrir uma conversa. É PII de terceiro num payload que parece inofensivo.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-05 · Atribuir e remover responsável funciona — e nunca para alguém de fora
- **Persona:** atendente/supervisor — atribui a conversa a um colega e depois desfaz.
- **Aceite:** Dado uma conversa do meu business · Quando atribuo a um operador do mesmo business · Então a conversa passa a constar como dele e cai na aba "Minhas"; Quando removo · Então volta a ficar sem responsável; e Quando tento atribuir a um usuário de OUTRO business · Então volta **422** e nada muda.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-05 · R-WA-CAIXA-UNIF-005 — assign atribui/remove operador + bloqueia cross-tenant (Tier 0)`.
- **Regressão que defende:** US-WA-302. O par de escrita do UC-CXU-04: cobrir só a **leitura** do picker deixaria aberto o vetor que importa — gravar `assigned_user_id` apontando pra fora do tenant, que amarra um registro de um business a um usuário de outro.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-06 · Só template aprovado da própria empresa aparece pra enviar
- **Persona:** atendente — abre os templates no composer pra responder fora da janela de 24h.
- **Aceite:** Dado templates em vários estados (LOCAL/APPROVED, pendente, rejeitado) no meu business e templates de outro business · Quando abro o seletor · Então só aparecem os prontos (LOCAL/APPROVED) do meu business, filtrados pelo provider do canal da conversa.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-06 · R-WA-CAIXA-UNIF-006 — availableTemplates só ready (LOCAL/APPROVED) do business atual (Tier 0)`.
- **Regressão que defende:** US-WA-303 + Tier 0. Template carrega a **copy comercial** do tenant (preço, promoção, tom da marca) — vazar a lista é vazar estratégia. E oferecer template não-aprovado leva o atendente a um envio que a Meta rejeita na hora, com o cliente esperando.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-07 · As filas vêm do banco e são semeadas uma única vez
- **Persona:** [W] — abre a caixa pela primeira vez num business que nunca teve fila configurada.
- **Aceite:** Dado um business sem filas no banco · Quando abro a caixa · Então as filas do `config` são semeadas no banco (uma vez); e Quando abro de novo · Então nada é duplicado e o payload lê do banco, não do config.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-07 · R-WA-CAIXA-UNIF-007 — filas: seed lazy idempotente do config + payload lê DB + Tier 0`.
- **Regressão que defende:** US-WA-301 / ADR 0267. Seed não-idempotente numa rota de **leitura** multiplica filas a cada visita — a tela degrada sozinha, sem ninguém ter editado nada, e a heurística tag→fila passa a apontar pra duplicata.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-08 · Dá pra configurar as filas sem conseguir apagar a fila padrão
- **Persona:** [W] — cria a fila "Orçamento", renomeia outra e tenta apagar a padrão.
- **Aceite:** Dado o painel de filas aberto · Quando crio/edito/apago uma fila do meu business · Então a mudança persiste; e Quando tento apagar a fila **default** · Então a operação é recusada e a fila continua lá.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-08 · R-WA-CAIXA-UNIF-008 — CRUD filas: store/update/destroy + default protegida + Tier 0`.
- **Regressão que defende:** US-WA-301. Apagar a fila default deixa conversa órfã sem destino — e como o `queue_override` guarda **slug** (não FK, de propósito), não há erro de banco pra avisar: a conversa simplesmente some do painel de quem tria.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-09 · Mover a conversa de fila à mão vence a automática — e dá pra voltar atrás
- **Persona:** atendente — a heurística por tag mandou a conversa pra fila errada e ele corrige.
- **Aceite:** Dado uma conversa cuja fila veio da heurística tag→fila · Quando movo pra outra fila · Então o override manual vence a heurística e a conversa mostra "manual"; Quando volto pra automática · Então a heurística passa a valer de novo; e Quando mando um slug que não é fila do business · Então volta **422**.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-09 · R-WA-CAIXA-UNIF-009 — moveQueue: override vence heurística, null volta, slug inválido 422`.
- **Regressão que defende:** US-WA-305. Sem a precedência explícita, re-tagar a conversa desfaria em silêncio a correção feita à mão — o atendente conserta, a máquina desconserta, e ninguém entende por quê. O 422 no slug inválido evita gravar um destino que não existe.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-10 · Começar conversa nova com quem já falou reabre a antiga, não cria outra
- **Persona:** atendente — vai falar com um cliente e não lembra se já existe conversa aberta.
- **Aceite:** Dado um número que nunca conversou · Quando inicio conversa · Então uma nova é criada e abre; Dado um número que **já** conversou · Quando inicio conversa · Então a thread existente **reabre** (nenhuma duplicata); e Quando uso canal inativo ou de outro business · Então volta 403/422.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-10 · R-WA-CAIXA-UNIF-010 — startConversation: cria, reabre (não duplica) + guards canal/phone`.
- **Regressão que defende:** US-WA-307. Thread duplicada parte o histórico do cliente em dois: o atendente responde numa e o cliente respondeu na outra. É o defeito que o cliente sente como "vocês não leram o que eu já falei".
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-11 · O broadcast só conta quem deu opt-in e está dentro da janela
- **Persona:** [W] — prepara um disparo e quer saber, antes, pra quantos ele pode legalmente ir.
- **Aceite:** Dado uma base com contatos com e sem `whatsapp_opt_in_at` · Quando peço o pre-flight do broadcast · Então o total só conta quem tem opt-in, separa quem está fora da janela de 24h (só HSM) e grava um rascunho auditável com o snapshot congelado no servidor.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-11 · R-WA-CAIXA-UNIF-011 — broadcast pre-flight: opt-in LGPD + janela 24h + draft auditável`.
- **Regressão que defende:** US-WA-306 / ADR 0268 + **LGPD**. Contar quem não deu opt-in é a diferença entre uma campanha e um disparo ilegal — e o snapshot congelado é o que permite provar depois **para quem** o envio foi autorizado. O disparo em massa segue Non-Goal desta fase (botão disabled até a fase 2, gate [W]).
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-12 · A IA da thread não gasta token em teste e respeita o ACL do canal
- **Persona:** atendente — pede um resumo da conversa antes de assumir o atendimento.
- **Aceite:** Dado `copiloto.dry_run` ligado · Quando peço resumo/pergunta/sugestão · Então volta uma fixture **sem** chamar o provider (custo zero); e Dado uma conversa de canal a que não tenho acesso · Quando peço a IA · Então volta erro fail-loud, não o conteúdo.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-12 · R-WA-CAIXA-UNIF-012 — inbox AI: dry_run devolve fixture sem LLM + ACL canal fail-loud`.
- **Regressão que defende:** dois riscos num caso só. **Custo:** sem o `dry_run`, cada rodada de CI queimaria token de verdade. **LGPD/Tier 0:** o endpoint de IA lê o transcript inteiro — se ele não repetir o ACL do canal, vira uma porta lateral que devolve, resumida, exatamente a conversa que o UC-CXU-03 bloqueia na porta da frente.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-13 · O canal vivo aparece como ativo, com a contagem real
- **Persona:** atendente — olha os filtros de canal pra achar de onde a conversa veio.
- **Aceite:** Dado o canal WhatsApp ativo (provider `whatsapp_whatsmeow`) com conversas · Quando abro os filtros de canal · Então ele aparece como **ativo** com a contagem real, e filtrar por ele traz as conversas daquele canal (não cai em "em breve").
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-13 · R-WA-CAIXA-UNIF-013 — canal whatsmeow ativo vira chip ativo com count real (PARTE 4)`.
- **Regressão que defende:** regressão **real já ocorrida** (2026-06-16): o payload listava o provider morto `whatsapp_baileys` enquanto o canal vivo era `whatsapp_whatsmeow` — todos os chips caíam em "em breve" e **o canal de onde as conversas realmente chegam ficava escondido**. O helper de teste semeava o type morto e mascarava o bug.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-14 · Saldo e histórico do cliente vêm do ERP — e sem cliente cadastrado não quebra
- **Persona:** atendente — antes de responder, quer saber se aquele cliente tem coisa em aberto.
- **Aceite:** Dado uma conversa vinculada a um Contact do CRM · Quando abro a thread · Então a sidebar mostra o a receber em aberto e o histórico de pedidos/LTV daquele contato, escopados pelo meu business; e Dado uma conversa **sem** Contact vinculado · Quando abro · Então a tela renderiza normalmente com o bloco vazio (sem erro).
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-14 · R-WA-CAIXA-UNIF-013 — customerContext agrega Saldo+Histórico do contact (Tier 0) + fallback sem contact`.
- **Regressão que defende:** US-WA-308 + Tier 0. Este payload cruza `transactions` do ERP com a conversa — o eixo mais sensível da tela, porque mostra **dinheiro** ao lado do telefone do cliente. O fallback sem Contact importa porque a maioria das conversas novas chega antes de existir cadastro: sem ele, a thread quebraria justamente no primeiro contato.
- **Nota (dívida preexistente, fora do escopo deste PR):** o id `R-WA-CAIXA-UNIF-013` está **duplicado** no arquivo de teste (linhas 401 e 977) — este UC ancora no da linha 977, o UC-CXU-13 no da linha 401. A citação aqui é pelo **título completo**, que é distinto. Renumerar é conserto próprio.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-15 · "Mídia nas últimas 24h" lê o schema novo, não a tabela legada
- **Persona:** atendente — filtra as conversas que receberam foto ou documento no último dia.
- **Aceite:** Dado conversas com mídia recente gravada no schema omnichannel (`messages`) · Quando aplico o filtro de mídia 24h · Então elas aparecem — mesmo que nada exista na tabela legada `whatsapp_messages`.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-15 · R-WA-CAIXA-UNIF-014 — media_inbound_24h filtra pela relação messages (schema novo), não whatsapp_messages legacy`.
- **Regressão que defende:** ADR 0135 (schema polimórfico, US-WA-056). Durante a coexistência dos dois schemas, um filtro que ainda consulte a tabela legada devolve **lista vazia** em vez de erro — o atendente conclui que não há mídia, quando há. Falha que se disfarça de resposta.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## UC-CXU-16 · Quando o canal cai, todo mundo do business vê o aviso
- **Persona:** qualquer atendente com `whatsapp.access` — o WhatsApp da empresa deslogou e ninguém percebeu.
- **Aceite:** Dado um canal com saúde `disconnected`/`banned` · Quando abro a caixa · Então o banner de canal caído aparece pra **qualquer** conta do business (não só admin com grant), com o CTA de reconectar; e Dado que os canais estão saudáveis · Então o banner não aparece; tudo escopado por `business_id`.
- **Teste:** `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` — `UC-CXU-16 · R-WA-CAIXA-UNIF-015 — canal caído entra business-wide (sem grant) + saudável fora + Tier 0`.
- **Regressão que defende:** **incidente real** (US-WA-308/309, 2026-06-18): o canal 11 deslogou às 07:50 sem webhook, `channel_health` continuou `healthy`, a Caixa não avisou e a linha ficou caída ~3h sem ninguém ver. A primeira versão do banner filtrava por ACL de canal — mas um business pode não ter grant nenhum, e aí **ninguém** via o aviso. Direção [W] 2026-06-19: "qualquer conta pode ver". O caso do canal saudável é o controle negativo — sem ele, um banner sempre-ligado passaria como se estivesse funcionando.
- **Status: 🧪** — passa no CT 100 na lane sqlite (2026-08-24); ✅ com o manifesto regravado.

---

## Backlog (prosa honesta — sem UC até ganhar teste que o cite)

O charter descreve estes comportamentos e eles existem na tela, mas **não têm teste** hoje —
então não viram UC (G-2 pune UC órfão, e UC sem prova é afirmação, não contrato):

- [BACKLOG] Real-time: Centrifugo `omnichannel:business:{id}` + polling 5s SEMPRE em paralelo, com pausa quando a aba está inativa (US-WA-066 — cliente real cancelou contrato por mensagem perdida).
- [BACKLOG] `preserveScroll` + `preserveState` em todo `router.reload` — sem eles a thread pula quando chega mensagem (US-WA-068).
- [BACKLOG] Switch de conversa recarrega só `thread`+`messages` no `only:[]`, nunca a lista inteira (lição de performance D-14).
- [BACKLOG] Atalhos de teclado J/K (navegar), `/` (buscar), E (resolver), A (aguardando), ⌘⇧N (toggle Resp/Nota) — ignorando quando o foco está em input/textarea.
- [BACKLOG] As 7 abas de status (`all`/`unread`/`assigned`/`bot`/`awaiting_human`/`resolved`/`archived`) filtram por `?tab=` e mapeiam o `?status=` legado.
- [BACKLOG] Canal em homologação (`status != active`) vira preview-only: banner amarelo na thread, chip "em breve" na lista e composer desabilitado em modo cliente (nota interna segue permitida).
- [BACKLOG] Macros `/` com autocomplete inline e variáveis `{{nome}}`/`{{telefone}}`/`{{operador}}` com preview resolvido — nota interna mantém o literal.
- [BACKLOG] SLA pill (75% âmbar, estourado vermelho) só conta quando o cliente falou por último.
- [BACKLOG] Favoritos por usuário em `localStorage`, sem DB (anti-hook do charter) — favoritas ordenam no topo.
- [BACKLOG] Transcript imprimível e modo apresentação escondem notas internas por padrão.

> Os de frontend (atalhos, abas, preview-only, SLA, favoritos, transcript) pedem **e2e Playwright**,
> não Pest de controller — a lane hoje cobre só o payload do `CaixaUnificadaController`. Os de
> real-time pedem um harness de Centrifugo que não existe no CI.
