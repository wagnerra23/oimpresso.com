---
date: '2026-07-27'
topic: "Chip S-Cliente do passo 5 — SDD do cadastro de Cliente + a lane de PR que faltava (15 US 'verde impossível' → 1)"
authors: [C]
id: sessions-2026-07-27-sdd-cliente-cadastro
outcomes:
  - "SDD-cadastro-cliente-v1.0.md criado (§0–§11, 15 CU, 3 fontes — Delphi ausente declarado)"
  - "lane cliente-pest.yml criada (advisory, 17 arquivos) — anchor-lint 'fora de lane' 15 US → 1"
  - "UC-CSHW-03 novo (PII bancária + Tier 0 da aba Pagamentos) + ClientePagamentosPiiTest"
  - "SPEC §1 corrigido: App\\Contact NÃO tem global scope (0 addGlobalScope, medido)"
  - "SPEC §2 corrigido: maskTaxNumber FORMATA, não redige"
  - "falso-verde da porta viva ('Nenhuma lacuna' com CU=0) fechado — status das 22 US movido pra metadata legível"
us:
  - US-CRM-063
  - US-CRM-064
  - US-CRM-065
  - US-CRM-076
  - US-CRM-080
related_adrs:
  - '0351-sdd-from-source'
  - '0093-multi-tenant-isolation-tier-0'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0273-anchor-spec-codigo-formato-canonico-fluxo-novo'
---

# Chip S-Cliente (Onda 2, passo 5) — o módulo que já tinha contrato e não tinha lane

**Alvo:** `Cliente/Show` como âncora, e as 6 irmãs. **Agent:** `sdd-from-source` ([ADR 0351](../decisions/0351-sdd-from-source.md)). **Plano:** [passo-5-sdd-por-modulo.md](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).

O Cliente entrou diferente dos irmãos da leva: **já tinha 21 UC em 7 `casos.md`, todos com teste que os cita**. Não havia contrato a escrever do zero. O trabalho foi **(a)** derivar o SDD do que já estava contratado, **(b)** medir se aqueles testes *rodavam em algum lugar*, e **(c)** contratar o único eixo Tier 0 que estava descoberto.

---

## 1. O número que justifica a corrida (derivado, antes → depois)

`node scripts/governance/anchor-lint.mjs memory/requisitos/Cliente/SPEC.md`:

| Métrica | Antes | Depois |
|---|---:|---:|
| **US com teste-que-cobre FORA de lane** ("verde impossível") | **15** | **1** |
| anchor coverage | 100% | 100% |
| `anchored_dead` · `anchored_zombie` · testes-fantasma | 0 · 0 · 0 | 0 · 0 · 0 |
| gate de entrada (US sem aceite / sem teste) | 0 · 0 | 0 · 0 |
| `anchor-lint --check` exit | 0 | **0** |

A única US que sobra fora de lane é a **US-CRM-073**, cujo `Testado em:` aponta `tests/br-inputs.test.tsx` — **Vitest, não Pest**: outro runner, outra régua. Não é dívida desta lane.

**As três portas de "roda / é cobrado"**, e onde o Cliente estava em cada uma:

| Pergunta | Porta | Antes | Depois |
|---|---|---|---|
| roda em algum lugar? | `phpunit.xml` (`./tests/Feature` recursivo) | ✅ nightly CT 100 | ✅ |
| **roda no PR?** | `.github/ci-sqlite-pest.list` (allowlist) | ❌ **0 entradas de Cliente** | ✅ lane nova |
| bloqueia merge? | `governance/required-checks-baseline.json` | ❌ não | ❌ **segue não** (advisory) |

Cinco dos sete `casos.md` afirmavam em prosa *"lane ativa"* / *"passa no CI"*. Era **falso** — corrigido em todos, com a força do veredito agora **declarada** (advisory, lida do baseline, não deduzida do YAML).

## 2. Porta viva do módulo (antes → depois)

`node scripts/governance/requisitos-status.mjs Cliente` — ⚠️ **medido com a régua nova** (o parent corrigiu o extrator de UC no meio da corrida; números da régua velha não comparam):

| Elo | Antes | Depois |
|---|---:|---:|
| US no SPEC | 22 | 22 |
| **CU no SDD** | **0** (não havia SDD) | **15** |
| Telas com `casos.md` | 7/7 | 7/7 |
| UC declarados | 22¹ | **22** |
| UC com teste que os cita | 22¹ | **22** |
| **Veredito do painel** | *"Nenhuma lacuna"* — **falso-verde** | 3 CU + 12 US na fila |

