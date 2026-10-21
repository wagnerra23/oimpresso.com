---
id: resources-js-pages-sells-show-casos
casos: Detalhe da venda · /sells/{id}
irmaos: Show.charter.md (lei) · tests/Feature/Sells/SellsShowContratoTest.php (defesa)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-21"
---

# Casos de Uso & Aceite — Detalhe da venda

> Tela **Tier-0 topo do débito** (`node scripts/qa/exposicao-tier0.mjs` em 2026-07-27:
> `exposure_score 11`, categorias `dinheiro,estoque,fiscal` — 1ª de 89 telas quentes sem teste de
> comportamento). Os 3 arquivos que pareciam cobri-la (`Wave1ShowBaselineTest`,
> `Wave1ShowInertiaTest`, `SellsShowCoworkTest`) são **estruturais**: leem o `.tsx`/Controller com
> `file_get_contents` e casam string. Provam que o código está ESCRITO — nenhum prova que a
> resposta faz o que o charter promete.
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> **De onde os casos saem (ordem de fonte, `memory/how-trabalhar.md`):** `Show.charter.md`
> §Goals/§Non-Goals · `memory/requisitos/Sells/RUNBOOK-show.md` §2/§9/§10 ·
> `memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md` §CU-07 · ADR 0093 · ADR 0143 · ADR 0101.
> O `SellController@show` foi lido só pra **confirmar** — nenhum caso deriva dele (teste derivado
> do código é tautológico, `memory/proibicoes.md` §5 2026-06-05).

---

## UC-VSHOW-01 · Abrir uma venda que não é da minha empresa não mostra nada
- **Persona:** qualquer operador — o isolamento entre empresas não pode depender de ninguém "não digitar o id errado".
- **Aceite:** Dado uma venda que pertence a OUTRO business · Quando abro `/sells/{id}` · Então recebo 404 e nenhum dado da venda alheia viaja no payload.
- **Âncora:** `Show.charter.md` §Goals ("Multi-tenant Tier 0: scope `business_id` no controller") + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL) + `RUNBOOK-show.md` §10 ("NÃO usar `withoutGlobalScopes`").
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php` — lane `sells-pest.yml` (MySQL real, biz=1 vs biz=2).
- **Status: 🧪** — **recibo:** `vendor/bin/pest tests/Feature/Sells/SellsShowContratoTest.php` com `DB_CONNECTION=mysql` na lane `sells-pest.yml`, [run 30292772351](https://github.com/wagnerra23/oimpresso.com/actions/runs/30292772351) (2026-07-27): **7 passed, 37 assertions**, zero skip. Continua 🧪 (não ✅) porque o `casos-results-publish` colhe JUnit só do último run VERDE de **main** — sobe a ✅ quando o manifesto G-7 regravar pós-merge. Sem fingir prova.

---

## UC-VSHOW-02 · Quem não tem direito de ver venda não abre a venda
- **Persona:** Larissa configurando perfis — um usuário de estoque/produção não deve enxergar o financeiro da venda.
- **Aceite:** Dado um usuário do MESMO business sem `sell.view`, sem `direct_sell.access` e sem `view_own_sell_only` · Quando abro `/sells/{id}` · Então a venda **não** é entregue (403 do gate, ou 302/401 da camada de auth) — nunca 200.
- **Âncora:** `Show.charter.md` §Goals ("Permission gate: `sell.view` OR `direct_sell.access` OR `view_own_sell_only`") + `RUNBOOK-show.md` §2 pré-condições / §9 DoD.
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php` — cria user próprio, sem a role `Admin#{biz}` (senão o `Gate::before` do `AuthServiceProvider:41` liberaria tudo e o caso mediria o cenário errado).
- **Status: 🧪** — mesma condição do UC-VSHOW-01.

---

