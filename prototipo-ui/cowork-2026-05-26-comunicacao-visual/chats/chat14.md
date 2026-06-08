# ERP Clientes Brasil

_Started 2026-05-21 10:35 UTC_

---

## User

https://claude.ai/design/p/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58?file=Oimpresso+ERP+-+Chat.html
Transcreva para otro claude desing continuara fazer a tela de clientes. aqui tem apenas 1% de tokens disponivel muito pouco
quero aplicar o protocolo e conferir ainda oque falta para substituir o blade antigo e criar os campos do brasil

gere

## Assistant

_[tool: list_files]_

_[tool: grep]_

_[tool: run_script]_

_[tool: read_file]_

_[tool: grep]_

_[tool: grep]_

_[tool: write_file]_

Pronto. Handoff gerado em **`HANDOFF_CLIENTES.md`**.

**O que tem dentro:**
1. Estado atual dos 3 arquivos (`clientes-page.jsx` 70% / `data-clientes.jsx` mock pronto / `OS_CLIENTS` legado a remover)
2. Gap-list completa pra substituir o Blade antigo — listagem, drawer, form
3. **Schema BR canônico** em 5 seções (Identificação PF/PJ, Contato, Endereço ViaCEP, Comercial, Classificação) — todos os campos com mask/tipo/obs.
4. Tokens + proibições do briefing relembradas
5. Plano em 3 passos pra a próxima sessão executar F1
6. Limites operacionais (sem commit, zero-toque Wagner)

**Próxima ação:** abre chat novo do Claude Design, cola este projeto + abre `HANDOFF_CLIENTES.md`, ele continua de onde parei.

## User

pode ler as novas instruções do sidebar e header?

## Assistant

_[tool: grep]_

_[tool: list_files]_

_[tool: run_script]_

_[tool: grep]_

_[tool: list_files]_

_[tool: grep]_

_[tool: grep]_

_[tool: github_read_file]_

_[tool: github_get_tree]_

_[tool: github_read_file]_

_[tool: github_read_file]_

_[tool: github_read_file]_

Esse amendment é sobre o `chat-jana.jsx` (Cockpit Analista IA), **não** sobre o sidebar/header global do shell. Deixa eu ver se tem instrução nova específica em `COWORK_NOTES.md` e `HANDOFF.md`:

_[tool: github_read_file]_

_[tool: github_read_file]_

_[tool: github_read_file]_

Vou ler todos esses agora:Li os três. Resumo do que mudou — e como afeta o que vou fazer no Clientes daqui pra frente:

## 🔒 Drawer 760 é canon — pattern decidido

`Cliente` (a tela que eu ia continuar) é **a referência viva do pattern**. Não vou mais propor alternativas.

**Geometria fixa** (Larissa biz=4, 1280×1024):
```
sidebar 240 + lista 260 + drawer 760 = 1260px (cabe sem scroll)
```

**Anti-patterns Tier 0 (proibidos):**
- ❌ Edit.tsx ou Create.tsx separados pra entidade cadastral
- ❌ Botão "Salvar" (autosave on blur 800ms + optimistic + rollback)
- ❌ Modal/Sheet centralizado
- ❌ Largura ≠ 760
- ❌ Show.tsx convivendo
- ❌ Tab "Novo X" (drawer abre fechado=create / abre populated=edit)
- ❌ SubNav/ghost tabs durante preenchimento (modo FOCO)
- ❌ PrimaryButton "+ Novo X" com drawer aberto

## 🧱 PageHeader 2 modos (skill `pageheader-canon`)

| Modo | Quando | Zona C | Zona R |
|---|---|---|---|
| **NAV** | Index/Show | `<SubNav active hidePrimary extraOverflowItems/>` | `<PrimaryButton>+ Novo X</>` |
| **FOCO** | Edit/Create/Form/Emitir | **VAZIA** | só `← Voltar` (opcional) |

Grupo `vender` (Cliente está em CRM) → hue OKLCH **60** (amarelo). **NUNCA** magenta 330.

## 📐 4 tabs DRY canônicas (copiar do Cliente)