¹ Os 22 "antes" da régua nova incluíam **1 falso-positivo**: `UC-11`, id de um UC da **OficinaAuto** citado em prosa num `[BACKLOG]` do `Ledger.casos.md`. O `extrairUC` varre o **texto inteiro** do `casos.md`, então id de outro módulo mencionado em prosa entra na contagem — e o `ucCitadoPorTeste` faz `src.includes('UC-11')`, então "coberto" por qualquer teste da Oficina. **Reportado ao dono do script** (arquivo fora da área do chip); aqui só tirei o gatilho, com a razão escrita ao lado. Logo: reais eram **21**, e o `+1` do depois é o UC novo.

### Por que o painel dizia "Nenhuma lacuna" com CU=0

`extrairUS` lê `status:` nas **3 linhas seguintes** ao heading `### US-...`. O `Cliente/SPEC.md` escrevia `**Status:** done (PR …)` na **6ª** linha — fora da janela → as 22 US liam `desconhecido` → nenhuma contava como *entregue* → nenhuma podia ser *"entregue sem contrato"* → **falso-verde**. Mesma família (LC-11) dos dois falso-verdes que a própria porta corrigiu hoje.

**Fix, sem duplicar fato:** a linha `**Status:**` foi **movida** (não copiada) pra logo abaixo do heading, no formato de metadata que o parser documenta: `> status: done · PR #1298 Wave 5 W-A · 2026-05-21`. 22 blocos. A US-CRM-078 (*"PR1+PR2 done · PR3 pendente"*) virou `doing` — interpretação declarada aqui, não escondida.

## 3. Achados (com varredura CONTADA)

### A-1 · `App\Contact` NÃO tem global scope — e o SPEC §1 dizia que tinha ⚠️ Tier 0

`grep -c "addGlobalScope" app/Contact.php` → **0**. Traits: `Notifiable`, `SoftDeletes`, `LogsActivity`. O padrão canônico existe (`app/Concerns/HasBusinessScope.php`, usado por 10 Entities do ComVis + `Arquivo`) e **`Contact` não o usa**. O filho `ContactAddress` usa `BelongsToBusinessViaParent` **e tem** teste cross-tenant; o **pai não**.

O SPEC §1 afirmava: *"`App\Contact` usa global scope `business_id` (UPOS canon). TODA query passa por scope automático."* O próprio SPEC se contradizia: **US-CRM-080** se chama *"Teste cross-tenant no `App\Contact` pai + avaliar global scope"* e o DoD dela já dizia a verdade.

**Corrigido no mesmo PR** (fato verificável, não intenção — Fase 2.6). `Contact::where(...)`/`Contact::find(...)` aparece em **126 linhas** de `app/` + `Modules/`; **não afirmo que exista vazamento hoje** (não varri as 126 uma a uma nem rodei teste que prove) — afirmo que a defesa é **disciplina, não mecanismo**, e que a doc dizia o contrário.

### A-2 · O "mascaramento" de CPF/CNPJ **formata**, não redige ⚠️ é o diferencial nº 1 declarado do módulo

`grep -rn "maskTaxNumber" --include=*.php` (fora de `memory/`) → **19 ocorrências, 2 implementações** (`ContactController:419`, `ClienteAutosaveController:711`). As duas só aplicam `preg_replace` de pontuação: `12345678901` → `123.456.789-01`. **Nenhum dígito escondido.**

O código é honesto — o docblock do segundo diz textualmente *"mantem digitos visiveis … nao redact … futura ADR pode endurecer pra realmente censurar"*. **A documentação não era:** SPEC §2, os Anti-hooks de `Index`/`Show` e a `CAPTERRA-FICHA` C03 (*"à frente de TODO ERP BR"*) leem como redação. E os **4 testes que "provam" o masking** fazem `file_get_contents` do Controller pra checar que **a chamada está escrita** — presença de chamada, nunca efeito (L-24); 2 deles em `@group legacy-quarantine`.

**O que fiz:** corrigi a redação do SPEC §2 (fato), registrei no SDD §5.4.3, e **deliberadamente NÃO escrevi teste que trave o comportamento atual** — travar o desvio é o anti-padrão de [proibicoes §5](../proibicoes.md) 2026-06-05. *Censurar ou não* é decisão de produto + jurídico → **escalado**.

### A-3 · Contato `type='both'` perde 2 abas na ficha React (paridade MWART)

`contact/show.blade.php:66-82` serve **Compras** (`purchases_tab`) e **Relatório de estoque** (`stock_report_tab`) para `type ∈ {both, supplier}`. Varredura: `resources/js/Pages/Cliente/_show/` = **13 arquivos**; nenhum é `PurchasesTab`/`StockReportTab`. O `_drawer/OssTab` reusa **7** dos 13 — nenhum deles tampouco. E `/cliente/{id}` aceita `type ∈ {customer, **both**}`.

