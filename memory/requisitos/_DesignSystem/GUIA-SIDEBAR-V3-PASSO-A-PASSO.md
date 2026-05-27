# GUIA — Sidebar v3 oimpresso: do estado atual ao canon completo

> **Pra quem está se perguntando "como faço pra arrumar a bagunça do sidebar?"** — este guia é seu roteiro executável. Passo a passo, com comandos prontos pra copiar, ordem de execução, e validação visual a cada onda.

**Última atualização:** 2026-05-21 · Wagner solicitou guia executável pós-sessão Financeiro

---

## 🎯 A meta final (o que você está construindo)

```
TOPO (sempre visível — 3 destinos)
  ✦ IA              G I   →  Copiloto · Brief · Memórias · KB · Regras
  ☎ Atendimento     G A   →  WhatsApp · Tickets · OS Pública
  ◐ Equipe          G E   →  Pessoas · Tarefas · Convites

VENDER (3 destinos)
  $ Vendas          G V   →  Unificado · PDV · Orçamentos · Pipeline · Woo · Relatório
  ♥ Clientes        G C   →  Unificado · Contatos · Tags · Histórico
  ▣ Catálogo        G K   →  Unificado · Produtos · Serviços · Vestuário · Preços

OPERAR (3 destinos)
  ⚙ Ordens de Serviço G O →  Unificado · Reparar · Oficina Auto · Comunic.Visual
  ⚒ Produção        G P   →  Unificado · OP · Kanban · BOM · Apontamento
  ▥ Estoque         G S   →  Unificado · Compras · Transferências · Ajustes · Inventário · Ativos

FINANÇAS (2 destinos) ✅ JÁ FEITO
  ₿ Financeiro      G F   →  Unificado · Pagar · Receber · Caixa · Conciliação · Boletos · DRE · Plano de Contas
  ⎙ Fiscal          G X   →  Unificado · NF-e · NFSe · NFC-e · Certificado · SPED

PESSOAS (1 destino)
  ☻ RH              G H   →  Unificado · Colaboradores · Ponto · Folha · Férias

SISTEMA (2 destinos)
  ⚖ Governança      G G   →  Unificado · Auditoria · ADS · Module Grades · LGPD
  ◇ Plataforma      G T   →  Unificado · Módulos · CMS · Conector · Officeimpresso · Backup
```

**Métricas finais:** 3 fixos + 11 destinos = 14 labels visíveis · 5 grupos · Hick's Law 10/10 · score 91/100.

---

## 📊 Onde você está hoje (estado atual em main)

| Status | Quantos | Módulos |
|---|---|---|
| ✅ **Canon 100%** (ghosts + primary + shortcut declarados) | 11 | Financeiro · Crm · ProductCatalogue · Repair · OficinaAuto · Manufacturing · Compras · Essentials · NfeBrasil · Governance · Jana |
| 🟡 **Canon parcial** (tem ghosts mas falta primary OU shortcut) | 7 | AssetManagement · Ponto · NFSe · ADS · Cms · Connector · Officeimpresso |
| ⚠️ **Sem ghosts** (declared como módulo mas sem sub-views canon) | 8 | Auditoria · KB · Brief · SRS · TeamMcp · ProjectMgmt · Whatsapp · ConsultaOs |
| ❌ **Sem DataController** (core UPOS legacy) | 1 | Sells |

**Estado do FRONTEND:** Só Financeiro tem 11 telas adotando `FinanceiroSubNav` + `FinanceiroPrimaryButton`. Outras 27+ telas Inertia esperam o pattern.

---

## 🗺️ Plano em 5 ondas (do mais fácil ao mais complexo)

### 🟢 Onda 1 — REFINAR canon parcial (7 módulos, ~3h)

**Por que começar aqui:** já têm ghosts declarados — só falta adicionar `primary` + `shortcut` completos. Mais rápido + zero risco.

**Módulos:** AssetManagement, Ponto, NFSe, ADS, Cms, Connector, Officeimpresso

**Comando próxima sessão:**
```
/pageheader-canon refine — completar primary+shortcut nos 7 módulos parciais
```

**Ou (manual):** Sub-agent abre cada `Modules/<X>/Http/Controllers/DataController.php`, lê o que falta, adiciona `'primary' => [...]` + `'shortcut' => 'G ?'` na entry principal usando Financeiro como template.