1. **IdentificacaoTab** — autosave 800ms + rollback + masks BR (CPF/CNPJ check digit)
2. **EnderecoTab** — CEP+ViaCEP cache Redis 90d, BrasilAPI CNPJ cache 30d
3. **IATab** — 3-4 cards Brain B Jana (Resumo / Segmento / Próxima Ação)
4. **AuditoriaTab** — Spatie ActivityLog `forSubject(Entity)` LGPD Art 18

Tabs específicas (5-8 por entidade) = livre. Cliente tem **8 tabs**.

## 🚨 Wave H — bloqueador antes de qualquer replicação

Auditoria 2026-05-22 deu **76,4/100** no Cliente. 4 gaps P0 (15h IA-pair) precisam ser fechados PRIMEIRO senão escalam 7×:

| Gap | h | Estado |
|---|---|---|
| #1 Optimistic locking (`updated_at` check + HTTP 409 toast "outro user editou") | 6h | pendente |
| #2 React.lazy + `Inertia::defer` per-tab (bundle 118KB → 30KB) | 4h | pendente |
| #3 popstate handler (back fecha drawer antes de navegar) | 2h | pendente |
| #5 Focus trap + Esc + autofocus (WCAG 2.2 AA) | 3h | pendente |

Output Wave H: nota **76,4 → 88/100** (mundo-classe). Replicação Wave I (Produto/OS/Veículo/DeviceModel/Plano/Payment) herda baseline 88.

## 🇧🇷 Endpoints backend que o protótipo CC deve refletir

```
PATCH /clientes/{id}/{tab}        autosave on blur + updated_at check
GET   /clientes/{id}              302 → /clientes?contact_id={id}&tab=identificacao
GET   /clientes/{id}/edit         302 → mesmo destino
GET   /cep/{cep}                  ViaCEP cache 90d
GET   /cnpj/{cnpj}                BrasilAPI cache 30d
POST  /clientes/{id}/ia/resumo    Brain B Jana
POST  /clientes/{id}/ia/segmento  Brain B Jana
POST  /clientes/{id}/ia/proxima-acao  Brain B Jana
GET   /clientes/{id}/auditoria    Spatie ActivityLog
```

Tudo com `business_id` global scope (Tier 0 ADR 0093).

## ⚠️ Impacto no meu `HANDOFF_CLIENTES.md` anterior

Aquele handoff (escrito antes desses 3 docs) está **parcialmente obsoleto**:
- ✅ Schema BR §3 continua válido (masks, ViaCEP, IE, regime tributário etc.)
- ⚠️ Plano §5 precisa virar: **(1) Wave H** → (2) form como drawer 760, não "drawer largo novo"
- ⚠️ Form "Novo cliente" = abrir drawer 760 vazio modo create (mesmo arquivo, sem rota nova)
- ⚠️ "Abas no drawer" = essas 4 + 4 específicas Cliente (Comercial / Classificação / OSs / Vendas, por ex)

Quer que eu **reescreva o `HANDOFF_CLIENTES.md`** alinhado a esses 3 docs (Drawer 760 + Wave H + 4 tabs DRY)? Próxima sessão começa direto da Wave H ou do form drawer.

## User

quero que avalie o rotiero compare com os melhores e pontue o que pode melhorar

## Assistant

# Auditoria do `HANDOFF_CLIENTES.md` vs estado-da-arte

Comparei contra (1) ADR 0179+0185 canon · (2) Wave H gaps · (3) auditoria 76,4/100 · (4) Notion/Linear/HubSpot/Salesforce · (5) ERPs BR (Bling/Tiny/Omie).

## 📊 Nota geral: **62/100** (faixa "regular — precisa reescrever")