→ perda de superfície na migração. **Nenhum charter declara Non-Goal.** Virou CU-CLI-15 ⬜ + `[BACKLOG]` sem id (não há implementação a defender; UC agora nasceria órfão e travaria o merge de quem for atendê-lo, G-2).

### A-4 · Três Non-Goals de charter que o código já contradiz

| Charter | Non-Goal | Realidade |
|---|---|---|
| `Show` | *"❌ Tab Atividades / Pessoas de contato / Assinaturas — escopo futuro"* | os 3 componentes existem; US-CRM-068/069 `done` |
| `Create` | *"❌ Lookup CEP automático ViaCEP (futuro)"* | `BrLookupService` faz ViaCEP server-side; US-CRM-075 `done` |
| `Index` | *"❌ Show.tsx full-page (DELETADO no mesmo PR)"* | `Show.tsx` existe e é servido quando `cliente_show` liga |

**Não tocados** — Non-Goal é intenção, só [W] escreve. Registrados no SDD §5.4.6.

## 4. Reportado, não consertado (arquivo global / fora da área)

| # | O quê | Onde |
|---|---|---|
| G-1 | `Modules/Crm/Tests/Feature` tem **14 arquivos** e **não está em `phpunit.xml`** — não roda nem no nightly. Falsa cobertura da classe *"`Modules/X/Tests` sem CI"* | `phpunit.xml` |
| G-2 | `extrairUC` do `requisitos-status.mjs` conta id de UC de **outro módulo** citado em prosa (o `UC-11` acima); `ucCitadoPorTeste` usa `includes()`, então casa substring (`UC-11` ⊂ `UC-110`) | `scripts/governance/requisitos-status.mjs` |
| G-3 | **Não existe `memory/dominio/cliente.md`** (`ls memory/dominio/` = 6: compras · estoque · financeiro · fiscal-faturamento · oficina-auto · vendas) → os enums de `contacts.type` estão **fora** do `dominio-gate` G-4 | `memory/dominio/` |
| G-4 | `screen-coverage-map` procura RUNBOOK/visual-comparison em `memory/requisitos/<mod>/`, e o `<mod>` vem do path das Pages (`Cliente`) — mas os 8+8 do Cliente vivem em `memory/requisitos/**Crm**/`. **Mitigado sem tocar o script**: declarei `related_runbook:`/`related_visual_comparison:` nos 7 charters (o mecanismo autoritativo que o próprio script prefere) → 14 artefatos saíram de `✗ ausente` pra `✓ declarado no charter` | `scripts/qa/screen-coverage-map.mjs` |

## 5. Orçamento da corrida

| Item | Valor |
|---|---|
| Arquivos lidos (integral ou parcial) | ~35 |
| Varreduras contadas (`grep`/`find` sem corte) | 9 — `addGlobalScope` (0) · `maskTaxNumber` (19) · `Contact::where\|find` (126) · `_show/` (13) · `ANTI-REGRESSAO` (2, ambos do Produto) · `CU-CLI-*` pré-existentes (0) · `memory/dominio/` (6) · abas do `show.blade` (9 + injetadas) · workflows Pest (11 lanes) |
| Gates rodados | `requisitos-status` · `anchor-lint` (+`--check`) · `casos-coverage-guard` · `deadlink-gate --check` · `screen-coverage-map` (+`--screen`) |
| **UC pré-existentes que ganharam teste** | **0** — os 21 já tinham. O chip **não reescreveu nenhum** |
| **UC novos** | **1** — `UC-CSHW-03` (PII bancária + Tier 0 da aba Pagamentos), promovendo o `[BACKLOG]` mais antigo do `Show.casos.md` |
| `[BACKLOG]` novos (sem id, de propósito) | 4 — cross-tenant da listagem · `both` sem abas de fornecedor · CPF censurado · (o de export reescrito) |
| Testes escritos | 1 arquivo, 4 casos |
| CU propostos | 15 (`CU-CLI-01..15`) |
| Telas cobertas | **7 de 7** |
| Reusado da análise do módulo (Fase 1.4) | **alto** — 1ª tela do módulo, mas o módulo tinha `CAPTERRA-FICHA` + `CAPTERRA-INVENTARIO` + 8 RUNBOOKs + 21 UC prontos. As 6 telas irmãs custaram pouco: o §5.3 sai de um Controller **único** (`ContactController`, 3.558 LOC) e a resolução da Blade é a **mesma máquina** (7 flags, mesmo gate) — resolvi as 7 de uma vez, não uma a uma |
| Re-varrido (não reusável) | resolução da Blade por tela (7×) · consumidores de `maskTaxNumber` e de `Contact::` · abas do `show.blade` vs `_show/` |
| **Gargalo** | **medir as 3 portas de execução** (`phpunit.xml` × `ci-sqlite-pest.list` × `required-checks-baseline.json`). Foi o que consumiu mais e o que rendeu mais: o achado 15→1 não estava em nenhum documento — só apareceu rodando o `anchor-lint` e cruzando com a allowlist. Segundo gargalo: entender **por que** a porta viva dizia "Nenhuma lacuna" (janela de 3 linhas do parser × formato do SPEC) |