## UC-VSHOW-03 · Vendedor com acesso restrito só abre as próprias vendas
- **Persona:** vendedor com comissão — vê o que vendeu, não a carteira dos colegas.
- **Aceite:** Dado um usuário que tem **apenas** `view_own_sell_only` · Quando abro uma venda criada por OUTRO usuário do mesmo business · Então recebo 404 (a venda não é resolvida pra mim).
- **Âncora:** `Show.charter.md` §Goals (mesma linha do gate) + `RUNBOOK-show.md` §2 (a restrição `created_by` faz parte da policy legacy preservada na migração).
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php` — pré-condições provam que o user tem SÓ a permissão restrita e que a venda é mesmo de outro criador.
- **Status: 🧪** — mesma condição do UC-VSHOW-01.

---

## UC-VSHOW-04 · Os números de dinheiro da venda são os do banco
- **Persona:** Larissa / Kamila — "quanto essa venda ainda deve?" respondido pela tela, sem conferir no relatório.
- **Aceite:** Dado uma venda de R$ 100,00 com dois pagamentos registrados (R$ 30,00 + R$ 25,00) e `payment_status = partial` · Quando abro `/sells/{id}` · Então o cabeçalho traz **Total** = 100,00, **Pago** = 55,00 (a soma real dos pagamentos), **Status** = parcial, e **Falta** fecha em 45,00.
- **Âncora:** `Show.charter.md` §Goals ("4 KPIs grandes (canon V2): Total / Pago / Falta / Status pgto") + REGRA MESTRE valor/estoque de [`memory/proibicoes.md`](../../../../memory/proibicoes.md). "Falta" é derivada no front (`Show.tsx:303 (verificado@d4afe95)`), então o contrato do backend é Total + Pago + Status coerentes.
- **Regressão que defende:** um KPI "Pago" que não some os pagamentos faz a tela **mentir sobre a dívida do cliente** — a mesma família do incidente `num_uf` (R$ 204,99 gravado como R$ 20.499.605, 16 vendas infladas ×100k antes de alguém ver).
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php` — estado semeado por INSERT direto (não pelo fluxo sob teste), tolerância de centavo pelo `decimal(22,4)`.
- **Status: 🧪** — mesma condição do UC-VSHOW-01.

---

## UC-VSHOW-05 · A venda abre rápido: o detalhe pesado vem depois
- **Persona:** Larissa no monitor de 1280px — clica na venda e o cabeçalho aparece na hora; linhas/pagamentos/histórico chegam em seguida.
- **Aceite:** Dado a venda carregando · Quando o primeiro response chega · Então ele traz `headline` e `permissions` mas **não** traz `detail`; e quando o front pede `detail` (o `<Deferred data="detail">`), ele chega com as linhas da venda.
- **Âncora:** `Show.charter.md` §Goals ("Detail prop deferred via `Inertia::defer()`") + §UX Targets (p95 first-paint < 800ms) + `RUNBOOK-show.md` §10 ("NÃO eager-load 8 `with()` na resposta inicial") + [RUNBOOK-inertia-defer-pattern](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md).
- **Por que as DUAS metades:** só asserir "`detail` ausente" passaria se alguém simplesmente **deletasse** a prop. A segunda metade prova que o dado existe e chega quando pedido.
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php`.
- **Status: 🧪** — mesma condição do UC-VSHOW-01.

---

## UC-VSHOW-06 · Abrir a venda não muda a venda
- **Persona:** Wagner auditando — consultar um documento nunca altera o documento nem faz o pipeline andar sozinho.
- **Aceite:** Dado uma venda em qualquer estágio · Quando abro `/sells/{id}` e a tela renderiza · Então `updated_at`, `final_total`, `payment_status` e `current_stage_id` continuam idênticos.
- **Âncora:** `Show.charter.md` §Non-Goals ("❌ Edição inline" · "❌ Mudança de stage FSM direto — `current_stage_id` é trait-protected") + [ADR 0143](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (só `ExecuteStageActionService` transiciona).
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php` — pré-condição anti-vácuo confirma que a request percorreu o caminho inteiro (200 + `component` + `headline`) antes de afirmar "nada mudou".
- **Status: 🧪** — mesma condição do UC-VSHOW-01.

---