**Resultado esperado:** sidebar v3 mostra primary contextual em cada destino + atalhos `G X X` funcionam.

---

### 🟢 Onda 2 — DECLARAR canon nos 8 sem ghosts (~4h)

**Módulos:** Auditoria, KB, Brief, SRS, TeamMcp, ProjectMgmt, Whatsapp, ConsultaOs

**Estes são especiais** — muitos viram **ghost de OUTRO destino** (não entry principal própria):
| Módulo | Destino canon | Tratamento |
|---|---|---|
| KB | IA ghost | Adiciona ghost na entry Jana (`Modules/Jana/.../DataController`) — não cria entry própria |
| Brief | IA ghost | Adiciona ghost na entry Jana |
| SRS | IA ghost | Adiciona ghost na entry Jana |
| ProjectMgmt | Equipe ghost | Adiciona ghost na entry TeamMcp |
| Auditoria | Governança ghost | Adiciona ghost na entry Governance |
| ConsultaOs | Atendimento ghost | Adiciona ghost na entry Whatsapp |
| Whatsapp | Atendimento entry própria | Refina canon (ghosts: Tickets / Inbox / OS Pública) |
| TeamMcp | Equipe entry própria | Refina canon (ghosts: Pessoas / Convites + ghost ProjectMgmt como "Tarefas") |

**Comando próxima sessão:**
```
/pageheader-canon refine — adicionar ghosts canon nos 8 módulos sem
```

---

### 🟡 Onda 3 — FRONTEND propagação (~6-8h, paralelizável)

**O backend está pronto** (após Ondas 1+2) mas o **frontend** das telas Inertia ainda usa botões legacy custom. Cada módulo precisa:

1. Criar `Pages/<Modulo>/_shared/<Modulo>SubNav.tsx` (template = `Pages/Financeiro/_shared/FinanceiroSubNav.tsx`)
2. Criar `Pages/<Modulo>/_shared/<Modulo>PrimaryButton.tsx` (template = `Pages/Financeiro/_shared/FinanceiroPrimaryButton.tsx`)
3. Cada `Pages/<Mod>/<X>/Index.tsx` adota `<{Modulo}SubNav active="<key>" hidePrimary/>` + `<{Modulo}PrimaryButton>`

**Estratégia recomendada — 4 sub-agents paralelos:**

| Sub-agent | Wave | Módulos | Cor hue |
|---|---|---|---|
| 1 | **VENDER** | Sells · Crm · ProductCatalogue | 60 amarelo |
| 2 | **OPERAR** | Repair · OficinaAuto · Manufacturing · Compras · AssetManagement | 350 magenta |
| 3 | **FISCAL** | NfeBrasil · NFSe (+ tabela "Fiscal" consolidada) | 145 verde |
| 4 | **SISTEMA** | Governance · ADS · Auditoria · Cms · Connector · Officeimpresso · Essentials · Ponto · IA(Jana/KB/Brief/SRS) · Atendimento(Whatsapp/ConsultaOs) · Equipe(TeamMcp/ProjectMgmt) | 200/295/220/30/270 |

**Comando próxima sessão:**
```
Spawn 4 sub-agents em paralelo via skill pageheader-canon — 1 wave por agent
```

Cada sub-agent segue protocolo 5 fases (Descoberta → Decisão → Naming → Implementação → Validação visual OBRIGATÓRIA browser MCP).

---

### 🟠 Onda 4 — CASO ESPECIAL Sells (~2h)

**Problema:** Sells (core UPOS) NÃO tem `DataController` no padrão Modules/. Suas entries vivem em `app/Http/Middleware/AdminSidebarMenu.php` (Blade legacy).

**3 opções:**

| Opção | O quê | Esforço |
|---|---|---|
| **A** ⭐ recomendada | Criar `Modules/Sells/Http/Controllers/DataController.php` declarando ghosts canon. UPOS continua adicionando entries via AdminSidebarMenu, mas o novo DataController declara entry consolidada com `group='vender'` + ghosts pra todas sub-views (POS, Sells list, Drafts, Returns) | 1.5h |
| B | Adicionar lógica no LegacyMenuAdapter pra detectar entries `Sells/*` do AdminSidebarMenu legacy + consolidar | 3h (mais complexo) |
| C | Esperar Sells migrar pra Modules/ (Roadmap futuro) — manter UPOS legacy nessa transição | 0h agora, custo futuro |

