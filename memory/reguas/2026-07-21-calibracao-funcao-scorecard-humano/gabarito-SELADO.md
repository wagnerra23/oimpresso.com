# ⚠️ GABARITO SELADO — NÃO ABRIR ANTES DE PREENCHER A `folha-cega.md`

> **[W]: se você está lendo isto antes de responder a folha, a rodada morreu** — registre
> `cego: false` (o validador rejeita a entry, e está certo em rejeitar) e monte outra rodada com
> funções que não passaram pelo seu canal. Rotular vendo o veredito é ancoragem, não calibração.
>
> Este arquivo existe por **auditabilidade**: sem ele, "conferi e deu K/9" é palavra do agente.
> Com ele, qualquer um refaz a conta. Mesmo motivo do ledger ser append-only.

## O que o juiz decidiu (o que sua resposta será comparada contra)

Fonte: juiz Fable isolado, sessão fresca, 2026-07-21. Protocolo §4 do
[FUNCAO-SCORECARD-METODO.md](../../requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md): rubrica lida
ANTES do código, varredura contada (`git grep` sem `head_limit`), âncora de intenção buscada no
canon (`memory/decisions/` + `memory/requisitos/` + proibicoes §5 + handoffs). Nenhum veredito sem
citação extrativa (§2). Nenhum valor R$ colado em citação (regra BRL).

