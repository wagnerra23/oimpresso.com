---
id: resources-js-pages-nfe-brasil-tributacao-config-default-charter
page: /nfe-brasil/tributacao/config-default
component: resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-28"
parent_module: NfeBrasil
related_adrs: [29, 93, 94]
related_us: [US-NFE-010]
tier: A
charter_version: 3
---

# Page Charter — /nfe-brasil/tributacao/config-default

> **Status:** draft em 2026-05-16. Charter criado pelo Wave M boost (auditoria NfeBrasil 71→82, gap D3.c charters 30%). Non-Goals + Anti-hooks aguardam aprovação Wagner antes de promover pra `status:live`.
>
> **Contrato executável:** [`ConfigDefault.casos.md`](ConfigDefault.casos.md) — UC-NFCD-01..06, defendidos por [`ConfigDefaultContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php).
>
> **v2 (2026-07-27) — errata de fatos:** a v1 nomeava um model, uma tabela, um FormRequest, dois métodos de controller e um service que **não existem** no repo; e dava como pendente um AuditLog que já estava implementado. Corrigido abaixo (⚠️ marca cada ponto). Precedência: *teste verde > casos > charter > SPEC* — o charter é lei de intenção, não garantia de correção ([`proibicoes.md`](../../../../../memory/proibicoes.md) §Regra de precedência).

---

## Mission

Configurar os **defaults tributários por business** (regime fiscal, CSOSN/CST, ICMS, PIS, COFINS, IPI) que ficam no Nível 4 da cascata de defaults do motor tributário (business → NCM → UF → produto). Única tela onde Gestor/Admin define os fallbacks aplicados quando nenhuma regra NCM específica casa.

---

## Goals — Features (faz)

- AppShellV2 + Head `Defaults Tributários · NF-e Brasil`
- Form com regime (mei / simples / lucro_presumido / lucro_real) — Select
- Wizard "Aplicar pelo regime" (botão `Wand2`) — pré-popula CSOSN/CST + alíquotas conservadoras do `REGIME_DEFAULTS` constante
- Toggle CSOSN vs CST automático pelo regime (Simples/MEI=CSOSN, Lucro Pres/Real=CST)
- Inputs ICMS, PIS, COFINS, IPI (decimal 0.0000 — 4 casas pra precisão fiscal)
- Save via `useForm` Inertia POST `/nfe-brasil/tributacao/config-default`
- Validação backend `UpsertConfigDefaultRequest::authorize` (`nfe.tributacao.manage`) — ⚠️ v1 dizia `ConfigDefaultRequest` (classe inexistente)
- Toast feedback (sonner) — success em save, error em 4xx/5xx
- Link "Voltar" → `/nfe-brasil/tributacao` (Index)
- Multi-tenant Tier 0: `NfeBusinessConfig::where('business_id', $businessId)` + trait `HasBusinessScope` (ADR 0093) — ⚠️ v1 dizia `NfeTributacaoConfig` (model inexistente)
- Defaults conservadores SP (ICMS 18%) — outros estados ajustam via regras NCM Nível 2/3
- Hint visual no Select regime mostra defaults aplicados (educacional)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test.

- ❌ Editar regras NCM individuais (essa tela é só Nível 4 — regras NCM em `/nfe-brasil/tributacao` Index + RegraForm)
- ❌ Aplicar defaults cross-business (cada business tem `tributacao_default` próprio — ADR 0093)
- ❌ Wizard automático por UF (defaults são SP; outros estados via Nível 3 regra UF)
- ❌ Importar CSV NCM nessa tela (existe `ImportCsv.tsx` próprio)
- ❌ Calcular tributação de venda exemplo (motor calcula via `MotorTributarioService`, não preview UI)
- ❌ Histórico de mudanças de defaults (audit via `activity_log`, não UI aqui)
- ❌ Toggle ICMS-ST/MVA nessa tela (escopo cascata Nível 4 é defaults básicos; ST é regra NCM)

---

## UX Targets

- p95 first-paint < 1200ms (form simples + 1 query DB)
- Save Inertia POST < 1500ms p95
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal
- Tipografia canon ADR 0110: header 24px, label 13px, input 14px
- Cores semânticas: emerald (save success), red (validation error), sky (wizard hint)
- Decimal inputs aceitam vírgula PT-BR (0,1800) E ponto (0.1800)
- Wizard "Aplicar pelo regime" mostra valores antes de aplicar (preview opcional)
- File `preserveScroll: true` em save (sem reload full)
- Required fields marcados com `*` PT-BR

---

## UX Anti-patterns

- ❌ Auto-save em onChange (canon = save explícito via botão; mudança de regime é decisão grave)
- ❌ Wizard sobrescrever sem confirm (canon = mostrar valores antes, usuário aceita)
- ❌ Cor crua `bg-(green|red)-N` (canon = emerald/red semântico ADR 0110)
- ❌ Reload full após save (canon = `preserveScroll: true` Inertia)
- ❌ Validar apenas no backend (canon = validação client-side básica + backend canônico)
- ❌ Esconder hint de regime após primeiro uso (canon = sempre visível — operação rara, contexto importa)
- ❌ Modal pra confirmar save (canon = toast pós-save + flash message; modal só pra destrutivo)
  - _(v3 2026-07-28: esta regra é a que vale. O §Non-Goals tinha um item oposto — "❌ Save sem confirmação de mudança crítica" — que **contradizia** esta linha no mesmo charter. Removido por decisão [W]; ver §Histórico.)_
- ❌ Aceitar regime ∉ {mei, simples, lucro_presumido, lucro_real} (canon = enum estrito)

---

## Automation Hooks

- `GET /nfe-brasil/tributacao/config-default` → `ConfigDefaultController::show` (Inertia render com config atual) — ⚠️ v1 dizia `::edit`; a rota nomeada é `nfe-brasil.tributacao.config.show`
- `POST /nfe-brasil/tributacao/config-default` → `ConfigDefaultController::upsert` (FormRequest valida regime+permissão; o **próprio controller** persiste via `NfeBusinessConfig::updateOrCreate` em `nfe_business_configs.tributacao_default` JSON) — ⚠️ v1 dizia `::update` + tabela `nfe_tributacao_config` (inexistente) + "service persiste" (não há service neste caminho)
- Multi-tenant: `NfeBusinessConfig` usa `HasBusinessScope` (ADR 0093) — 1 row por business (`business_id` UNIQUE)
- ⚠️ **Revogado na v2:** *"Service `TributacaoConfigService::aplicarDefaults(regime)` espelha REGIME_DEFAULTS server-side"* — **essa classe não existe**. O `REGIME_DEFAULTS` vive **só no cliente** (`ConfigDefault.tsx`), e o wizard "Aplicar pelo regime" apenas pré-preenche o form; o servidor grava o que for submetido. Quem tem defaults server-side é o `TributacaoTemplateService` (outro caminho, disparado pelo Index). Afirmar o espelhamento é anti-padrão inventado que parece canon (`proibicoes.md` §5 2026-07-16)
- Motor tributário downstream: `MotorTributarioService::calcular(item)` usa `tributacao_default` como fallback Nível 4 quando NCM regra não casa — lê a chave **`cfop`** (não `cfop_default`); ver `UC-NFCD-02`
- Audit: ✅ **implementado** — `activity('nfe.tributacao')->log('config_default.upserted')` no `upsert` (com `business_id` + `regime` + `ncm_default`). ⚠️ v1 dava como pendente em US-NFE-062 P1 e usava o log-name `nfe.tributacao.config`, que não é o emitido
- Validation: ICMS/PIS/COFINS/IPI ∈ [0, 1] (alíquotas decimais — 0.18 = 18%)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir ou salvar (ação interna config)
- ❌ Não dispara emails em mudança de regime (decisão administrativa, sem notificação automática)
- ❌ Não escreve no banco no render inicial (só no POST)
- ❌ Não acessa config de outro `business_id` (multi-tenant Tier 0)
- ❌ Não chama SEFAZ no save (config é interna; SEFAZ só vê na próxima emissão)
- ❌ Não dispara re-cálculo retroativo de NFes já autorizadas (config muda só futuro — append-only fiscal)
- ❌ Não modifica `nfe_emissoes` existentes (config é só template; emissões guardam snapshot da tributação aplicada)
- ❌ Não loga PII (config tributária é dado público fiscal — sem CPF/CNPJ aqui de qualquer forma)
- ❌ Não dispara Job background no save (operação síncrona simples — UPDATE config)

---

## Métricas vivas (Pest GUARD)

⚠️ **v2 — errata:** a v1 listava 12 `it(...)` num arquivo `Modules/NfeBrasil/Tests/Charters/ConfigDefaultCharterTest.php` que **nunca existiu** (nem o diretório `Tests/Charters/`). Charter que promete teste inexistente deve ser revogado, não mantido como decoração (`how-trabalhar.md` §"Quando [W] pede o contrato da tela": *"§Pest GUARD que promete teste inexistente = revogar (grep antes de confiar)"*).

**Escritos** — [`Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php`](../../../../../Modules/NfeBrasil/Tests/Feature/ConfigDefaultContratoTest.php), rastreados por UC em [`ConfigDefault.casos.md`](ConfigDefault.casos.md):

| Promessa da v1 | Onde vive agora |
|---|---|
| `isolates config by business_id` | `UC-NFCD-01` |
| `rejects regime outside enum {…}` | `UC-NFCD-03` |
| `rejects aliquota outside [0, 1] range` | `UC-NFCD-04` |
| `does not modify existing nfe_emissoes (append-only fiscal)` | `UC-NFCD-05` |
| — (não estava na v1; achado ao ler o motor) | `UC-NFCD-02` (alias `cfop`) · `UC-NFCD-06` (CSOSN⊕CST) |

**Não escritos, e honestamente em aberto** (sem teste ⇒ sem afirmação): p95 de render, ausência de e-mail/SEFAZ no save, "não escreve no render", 1280px sem scroll, `preserveScroll`, preview do wizard, e o log de `activity` no save (implementado, mas nenhum teste prova). Viram UC quando ganharem teste que os cite (G-2) — enquanto isso não contam como cobertura.

---

## Refs

- [US-NFE-010](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — Tributação + cascata defaults
- [ADR 0029](../../../../../memory/decisions/0029-padrao-inertia-react-ultimatepos.md) — Inertia + UltimatePOS
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- ADR satélite `Modules/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto` — cascata 4 níveis (business → NCM → UF → produto)
- [Tributacao Index.charter.md](../Tributacao/Index.charter.md) — charter irmã (regras NCM Nível 2/3)
- [BRIEFING.md](../../../../../memory/requisitos/NfeBrasil/BRIEFING.md) — estado consolidado módulo

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | [CC] Wave M boost | Draft criado pelo Wave M auditoria (NfeBrasil 71→82, gap D3.c charters 30%). Non-Goals + Anti-hooks aguardam aprovação Wagner. |
| 2026-07-27 | [CC] | **v2 — errata de fatos + trio fechado.** Corrigidos 6 pontos que nomeavam artefatos inexistentes (`NfeTributacaoConfig`, `nfe_tributacao_config`, `ConfigDefaultRequest`, `::edit`/`::update`, `TributacaoConfigService`) ou davam por pendente o AuditLog já implementado. §Pest GUARD revogado (arquivo prometido nunca existiu) e substituído pelo mapa promessa→UC. Nasce `ConfigDefault.casos.md` (UC-NFCD-01..06) + `ConfigDefaultContratoTest`. Non-Goal do "confirm no save" marcado como divergente e devolvido a [W] — segue **não aprovado**, então não virou `[must]`. |
| 2026-07-28 | [W] + [CC] | **v3 — Non-Goal do `confirm` REMOVIDO.** O item "❌ Save sem confirmação de mudança crítica" **contradizia** o §UX Anti-patterns do mesmo charter ("❌ Modal pra confirmar save… modal só pra destrutivo"): os dois foram escritos pelo mesmo agente na mesma passada de 2026-05-16 e **nenhum** foi aprovado por [W]. Não era lei, era rascunho. O risco que ele imaginava já está coberto por `UC-NFCD-05` (salvar **não** reescreve NFe emitida — o efeito é só sobre emissões futuras, corrigível voltando na tela). Fica valendo a regra irmã: sem modal; feedback é toast + flash. Se um dia [W] quiser rede de segurança, o caminho coerente é **aviso inline** ao trocar o regime (vira UC com teste), não modal. |