**Comando próxima sessão:**
```
/pageheader-canon Sells --strategy=criar-data-controller-novo
```

---

### 🟣 Onda 5 — VALIDAÇÃO + CLEANUP (~2-3h)

Pós-Ondas 1-4, executar:

1. **Browser MCP validação visual obrigatória** (Fase 5 skill `pageheader-canon`) — script JS canon nas 30+ telas Inertia confirma:
   - C1 Tabs renderizam · C2 Active visible · C3 Labels curtos · C4 Hue correto · C5 Sem 500 · C6 Overflow funcional
   - Gate ✓/⚠️ por tela
2. **Cleanup `LEGACY_GROUP_MAP`** (Sidebar.tsx) — após TODOS módulos migrados, remover as 11 keys legacy
3. **CI gate `pageheader:health`** — workflow valida em PR futuro que cada `Pages/<Mod>/Index.tsx` usa `<{Modulo}SubNav>`
4. **Update matriz `pageheader-matriz-diferencas.md`** — estado final por módulo

---

## 📋 Como executar (sequência operacional)

### Pré-requisitos (já feitos)

- ✅ Skill `pageheader-canon` em `.claude/skills/pageheader-canon/SKILL.md`
- ✅ Matriz em `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md`
- ✅ Template Financeiro em `Pages/Financeiro/_shared/{FinanceiroSubNav, FinanceiroPrimaryButton}.tsx`
- ✅ ADRs 0180/0182/0183/0184 aceitas

### Próxima sessão — comando 1 (mais simples)

```
Pegar handoff: leia memory/handoffs/2026-05-21-2230-pageheader-propagacao-pendente.md
e o guia memory/requisitos/_DesignSystem/GUIA-SIDEBAR-V3-PASSO-A-PASSO.md.
Comece pela Onda 1 (refinar 7 módulos canon parcial).
```

### Próxima sessão — comando 2 (mais ousado, paralelo)

```
Spawn 4 sub-agents em paralelo aplicando skill pageheader-canon:
- Agent 1: Onda 1 refine (AssetManagement+Ponto+NFSe+ADS+Cms+Connector+Officeimpresso)
- Agent 2: Onda 2 ghosts (Whatsapp+ConsultaOs+TeamMcp+ProjectMgmt + Jana(adiciona KB/Brief/SRS ghosts))
- Agent 3: Onda 3 Frontend Wave VENDER (Sells+Crm+ProductCatalogue telas)
- Agent 4: Onda 3 Frontend Wave OPERAR (Repair+OficinaAuto+Manufacturing+Compras telas)
Cada agent: protocolo 5 fases + validação visual obrigatória.
```

---

## ⚠️ Pegadinhas conhecidas (não cair de novo)

