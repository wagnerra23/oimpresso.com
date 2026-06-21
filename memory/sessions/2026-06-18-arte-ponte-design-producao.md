---
slug: arte-ponte-design-producao
title: "Estado da arte 2026 — ponte design→produção: Figma vs gerar-na-stack vs DS-como-contrato (decisão aterrada pra time 1-2 + IA-pair)"
type: session
authority: advisory
lifecycle: ativo
session_date: '2026-06-18'
quarter: 2026-Q2
related:
  - '0255'  # contrato de view determinístico (charter + design-spec.json)
  - '0239'  # governança DS git SSOT
  - '0114'  # loop Cowork formalizado (Wagner aprova screenshot)
  - '0264'  # governança executável trio-de-tela
pii: false
---

# Ponte design→produção — quem ganha em 2026 e o que o oimpresso faz

> Pergunta do dono: _"Penso no Figma como padrão — parece ser ponte melhor? Troco o Cowork pelo Figma (Dev Mode + Code Connect + Variables)?"_
>
> **Resposta curta: NÃO troque a ferramenta de desenho. O problema não é o desenho — é a camada de tradução (Cowork-CSS → Tailwind) que existe em QUALQUER ferramenta. Figma entra parcial (tokens + Code Connect pro DS), nunca pra layout por-tela. O barato pro seu time é o que você JÁ começou: DS-como-contrato + gerar-na-stack-real + protótipo descartável. Você está a 1 passo de fechar — falta ADOTAR o gate que criou hoje.**

---

## Seção 1 — Pesquisa (estado da arte 2026, sem viés do repo)

Pesquisa limpa (WebSearch/WebFetch), depois aterrada no repo. Quem shipa rápido em 2026 e a razão **estrutural** (não buzzword):

| Player / método | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **monday.com (eng. blog 2026)** | Figma MCP cru gerava lixo: cor hardcoded, sem componente do DS, CSS manual. Solução: **DS-MCP custom** = DS inteiro como conhecimento _machine-readable_ (componentes válidos, props, tokens, regras a11y) derivado das **fontes reais** (código, types, token defs). O agente **consulta** o DS, não adivinha. | Prova estrutural: o gargalo nunca foi o modelo nem a ferramenta de desenho — é o agente **não conhecer o DS**. Quem dá o DS como contrato ganha. |
| **v0 by Vercel** | Gera React+Tailwind+shadcn idiomático, "que um sênior aprovaria em review", TypeScript tipado, decomposição sensata. Aponta pro codebase existente. | Melhor qualidade de código gerado dos 3 (v0/Lovable/Bolt). Mas é shadcn/Next — não fala Laravel/Inertia nativo. |
| **Time "60 componentes em 35 dias"** | Tool que produz **código pronto pra review e ship no mesmo dia**, 3.077 testes, **zero hex cru**. 300 engenheiro-dias estimados → 35 dias reais. | Prova de velocidade quando o DS é o produto e o código nasce testado/governado. |
| **Figma Code Connect (CLI, fev/2026)** | Mapeia **1 nó Figma → 1 componente de código real**. Dev Mode mostra o snippet do SEU componente em vez de CSS auto-gerado. "#1 jeito de ter reuso consistente — sem isso o modelo chuta." | Padrão de mercado pro **design system**. **Component-focused, NÃO screen-focused** (confirmado nos docs Figma). |
| **W3C DTCG `.tokens.json` (estável 2025.10)** | Formato vendor-neutral de troca de tokens em JSON. Interop entre Figma/Penpot/Style Dictionary/Supernova. | Único padrão real de tokens. É **formato, não pipeline**. |

**Convergência das 5 evidências (a tese):** ninguém sério em 2026 trata "ferramenta de desenho → código" como o problema. Todos resolveram a mesma coisa — **dar o design system ao gerador como contrato machine-readable** — e deixaram o desenho onde estava. Figma vence **no DS** (Code Connect mapeia componente). Ninguém vence **no layout por-tela**: Code Connect é 1-nó→1-componente por definição, não monta página; v0/Lovable montam página mas em shadcn/Next, não Inertia, e geram código que precisa re-limpar (Stanford: 80% das apps geradas por IA têm ≥1 vuln explorável).