## UC-VSHOW-07 · O histórico da venda chega a quem abre a venda
- **Persona:** Wagner — *"sem ninguém ter autorizado"*: dá pra ver **quem** fez **o quê** e **quando**, sem abrir o banco.
- **Aceite:** Dado uma venda com trilha registrada · Quando abro `/sells/{id}` e o detalhe carrega · Então a trilha chega em `detail.activities` com a descrição do que aconteceu e a data.
- **Âncora:** [`CASOS-USO-PIPELINE-VENDAS.md`](../../../../memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md) §CU-07 ("Timeline auditável visível ao operador" — §Estado atual: *"registra tudo, mas não tem UI que exiba"*) + `Show.charter.md` §Mission ("atividades").
- **Escopo honesto:** este caso cobre a trilha do `activity_log` que o `show()` já entrega. A timeline de **transições FSM** (`sale_stage_history` com from/to stage, role e side-effects) que o CU-07 descreve por inteiro ainda **não** é coberta aqui — está no backlog abaixo.
- **Teste:** `tests/Feature/Sells/SellsShowContratoTest.php` — fato semeado por INSERT antes da leitura (independente do fluxo sob teste).
- **Status: 🧪** — mesma condição do UC-VSHOW-01.

## Volumes no protocolo de entrega (US-SELL-073 · UC-FLX-23)

> **Âncora:** aceite da **US-SELL-073** (`memory/requisitos/Sells/SPEC.md`) e o **UC-FLX-23** do catálogo de
> fluxos (*"gera o protocolo com itens, volumes e quem recebeu"*). O protocolo `ENT-NNNN` da venda é
> gravado pela entrega do app (`POST /api/app/pedidos/{id}/entrega`, contrato
> [`tela-22-entrega.md`](../../../../memory/requisitos/AppMobile/api/tela-22-entrega.md)), que já guardava
> itens conferidos e quem recebeu. Esta entrega acrescenta o **total de volumes**, opcional para não
> quebrar o app que já está nos celulares. A venda é a mesma deste detalhe; o detalhe ainda não mostra o
> protocolo (fica como aceite aberto da US-SELL-073). Prova: `tests/Feature/Sells/AppPedidoEntregaContratoTest.php`.