| # | Função | Critério | Veredito do juiz | Evidência (citação extrativa + linhas) | Âncora de intenção |
|---|--------|----------|------------------|----------------------------------------|--------------------|
| 1 | `Util::num_uf` | C2 valor | **concordo** | `if ($dotCount === 1 && $afterLastDot !== 3) { $normalized = $abs; }` (Util.php:91-92). Prova golden DIRETA contada: `NumUfHeuristicPtBRTest:108` (`$this->util->num_uf($input)`) + `IncidentValorInfladoNumUfTest:64/74-78/99` — 2 arquivos de teste unitário invocando a função. 325 hits `num_uf(` no repo (fan-in altíssimo). | **(canon)** proibicoes §REGRA MESTRE ("separador de milhar tem SEMPRE 3 dígitos") + `.claude/rules/calculo-valor-estoque.md` + session 2026-06-05 (fix #2279). A heurística é decisão canon datada pós-2 incidentes, com golden cobrindo os vetores exatos (`80.00`, `204.99605`, `25.000`). Ambiguidade residual ("25.000" digitado como en-US de 3 casas) é trade-off RESOLVIDO pelo canon, não deixado ao acaso. |
| 2 | `Util::format_date` | C7 tipos/falha | **discordo** | Docblock: `Converts date in mysql format to business format ... @return strin` (Util.php:334-340) — **zero menção ao +3h**; corpo: `\Carbon::createFromTimestamp(strtotime($date))->format($format)` (Util.php:353). O vizinho denuncia: `Formata data já-existente do DB SEM o shift +3h histórico do format_date` (Util.php:357) e chama de `bug intencional de +3h` (Util.php:387). | **(canon)** ADR 0066 (`format_date com shift +3h preservado intencionalmente`, aceito 2026-04-24). O COMPORTAMENTO é intencional e protegido por ADR — mas C7 julga a VERDADE do docblock, e o docblock silencia um desvio material documentado. O incidente Larissa recibo 23:47 vs 18:00 real (docblock do `format_date_no_shift`, Util.php:359-362) prova consumidor enganado pela assinatura. Extra: retorna `null` em `$date` vazio (353) e o docblock diz `strin`. |
| 3 | `ProductUtil::getVariationGroupPrice` | C3 dado-ausente | **discordo** | `if(!$price_group){ return [ 'price_inc_tax' => '', 'price_exc_tax' => '', ]; }` (ProductUtil.php:1057-1062) — string vazia não-tipada; docblock diz `@return decimal` (1048). Varredura contada: **5 consumidores** (Crm/OrderRequestController:323 · WoocommerceUtil:341 · WoocommerceUtil:731 · LabelsController:143 · SellPosController:1790). | **(canon)** US-PROD-027 (`[V0] Travar o acidente do 0-row` — "0-row inerte por sorte do PHP", handoff 2026-07-15) + proibicoes §5 2026-07-15 ("o `''` (sem row) é o caso **normal** e é o que sangra em `Labels`/`Woo`"). O canon já reconhece o retorno-vazio como acidente armado; o golden existente (CalculoValorProdutoTest:271) cobre o caminho percentage, NÃO o 0-row. |
| 4 | `ProductUtil::getVariationGroupPrice` | C1 multi-tenant | **discordo** | `$price_group = VariationGroupPrice::where('variation_id', $variation_id) ->where('price_group_id', $price_group_id)` (ProductUtil.php:1052-1053) + `$variation = Variation::find($variation_id);` (1066) — sem `business_id` e sem relação escopada; model `app/VariationGroupPrice.php` verificado: **nenhum global scope** (só `$guarded` + accessor). Varredura contada: 5 consumidores (idem #3). | **(canon)** ADR 0093 Tier 0 ("toda query em tabela tenant-owned filtra business_id, direto ou via relação escopada; nenhum `Model::find($idDoRequest)` cru em dado de negócio") + handoff 2026-07-15: adversário contou "**5 consumidores, 3 sem guard** (Labels:145 · Woo:343,733)" e o teste vermelho UC-PTAB-04 provou cross-tenant REAL nesta mesma tabela no caminho de escrita (fix #4300). Nota honesta: o desenho UltimatePOS delega escopo ao caller — mas 3/5 callers documentados sem guard = a defesa não existe em lugar nenhum nesses caminhos. |
| 5 | `ProductUtil::calculateInvoiceTotal` | C3 dado-ausente | **incerto** | `if (empty($products)) { return false; }` (ProductUtil.php:642-644); docblock DECLARA o polimorfismo: `@return mixed (false, array)` (638). Varredura contada: **6 call sites** (Connector/SellController:925,1275 · Crm/OrderRequestController:216 · SellPosController:434,1308 · TransactionUtil:6094) + 1 golden direto no caminho feliz (CalculoValorSellsTest:144). | **Só código → incerto** (§0.5). O `false` é documentado (não é silencioso no sentido estrito do critério), mas é o mixed `false|array` que C3/C7 nomeiam como anti-padrão. Nenhum SPEC/CU/ADR resolve se `$products` vazio é entrada VÁLIDA (total zerado tipado) ou ERRO (exceção); nenhum golden cobre o caminho vazio. O método §5 rodada-1 já classificou este parecer como "hipótese útil ancorada", não confirmada. Se a intenção do dono existir só de cabeça, é gap de doc — não forço `discordo`. |
| 6 | `ProductUtil::getProductDiscount` | C6 SQL cru | **discordo** | `$sub_q->whereRaw('(brand_id="'.$product->brand_id.'" AND category_id IS NULL)') ->orWhereRaw('(category_id="'.$product->category_id.'" AND brand_id IS NULL)');` (ProductUtil.php:1559-1560) — interpolação literal de variável em raw, **zero bindings**. Varredura contada: **7 call sites** (Connector Transformers ×3 · Crm:338 · Officeimpresso:97 · ProductCatalogue:128 · SellPos:1821); 0 teste direto encontrado. | **(canon)** Laravel security (bindings obrigatórios em `whereRaw` — âncora declarada do C6 na rubrica) + espírito ADR 0093. Medida binária do critério satisfeita: há `Raw` com `"...$var..."`. Mitigante honesto (não absolve): `$product->brand_id`/`category_id` vêm do DB (int/null), superfície de injeção baixa — mas `null` gera `brand_id=""` silencioso, e o critério é de higiene, não de exploitabilidade. |
| 7 | `KbAutoClassifierService::runClassification` | C1 multi-tenant | **concordo** | `// SUPERADMIN: rodado em CLI/job (session vazia → global scope não resolve o tenant); // o tenant é reimposto explicitamente pelo ->where('business_id', $businessId) abaixo (ADR 0093).` + `KbSubcategory::query() ->withoutGlobalScopes() ->where('business_id', $businessId)` (KbAutoClassifierService.php:73-77); mesmo padrão nas 3 queries (75-79, 83-87, 106-113 — o UPDATE é duplo-scopado `business_id` + `whereKey`). | **(canon)** ADR 0093 + proibicoes ("Não usar `withoutGlobalScopes` sem comentário `// SUPERADMIN:`"; "Job assíncrono SEMPRE passa `$businessId`"). É o padrão que o canon PRESCREVE, executado à letra: tenant por parâmetro explícito (CLI/job), comentário SUPERADMIN em cada uso, e teste direto cross-tenant contado: `KbAutoClassifierTest:106` (`classify(99, apply: true)` → biz=99 intacto). Consumidor: 1 (KbClassifyCommand:38, dry-run default). |
| 8 | `FsmAuthorizationFlag::consume` | C7 tipos/falha | **discordo** | Docblock: `Per-request scope (static reset entre requests PHP-FPM/Octane)` (FsmAuthorizationFlag.php:22) vs `private static array $authorized = [];` (34). Verificado: `config/octane.php` `'flush' => [ // ]` **vazio** (137-139) e `reset()` só é chamado em testes (CurrentStageIdBypassObserverTest:75 · FsmAuthorizationFlagPropertyTest:45-46,140) — **nenhum listener de app reseta o static**. O próprio docblock admite o vetor: `Em jobs em fila ... mesmo PHP process — funciona` (28-29). | **(canon + fato técnico verificável)** Sob Octane worker-mode (FrankenPHP — ADR 0058 no quadro de runtime) e sob queue worker longevo (prod roda worker `database`), static de classe NÃO reseta entre requests/jobs — a metade "Octane" do claim é falsa como escrita; um `mark()` não-consumido (exceção entre mark e save) vaza pro request/job seguinte do mesmo processo. A metade PHP-FPM é verdadeira, e a FALHA é observável um nível acima (`GuardsFsmTransitions:48-54` lança `UnauthorizedActionException` no `false`) — o discordo é pela promessa de reset que o runtime não cumpre, não pelo `false` em si. Nota: proibicoes §5 2026-06-05 já marcou o `FsmAuthorizationFlagPropertyTest` como tautológico (invariantes derivadas do código). |
| 9 | `ProductUtil::generateProductSku` | C1 multi-tenant | **concordo** | `$business_id = request()->session()->get('user.business_id'); $sku_prefix = Business::where('id', $business_id)->value('sku_prefix');` (ProductUtil.php:701-702) — a única query É filtrada por `business_id`. Varredura contada: **4 consumidores, todos controllers HTTP** (ImportProductsController:628 · ProductController:711 · ProductController:1803 · ModifierSetsController:127) — zero caller em CLI/job/fila. | **(canon)** ADR 0093: a regra de tenant-explícito-por-parâmetro é escopada a **job/CLI** ("Job assíncrono SEMPRE passa `$businessId` — `session()` não funciona em fila"); em contexto web, session é a fonte canônica UltimatePOS (`SetSessionData`), padrão restateado pelo próprio canon (KbAutoClassifierService.php:33-35 delimita a proibição a "CLI/job"). Medida binária do C1: não há query sem escopo. Residual honesto: a assinatura session-acoplada torna a função insegura pra uso FUTURO em fila (`business_id` viraria null → SKU sem prefixo); hoje nenhum caminho vivo exercita isso — se um caller de job nascer, o veredito flipa. |

**Totals do juiz:** `concordo: 3 · discordo: 4 · incerto: 1 · n/a: 0` — mas o que vale na leitura é
item-a-item + os 4 modos de discordância da folha, nunca o placar agregado (§0.2 PLUGAR-NÃO-FUNDIR).

## Confiança auto-reportada do juiz (declarada ANTES de ver os rótulos de [W])

Registrado pra leitura post-hoc — é a MINHA incerteza, não uma afirmação sobre o que [W] responderá:

1. **#5 (`calculateInvoiceTotal` C3) — o menos firme.** O fio da navalha `incerto`×`discordo`: o
   `false` é documentado no docblock (não "silencioso"), mas é exatamente o mixed que o critério
   nomeia. Decidi por `incerto` porque nenhum canon externo resolve se entrada-vazia é caso válido —
   se [W] tiver essa intenção de cabeça, o item revela gap de doc (o cenário que este braço existe
   pra medir). Se [W] resolver `(canon)` com algo que não achei, é miss de lookup meu.
2. **#9 (`generateProductSku` C1).** A medida binária do C1 passa (query filtrada), mas a folha
   enquadra a PROVENIÊNCIA (session vs parâmetro) como a questão. Se a régua de [W] for "util de
   tenant nunca lê session", o certo era `discordo` e minha leitura do escopo do ADR 0093 foi
   estreita demais.
3. **#4 (`getVariationGroupPrice` C1).** Firmeza média: ADR 0093 é explícito e o vermelho
   cross-tenant na MESMA tabela (escrita) existe — mas o desenho "caller escopa" é defensável e a
   sessão de 2026-07-15 deliberadamente NÃO fez achado disto por falta de CU. Se [W] responder
   `incerto` (design em aberto), eu super-afirmei.
4. **#8 (`FsmAuthorizationFlag` C7).** O fato técnico (static não reseta sob Octane/worker; flush
   vazio verificado) me parece objetivo — mas [W] pode pesar "o app FSM roda só em FPM na prática"
   e ler o docblock como aproximação aceitável (`concordo` com ressalva). O meu `discordo` é sobre
   a LETRA do claim, não sobre risco realizado.

Os mais firmes: #7 (o padrão canon executado à letra), #6 (interpolação literal, critério mecânico),
#2 (docblock silencia desvio que o vizinho documenta), #1 (golden direto + heurística ratificada).

## Nota metodológica (limite honesto desta rodada)

- Este braço calibra o juiz em **critérios intent-ambíguos (C1/C2/C3/C6/C7) sobre 9 funções REAIS**
  — o complemento da fixture sintética (que cobre defeito mecânico com rótulo objetivo). Aqui o
  gold é [W], e "concordância" mede "o juiz aplica a rubrica + acha o canon como um humano faria".
- **Contaminação de canon é aceitável e DECLARADA por design:** `proibicoes.md`/handoffs são
  contexto do repo; achar ADR 0066/0093/US-PROD-027 é comportamento CORRETO ao julgar código real
  (diferente da fixture, onde seria cola). O número desta rodada NÃO mede "acertar do zero sem
  canon".
- **N = 9, uma rodada.** Nada aqui generaliza sozinho; o `--juiz-report` agrega. Vereditos deste
  gabarito são PARECER (§0.3/§6): nenhum `discordo` autoriza fix, task ou US — viram candidatos só
  com aprovação humana + protocolo completo (varredura + âncora + vermelho; REGRA MESTRE se
  valor/estoque).
- Varreduras contadas rodadas em 2026-07-21 no worktree `kind-nobel-29963f` (`git grep` sem
  `head_limit`); números de consumidores/testes citados na tabela são dessa medição datada — se a
  data incomodar, re-rode o grep, não edite o número.