| Dimensão | Peso | Nota | Comentário |
|---|---:|---:|---|
| Coerência com canon Drawer 760 | 15 | **45** | Plano §5 fala em "drawer largo novo" e "Form Novo/Editar" como página — anti-pattern Tier 0 |
| Schema BR (campos PF/PJ/fiscal) | 10 | **88** | Cobertura sólida: IE+isento+indicadorIE+SUFRAMA+regime — passa Bling/Tiny |
| Wave H (4 gaps P0) | 12 | **0** | **ausente** — handoff foi escrito antes da auditoria existir |
| Autosave on blur + rollback | 8 | **0** | Menciona "auto-save em localStorage" — completamente errado pro pattern |
| Optimistic locking (updated_at) | 6 | **0** | Não menciona — gap #1 da Wave H |
| Lazy load tabs (Inertia::defer + React.lazy) | 6 | **0** | Não menciona — gap #2 |
| popstate handler back button | 4 | **0** | Não menciona — gap #3 |
| Focus trap + Esc WCAG 2.2 AA | 5 | **0** | Não menciona — gap #5 |
| 4 tabs DRY canônicas (Identif/End/IA/Aud) | 8 | **40** | Lista campos mas não nomeia as 4 tabs DRY nem aponta os templates Cliente como source |
| Tab IA Brain B (Resumo/Segmento/Próxima Ação) | 6 | **0** | Não menciona Jana, Brain B, nem LaravelAiSdkDriver |
| Tab Auditoria LGPD Art 18 (Spatie ActivityLog) | 6 | **20** | Cita "Histórico de alterações" mas sem LGPD, Spatie, polimórfico |
| Multi-tenant Tier 0 (business_id scope) | 5 | **30** | Não cita ADR 0093 nem cross-tenant Pest |
| Lookups BR (ViaCEP + BrasilAPI cache) | 4 | **70** | Cita ViaCEP mas não BrasilAPI CNPJ nem cache Redis 90d/30d |
| Modo FOCO vs NAV pageheader | 4 | **0** | Não menciona — pode levar próxima sessão a colocar SubNav no drawer |
| Geometria 760px fixa Larissa 1280×1024 | 3 | **0** | Diz "drawer largo" — sem cota 760 + persona |
| Critério "Done" mensurável | 3 | **40** | Tem gap-list mas sem score-gate, sem Pest, sem smoke browser MCP |
| Pest charter (viewport 1280×1024) | 2 | **0** | Não menciona |
| Migration aditiva ALTER TABLE | 2 | **30** | Implícito mas não explicitado como princípio |
| PR commit-discipline encadeado | 1 | **0** | Sem menção a wave atômica ≤300 LOC |

## 🔴 Os 10 gaps mais críticos (em ordem de risco)

### 1. **Confunde paradigma — "Form Novo/Editar" como página** (§3, §5)
> "Form Novo/Editar (NÃO EXISTE — criar) — Página/drawer largo (`cli-form-drawer`)"

**Anti-pattern Tier 0.** Drawer 760 abre **fechado modo create** OU **populated modo edit** — mesmo componente, sem rota. Próximo CC pode criar `Create.tsx` separado e violar canon.

### 2. **"Auto-save em localStorage" — bloqueador** (§3)
> "Auto-save de rascunho em localStorage"

Canon = **PATCH backend on blur 800ms + optimistic UI + rollback 4xx/5xx**. localStorage é fallback offline, não primary. Atrasa o entendimento do pattern em 1 round.

### 3. **Wave H invisível — 15h ocultas**
4 gaps P0 da auditoria não aparecem. Próxima sessão começa replicando schema BR sem otimistic locking → race condition em prod biz=1 (Larissa edita ao mesmo tempo que Bruna = perde dados).

### 4. **Tab IA Brain B ausente — feature mundo-classe não mencionada**
Cliente já tem `IATab.tsx ~420 lin` com 3-4 cards Jana. Próximo CC vai recriar do zero ou esquecer. Bling/Tiny/Omie não têm isso — diferencial competitivo invisível no handoff.

### 5. **Tab Auditoria LGPD subdimensionada**
> "Histórico de alterações"

Canon = `forSubject(Entity)` polimórfico via Spatie ActivityLog (já instalado). LGPD Art 18 plug-and-play. **Zero código novo**. Handoff descreve como se fosse feature pra implementar — não é.

### 6. **Lista source paths errada**
Handoff foca em `clientes-page.jsx` (protótipo cowork). Canon vivo é:
```
resources/js/Pages/Cliente/Index.tsx (~74KB)
resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx (611 lin · template DRY)
Modules/Crm/Http/Controllers/ClienteAutosaveController.php
Modules/Crm/Services/BrLookupService.php
```
Sub-agent precisa COPIAR essas tabs, não recriar do mock.