## UC-ENTR-01 · O protocolo grava os volumes junto com os itens e quem recebeu · `must`
- **Aceite:** Dado uma venda na etapa de Entrega com as 2 linhas conferidas · Quando o app registra a entrega com `volumes: 3` · Então a resposta 201 traz `volumes: 3` e `recebido_por`, a linha de `app_entregas` tem `volumes = 3` e os ids das 2 linhas em `itens`, e a venda vai para `delivered`. Repetir a mesma `Idempotency-Key` com `volumes: 3` devolve o mesmo protocolo; com `volumes: 5` → `422 idempotencia_conflito`.
- **Teste:** `tests/Feature/Sells/AppPedidoEntregaContratoTest.php` — `UC-ENTR-01`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-ENTR-02 · Volumes inválido é recusado e nada é gravado · `must`
- **Aceite:** Dado a mesma venda · Quando o app manda `volumes` 0, -1, 2.5, `"tres"` ou 10000 · Então cada um volta `422 validacao` com `campos.volumes`, nenhuma linha em `app_entregas` nem reserva de chave, e a venda segue em `paid`.
- **Teste:** `tests/Feature/Sells/AppPedidoEntregaContratoTest.php` — `UC-ENTR-02`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-ENTR-03 · Sem o campo volumes a entrega funciona como antes · `must`
- **Aceite:** Dado o corpo do app antigo, sem `volumes` · Quando registra a entrega · Então 201 com `ENT-NNNN`, `volumes: null` na resposta e na linha, venda em `delivered`; a repetição da mesma chave com o mesmo corpo devolve o mesmo protocolo (o campo novo não mudou o hash do corpo antigo).
- **Teste:** `tests/Feature/Sells/AppPedidoEntregaContratoTest.php` — `UC-ENTR-03`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-ENTR-04 · Volumes no pedido de outra empresa não grava nada · `[T0]` `must`
- **Aceite:** Dado uma venda de outro business na etapa de Entrega · Quando o usuário registra a entrega dela com `volumes: 4` · Então `404 nao_encontrado`, nenhuma linha em `app_entregas` e a venda alheia segue em `paid`. Controle positivo: o mesmo corpo na venda da própria empresa grava `volumes = 4` com o `business_id` do usuário.
- **Teste:** `tests/Feature/Sells/AppPedidoEntregaContratoTest.php` — `UC-ENTR-04`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-08 · O orçamento aberto mostra se o custo mudou desde que foi feito
- **Persona:** Wagner — *"o que é negociado nunca é o que é empregado"*: ao reabrir um orçamento, quer ver com que custo ele foi calculado e quanto da margem sobrou com o custo de hoje.
- **Aceite:** Dado um orçamento (venda em rascunho) cuja linha guardou custo 20,00 no dia, e uma compra recebida hoje a 30,00 · Quando abro `/sells/{id}` com `view_purchase_price` · Então `detail.custo` traz, para a linha, guardado 20,00 e hoje 30,00 marcada como mudou, totais 40,00 → 60,00 e margem 60,0% → 40,0%. Sem a permissão, `detail.custo` é nulo e nenhum custo vai no JSON. Venda finalizada não traz o bloco.
- **Âncora:** catálogo da tela de fluxos UC-FLX-34 (`prototipo-ui/cowork/Wagner/fluxos-data.jsx`) + SPEC Sells US-SELL-067/068 + gate de custo `view_purchase_price` do Blade (`Produto/Index.casos.md` UC sobre `view_purchase_price`).
- **Teste:** `tests/Feature/Sells/SellsShowCustoOrcamentoContratoTest.php` — números à mão no cabeçalho do teste; usuários novos com permissões exatas (o admin do seed liberaria tudo).
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-20 · O orçamento aberto mostra se o cliente já abriu a proposta · `must`
- **Persona:** vendedor que mandou o link do orçamento e precisa saber se ligar de volta.
- **Aceite:** Dado um orçamento aberto pelo cliente duas vezes (primeira 20/10 10:00, última 11:30) e outro nunca aberto · Quando abro `/sells/{id}` · Então `detail.acesso` traz `aberto: true`, as duas datas e `vezes: 2` no primeiro, e `aberto: false`, `vezes: 0` no segundo; a tela mostra "Aberto pelo cliente em 20/10 10:00 (2 vezes, a última em 20/10 11:30)" ou "Ainda não aberto pelo cliente". Venda finalizada e rascunho comum não trazem o bloco (`acesso` nulo).
- **Âncora:** aceite 2 da US-SELL-070 (`memory/requisitos/Sells/SPEC.md`) + UC-FLX-04 do catálogo da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php`). O gate de quem vê é o do próprio `/sells/{id}` (UC-VSHOW-02/03).
- **Teste:** `tests/Feature/Sells/QuoteAccessTelaContratoTest.php` — `UC-VSHOW-20`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-21 · Outro business não vê a abertura do orçamento · `[T0]` `must`
- **Aceite:** Dado o orçamento aberto do business 98 · Quando um usuário do 99 com `sell.view` pede o detalhe · Então 404 e nada da abertura no corpo. E uma linha de acesso gravada com o business 99 apontando para um orçamento do 98 não conta: o detalhe e a lista seguem "não aberto" para ele. Controle positivo: o dono do 98 lê as 2 aberturas do orçamento A.
- **Âncora:** aceite 5 da US-SELL-070 + ADR 0093.
- **Teste:** `tests/Feature/Sells/QuoteAccessTelaContratoTest.php` — `UC-VSHOW-21`.
- **Status: 🧪** — roda na lane `sells-pest`.

---

## Pesquisa de satisfação depois da entrega (US-SELL-071 · UC-FLX-25)

> **Âncora:** aceite da **US-SELL-071** (`memory/requisitos/Sells/SPEC.md`) e o **UC-FLX-25** do catálogo
> de fluxos (Entregue + 2 dias, envia a pesquisa; nota baixa abre atendimento). Nesta entrega é o backend:
> a rotina diária `sells:pesquisa-entrega` grava uma linha por venda em `transaction_satisfaction_surveys`,
> envia pelo CSAT do WhatsApp ou por e-mail respeitando o opt-in LGPD do cliente, e a nota baixa vira
> tarefa no agendamento do CRM (`crm_schedules`). Liga por empresa em
> `business.common_settings['sells_pesquisa_entrega']`, desligada por padrão. A tela ainda não mostra as
> respostas — fica no backlog abaixo. Prova: `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php`.

## UC-VSHOW-09 · Com a pesquisa desligada (padrão), nenhuma venda recebe pesquisa · `must`
- **Aceite:** Dado uma empresa que não ligou a pesquisa e uma venda entregue há 2 dias · Quando a rotina roda · Então nenhuma linha é gravada e nenhum e-mail sai.
- **Teste:** `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php` — `UC-VSHOW-09`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-10 · Entregue há 2 dias recebe a pesquisa; há 1 dia, cancelada ou de outra empresa não · `[T0]` `must`
- **Aceite:** Dado no business 98 a venda A entregue há 2 dias, B entregue há 1 dia, C entregue há 2 dias e cancelada depois, e no business 99 a venda D entregue há 2 dias · Quando a rotina roda para o 98 · Então só A recebe a pesquisa (1 e-mail, linha `enviado` no business 98); B, C e D ficam sem linha.
- **Teste:** `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php` — `UC-VSHOW-10`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-11 · Rodar de novo não manda outra pesquisa para a mesma venda · `must`
- **Aceite:** Dado a venda A já pesquisada · Quando a rotina roda no dia seguinte · Então 0 pesquisas novas, A segue com 1 linha e 1 e-mail no total.
- **Teste:** `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php` — `UC-VSHOW-11`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-12 · Cliente que recusou WhatsApp e e-mail não recebe, e fica o motivo · `must`
- **Aceite:** Dado uma venda entregue há 2 dias cujo cliente tem `whatsapp_consent` e `email_consent` falsos · Quando a rotina roda · Então nenhum e-mail sai e a linha fica `nao_enviado` com motivo `opt_out`.
- **Teste:** `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php` — `UC-VSHOW-12`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VSHOW-13 · Nota igual ou abaixo do limite abre um atendimento ligado à venda, uma vez · `must`
- **Aceite:** Dado duas vendas pesquisadas, uma com nota 2 e outra com nota 4, e o limite padrão 2 · Quando a rotina roda · Então 1 tarefa no agendamento do CRM, do business 98 e ligada à venda de nota 2 (`crm_followup_invoices`); a de nota 4 não ganha tarefa. Rodar de novo cria 0.
- **Teste:** `tests/Feature/Sells/PesquisaSatisfacaoEntregaContratoTest.php` — `UC-VSHOW-13`.
- **Status: 🧪** — roda na lane `sells-pest`.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão (e o `casos-gate` é required com
> `enforce_admins`). Itens SEM token de UC até existir teste real.

- **[BACKLOG] Timeline de transições FSM completa** — `sale_stage_history` com autor, estágio de origem/destino, papel exigido e badges de side-effect (o corpo do §CU-07). Exige pipeline FSM semeado na fixture; hoje só a trilha `activity_log` é coberta (UC-VSHOW-07).
- **[BACKLOG] Fallback Blade preservado** — requisição sem `X-Inertia` continua caindo em `sale_pos.show` (charter §Endpoints + `RUNBOOK-show.md` §10 "NÃO esquecer fallback Blade"). Exige harness que renderize a Blade legacy sem o build do front.
- **[BACKLOG] Os 4 gaps de gestão pós-venda do charter** — aprovação do cliente, transportadora/rastreio/entrega, seleção de tipo de NF com parcelas, e histórico de pedidos no perfil do cliente. Estão marcados 🟡 (sem cobertura) em `Show.charter.md` §UCs cobertos e continuam **fora** do escopo entregue da tela — viram UC quando a feature existir e tiver teste.
- **[BACKLOG] Respostas da pesquisa de entrega na tela** — o detalhe da venda mostra a nota e o atendimento aberto, só para quem tem `sell.view` na empresa; outro business recebe 404 (aceite 5 da US-SELL-071). O dado já está em `transaction_satisfaction_surveys`.
- **[BACKLOG] Resposta da pesquisa por e-mail** — hoje só a nota do WhatsApp (lida pelo `CsatResponseParser`) volta para a venda; resposta de e-mail não tem leitor.
- **[BACKLOG] Atalhos de teclado E / P / Esc** — charter §Goals; hoje só há assert estrutural (`SellsShowCoworkTest` casa a string `e.key === 'e'`), que não prova o comportamento. Exige E2E Playwright.

## Como rodar a suíte
1. **Contrato (MySQL real):** lane `sells-pest.yml` no CI — `vendor/bin/pest tests/Feature/Sells/SellsShowContratoTest.php` com `DB_CONNECTION=mysql` e o seed biz=1/biz=2 da action `pest-mysql-setup`. O JUnit sobe como artifact `pest-sells-junit` e é colhido pelo `casos-results-publish.yml` (manifesto G-7).
2. ⚠️ **Não roda no container `oimpresso-staging` do CT 100** — medido em 2026-07-27: o banco `oimpresso_staging` tem 15 tabelas (sem o schema UltimatePOS), então `EstoqueFixture::schemaReady()` devolve false e os 7 casos dão `markTestSkipped` (skip gracioso, não falso-verde).
3. **Cadência:** rodar ao fim de toda mexida em `Sells/Show.tsx` ou em `SellController@show`. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-10-21 · [CL] UC-VSHOW-20/21 (US-SELL-070 · UC-FLX-04): faixa "aberto pelo cliente" no orçamento, lida de `transaction_quote_accesses` pelo business da venda. Veio do backlog do `Quotations.casos.md`. Teste `QuoteAccessTelaContratoTest`, nasce sem run.
- 2026-10-21 · [CL] `last_run` 2026-10-20 → 2026-10-21 por conferência, não por run novo: o `Show.tsx` deste PR só acrescenta a faixa `AcessoDoOrcamento` (dentro de `ShowDetailSections`, antes da faixa de custo) e o tipo `AcessoOrcamento`; nada de UC-VSHOW-01..13 muda. Nenhum teste re-rodado aqui.
- 2026-10-20 · [CL] UC-VSHOW-09..13 (US-SELL-071 · UC-FLX-25): pesquisa de satisfação 2 dias depois da entrega e atendimento na nota baixa. Teste `PesquisaSatisfacaoEntregaContratoTest`, nasce sem run.
- 2026-10-20 · [CL] `last_run` 2026-07-27 → 2026-10-20 por conferência, não por run novo: desde o `last_run` o `Show.tsx` mudou só em a456273f41 (#9612, US-SELL-068), que acrescenta a faixa de custo do orçamento do UC-VSHOW-08 (63 linhas, só adições). UC-VSHOW-01..08 seguem batendo com a tela; nenhum teste foi re-rodado aqui.
- 2026-07-27 · [CC] criado — trio fechado pra `Sells/Show` (alvo #1 do débito Tier-0 de `exposicao-tier0.mjs`) com UC-VSHOW-01..07 + `SellsShowContratoTest.php` + lane `sells-pest.yml` (nenhuma das 9 lanes MySQL rodava `tests/Feature/Sells/**`). Casos derivados do charter §Goals/§Non-Goals + RUNBOOK-show + §CU-07; o Controller foi lido só pra confirmar. Ref [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md).
- 2026-10-20 · [CC] + UC-ENTR-01..04 (volumes no protocolo de entrega, US-SELL-073 · UC-FLX-23) e `last_run` revalidado: a única mudança do `Show.tsx` depois de 2026-07-27 é a faixa de custo do orçamento (#9612, 2026-10-19), que é aditiva e já tem caso próprio (UC-VSHOW-08); os UC-VSHOW-01..07 tratam de isolamento, permissão, números, carga adiada, leitura sem efeito e trilha, que aquele diff não toca. Os testes rodam na lane `sells-pest`.
