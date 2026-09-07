---
date: "2026-09-06"
topic: "Re-destilação real das 12 portas BRIEFING stale (jana:distill-module-truth no CT 100, checkout fresco) + refutação GT-G5 em rodadas — distiller_freshness 12→0"
authors: ["C"]
prs: [6932]
related_adrs: ["0291-distiller-modulo-verdade-contrato-emenda-0270-f3", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
---

# Re-destilação das 12 portas stale + refutação GT-G5 (2026-09-06)

> Protocolo: `memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md` §2–§4. Gerador: `jana:distill-module-truth` (LLM `gpt-4o-mini` via `laravel/ai`, PiiRedactor no write) + correções entre rodadas pelo gerador (sessão principal, §2.6). Refutadores: Claude Opus 5 / Opus 4.5 em subagente com contexto próprio, uma instância nova por lote e por rodada, instruídos a não abrir os logs de rodadas anteriores. Tipo `prosa`, amostra 100% (12 de 12 arquivos, corpo integral).

## Como a destilação rodou (o que a nota_absorcao do baseline pedia)

- **Por que**: `governance/sdd-scorecard-baseline.json` → `metrics.distiller_freshness` estava em **12** com uma cadeia de `nota_absorcao_*` (0→5→6→7→8→9→11→12) — cada nota registrava que o CT 100 tinha checkout defasado e adiava a re-destilação real. O único escritor legítimo de `distilled_at` é `Modules/Jana/Services/Memoria/DistillerModuloVerdade.php`.
- **Checkout**: o container `oimpresso-staging` (`/opt/oimpresso-staging/code`) está em `c1abe9548` (2026-08-26) com dezenas de arquivos sujos de outra sessão — **não foi tocado**. Clone novo no host CT 100 em `/root/distill-fresh/oimpresso` (`git clone --shallow-since=2026-06-01`, HEAD `445efc7bb` = tip de `origin/main` na hora do clone), `vendor/` copiado do staging (`composer.lock` idêntico entre `c1abe9548` e `origin/main` — diff vazio) e `.env` do staging (fica no host, não sai dali).
- **Execução**: container irmão descartável a partir da mesma imagem do staging (`oimpresso/mcp:latest`, `f2e3f8cd53ce`), com o clone montado em `/var/www/html`, na rede `docker-host_default`, `--entrypoint php`, um `--module=<X>` por vez (nunca `--all` — o kill-switch do Kernel continua comentado). Dry-run do Cliente primeiro (10 eventos, exit 0), depois os 12 reais: **12/12 `porta reescrita`, 0 `refused_pii`**, ~6 s cada (log em `/root/distill-fresh/real-12.log` no CT 100).
- **Transporte**: os 12 `BRIEFING.md` vieram por `tar | base64` via `tailscale ssh` (sem transcrição). Pós-processo mecânico declarado: `id:` restaurado com o valor do HEAD (o distiller emite só 5 campos de frontmatter e derrubava o `id:` do `doc-id-index`), H1 duplicada emitida pela LLM e header-quote legado ("Última atualização") removidos. `distilled_at`/`distilled_by` **intocados**.
- **Medição** (repo desrasado, `git rev-parse --is-shallow-repository = false` nos dois lados): `measureDistillerFreshness` antes = **12** (portas 80, carimbadas 14, stale 12, oldest 2026-07-17); depois = **0** (portas 80, carimbadas 14, stale 0, oldest 2026-08-13). Aviso operacional: o `git fetch origin governance/nightly-floor --depth 1` sugerido pelo próprio ratchet deixa o repo local com marcador shallow — desfeito com `git fetch --unshallow origin governance/nightly-floor` antes de qualquer medição.

## Rodada r1 — 4 refutadores (opus, sessão fresca, 3 módulos cada) · REPROVADO

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 14 | 5 | 35,7% |
| A | Crm | 16 | 8 | 50,0% |
| A | NfeBrasil | 19 | 3 | 15,8% |
| B | OficinaAuto | 18 | 4 | 22,2% |
| B | PaymentGateway | 18 | 4 | 22,2% |
| B | Whatsapp | 18 | 5 | 27,8% |
| C | Fiscal | 18 | 6 | 33,3% |
| C | Sells | 17 | 9 | 52,9% |
| C | Jana | 15 | 9 | 60,0% |
| D | Financeiro | 16 | 3 | 18,8% |
| D | RecurringBilling | 15 | 3 | 20,0% |
| D | Repair | 14 | 4 | 28,6% |
| **r1** | **12 portas** | **198** | **63** | **31,8%** |

PII: 0 hits nos 4 lotes (controle positivo demonstrado em cada um). Valor em R$: 0.

### Padrões transversais achados na r1 (insumo pro distiller, não só pra esta correção)

1. **Número derivado no corpo apesar da regra dura do prompt** — 9 ocorrências em 7 portas (`14 das 15 US`, `80/100`, `63/100`, `67/100`, `Seis drivers`, `Onze drivers CNAB`, `8 telas e 35 componentes`, `87% de cobertura`, `9 dos 13 arquivos`, `3 testes`). Metade estava **factualmente errada** além de proibida (Cliente 14/15 contra 23 US no SPEC; Sells 8/35 contra 9/44; RecurringBilling 9/13 contra 14 arquivos na lane).
2. **"Última mudança" atrás dos eventos que a própria Proveniência lista** — 11 de 12 portas narraram evento de julho/começo de agosto como "recente" num doc datado 2026-09-06; Repair ignorou uma onda MWART de 2 dias antes; Financeiro ignorou um fix Tier 0 (#6335).
3. **Estado atual descrevendo o ÚLTIMO EVENTO em vez do MÓDULO** — Jana virou "a aba Metas do superadmin" (1 de 7 telas); Crm virou "o silenciamento"; Cliente virou "contagem de US".
4. **Recibo trocado por prosa** — sumiram PR numbers, US-ids, ADRs, tenants (`biz=164`, `biz=1`), seções de Governança/Contrato de tela e os ponteiros pro dono vivo do número (`requisitos-status.mjs`). O refutador D chamou de "perda de rastreabilidade" a regressão dominante.
5. **Claim em presente contradita por evento na Proveniência** — Whatsapp "operação estável" com incidente P1 de 2026-09-02 listado na própria proveniência; Crm "sem previsão de evoluções" com 3 PRs posteriores.
6. **Erro de sentido** — Fiscal `bus=4` (por biz=4), "criação de DF-e" (o módulo manifesta, não cria), "config read-only" (deixou de ser em 2026-09-04); Sells "esta última" invertendo o referente do cutover; Cliente "tela de detalhamento" por tela de mapa.
7. **PR citado que nunca mergeou** — Fiscal cita #4866 (CLOSED); o SDD entrou pelo #4891.
8. **Gap já entregue apresentado como gap** — NfeBrasil "contingência EPEC necessária" (entregue em 2026-09-02, US-NFE-006 fases 1–6; o `_pendente_` do SPEC ficou atrás do código); Repair "3 testes falham por `basePath()`" (corrigido em #6240, 2026-08-25; SPEC US-REPA-002 também atrás).
9. **Proveniência circular** (RecurringBilling) — as duas únicas fontes datadas eram logs de refutação de OUTRO lote (#6897), não fatos do módulo; o distiller se alimentou do próprio processo.

### Correção aplicada pelo gerador entre r1 e r2 (§2.6 do protocolo)

Corpo dos 12 BRIEFINGs reescrito seguindo as correções prescritas pelos refutadores: números derivados removidos e substituídos por ponteiro pro dono; "Última mudança" refeita a partir de `git log origin/main -- Modules/<Mod> resources/js/Pages/<Mod>`; Estado atual reescrito pra descrever o módulo; recibos (PR/US/ADR/tenant) restaurados; seções de governança restauradas em forma resumida; erratas restauradas; gaps já entregues movidos pra Capacidades com a nota de que o SPEC ficou atrás. Frontmatter (`distilled_at`/`distilled_by`) **intocado** — foi escrito pelo `DistillerModuloVerdade` no CT 100.

## Rodada r2 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO (8,6%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 23 | 0 | 0,0% |
| A | Crm | 23 | 2 | 8,7% |
| A | NfeBrasil | 34 | 4 | 11,8% |
| B | OficinaAuto | 26 | 1 | 3,8% |
| B | PaymentGateway | 24 | 3 | 12,5% |
| B | Whatsapp | 27 | 2 | 7,4% |
| C | Fiscal | 21 | 2 | 9,5% |
| C | Sells | 23 | 0 | 0,0% |
| C | Jana | 30 | 6 | 20,0% |
| D | Financeiro | 26 | 3 | 11,5% |
| D | RecurringBilling | 23 | 1 | 4,3% |
| D | Repair | 21 | 2 | 9,5% |
| **r2** | **12 portas** | **301** | **26** | **8,6%** |

PII estruturada: 0 hits nos 4 lotes (controle positivo em cada). Ressalvas declaradas pelos refutadores, não contadas: nomes de pessoa herdados do canon (Larissa/Guilherme em Sells — idênticos em SPEC, charter e `.tsx`; Martinho/biz=164 em OficinaAuto — identificador canônico de piloto em CLAUDE.md). Decisão de redigir é [W].

### O que a r2 pegou (todos corrigidos antes da r3)

- **Âncora errada por trás de fato verdadeiro** (a classe dominante, 4 de 6 no lote D): `OnTituloCriadoLog` citado como conciliação (é listener de log; a conciliação é o `ConciliacaoController`); `AUDIT-FUNCOES` mandado "rodar" (é markdown datado, não porta viva); `route-hits.json` citado como quem lista as US sem hit (o oráculo é o `anchor-lint --servido`); #3901 atribuído ao Kanban (tocou `DeviceModels`, erro herdado da porta HEAD); #6457/#6629 rotulados como E2E Browser (Feature test e fix de rota).
- **Generalização além do medido**: "whatsmeow é o driver dos canais de biz=1" (a sessão mediu 2 canais); `inter-reconcile-pix` "em live para biz=1" (roda em `local`+`live` para todos os tenants com credencial); "PesaPal só no enum" (há branch `warnFor()` deprecated).
- **Capacidade vendida onde há ausência declarada**: Fiscal "prévia do TXT" (`previaTxt: null`, decisão [W] pendente — a tela mostra prévia de arquivo de referência); NfeBrasil "emissor de NFS-e" (emissão modelo 56 é stub, só o cancelamento é real); "substituir certificado exige destino + motivo" (exige só o gate); "status por broadcast" (é polling — broadcast barrado por ADR 0058/0062).
- **Modelo fiscal errado**: CT-e citado como modelo 67 (é 57; MDF-e é 58).
- **Ids trocados / estado invertido na Jana**: `context_recall` baixo atribuído à US-COPI-136 (que é o piso, `done`) em vez da US-COPI-133; "piso em subida" (o piso é fixo em 0,36 — quem subiu foi o recall medido); ratio "da memória" (é do fluxo de trabalho, e o ponto é que o alarme nunca dispara); agents `advisor`/`concierge` que não existem; `retention-purge` apresentado como risco vivo (está atrás de `JANA_RETENTION_ENABLED=false`); data 2026-09-02 para o #6609 (mergeou 2026-09-03).
- **Rótulo falso**: "última mudança de capacidade: SDD + contratos" (é documentação/teste); "faturamento automático" no Repair (é venda derivada manual pelo POS); "só i18n e a11y" exaustivo e falso.
- **LC-10 (enforcement em presente)**: "`Pest Repair` não é required" — reescrito apontando o dono (`governance/required-checks-baseline.json`).
- **Crm**: BLOQUEIO 1 "medido só em CI" (foi no CT 100, que não é réplica de prod); data-guarda 2026-09-05 com #6774 de 09-04.

Correção aplicada pelo gerador entre r2 e r3 (§2.6): todas as prescrições acima + restauração das regressões apontadas (redação real de `bank_account_number` no Cliente; `forbidden_drivers`, PR #1768, ADR 0334/US-INFRA-002 no Whatsapp; ADR 0171, errata anti-reincidência, `dvi/{item}/to-orcamento` no OficinaAuto; causa do falso-verde e run 31040822015 no Repair; `PlanoSemFaturaContratoTest` fora da lane + US-RB-002 no RecurringBilling; `fiscal:cert-health-check`/US-FISCAL-022 e `module_clients.yaml` no Fiscal; `NumUfHeuristicPtBRTest`, gap "estornar cancelamento" e `RUNBOOK-create-v3.md` no Sells; `migrate --force` mascarando, lane Jana advisory, `UC-PLAT-03` e ADR 0087 na Jana).

## Rodada r3 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO (4,1%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 25 | 0 | 0,0% |
| A | Crm | 19 | 3 | 15,8% |
| A | NfeBrasil | 33 | 1 | 3,0% |
| B | OficinaAuto | 25 | 0 | 0,0% |
| B | PaymentGateway | 28 | 0 | 0,0% |
| B | Whatsapp | 31 | 0 | 0,0% |
| C | Fiscal | 24 | 2 | 8,3% |
| C | Sells | 29 | 0 | 0,0% |
| C | Jana | 37 | 2 | 5,4% |
| D | Financeiro | 28 | 1 | 3,6% |
| D | RecurringBilling | 22 | 3 | 13,6% |
| D | Repair | 19 | 1 | 5,3% |
| **r3** | **12 portas** | **320** | **13** | **4,1%** |

PII estruturada: 0 hits nos 4 lotes (controle positivo em cada). Lote B aprovado isolado (0/84); o lote é reprovado como um todo (§2.6: reprova inteiro, re-verifica inteiro).

### O que a r3 pegou (todos corrigidos antes da r4)

- **Crm** (3): portal `/contact/*` classificado como parte B quando o plano o declara ZONA CINZA fora do escopo; "uso medido = zero" e "três eixos" restateados sem data nem dono (dono: `DEPRECATION-PLAN-pipeline.md` §Recibo do portal).
- **NfeBrasil** (1): "broadcast é Fase 2C" — invertido: a fase 2C **é** o polling (US-NFE-002); broadcast é alternativa descartada sem número de fase. Ressalva incorporada: o código rotula CT-e como 67 (norma diz 57) — divergência doc×código agora declarada na porta.
- **Fiscal** (2): "Eliana fecha o SPED no dia 15" (o 15 é heurística do painel; prazo legal é por UF; o verbo canon é *entrega*); "pílula de prazo de 90 dias" (o schema usa 180d da NT 2014.002 e a pílula lê o prazo gravado pela SEFAZ).
- **Jana** (2): lane advisory afirmada em presente sem dono (LC-10 — reescrita apontando `required-checks-baseline.json`); `NullDriver` (a classe da Jana é `NullMemoriaDriver` — o erro também está no CLAUDE.md).
- **Financeiro** (1): "parser de retorno CNAB pendente" — existe no PaymentGateway (`CnabRetornoProcessor`); o gap do Financeiro é só o CSV.
- **RecurringBilling** (3): `anchor-lint --servido` não é flag (é veredito no `--json`, chave por Page) e o SDD não tem "§Sinal de uso" (é §9.4); "a maior parte dos testes entrou na lane" (14 de 40); "última mudança 2026-08-05" com 4 PRs posteriores (#5369, #5711, #6307, #6464).
- **Repair** (1): run 31040822015 é de 2026-08-05 (data da descoberta), 2026-08-25 é o conserto (#6240) — a frase trocava descoberta por correção.

Regressões restauradas na mesma passada: ADR 0093 cross-tenant→404 e aba IA no Cliente; `ExecuteStageActionService`/processo `oficina_mecanica_os`, 2ª errata e caveat "não remedidos" no OficinaAuto; recibos KL-E2 (#2750/#2757/#3653) e `Wave26WhatsappSaturationTest` no Whatsapp; drawer SEFAZ J/K, pré-condição NfeBrasil LIVE e destino pós-canary no Fiscal; Caixa/IA/impressão, SDD #4868 e evidência tripla do rollout no Sells; pendência da ADR própria da 0366 §D-C, errata "85%", resultado negativo do `JanaViewsSemAndaimeTest`, 301 das URLs antigas e gate `hasPermissionTo` na Jana; causa-raiz do US-FIN-068 e lane required no Financeiro; biz=1, `CU-RB-09` sem UC e motivo Spatie dos arquivos fora da lane no RecurringBilling; erro `Container::basePath()` e mecanismo da matriz no Repair.

## Rodada r4 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO por pouco (2,43%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 25 | 1 | 4,0% |
| A | Crm | 21 | 0 | 0,0% |
| A | NfeBrasil | 25 | 0 | 0,0% |
| B | OficinaAuto | 29 | 1 | 3,4% |
| B | PaymentGateway | 25 | 2 | 8,0% |
| B | Whatsapp | 31 | 0 | 0,0% |
| C | Fiscal | 24 | 0 | 0,0% |
| C | Sells | 32 | 0 | 0,0% |
| C | Jana | 36 | 1 | 2,8% |
| D | Financeiro | 32 | 0 | 0,0% |
| D | RecurringBilling | 27 | 3 | 11,1% |
| D | Repair | 22 | 0 | 0,0% |
| **r4** | **12 portas** | **329** | **8** | **2,43%** |

PII estruturada: 0 hits nos 4 lotes (controle positivo em cada). Os 4 lotes individualmente ficaram entre 1,1% e 3,7%; o lote inteiro cruza o teto de 2% por 8 itens em 329. Incidente de infraestrutura registrado: os 4 refutadores da r4 morreram na 1ª tentativa por limite de sessão da API (HTTP 429, `claude-opus-5`) e foram relançados; o lote B precisou de 3 tentativas porque o classificador de auto-mode estava rate-limited.

### O que a r4 pegou (todos corrigidos antes da r5)

- **Cliente**: "Última mudança 2026-08-26 (#6303)" — superlativo falso: #6344 (2026-08-27, copy "Copiloto") e #6910 (2026-09-06, `data-contract` no Index) vieram depois. Mesma classe do §5 2026-08-20 (claim escrita de memória, não re-rodada).
- **OficinaAuto**: "números não remedidos desde julho" colapsava duas frases — a medição é de 2026-05-13.
- **PaymentGateway**: a frase "as especificações que citam 'Onda 0 não habilitado' estão corretas" citava texto que só existe no próprio BRIEFING (README/SCOPE dizem "Onda 0 · registrado mas não habilitado" e ancoram em "ADR 0170 proposto", hoje arquivada); US-PG-005 listada como gap seco quando `RegisterInterWebhookCommand` existe com âncora `verificado@98cae0a`.
- **Jana**: intervalo `#6655–#6664` varria #6656 (visual-regression) e #6658 (LICOES) que não são paridade da Jana.
- **RecurringBilling**: "a maior parte das capacidades ausente" (número derivado de estado); "onze arquivos" (o #5194 adicionou 12 e retirou 3 — nove líquidos); razão-Spatie dos arquivos fora da lane escrita em presente quando `Wave21`/`Wave23` voltaram no #5222 no mesmo dia.

Regressões restauradas na mesma passada: LGPD do activity log e do PDF do ledger, âncora `cliente-mapa.jsx` e decisão Wagner 2026-06-22 no Cliente; `fiscal:habilitar-business` e guard cross-tenant nos Services do Fiscal; decisão [W] pendente do module-grade, condição "30 dias de monitor" da US-SELL-009 e fix #2279 no Sells; 3 chips mudos do brief, pendências do superadmin, código MCP que ficou na Jana e advisory do module-grade (ADR 0314 D-1) na Jana; KPI `valor_em_curso` = 0, P5 do RUNBOOK e rota `/aprovar-os/{token}` no OficinaAuto; US-PG-001/002 `todo` + fila de revisão de âncora no PaymentGateway; classe e tabelas do Baileys removidas no Whatsapp; `CU-RB-15` sem UC e "Hello World"/Onda 10 no RecurringBilling.

## Rodada r5 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro + 1 refutador-delta sobre as 17 frases editadas após a r4 · REPROVADO (4,5%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 38 | 0 | 0,0% |
| A | Crm | 25 | 1 | 4,0% |
| A | NfeBrasil | 43 | 1 | 2,3% |
| B | OficinaAuto | 33 | 3 | 9,1% |
| B | PaymentGateway | 31 | 1 | 3,2% |
| B | Whatsapp | 32 | 1 | 3,1% |
| C | Fiscal | 27 | 0 | 0,0% |
| C | Sells | 30 | 0 | 0,0% |
| C | Jana | 37 | 3 | 8,1% |
| D | Financeiro | 31 | 4 | 12,9% |
| D | RecurringBilling | 30 | 3 | 10,0% |
| D | Repair | 25 | 0 | 0,0% |
| **r5** | **12 portas** | **382** | **17** | **4,5%** |
| r5-delta | 17 frases pós-r4 (5 portas) | 61 | 3 | 4,9% |

PII estruturada: 0 hits em todos (controle positivo em cada; o único hit de 11 dígitos no Repair era o id do run de CI, descartado com evidência).

### O achado de método da r5 — por que a taxa SUBIU depois de cair

Treze dos 17 erros da r5 nasceram das **"regressões factuais restauradas"** nas rodadas r2–r4: a cada rodada o refutador listava o que a porta nova havia perdido vs a porta HEAD, e o gerador restaurava. Só que a porta HEAD carregava fatos **caducos** (é exatamente por isso que a métrica a acusou de stale), e restaurar sem re-medir os re-introduziu:

- Jana: *"a ADR própria que a 0366 §D-C exige NÃO existe"* — existe, é a ADR 0378 (aceita por [W] em 2026-08-13); a porta HEAD (2026-08-13) foi escrita no mesmo dia e não a viu.
- OficinaAuto: *"KPI `valor_em_curso` = 0"* (o KPI vivo do Board deriva de `total_items`; o zerado era o do kanban que hoje é redirect) e *"UI 'Locações ativas' a remover"* + charter `ProducaoOficina/Index` v4 — ponteiros podres herdados do SPEC; o charter não existe sob `Pages/OficinaAuto/`.
- Financeiro: *"suspeita não fechada em `aprovacao_status` (US-FIN-027/028)"* — ambas `done` no SPEC; e "Boletos" como capacidade viva (tela aposentada por [W], 301 para Cobrança).
- Whatsapp: decisão de estender `clients_feedbacks` atribuída à ADR 0334 (que só classifica a atrofia) e apresentada em presente, escondendo que `Modules/VozDoCliente` (`voz_sinais`) nasceu em 2026-07-28.
- PaymentGateway: HMAC de webhook listado como capacidade — na verdade só 2 de 7 controllers validam, Asaas e C6 passam `signatureValid: true` sem validar (US-PG-002 `todo`, VULN P0-#2). Esse veio de uma prescrição da r2 que um refutador posterior derrubou.
- RecurringBilling: contagens do #5194 pelo comentário de cabeçalho (12/3) em vez do diff mergeado (+9/−0); "saíram e voltaram" para arquivos que nunca tinham entrado.

Também: divergência entre refutadores (r4 D prescreveu "adicionou 12 e retirou 3"; r5 D mediu o diff e derrubou) — a regra que fica é *o diff mergeado é o dono, não a mensagem do PR*.

Correção aplicada entre r5 e r6: só as prescrições — nenhuma restauração nova. Onde a frase antiga era estado em presente, virou fato datado com dono ou saiu.

## Rodada r6 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO (2,8%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 34 | 0 | 0,0% |
| A | Crm | 24 | 0 | 0,0% |
| A | NfeBrasil | 35 | 0 | 0,0% |
| B | OficinaAuto | 35 | 4 | 11,4% |
| B | PaymentGateway | 26 | 1 | 3,8% |
| B | Whatsapp | 34 | 1 | 2,9% |
| C | Fiscal | 25 | 0 | 0,0% |
| C | Sells | 30 | 1 | 3,3% |
| C | Jana | 44 | 1 | 2,3% |
| D | Financeiro | 33 | 1 | 3,0% |
| D | RecurringBilling | 42 | 2 | 4,8% |
| D | Repair | 28 | 0 | 0,0% |
| **r6** | **12 portas** | **390** | **11** | **2,8%** |

PII estruturada: 0 hits em todos (controle positivo em cada). Lote A fechou em 0/93.

### O que a r6 pegou (todos corrigidos antes da r7)

- **OficinaAuto** (4): três âncoras `SPEC:NNN` que eu copiei da nota de regressão da r3 — que por sua vez as copiara da porta HEAD — apontavam para linhas erradas (373/752/707 → 377/766/721, e as duas últimas são US-AUTO-008/006, não US-OFICINA); "o charter que a US cita não existe" — existe, sob `Pages/Repair/ProducaoOficina/` (o kanban mudou de dono).
- **PaymentGateway** (1): parêntese órfão "(CAPTERRA-FICHA + Onda 2, test-only, PR #3739)" colado sem referente — sobra do corpo HEAD.
- **Whatsapp** (1): recibo #2757 atribuído à fusão KL-E2 (o PR é sobre ghosts de Modules/Sells; os recibos reais são #2750 e #3653).
- **Financeiro** (1): `VISREG-FIN-001` apresentado como id de gate — é o número de um `fin_titulos` semeado como fixture pelo `FinanceiroFlowBaselineTest`.
- **RecurringBilling** (2): "primeiro SDD do repo criado do zero" — o do Cockpit de Compras aterrissou 12h antes; "recibo de 2026-07-28" pendurado num `_STATUS-GENERATED.md` que só existe desde 2026-09-06.
- **Sells** (1): `app/Models/Transaction.php` não existe — é `app/Transaction.php` (erro herdado da porta HEAD).
- **Jana** (1): resultado negativo datado de 2026-08-05 — a medição é de 2026-08-27.

Sete dos 11 vieram de âncoras herdadas da porta HEAD via notas de regressão de refutadores anteriores. Regra que fica para o gerador: **nota de regressão não é fonte — é lista de candidatos a re-medir**. Frontmatter `related_adrs` (4 slugs) restaurado no OficinaAuto porque é metadado que a máquina lê e o distiller o havia derrubado.

## Rodada r7 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO (2,8%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 34 | 0 | 0,0% |
| A | Crm | 25 | 0 | 0,0% |
| A | NfeBrasil | 43 | 0 | 0,0% |
| B | OficinaAuto | 37 | 2 | 5,4% |
| B | PaymentGateway | 37 | 2 | 5,4% |
| B | Whatsapp | 37 | 0 | 0,0% |
| C | Fiscal | 26 | 1 | 3,8% |
| C | Sells | 25 | 0 | 0,0% |
| C | Jana | 29 | 3 | 10,3% |
| D | Financeiro | 36 | 2 | 5,6% |
| D | RecurringBilling | 38 | 1 | 2,6% |
| D | Repair | 30 | 0 | 0,0% |
| **r7** | **12 portas** | **397** | **11** | **2,8%** |

PII estruturada: 0 hits em todos (controle positivo em cada). Lote A: segunda rodada consecutiva em 0/102.

### O achado mais caro da r7 — dois refutadores discordaram sobre um fato Tier 0, e eu medi

Na r5 o refutador do lote B afirmou que os webhooks Asaas e C6 passavam `signatureValid: true` **sem validar** (VULN P0-#2 aberta); eu apliquei a prescrição e a porta do PaymentGateway passou a declarar um buraco de segurança. Na r7 outro refutador leu os controllers e disse o contrário: os 4 chamam `validateSignature` **antes** e devolvem 401. Medi (`git show origin/main:Modules/PaymentGateway/Http/Controllers/Webhooks/*WebhookController.php`): Asaas:55, C6:51, Inter:56 e BcbPix:60 chamam `$this->processor->validateSignature(...)` e retornam 401 na falha; o `signatureValid: true` das linhas seguintes é o **resultado** do gate. O r5 leu o finding do SPEC:67 (auditoria de 2026-05-25) como estado presente. Regra que fica para o gerador **e** para o refutador: **afirmação sobre comportamento de código se verifica no código, nunca num finding datado de SPEC/auditoria** — e prescrição de refutador é hipótese até o gerador medir.

### O que mais a r7 pegou (todos corrigidos antes da r8)

- OficinaAuto: dono das fotos invertido (`ServiceOrderPhotoController` é OS-level; a foto por item DVI é `DviInspectionController@uploadPhoto`); "P5 do RUNBOOK pendente" — o P5 landou no #2500 (2026-06-10), o RUNBOOK é que está stale.
- Financeiro: `/boletos` sem o prefixo `financeiro`; `VISREG-FIN-001` tem 4 escritores com a mesma chave (a lápide de 2026-08-26 já registrava isso).
- RecurringBilling: "o segundo SDD do repo" — é o 13º pela data de criação; a ordem é derivada, não se escreve.
- Fiscal: generalização "os demais Services confiam só no scope" (dois deles não leem dado de tenant).
- Jana: data das metas vazias (2026-08-09, não 08-31); `Exportar` mudou de balde em 2026-08-31 (só `Ouvir áudio` ainda promete data); "`index()` do Superadmin" — o método é do `MetasController`, grupo `can:jana.access`, outra superfície.

## Rodada r8 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO (3,3%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 34 | 0 | 0,0% |
| A | Crm | 24 | 0 | 0,0% |
| A | NfeBrasil | 36 | 0 | 0,0% |
| B | OficinaAuto | 30 | 0 | 0,0% |
| B | PaymentGateway | 25 | 1 | 4,0% |
| B | Whatsapp | 30 | 2 | 6,7% |
| C | Fiscal | 32 | 4 | 12,5% |
| C | Sells | 30 | 1 | 3,3% |
| C | Jana | 48 | 2 | 4,2% |
| D | Financeiro | 37 | 1 | 2,7% |
| D | RecurringBilling | 37 | 1 | 2,7% |
| D | Repair | 27 | 1 | 3,7% |
| **r8** | **12 portas** | **390** | **13** | **3,3%** |

Lote A: terceira rodada consecutiva em 0 erros (93 → 102 → 94 itens). OficinaAuto chegou a 0 pela primeira vez. O lote D da r8 morreu na 1ª tentativa por limite de sessão da API (HTTP 429) e foi relançado.

### O que a r8 pegou (corrigidos antes da r9)

- **PaymentGateway**: âncora errada para 1 dos 6 gateways — o Pagar.me valida HMAC no próprio controller, não no `WebhookProcessor::validateSignature` (que conhece 5 chaves e `default => false`).
- **Whatsapp**: "inbox omnichannel: WhatsApp, Instagram, Facebook, e-mail, Mercado Livre" listado como capacidade — só o eixo WhatsApp opera; os outros tipos existem em `Channel::TYPES` mas o `ChannelDriverFactory` lança `NotImplementedDriverException` e não há webhook (US-WA-063/064/065, backlog gated por sinal de cliente, ADR 0135). Erro **herdado da porta HEAD** e agravado pela enumeração nominal. Movido para Gaps. Também: `LembreteHandler` não persiste mensagem — quem persiste é `Services/Webhook/MessagePersister`.
- **Fiscal** (4): "os outros dois Services" (contagem de estado); NFS-e não tem drawer SEFAZ nem J/K (só a NF-e — e o próprio `Cockpit.charter.md` diz que anunciar J/K onde não existe é LC-15); a leitura da config exige `fiscal.config.edit` (não é "para todos"); a lista de 6 hardcodes é o **escopo** do GAP-FISCAL-003, não o resíduo — o motor já é chamado, o CFOP interestadual foi tratado, o `COD_PART` deixou de ser fixo; o que resta é o `COD_MUN` placeholder.
- **Sells**: "abas no drawer de item" atribuídas ao `SaleSheet` da tela viva — são do `ItemDetalhe.tsx` da preview V3 (#6360).
- **Jana**: "em produção — biz=4 recebe respostas com dados reais" (estado em presente sem dono, contradito pela série de agosto do `jana-ragas-real-baseline.json`); "paridade de design medida contra a âncora" (vaga, sem veredito nem dono).

- **Financeiro/RecurringBilling/Repair** (LC-10, os três): "flags OFF em prod", "live em biz=1" e "Configurações em Inertia" eram estado de produção/ambiente afirmado em presente sem dono — a última porque o #6779 entregou atrás da flag `repair_settings_index` (default OFF) e o cutover é decisão [W] (US-REPA-003 `_parcial_`).

## Rodada r9 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · REPROVADO por pouco (2,43%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 35 | 0 | 0,0% |
| A | Crm | 25 | 0 | 0,0% |
| A | NfeBrasil | 39 | 0 | 0,0% |
| B | OficinaAuto | 31 | 0 | 0,0% |
| B | PaymentGateway | 32 | 0 | 0,0% |
| B | Whatsapp | 36 | 2 | 5,6% |
| C | Fiscal | 28 | 2 | 7,1% |
| C | Sells | 28 | 1 | 3,6% |
| C | Jana | 43 | 0 | 0,0% |
| D | Financeiro | 27 | 0 | 0,0% |
| D | RecurringBilling | 28 | 3 | 10,7% |
| D | Repair | 19 | 1 | 5,3% |
| **r9** | **12 portas** | **371** | **9** | **2,43%** |

PII estruturada: 0 hits em todos (controle positivo em cada). Lote A: quarta rodada consecutiva em 0 erros; OficinaAuto, PaymentGateway, Jana e Financeiro também em 0. Sete das 12 portas fecharam limpas; os 9 erros estão em 5.

### O que a r9 pegou (corrigidos antes da r10)

- **Whatsapp** (2): US-WA-310 citado como dono da âncora `_parcial_` — o SPEC não tem heading pra essa story; a âncora é da US-WA-001 (Wizard). US-WA-063/064/065 citados como o backlog omnichannel — no SPEC esses ids já foram consumidos por Tags e Contact/@lid (`done`), e 065 não existe; a ADR 0135 os reserva na lista de intenção, mas o SPEC é o contrato. Regra que fica: **citar a fase da ADR, nunca um id que o SPEC não tenha**.
- **Fiscal** (2): NFS-e "listagem, detalhe e ações" — só há `GET /fiscal/nfse` e a tela se declara `em-implementacao`; a pílula de prazo DF-e "gravado pela SEFAZ" — quem grava é o próprio importador (`data_emissao + 180d`), erro herdado do SDD e refutado pelo código.
- **Sells** (1): "não há canário pendente" — frase que EU introduzi numa correção anterior, contradita pelo SPEC vivo (US-SELL-001: canary 7d / monitor 30d em aberto; US-SELL-009 `_pendente_`).
- **RecurringBilling** (3): "SPEC + charters + testes, e nada mais" (exaustividade falsa — o `casos.md` é contrato próprio); a enumeração `CU-RB-09/13/15 sem UC` com a porta viva ao lado como recibo — o disfarce da lápide 2026-07-17, proibido mesmo verdadeiro; "NFe automática após pagamento" como capacidade — o listener existe atrás de `nfebrasil.auto_emission_on_invoice_paid` default `false` (US-RB-044).
- **Repair** (1): "contrato executável das telas" — `JobSheet/Index` segue sem `casos.md` (US-REPA-004 `_parcial_`).

Restaurações re-medidas na mesma passada (cada uma conferida no código antes de voltar): `store`/`update` da tributação checam via FormRequest (NfeBrasil); nomes das telas `Drafts`/`Quotations`/`Subscriptions` (Sells); escopo de seis hardcodes do GAP-FISCAL-003 com o `COD_MUN` como resíduo (Fiscal); gate Tier 0 do `PeriodosController` (#4474) na Jana; `POST /financeiro/unificado/bulk` (Financeiro); "o WR Comercial não tinha recorrência" (RecurringBilling).

### A 13ª porta — Governance (lote E)

Enquanto a r9 rodava, `origin/main` recebeu o #6907 (workflow `refutador-gt-g5`), que tocou `memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md` e deixou o BRIEFING do Governance (carimbo 2026-08-13) stale — o baseline em main passou a 13 com a `nota_absorcao_2026_09_06_governance`. Re-destilei o Governance no mesmo clone fresco do CT 100, antes movido para `e2bb4fdc1a` (tip de `origin/main`; `composer.lock` sem diff, `vendor/` reaproveitado): `porta reescrita (3 eventos de 48 candidatos)`, 0 `refused_pii`. Refutação como lote E próprio:

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| E r1 | Governance | 22 | 13 | 59,1% |

A saída bruta da LLM com só 3 eventos de proveniência repetiu os padrões da r1 do lote principal: "quatro RUNBOOKs recém-adicionados" (contagem + "recém" 32 dias depois), "Emibido" (palavra inexistente), "estão sendo tratados" sem dono, "a maior parte da lógica em Node", "88/100" nu, "alta qualidade", "rota `governance.policies.edit`" (é permission), "quatro RUNBOOKs operacionais" (são `rascunho`), e uma "Última mudança" que parafraseava slugs de arquivo (refutação de PR, migração Blade, crons inativos — estes últimos corrigidos em #5443/#5444 em 2026-08-08) 25 dias atrás do evento git mais recente (#6928/#6916/#6907). Corpo reescrito pelo gerador a partir de re-medição direta (rotas, config, controllers, status dos RUNBOOKs, `gh pr view`), preservando o frontmatter do distiller.

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| E r2 | Governance | 49 | 9 | 18,4% |

A r2 do lote E pegou erros **do gerador**, não da LLM: PRs #5443/#5444 com os temas trocados (a fonte, o handoff de 2026-08-08 23:40, estava certa); entry de sidebar atribuída ao #5311 quando o `DataController` mudou no #5308; #6928 listado como mudança do Governance sem tocar um arquivo do módulo; o comando de grep citado como recibo não era o que eu rodei (a forma citada devolve 5 matches, a forma rodada devolve 0 — o recibo tem que ser a forma exata); "segue ABERTO em `RUNBOOK-policies.md` §Estado" quando o achado mora em `RUNBOOK-audit.md` §10 item 6; e a frase "este BRIEFING não restateia enforcement" seguida de uma cláusula que restateia (LC-10 autofalseante). Restaurações re-medidas: alias `actiongate` registrado no `GovernanceServiceProvider`, rota `/governance/audit` sem `can:`, `mcp_governance_rule_history` como prosa, master switch `drift_framework_enabled`, injetores do Daily Brief e a família de comandos artisan. Um fato da porta antiga caducou e **não** voltou: `design-gate-bites.jsonl` existe em `origin/main`.

## Rodada r10 — 4 refutadores novos (opus, sessão fresca) sobre o lote inteiro · APROVADO (1,58%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| A | Cliente | 39 | 1 | 2,6% |
| A | Crm | 23 | 0 | 0,0% |
| A | NfeBrasil | 40 | 0 | 0,0% |
| B | OficinaAuto | 30 | 1 | 3,3% |
| B | PaymentGateway | 23 | 0 | 0,0% |
| B | Whatsapp | 28 | 0 | 0,0% |
| C | Fiscal | 32 | 2 | 6,3% |
| C | Sells | 35 | 0 | 0,0% |
| C | Jana | 46 | 0 | 0,0% |
| D | Financeiro | 33 | 2 | 6,1% |
| D | RecurringBilling | 31 | 0 | 0,0% |
| D | Repair | 19 | 0 | 0,0% |
| **r10** | **12 portas** | **379** | **6** | **1,58%** |

PII estruturada: 0 hits em todos (controle positivo em cada). Primeira rodada abaixo do teto de 2% do protocolo — oito das 12 portas em 0 erros; Crm, NfeBrasil, PaymentGateway, Jana e Repair fecharam limpas em rodadas consecutivas.

### Os 6 da r10 (corrigidos no mesmo PR, depois da aprovação — mesma forma do #6928)

- **Cliente**: "fidelidade visual segue não medida" — estado em presente sem dono, e a justificativa que o sustentava na porta HEAD ("Cliente não tem baseline visual") caducou no mesmo dia em que foi escrita (há `.snap` de Import e Map). Reescrito como fato datado com os 3 donos do veredito.
- **OficinaAuto**: P5 do RUNBOOK atribuído ao #2500 — `git log -S` mostra que a troca `'Caçambas'→'Veículos'` entrou no #2468 (2026-06-09); o #2500 tocou outra linha do topnav.
- **Fiscal** (2): gate de ambiente descrito como cumulativo com `fiscal.config.edit` — o `garantirGateAmbiente` checa `superadmin || fiscal.config.ambiente`, gate próprio; e a constante `PERMS_BLOQUEADAS_ATE_GAP_003` apontada como "lista dos seis hardcodes" — ela lista uma permissão; a lista dos seis vive só no cabeçalho do gerador. Ambos nasceram de correções minhas da r8/r9 — corrigir um número sem re-medir a evidência dele (lápide 2026-08-28).
- **Financeiro** (2): enumeração de 3 testes do bucket C logo depois da frase "re-rode a lista, não copie" (a lista declara que deve encolher — enumeração apodrece por construção); e "suspeita em `aprovacao_status` é ponteiro vivo" — grep na quarentena devolve 0 ocorrências do tema; era recibo datado de 2026-08-05 convertido em presente.

Restaurações re-medidas na mesma passada: donos datados do veredito de paridade e Non-Goal LGPD (Cliente); nomes das telas com `casos.md` (NfeBrasil); `POST /boletos/{remessaId}/cancelar` vivo ao lado do 301 e "quarentenado não produz veredito" (Financeiro); recibo 301 do cutover (RecurringBilling, no lugar de uma frase duplicada); totais de topo da V3 chegam prontos do controller (Sells); flags `false` por default, US-COPI-137/138 (Jana).

## Lote E — Governance, r3 · APROVADO (1,8%)

| Lote | Módulo | itens | erros | error_rate |
|---|---|---|---|---|
| E r3 | Governance | 55 | 1 | 1,8% |

O único refutado foi herdado da porta anterior: o nome da rota do dashboard legado é `governance.admin.dashboard.legacy` (o grupo prefixa `->name('governance.')`), não `admin.dashboard.legacy`. Restaurações re-medidas: `MultiTenantGovernanceTest` como segunda suíte cross-tenant; drift SPEC↔mundo das US-GOV-049/050; docblock "uso (futuro)" do `ActionGate`. O recibo do grep citado no corpo foi rodado na forma literal pelo refutador, com controle positivo do mesmo pathspec.

## Resultado

| lote | rodadas | trajetória (error_rate) | veredito final |
|---|---|---|---|
| 12 portas (A–D) | r1–r10 | 31,8 → 8,6 → 4,1 → 2,43 → 4,5 → 2,8 → 2,8 → 3,3 → 2,43 → **1,58%** (379 itens / 6 erros) | aprovado na r10 |
| Governance (E) | r1–r3 | 59,1 → 18,4 → **1,8%** (55 itens / 1 erro) | aprovado na r3 |

PII estruturada e valores em R$: 0 hits em todas as rodadas, com controle positivo em cada lote. As correções prescritas pela rodada aprovada foram aplicadas no mesmo PR, depois da aprovação (mesma forma do #6928); nenhuma restauração entrou sem re-medição no código de `origin/main`.

Medição final (`node scripts/governance/sdd-scorecard.mjs --json`, repo desrasado): `origin/main` puro = **13** stale (portas 80, carimbadas 14, oldest 2026-07-17); mesma árvore com os 13 BRIEFINGs = **0** (portas 80, carimbadas 14, oldest 2026-09-05). Floor re-apertado 13→0 no baseline com `nota_restauracao_2026_09_06`.

### Lições que ficam para o distiller (chips)

- O distiller derruba `id:` e `related_adrs` do frontmatter — consumidores (`doc-id-index`, schema) quebram; o pós-processo aqui foi mecânico, mas o conserto é no `DistillerModuloVerdade`.
- Com poucos eventos de proveniência (Governance: 3 de 48 candidatos) a LLM parafraseia slugs de arquivo como "última mudança" — o gatherer precisa ler `git log` do módulo, não só sessions/handoffs.
- Proveniência circular (RecurringBilling r1): sessions de refutação de OUTRO lote entraram como fonte — filtrar sessions de processo.
- Regra de método que se repetiu em 4 rodadas: **nota de regressão de refutador não é fonte — é candidato a re-medir**; restaurar da porta HEAD sem medir reintroduziu fatos caducos (r5) e âncoras erradas (r6).
- Regra de recibo (r2 do Governance): o comando citado no corpo tem que ser a forma **literal** que foi rodada — o refutador roda e compara.