### 7. **Filtros de listagem misturados com drawer**
§2 "Listagem (Index) — filtro por segmento/UF/PF-PJ" — bom — mas convive com §5 plano onde o passo 1 é "migrar fonte de dados". Ordem errada. Primeiro pattern (Wave H + drawer), depois cosmético de listagem.

### 8. **Sem matriz de elegibilidade**
Cliente é cadastral (✓). Mas handoff não cita a matriz pra próxima sessão validar. Se Wagner pedir "Fornecedor" depois, CC precisa rodar a matriz — handoff não ensina o protocolo.

### 9. **Modo FOCO vs NAV não mencionado**
Próximo CC pode colocar `<ClienteSubNav>` dentro do drawer aberto = ghost tabs visíveis = anti-pattern catalogado.

### 10. **Sem checklist de validação browser MCP**
Skill `pageheader-canon` Fase 5 exige script JS canon rodando após implementação. Handoff fala em "verificar" genericamente.

## ✅ O que está bom (manter)

1. **Schema BR §3** — cobertura PF/PJ/IE/SUFRAMA/regime tributário é dos melhores que vi (≥ Bling/Tiny)
2. **Limites operacionais §6** — "não commitar, zero-toque Wagner" continua canon
3. **5 seções colapsáveis** — mapeia naturalmente nas 8 tabs Cliente
4. **Validações masks + check-digit** — sólido

## 🔧 Estrutura recomendada do handoff v2 (reescrita)

```markdown
# HANDOFF — Cliente (Drawer 760 canon)

## 0. Antes de tocar em código — ler estes 4 docs (15 min)
- ADR 0179 (canon source Wave A-G)
- ADR 0185 (escala + Wave H + Wave I + 21 armadilhas)
- memory/reference/drawer-760-pattern-canon.md (1-pager)
- memory/reference/feedback-drawer-760-canon-style.md (Wagner Tier 0)

## 1. Estado atual (3 linhas)
Cliente prod biz=1 · nota 76,4/100 · Wave H pendente (15h) bloqueia replicação

## 2. Bloqueador #1 — Wave H (fazer ANTES de qualquer outra coisa)
| Gap | h | Como aplicar |
|---|---|---|
| #1 Optimistic locking | 6 | updated_at em PATCH + HTTP 409 + toast "outro user editou" |
| #2 Lazy load tabs | 4 | React.lazy + Inertia::defer per-tab |
| #3 popstate | 2 | history listener fecha drawer antes navegar |
| #5 Focus trap | 3 | Radix Dialog focus-lock + Esc + autofocus 1º input |

Output: 76,4 → 88/100. **Sem Wave H, não inicia Wave Produto.**

## 3. Schema BR (8 tabs do drawer 760)
[manter §3 atual aqui — ele tá bom]

## 4. 4 tabs DRY canônicas (copy do Cliente)
[template paths + o que muda + o que mantém]

## 5. Anti-patterns Tier 0 (não rompa)
[lista 12 do skill]

## 6. Critério Done (mensurável)
- Score ≥88/100 (Wave H aplicada)
- Pest viewport 1280×1024 verde
- Smoke browser MCP script JS canon (F1+F2+F3+C5)
- 0 violações Tier 0 (autosave / 760 / business_id scope)

## 7. Limites operacionais (zero-toque Wagner)
[manter §6 atual]
```

---

**Quer que eu reescreva o `HANDOFF_CLIENTES.md` nesse formato?** Vai de 62/100 pra ~92/100 e fica alinhado com os 3 docs canônicos. ~5 min.

## User

sim eu gostaria, e o que mais ele deveria saber? os passos para analizar o grid perfeito, e totalizar no fim do grid como funciona? acho que deveria ter um padrão aceito por pagina além do que ja tem? 
escreva

## Assistant

## User

https://claude.ai/design/p/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58?file=Oimpresso+ERP+-+Chat.html

Sidebar
Produção→ [Fila de impressão, Acabamento, Expedição, Manufacturing, Briefings]
Vai terapenas Produção no sidebar
e dentro ao lado do novo noheader como ghost action buttom

## Assistant