---

## Seção 2 — Compara (estado da arte × oimpresso hoje)

Aterrado no repo (`D:/oimpresso-contrato-tela`). Achado central: **o oimpresso já decidiu a arquitetura certa (ADR 0255) e já construiu o mecanismo certo (gate Contrato de Tela, hoje) — mas não adotou.**

| Dimensão | Estado da arte 2026 | oimpresso hoje | Distância |
|---|---|---|---|
| **DS como contrato machine-readable** | monday.com: DS-MCP custom; v0: aponta pro codebase | DS v6 canon: `tokens.css` oklch SSOT, REGISTRY 11 comp, `ds-guard.mjs`, ADR 0239 regressão-IA. **Tem, e melhor que a média** | **curta — já bate** |
| **Tokens single source** | DTCG `.tokens.json` | **DUAS fontes**: `prototipo-ui/tokens.css` (Cowork) + `resources/css/foundations.css` (prod, Tailwind 4 `@theme`). Não há export DTCG nem bridge automático | **média** |
| **Componente: 1 desenho → 1 código** | Figma Code Connect (CLI) | shadcn/ui em `Components/ui/` (31 comp) + REUSE_MAPPING, mas **sem mapa formal desenho↔componente**. Reuso forçado por convenção/guard, não por Code Connect | **média** (resolvido por disciplina, não por tool) |
| **Layout por-tela: desenho → página** | **Ninguém resolve sem tradução** (Code Connect não monta página; v0 monta mas noutra stack) | Port manual Cowork-CSS → Tailwind v4. **29 bundles CSS portados** (`cowork-*`, `fin-*`, `sells-*`). É "aqui nasce a perda de tradução" (palavra do próprio repo, sessão 06-06) | **longa — mas é a mesma do mercado** |
| **Anti-drift / fidelidade visual** | pixel-diff, visual-regression | charter + casos + Pest (2 pernas mecanizadas). **Fidelidade visual = perna que falta**. Métrica atual (`score-mechanized` nota 99) mede **higiene de token, não fidelidade** — verde ≠ bate com protótipo | **curta — gate JÁ existe, falta ligar** |
| **Ponte sem tautologia** | âncora estável > match de classe | **`scripts/contrato-de-tela.mjs` (criado HOJE)** valida `data-contract` + copy literal + ordem, determinístico, sem render. **Estado da arte. Mas 0 adoção: 0 âncoras nas Pages, SYNC_LOG sem fonte, protótipo em `_BACKUP-NAO-USAR/`** | **curta na ideia, longa na adoção** |
| **Idiomático pra Inertia/Tailwind** | v0/Lovable = shadcn/Next | **shadcn/ui + Tailwind 4 + Inertia v3** já é a stack. Anti-padrões de port catalogados (LICOES_F3) + 4 PHPStan rules de enforcement (tenant scope, no-op, silent fallback) | **curta — vantagem sua** |

**Honestidade:** o oimpresso **supera a média de mercado** em DS-como-contrato e em enforcement mecânico (PHPStan rules + guards que poucos times de 1-2 têm). A distância real é **operacional, não arquitetural**: a tradução por-tela é manual e o gate que a prenderia foi construído hoje e não foi ligado.

---

## Seção 3 — Avalia (o que falta, rankeado por impacto × esforço)

