# como-integrar — M1 (detector de mudança de UI não-declarada) + M2 (verdade visual tela×.jsx)

> Agente `como-integrar` (introspectivo). Só leu `memory/` + código. NÃO escreveu código de produção.
> Data: 2026-07-11. Branch de análise: `claude/m1-detect-ui-drift` (== origin/main, fresh).
> Pedido Wagner: mapear ONDE já está feito p/ decidir REUSE vs CRIAR. Dor textual: *"cada customização não é pega como alteração da máquina — parece que não sabe que alterou"*.

---

## Veredito de 1 linha

**M1 = PARCIAL forte (estende).** O vocabulário de autorização (`divergence_from_blueprint` + `related_prototype`) JÁ EXISTE e JÁ é lido (`reconcile-triplet`). O padrão diff-triggered "tsx tocado → artefato-irmão tocado?" JÁ EXISTE (`design-return-gate`). **Falta o casamento dos dois: um gate diff-aware por-PR que exige SINAL DE AUTORIZAÇÃO fresco quando a `.tsx` muda.** Nenhuma peça atual faz isso.

**M2 = COBERTO (só falta wire).** `design-diff.mjs` (ADR 0299) é a M2 medida. Falta apenas disparar per-PR nas ~11 telas bespoke. Não há engine a criar.

---

## FASE 1 — INVENTÁRIO

### Tabela M1 (detecção de mudança não-declarada)

| Peça existente | Arquivo | Cobre a INTENÇÃO do Wagner? | Cobertura |
|---|---|---|---|
| Vocabulário de autorização `divergence_from_blueprint` | frontmatter charter (lido em `reconcile-triplet.mjs:306`) | **SIM, parcial** — é exatamente o "declarou desvio". Mas usado só p/ classificar paridade de estado, não p/ gate on-change | 36/147 charters já têm o campo |
| Vocabulário `related_prototype` | frontmatter charter (lido por `ancora.mjs`) | **SIM, parcial** — é o "seguiu o protótipo". Mas ninguém checa se MUDOU junto com a .tsx | 84/147 (só **11 apontam .jsx/.html real**; 74 são "n/a herda PT-0X") |
| `reconcile-triplet.mjs` (paridade 3-way) | `scripts/governance/reconcile-triplet.mjs` | **ADJACENTE** — compara ESTADO (charter×prod por 6 slots PT-01), classifica CONFORME/DIVERGENCIA_DECLARADA/DIVERGENCIA_MUDA. NÃO é diff-triggered; roda `--all` ou 1 tela; protótipo é coluna decorativa | advisory, 147 telas mas só slots PT-01 grossos |
| `design-spec-gate` + `design-spec-gen.mjs` (ADR 0255) | `scripts/design-spec-gen.mjs` | **ADJACENTE** — torna mudança ESTRUTURAL visível (diff do .json). MAS: (a) só estrutura (imports/tokens/px), cega a reorder/copy/spacing sub-threshold; (b) **regen mecânica passa sem "por quê"** — zero conceito de autorização | **1/147** telas (só `.design-spec.json` commitado) |
| `design-return-gate` (§10.2 pós-merge) | `.github/workflows/design-return-gate.yml` | **PRECEDENTE ESTRUTURAL DA M1** — "tsx tocado no merge → SYNC_LOG tocado? senão ::warning::". É a MESMA forma da M1, mas no eixo do loop Cowork (SYNC_LOG), não da autorização (charter) | advisory, pós-merge (push main), todas telas |
| `anchor-drift.yml` (ADR 0273) | diff-aware no PR | ADJACENTE — lint spec↔código (SPEC.md), não charter/UI | advisory |
| `charter-live-signal.mjs` | `scripts/governance/charter-live-signal.mjs` | ADJACENTE — honestidade do `status: live` (prod-flags/route-hits), não mudança-não-declarada | advisory diff-aware |
| `visual-comparison-staleness.mjs` | idem | ADJACENTE — mede derivada temporal (doc de comparação parado enquanto tela anda). Não é autorização | reporter |

**Descoberta-chave:** a M1 do Wagner é a **interseção não-preenchida** de duas peças que já existem separadas — o vocabulário (`divergence_from_blueprint`/`related_prototype`, em `reconcile-triplet`+`ancora`) e a forma diff-triggered "tsx-tocado→irmão-tocado?" (em `design-return-gate`). Ninguém uniu.

### Tabela M2 (verdade visual tela viva × .jsx aprovado)