## 6. Lições de mecanismo (o que na definição do agent atrapalhou)

1. **A área declarada do chip apontava pro lugar errado, e a medição salvou.** O prompt mandava escrever testes em `Modules/Crm/Tests/**`. Medido: os testes do Cliente vivem em `tests/Feature/Cliente/`, e `Modules/Crm/Tests/Feature` **não está no `phpunit.xml`** — um teste novo lá não rodaria nem no nightly, reproduzindo exatamente o defeito "verde impossível" que o chip existe pra matar. Escrevi em `tests/Feature/Cliente/` e declaro o desvio aqui. **Sugestão:** a área do chip devia ser *derivada* (onde os testes do módulo já vivem), não assumida pelo nome do diretório de `Modules/`.
2. **"Verificar se os UC têm teste" não é a pergunta suficiente.** Os 21 tinham teste e a resposta era ✅ — mas os testes **não rodavam em lane de PR nenhuma**. A pergunta útil tem 3 níveis (roda em algum lugar / roda no PR / bloqueia merge), e o agent já sabe disso (§Camada 1.2), mas o *prompt do chip* pergunta só o 1º. Valeria o chip pedir os 3 explicitamente.
3. **Slug de ADR se confere, não se deduz.** Escrevi 5 links de ADR pelo assunto e os 5 estavam errados; o `deadlink-gate` (required) pegou. Adotei `ls memory/decisions/NNNN-*` antes de linkar — e o caso do **0178 duplicado** (dois arquivos, mesmo número, assuntos diferentes) mostra que nem o número desambigua.
4. **Contratar um eixo Tier 0 exige olhar o que a função FAZ, não como se chama.** `maskTaxNumber` tem "mask" no nome, 4 testes verdes e uma nota alta de benchmark — e não esconde um dígito. Se eu tivesse escrito o UC de PII em cima dele (o caminho óbvio), teria carimbado a formatação como redação. O que salvou foi ler as **duas** implementações inteiras antes de escrever o assert.
5. **Falso-positivo por prosa:** citar o id de um UC de outro módulo dentro de um `casos.md` o injeta na contagem daquele módulo. Não é óbvio ao escrever, e o texto era legítimo ("mesmo padrão UC-11 Oficina"). Um `casos.md` provavelmente deveria poder citar id externo sem ser contado.

---

## Artefatos deste chip

**Criados**
- `memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md` — §0–§11, 15 CU, 3 fontes (Delphi ausente, declarado)
- `memory/requisitos/Cliente/_STATUS-GENERATED.md` — derivado (22 US · 15 CU · 22 UC)
- `.github/workflows/cliente-pest.yml` — lane MySQL, **advisory**, 17 arquivos na allowlist
- `tests/Feature/Cliente/ClientePagamentosPiiTest.php` — 4 casos, UC-CSHW-03

**Editados**
- `memory/requisitos/Cliente/SPEC.md` — §1 (global scope: fato corrigido) · §2 (masking: fato corrigido) · nota de lane · `status:` das 22 US movido pra metadata legível pela porta
- 7 × `Cliente/*.casos.md` — lane + força declaradas · tabela de rastreabilidade UC→CU→US · trilha do tempo · `[BACKLOG]` novos · **nenhum UC pré-existente reescrito**
- 7 × `Cliente/*.charter.md` — **só** `related_runbook:` / `related_visual_comparison:` (declaração de arquivo que existe; zero intenção tocada)

## Escalado pra [W] (o agente não decidiu)

1. **CPF/CNPJ deve ser censurado no payload?** (A-2 · SDD §5.4.3) — produto + jurídico, com custo real (a Larissa confere documento na tela; a NFe precisa do número).
2. **`App\Contact` ganha global scope** ou o `where()` manual segue sendo a defesa? (A-1 · US-CRM-080)
3. **Contato `both`:** implementar as 2 abas ou declarar Non-Goal? (A-3 · CU-CLI-15)
4. **Os 3 Non-Goals caducados** dos charters: revogar, ou o código excedeu a lei? (A-4)
5. **CU-CLI-12** (direitos do titular, LGPD Art. 18): prioridade real ou Non-Goal pra PME?
6. **Merge** (R10) — o chip não faz git op nenhuma.
