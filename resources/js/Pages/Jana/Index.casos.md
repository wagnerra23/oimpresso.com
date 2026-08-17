---
id: resources-js-pages-jana-index-casos
casos: Jana Painel · metas ativas · farol server-side · /ia
irmaos: Index.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-index.md (runbook) · prototipo-ui/contrato/jana-painel.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /ia (Painel da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Index.charter.md` (§Goals/§Anti-hooks) e do `jana-painel.contract.json` — **não**
> do `Index.tsx`. Derivar do código seria tautológico (§5 2026-06-05): passaria verde mesmo com o
> comportamento errado.
>
> ⚠️ **A âncora de design desta tela tem um trecho ENVENENADO.** O `related_prototype` é
> `prototipo-ui/cowork/jana-merge.jsx` (resolva sempre por `node prototipo-ui/ancora.mjs Jana/Index`,
> nunca no olho). As regras **visuais** dele valem; o que ele diz sobre **dado e fonte** não:
> ele renderiza o KPI `Frota utilização`, a linha `Locadas` e `caçambas` — domínio erradicado pela
> [ADR 0265](../../../../memory/decisions/0265-oficina-reparo-erradica-locacao.md), vetado por [W] 2× —
> e cita 6 `Analise*Service` que **não existem** no repo (a fonte real é
> `app/Services/Sells/SellsCockpitAggregator.php`). Medido em 2026-08-17: `frota` 12× e `caçamba` 16×
> entre `jana-merge.jsx` e `chat-jana.jsx`, e o `dominio-gate` **não pega** — `prototipo-ui/` não
> está em `forbidden_ui_paths` (`memory/dominio/oficina-auto.md:51-55`). Quem derivar da âncora sem
> ler o §Non-Goals do charter reintroduz a locação, e o CI deixa passar.

## UC-COPI-PAINEL-04 — O farol é do SERVIDOR, e "sem base pra julgar" é `cinza`, nunca vermelho
Status: ✅ (`Modules/Jana/Tests/Feature/FarolServerSideTest.php` — 2 casos citam este UC no título)

O Painel pinta cada meta com um farol. Quem decide a cor é `ApuracaoService::farol()`; a Page só
**consome** o campo que chega no payload. Quando não há base pra julgar — sem período, sem apuração,
período que não começou, ou período de duração zero — a resposta é `cinza`, que é o rótulo de *"não
dá pra dizer"*, e **não** vermelho.

Âncora: charter §Goals *"Farol calculado server-side … frontend só consome"* + §Anti-hooks
*"⛔ Cálculo de farol no frontend"*.

O quarto caso é a divergência consciente do port e está travada de propósito: no JS a duração zero
dividia por zero → `NaN`, o `NaN` falhava os dois `>=` e a meta caía em **vermelho**. Dado incoerente
não é "meta indo mal".

**Pronto quando:** os quatro casos devolvem `cinza`; as fronteiras `-5%` e `-15%` seguem verde/amarelo/
vermelho; e `Index.tsx` **não** contém `function calcularFarol` (só o leitor `farolDaMeta`).

---

## Ainda sem UC — prosa honesta, porque UC sem teste quebra o G-2

> O pedido [CC] de 2026-08-13 propôs mais quatro casos. Eles são legítimos, mas **não viram UC aqui
> enquanto não existir teste que os cite**: id declarado sem teste reprova o G-2 ([ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md)), e
> prometer teste inexistente é pior que a ausência. Ficam como `[BACKLOG]` — prosa visível, sem gate.
>
> ⚠️ Cuidado ao escrevê-los: a convenção de teste deste módulo usa `markTestSkipped` quando o banco
> não está semeado, e **skip sai exit 0** (LC-13). Um teste que "passa" sem rodar é pior que teste
> nenhum — a prova é a contagem de *assertions*, não a de falhas.

- `[BACKLOG]` **Empty state** — nenhuma meta cadastrada mostra "Nenhuma meta cadastrada ainda" +
  CTA "Conversar com a Jana". Já **pinado** pelo contrato visual (`painel-metas-vazio`); falta o
  caso de servidor. Medido em 2026-08-09 pelo pedido [CC]: é o estado real de **100% dos tenants**.
- `[BACKLOG]` **Meta sem apuração** — `ultima_apuracao` nula renderiza "Aguardando apuração…",
  **nunca** zero como se fosse resultado. Copy pinada (`painel-meta-apurando`).
- `[BACKLOG]` **Série curta** — menos de 2 apurações não desenha sparkline: mostra "Sem histórico".
  Copy pinada (`painel-meta-sem-historico`).
- `[BACKLOG]` **Escopo `business_id` (Tier 0, [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))** — o `buildMetasPayload` filtra por
  `business_id` **ou `NULL`** (metas repo-wide). Esse `orWhereNull` é a parte que um teste
  cross-tenant precisa cobrir explicitamente: ele é intencional, e um teste ingênuo o leria como
  vazamento. Tenant de teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — nunca biz=4.

## Decisões pendentes de [W] que travam o ciclo desta tela

Medido em 2026-08-17 com `node scripts/governance/ciclo-completo.mjs`: `Jana/Index` fecha **1 de 6**.

- ⚖️ **`related_prototype`** — o check `pt_declarado` só casa `PT-0X`, e o campo vale
  `jana-merge.jsx`. Mantê-lo reprova `pt_declarado` e `golden_live` **para sempre**; trocar por
  `n/a (herda PT-04 Dashboard)` ganha o check e **perde** a proveniência declarada do drill-down
  (`JmDrillDrawer` · `JM_KPI_DRILL`). É proveniência de tela, não wiring — decisão [W].
- ⚖️ **Golden PT-04 `draft` → `live`** — aprovação de **screenshot** (gate F1.5, [ADR 0107](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).
  Nenhum código resolve, e trava **3 telas**, não só esta.
- ⚖️ **"Dashboard" × "Painel"** — a aba se chama Painel e a rota é `/ia`, mas título, breadcrumb e o
  nome do componente exportado ainda dizem Dashboard.
- ⚖️ **Os dois botões "(em breve)"** — Configurar e Exportar são clicáveis, sem `disabled` e sem
  rota. Somem, viram `disabled` com o motivo, ou entregam? Enquanto não decidido **não entram no
  contrato** — pinar uma promessa é congelá-la.