| Peça existente | Arquivo | Cobre a intenção? | Cobertura |
|---|---|---|---|
| `design-diff.mjs` (ADR 0299) | `prototipo-ui/design-diff.mjs` | **SIM — É a M2.** Diff MEDIDO computed-style prod×design, split probe(browser)+compare(node), dims D2/D4/D6/D8, determinístico+testável (`--selftest`) | engine pronto; disparo é agente-driven (skill `comparar-design-prod`) |
| `screen-smoke-after-merge.yml` | workflow | Smoke visual REAL pós-deploy (Playwright+OpenAI vision) | automação pós-deploy, não per-PR |
| `visual-regression.yml` (Pest 4 Browser) | workflow required | Regressão pixel vs baseline própria — NÃO compara com o .jsx Cowork | required |
| `ancora.mjs` | `prototipo-ui/ancora.mjs` | Resolve o .jsx legítimo da tela (`--list --json`), nega print de auditoria | — |

**M2 não tem gap de engine.** O gap é operacional: quais ~11 telas bespoke, quando disparar, e o olho do Wagner como juiz final (isso é intencional, não automatizável).

### ADRs / charters / SPECs relacionados

- **ADR 0255** — design-spec determinístico (a "prima da M1"). **ADR 0299** — design-diff/M2 + Figma≠fonte.
- **ADR 0271 / 0314** — PODA: required = só Tier-0; advisory precisa `terminal`+`anchor`+`promote_by` no `gates-registry.json` (§_meta / ADR 0298).
- **ADR 0336 (aceito 2026-07-11, HOJE)** — gates de design PODEM virar required por **mordida provada** (contrafactual coletável + desembrulho do exit code). Abre o caminho legítimo p/ a M1 nascer advisory→required por bite-log.
- **Proposta `2026-07-11-maquina-nascimento-tela.md`** — gerador `criar-tela.mjs` + gate `ciclo-completo.mjs`. Carimba `related_prototype` e casos no nascimento. **Não** cobre mudança-pós-nascimento (que é a M1).
- Tasks MCP: não consultei o MCP (introspectivo); a proposta acima + ADR 0336 são o sinal vivo mais recente do tema.

---

## FASE 2 — PEGADINHAS APLICÁVEIS (filtradas)

| # | Pegadinha | Como se aplica aqui |
|---|---|---|
| **P-A** | **PODA em curso (ADR 0271/0314/0336)** — o projeto SUBTRAI gates, não soma. Gate-teatro (advisory que nunca fica vermelho por construção `if/else ::warning:: exit 0`) foi o alvo da 0336 | M1 NÃO pode nascer como mais um advisory-eterno decorativo. Tem de ter **selftest bite/release** (fixture que MORDE a mudança-não-declarada e SOLTA a declarada) + `promote_by` + `anchor` de custo (a dor textual do Wagner serve) |
| **P-B** | **Gate que precisa de gate / cobertura-fantasma** — `design-spec-gate` "cobre 147" mas só tem 1 spec; mede o que declara, não o que existe (§pt-conformance count-pump, revisão 2026-07-11) | Se a recomendação for "dar cobertura ao design-spec", cuidado: 146 specs mecânicos = 146 diffs de ruído por-PR sem autorização. NÃO resolve a dor; infla. |
| **P-C** | **§5 proibicoes — presence-gate REJEITADO** (`charter-sync-gate` 2026-07-01; L-24 "presença ≠ correção") | M1 NÃO pode ser "o charter apareceu no diff = ok". Tem de medir SINAL SEMÂNTICO: o CAMPO de autorização (`divergence_from_blueprint`/`related_prototype`) mudou de valor, ou marcador `design-aplicado`/SYNC_LOG presente. Não basta "charter tocado". |
| **P-D** | **§5 proibicoes — âncora por NOME/PASTA REJEITADA** (2026-06-30, review adversarial 33/33). Proveniência = o que o charter DECLARA, computada por `ancora.mjs::resolveAncora` | Se M1 resolve "seguiu o protótipo?", tem de usar `ancora.mjs`, NUNCA adivinhar por path/nome do .jsx. |
| **P-E** | **advisory precisa `terminal`+`anchor`+`promote_by`** (gates-registry `_meta`, ADR 0298) | Qualquer workflow novo tem de entrar no `gates-registry.json` OU falha o `memory-health` (umbrella, todo PR). |
| **P-F** | **DETECÇÃO+DOC apenas — máquina NUNCA auto-edita a tela** (regra dura do Wagner) | M1/M2 só flagam/documentam. Nada de auto-fix na .tsx. (Alinhado com publication-policy: gate não cria task/merge sozinho.) |
| **P-G** | **CRLF/BOM em writes Windows** (proibicoes) — se criar `.mjs`/`.yml` | Usar Edit tool ou write UTF-8-sem-BOM LF; validar. |