| Gap | Impacto | Esforço (IA-pair, ADR 0106 10×) | Pré-req bloqueante? |
|---|---|---|---|
| **Adotar o gate Contrato de Tela na tela-piloto** (âncoras + `## Contrato visual` no charter + ligar advisory) | **alto** — fecha a perna de fidelidade que falta; mata "12/15 auto-certificado" | ~2-3h IA-pair pra Caixa Unificada | **Sim: fonte-da-verdade.** 1 protótipo canônico versionado (sair de `_BACKUP`), senão é teatro (pré-req #1 do RUNBOOK) |
| **Consertar fonte-da-verdade** (1 protótipo/tela versionado em `prototipo-ui/`, matar as 3 versões, SYNC_LOG real) | **alto** — sem isso todo gate roda sobre vazio | ~1-2h IA-pair de curadoria | Não — é o pré-req de tudo |
| **Unificar tokens (1 fonte → DTCG export)** `foundations.css` ⟵ gerado de `tokens.css` via Style Dictionary; mata a divergência prod↔Cowork | **médio** — elimina drift de cor silencioso entre as 2 stacks | ~3-4h IA-pair (setup Style Dictionary + DTCG) | Não, mas só vale depois da fonte-única |
| **Gerar-na-stack idiomático** (skill/prompt que recebe `design-spec.json` + DS REGISTRY → emite `.tsx` Inertia, em vez de portar CSS à mão) | **médio-alto** — ataca os 29 bundles CSS; reduz a "perda de tradução" | ~4-6h IA-pair pro template + 1 PoC | **Sim:** depende de DS-como-contrato adotado (gate) pra ter o que conferir |
| **Promover gate a required** sob `enforce_admins` | **médio** — sem isso mergeia vermelho (visual-regression já mergeou vermelho 2×/24h) | ~1h IA-pair | **Sim:** 1-2 semanas advisory medindo falso-positivo |
| **Figma Code Connect pro DS** (mapear 11 componentes REGISTRY → nós Figma) | **baixo agora** | alto (precisa lib Figma mapeada + Dev seat $12-35/mês/dev) | Sim, e **ROI negativo hoje**: sem designer, o "desenho" já é IA-native (Cowork). Figma adiciona um 3º sistema |

### Veredito da hipótese do dono

**CONFIRMADA, com correção de ênfase.**

- ✅ "Figma é ponte melhor só pro design SYSTEM (tokens + componentes via Code Connect)" — **correto**. Code Connect é 1-nó→1-componente por design (docs Figma). É a melhor ferramenta de mercado pra DS mapeado.
- ✅ "pro layout por-tela é a mesma camada de tradução (só ergonomia melhor)" — **correto e mais forte**: nem o Figma resolve. Code Connect não monta página; v0 monta mas em shadcn/Next (re-tradução pra Inertia). A tradução é intrínseca a ter 2 representações.
- ✅ "pro time pequeno IA-pair o barato é DS-como-contrato + gerar-na-stack + protótipo descartável, não trocar a ferramenta" — **correto, e é literalmente o que o ADR 0255 já decidiu e o gate de hoje implementa.**

**Correção:** pro oimpresso **hoje**, Figma é **ROI negativo até em parcial**. Razão: você **não tem designer**. O valor do Figma é a ergonomia de um humano desenhando + handoff pra dev. Seu "designer" é IA (Cowork/Claude Design), que já fala estrutura (não pixels) e ingere o DS do codebase. Meter Figma no meio adiciona um **3º sistema** (Cowork OU Figma → agente → Inertia) e custo de seat, pra resolver um problema (ergonomia de desenho humano) que você não tem. Figma vira candidato **só se** contratar designer humano dedicado.

---

## Recomendação — caminho-alvo + 3 primeiros passos

**Caminho-alvo: "DS como contrato + gerar-na-stack-real + protótipo descartável".** Não é uma troca de ferramenta — é **terminar o que o ADR 0255 começou e o gate de hoje habilita**. Você está a 1 passo de fechar o loop; o erro seria comprar Figma e recomeçar a curva de outra ferramenta.

**O papel exato do Figma:**
- **NÃO** pro layout por-tela (mesma tradução, custo novo, sem designer pra justificar a ergonomia).
- **NÃO** como troca do Cowork agora (3º sistema, ROI negativo sem designer humano).
- **PARCIAL/FUTURO** só se contratar designer dedicado: aí Figma Variables (export DTCG) + Code Connect pros 11 componentes do REGISTRY. Até lá: **não**.

**Os 3 primeiros passos (ordem fixa, alto-impacto-baixo-esforço, pré-req respeitado):**

1. **Consertar a fonte-da-verdade da Caixa Unificada** (pré-req #1, inegociável). Tirar o protótipo canônico de `prototipo-ui/_BACKUP-NAO-USAR/`, versionar 1 versão única em `prototipo-ui/`, matar as 3 cópias. Sem isso, qualquer gate roda sobre vazio = teatro. ~1-2h IA-pair.

2. **Instrumentar a Caixa Unificada com o gate Contrato de Tela** que você criou hoje: âncoras `data-contract` no `.tsx`, seção `## Contrato visual` no charter (copy literal + ordem), ligar `scripts/contrato-de-tela.mjs` **advisory**. Isso fecha a perna de fidelidade que falta e mata a auto-certificação "12/15". ~2-3h IA-pair.

3. **Medir falso-positivo 1-2 semanas → promover a required sob `enforce_admins`.** Em paralelo, PoC do passo "gerar-na-stack": 1 skill que recebe `design-spec.json` (ADR 0255) + DS REGISTRY e **emite o `.tsx` Inertia** em vez de portar CSS à mão — começando pela própria Caixa. É o que ataca os 29 bundles a médio prazo.

**Próxima ação hoje:** passo 1 — pegar o protótipo da Caixa Unificada em `prototipo-ui/_BACKUP-NAO-USAR/`, escolher a versão canônica, versionar única em `prototipo-ui/` e registrar a versão no SYNC_LOG. É o desbloqueador de todo o resto e não tem pré-req.

**O que NÃO fazer:** comprar seats Figma, mapear Code Connect, ou trocar o Cowork. Tudo isso é esforço alto pra resolver um problema (desenho humano ergonômico) que um time sem designer não tem — enquanto o problema real (tradução não-prendida por gate) já tem a solução construída esperando adoção.

---

## Custos/números reais (evidência 2026)

- **Figma Dev seat:** $12 (Pro) / $25 (Org) / $35 (Enterprise) por mês/dev, anual. Full seat: $16/$55/$90. Code Connect exige lib mapeada (setup manual por componente) + CLI.
- **Code Connect:** maduro como **component mapping** (1 nó → 1 componente), CLI fev/2026. **Não gera página/layout** — limitação de design, não bug.
- **Geração v0/Lovable/Bolt:** v0 = melhor qualidade ("sênior aprovaria"), mas shadcn/Next, não Inertia. Stanford: **80% das apps geradas por IA têm ≥1 vuln explorável** → review obrigatório. Figma MCP cru: ~85% "funciona", 15% falha previsível; sem DS-contrato vira "mess" (caso monday.com).
- **oimpresso:** custo marginal do caminho recomendado = **$0 de licença** (Cowork + Claude Code já pagos; gate é Node puro sem deps). Esforço total dos 3 passos ≈ **6-8h IA-pair** pra fechar o loop na tela-piloto.

## Refs
- ADR 0255 — contrato de view determinístico (charter + design-spec.json) — **a decisão que este doc confirma**
- ADR 0239 — governança DS git SSOT / regressão-IA
- ADR 0114 — loop Cowork (Wagner aprova screenshot)
- `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — o gate (criado 2026-06-18)
- `scripts/contrato-de-tela.mjs` — implementação determinística
- sessão 2026-06-06-arte-claude-design-handoff — survey anterior (Figma/v0/Builder/Locofy/DTCG × Claude Design)
- monday.com eng — "How We Use AI to Turn Figma Designs into Production Code" (DS-MCP custom)
- Figma docs — Code Connect (1 nó → 1 componente); Figma pricing 2026