| # | Pegadinha | Mitigação |
|---|---|---|
| 1 | **Primary magenta hue 330** (canon UPOS legacy `os-btn primary`) | Sempre usar `<{Modulo}PrimaryButton>` com hue do grupo |
| 2 | **Ghost ativo invisível no overflow** | `PageHeaderTabs` já tem auto-promoção (PR #1370) |
| 3 | **Botão duplicado com ghost** (ex "Conciliar" inline + ghost `conciliacao`) | **REMOVER** botão inline — ghost cobre navegação |
| 4 | **Labels longos** ("Contas a Receber", "Plano de Contas") | Encurtar (`Receber`, `Plano`). Tooltip pode ter completa |
| 5 | **DataController declara `'group' => 'office'`** (key v2 legacy) | LEGACY_GROUP_MAP cobre — mas refatorar pra `'vender'` direto é melhor |
| 6 | **Multi-tenant Tier 0** | SubNav retorna `null` se módulo desinstalado no tenant |
| 7 | **Vite cache pós-merge** | Aguardar ~90s pro deploy Hostinger refletir |
| 8 | **CI checks falham por herança** (Compras módulo novo sem label) | Aplicar label `module-grades-new-module-allowed` em cada PR |
| 9 | **PR atômico ≤300 LOC** | Cada onda = 1 PR; se estourar, dividir por tela |
| 10 | **case-drift Windows** worktree | `git config core.ignorecase true` + `git add` seletivo |

---

## 🎓 Cheatsheet (quando você precisar lembrar)

### Hue OKLCH per-grupo (CSS var `--gh`)

```
vender=60 (amarelo) · operar=350 (magenta) · financas=145 (verde) ·
pessoas=295 (roxo claro) · sistema=200 (azul-acinza) ·
ia=220 (azul) · atendimento=30 (laranja) · equipe=270 (roxo)
```

### Atalhos kbd canon

```
G I=IA · G A=Atendimento · G E=Equipe ·
G V=Vendas · G C=Clientes · G K=Catálogo ·
G O=OS · G P=Produção · G S=Estoque ·
G F=Financeiro · G X=Fiscal ·
G H=RH ·
G G=Governança · G T=Plataforma
```

### Naming labels

- **Ghost** ≤2 palavras (Receber/Pagar/Fluxo/Bancos)
- **Primary** verbo+objeto OU "Novo X" ≤3 palavras (Novo título / Nova categoria / Importar OFX)

### Componentes reusáveis

```tsx
import {Modulo}SubNav        from '@/Pages/{Modulo}/_shared/{Modulo}SubNav';
import {Modulo}PrimaryButton from '@/Pages/{Modulo}/_shared/{Modulo}PrimaryButton';

<header className="os-page-h">
  <div className="os-page-h-l">
    <h1>Tela <span className="fin-hero-title-sub">· subtitle</span></h1>
    <p>{contexto}</p>
  </div>
  <div className="os-page-h-r">
    <{Modulo}SubNav active="<key>" hidePrimary extraOverflowItems={[...]}/>
    <{Modulo}PrimaryButton onClick={...}>Nova X</{Modulo}PrimaryButton>
  </div>
</header>
```

---

## 🚀 Estimativa total

| Onda | Esforço | Pode rodar paralelo? |
|---|---|---|
| Onda 1 (refinar 7) | ~3h | Não — sequencial backend |
| Onda 2 (ghosts 8) | ~4h | Sim (depois Onda 1) |
| Onda 3 (frontend telas) | ~6-8h | **Sim — 4 sub-agents** |
| Onda 4 (Sells especial) | ~2h | Não — caso isolado |
| Onda 5 (validação+cleanup) | ~2-3h | Não — pós-tudo |
| **Total sequencial** | ~17-20h | |
| **Total com paralelização** | ~6-8h wall-clock | (Ondas 1+2 sequencial · 3 paralelo · 4+5 sequencial) |

---

## 💚 Por que vale a pena (resultado final)

- **Larissa @ ROTA LIVRE biz=4** decora UM padrão de header → conhecimento transfere entre Sells/Crm/Repair/Compras/etc
- **Onboarding novo colaborador**: aprende `Atalho G + letra` UMA vez → produtivo em qualquer módulo
- **Manutenção -60%**: adicionar ação = 1 entry em `extraOverflowItems[]`, não JSX novo
- **Hick's Law 6→10**: cada tela tem 3 elementos visíveis no header (vs 8-12 hoje)
- **Score consolidado 58→91** (Linear-tier — pesquisa estado-da-arte da sessão)
- **Você termina o projeto** que adorou começar 😄

---

## 📚 Onde ler mais

- `memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md` — sidebar v3 canon (5 grupos + 3 topo)
- `memory/decisions/0182-pageheadertabs-canon-pattern-telas.md` — header 3 zonas obrigatório
- `memory/decisions/0183-caixa-fisico-bridge-financeiro-canon.md` — ponte caixa→financeiro (referência boas práticas)
- `.claude/skills/pageheader-canon/SKILL.md` — protocolo 5 fases (descoberta → decisão → naming → implementação → validação)
- `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md` — F1-F12 fixas + V1-V9 variáveis
- `memory/handoffs/2026-05-21-2230-pageheader-propagacao-pendente.md` — handoff anterior da sessão

---

**Próximo passo agora:** copia o **comando 1** acima e cola na próxima sessão. Boa sorte! 🚀