Observação (sem pegadinha documentada, mas atenção): o diff-base num PR não é sempre `HEAD~1..HEAD` — `design-return-gate` usa `HEAD~1 HEAD` (pós-merge). Um gate de PR precisa de `merge-base origin/main HEAD` p/ não perder autorização feita em commit anterior do mesmo branch.

---

## FASE 3 — PONTO DE PLUGUE

### Resposta às perguntas do Wagner

**#2 — A dor "não sabe que alterou" é resolvida por DAR COBERTURA ao design-spec-gate, ou falta a CAMADA DE AUTORIZAÇÃO?**

**Falta genuinamente a CAMADA DE AUTORIZAÇÃO.** Justificativa:
- `design-spec-gate` responde *"O QUE mudou estruturalmente"* (diff do .json) — e mesmo isso só em mudança estrutural, cega a reorder/copy/spacing. Dar cobertura (146 specs) só multiplica ruído sem responder a pergunta do Wagner.
- A dor do Wagner é *"não sabe que alterou [sem eu ter declarado]"* → a pergunta é **"a mudança foi AUTORIZADA?"** (declarei desvio OU segui o protótipo). Isso é um eixo ORTOGONAL ao design-spec. Nenhuma peça o cobre.
- **Ambos ajudam, mas a camada de autorização é o núcleo da dor.** Cobertura do design-spec é secundária (e arriscada em fase de poda).

**#3 — REUSE vs CRIAR:** recomendação **(C+B fino): criar M1 novo `detect-ui-drift.mjs` FINO, reusando vocabulário e padrão existentes — NÃO reimplementar fingerprint nem estender design-spec-gate.**
- Estender `design-spec-gate` (opção A) é RUIM: acopla autorização a cobertura de spec (146 telas), infla, e mistura dois concerns (estrutura×autorização). Viola SoC.
- M1 novo (B) é honesto: é a interseção vazia. Mas "novo" aqui = ~1 script fino + 1 workflow, reusando 3 peças existentes (abaixo). Não é gate-teatro se nascer com bite-selftest + anchor (ADR 0336 abriu esse caminho HOJE).

### Plug-points concretos (se recomendação C+B aprovada)

| Peça | Arquivo:linha | Ação (REUSO, não reescrita) |
|---|---|---|
| Leitor de frontmatter escalar | `scripts/governance/reconcile-triplet.mjs:68` (`fmScalar`) + `:306` (lê `divergence_from_blueprint`) | Extrair p/ `lib/` ou importar — NÃO reimplementar parser YAML |
| Resolvedor de protótipo legítimo | `prototipo-ui/ancora.mjs:174` (`--list --json`) + `resolveAncora` | Consumir p/ saber o `related_prototype` de cada tela (P-D) |
| Padrão diff-triggered "tsx tocado→irmão tocado?" | `.github/workflows/design-return-gate.yml:47-64` (git diff, filtra Pages/**/*.tsx, checa irmão) | **Espelhar a forma**, trocar eixo: irmão = campo de autorização do charter, não SYNC_LOG. Rodar em `pull_request` (não pós-merge) via `merge-base` |
| Registro do gate | `scripts/governance/gates-registry.json:workflows` | Adicionar entrada: `terminal: advisory` + `anchor` (dor Wagner + ADR 0336) + `promote_by` (≤14d) — senão memory-health falha (P-E) |
| Selftest bite/release | `scripts/governance/*.test.mjs` + `governance-script-tests.yml` | Fixture: (a) .tsx muda + `divergence_from_blueprint` novo → SOLTA; (b) .tsx muda + zero sinal → MORDE (ADR 0256 anti-fantasma) |
| ⚠️ Marcador PR `design-aplicado` | **NÃO EXISTE** | Sub-tarefa: definir sinal canônico do "segui/atualizei o protótipo" — reusar SYNC_LOG (design-return-gate) OU label PR OU mudança de `related_prototype`. Decidir com Wagner p/ não inventar 4º vocabulário |

**Lógica M1 (determinística, sem LLM):** p/ cada `Pages/**/*.tsx` no diff `merge-base..HEAD` → há no MESMO diff um sinal de autorização fresco? = (charter irmão com `divergence_from_blueprint` alterado/novo) OU (`related_prototype` alterado) OU (SYNC_LOG/`design-aplicado`). Nenhum → 🚩 `ui_change_unauthorized` (advisory-agora / bloqueante-honesto-depois via 0336).

### M2 — plug-points

