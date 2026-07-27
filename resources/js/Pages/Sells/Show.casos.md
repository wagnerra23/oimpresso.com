---
id: resources-js-pages-sells-show-casos
casos: Detalhe da venda · /sells/{id}
irmaos: Show.charter.md (lei) · tests/Feature/Sells/SellsShowContratoTest.php (defesa)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-27"
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
- **Status: 🧪** — contrato escrito e rodando na lane MySQL; sobe a ✅ quando `npm run casos:results` regravar o manifesto (G-7, sem fingir prova).

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
- **Âncora:** `Show.charter.md` §Goals ("4 KPIs grandes (canon V2): Total / Pago / Falta / Status pgto") + REGRA MESTRE valor/estoque de [`memory/proibicoes.md`](../../../../memory/proibicoes.md). "Falta" é derivada no front (`Show.tsx:303`), então o contrato do backend é Total + Pago + Status coerentes.
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

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão (e o `casos-gate` é required com
> `enforce_admins`). Itens SEM token de UC até existir teste real.

- **[BACKLOG] Timeline de transições FSM completa** — `sale_stage_history` com autor, estágio de origem/destino, papel exigido e badges de side-effect (o corpo do §CU-07). Exige pipeline FSM semeado na fixture; hoje só a trilha `activity_log` é coberta (UC-VSHOW-07).
- **[BACKLOG] Fallback Blade preservado** — requisição sem `X-Inertia` continua caindo em `sale_pos.show` (charter §Endpoints + `RUNBOOK-show.md` §10 "NÃO esquecer fallback Blade"). Exige harness que renderize a Blade legacy sem o build do front.
- **[BACKLOG] Os 4 gaps de gestão pós-venda do charter** — aprovação do cliente, transportadora/rastreio/entrega, seleção de tipo de NF com parcelas, e histórico de pedidos no perfil do cliente. Estão marcados 🟡 (sem cobertura) em `Show.charter.md` §UCs cobertos e continuam **fora** do escopo entregue da tela — viram UC quando a feature existir e tiver teste.
- **[BACKLOG] Atalhos de teclado E / P / Esc** — charter §Goals; hoje só há assert estrutural (`SellsShowCoworkTest` casa a string `e.key === 'e'`), que não prova o comportamento. Exige E2E Playwright.

## Como rodar a suíte
1. **Contrato (MySQL real):** lane `sells-pest.yml` no CI — `vendor/bin/pest tests/Feature/Sells/SellsShowContratoTest.php` com `DB_CONNECTION=mysql` e o seed biz=1/biz=2 da action `pest-mysql-setup`.
2. ⚠️ **Não roda no container `oimpresso-staging` do CT 100** — medido em 2026-07-27: o banco `oimpresso_staging` tem 15 tabelas (sem o schema UltimatePOS), então `EstoqueFixture::schemaReady()` devolve false e os 7 casos dão `markTestSkipped` (skip gracioso, não falso-verde).
3. **Cadência:** rodar ao fim de toda mexida em `Sells/Show.tsx` ou em `SellController@show`. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-27 · [CC] criado — trio fechado pra `Sells/Show` (alvo #1 do débito Tier-0 de `exposicao-tier0.mjs`) com UC-VSHOW-01..07 + `SellsShowContratoTest.php` + lane `sells-pest.yml` (nenhuma das 9 lanes MySQL rodava `tests/Feature/Sells/**`). Casos derivados do charter §Goals/§Non-Goals + RUNBOOK-show + §CU-07; o Controller foi lido só pra confirmar. Ref [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md).