| Peça | Arquivo | Ação |
|---|---|---|
| Engine M2 | `prototipo-ui/design-diff.mjs` (pronto) | Nenhuma — já existe |
| Lista de telas bespoke | `ancora.mjs --list` → 11 charters com `related_prototype` .jsx/.html real | Wire: skill/checklist que roda `design-diff --probe`→Chrome MCP→`--compare --check` nessas 11 |
| Disparo | skill `comparar-design-prod` (PROTOCOLO-COMPARACAO-RUNTIME D1-D8) | Já dirige. Gap = quando/quais. Não é engine, é operação. Juiz final = olho do Wagner (intencional) |

**M2 veredito:** `design-diff.mjs` **basta**. Falta só o wire per-PR pras ~11 bespoke — e mesmo isso é agente-driven (precisa browser, não é gate CI puro). Sem gap de engine.

---

## FASE 4 — CHECKLIST PRÉ-CÓDIGO

```markdown
## Pré-código checklist — M1 detect-ui-drift + M2 wire

### Antes de Edit/Write
- [ ] Ler ADR 0336 (mordida provada) INTEIRA — é a lei que legitima M1 advisory→required
- [ ] Decidir com Wagner o "sinal design-aplicado" (SYNC_LOG reuse vs label vs related_prototype-change) — NÃO inventar 4º vocabulário
- [ ] Feature flag? NÃO (gate advisory, sem runtime)
- [ ] Migration? NÃO
- [ ] ADR nova? SIM se M1 virar canon — mas ADR 0336 já cobre a POLÍTICA; M1 pode nascer como PR de implementação citando 0336 + a proposta nascimento-tela. Confirmar com Wagner se quer ADR própria ou emenda

### Pegadinhas a respeitar
- [ ] P-A: nascer com selftest bite/release (NÃO gate-teatro) + anchor + promote_by
- [ ] P-B: NÃO dar cobertura mecânica ao design-spec (146 specs de ruído) como "solução"
- [ ] P-C: medir MUDANÇA DE VALOR do campo de autorização, não "charter apareceu no diff"
- [ ] P-D: "seguiu protótipo?" via ancora.mjs, nunca por nome/path
- [ ] P-E: registrar em gates-registry.json (senão memory-health falha)
- [ ] P-F: só DETECTA+DOCUMENTA, nunca auto-edita a .tsx
- [ ] diff-base = merge-base origin/main HEAD (não HEAD~1) p/ PR multi-commit

### Pontos de plugue (em ordem)
- [ ] Script: scripts/governance/detect-ui-drift.mjs — reusa fmScalar (reconcile-triplet:68) + ancora --list-json + forma do design-return-gate:47
- [ ] Workflow: .github/workflows/detect-ui-drift.yml — on: pull_request, paths Pages/**/*.tsx
- [ ] Registry: gates-registry.json — entrada terminal=advisory + anchor(dor Wagner+0336) + promote_by
- [ ] Selftest: detect-ui-drift.test.mjs (bite/release) + governance-script-tests.yml
- [ ] M2 wire: skill/checklist rodando design-diff nas 11 telas bespoke (ancora --list)

### Smoke pós-deploy
- [ ] biz=1: PR de teste que muda 1 .tsx SEM tocar charter → M1 deve MORDER (advisory ::warning::)
- [ ] biz=1: mesmo PR + divergence_from_blueprint novo → M1 deve SOLTAR
- [ ] Rodar `node scripts/governance/detect-ui-drift.mjs --selftest` verde antes de abrir PR

### Estimativa (IA-pair, ADR 0106)
- M1 script + workflow + registry + selftest: ~2-3h (reuso alto — 3 peças prontas)
- M2 wire (checklist/skill das 11 telas): ~1h (engine pronto)
- Decisão "sinal design-aplicado" com Wagner: bloqueia início — 15min de conversa
- Total: ~3-4h após Wagner destravar o vocabulário do sinal
```

---

## Resumo executivo

- **M1 = PARCIAL (estende via peça fina nova).** Vocabulário de autorização + padrão diff-triggered já existem SEPARADOS; ninguém os uniu. A dor do Wagner é a **camada de autorização-on-change**, genuinamente ausente — NÃO se resolve dando cobertura ao design-spec-gate (isso infla ruído em fase de poda).
- **M2 = COBERTO.** `design-diff.mjs` é a M2. Falta só wire per-PR nas ~11 telas bespoke (agente-driven, olho do Wagner = juiz).
- **Maior risco/pegadinha:** nascer gate-teatro (P-A) — a ADR 0336 (HOJE) mira exatamente advisory que nunca fica vermelho. M1 SÓ é legítimo com selftest bite/release + anchor + promote_by, e com sinal SEMÂNTICO (P-C: valor do campo mudou), não presença no diff (padrão morto §5).
```
