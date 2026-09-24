---
id: resources-js-pages-comunicacao-visual-index-charter
page: /comunicacao-visual
component: resources/js/Pages/ComunicacaoVisual/Index.tsx
bundle_source: prototipo-ui/cowork/Wagner/comunicacao-visual-page.jsx  # 2026-09-06 [C]: porte REVERSO do vivo no Cowork (shell: "Telas que existiam no vivo e faltavam no Cowork") — fonte de bundle, não design aprovado (§5 2026-08-28)
related_us: [US-COMVIS-001]
related_us_nota: "2026-09-06 [C]: a tela É a calculadora por m² (US-COMVIS-001 · P0) — declarado ao tocar o charter (charter-us-lint no-new-lie)"
related_prototype: "n/a — ferramenta bespoke (calculadora de m²) construida do Design System; arquetipo unico no repo (so 1 tela do tipo), sem padrao de tela dedicado"
status: draft
---

# Charter — Pages/ComunicacaoVisual/Index.tsx

> Charter MWART F1.5 (ADR 0107 visual-comparison gate) — Comunicação Visual landing.

## Persona-alvo

Larissa-equivalente — dona/operadora gráfica pequena (1-5 funcionários, ~R$ [redacted Tier 0]k/mês). Monitor 1280px típico. Cenário: chega no balcão, precisa abrir orçamento ou checar PCP rápido.

## Objetivo desta página

Calculadora de orçamento por m² do vertical ComVis (US-COMVIS-001 · P0). É o que a tela faz hoje:

1. **Peças do orçamento** — linhas com material (catálogo do business), descrição, largura × altura × qtd e R$/m²; área e subtotal por peça calculados na hora.
2. **Ajustes gerais** — um campo manual de acabamento/instalação/entrega e um de desconto, ambos em R$.
3. **Prévia × oficial** — o total da tela é prévia; o botão "Conferir no servidor" chama `POST /comunicacao-visual/api/calcular` e o valor do servidor é o oficial (UC-CV-01).
4. **"Em breve nesta tela"** — 3 cartões honestos (Ordens de serviço · Materiais · Apontamentos), sem ação.

Contrato comportamental: [`Index.casos.md`](Index.casos.md).

## Estado atual

Tela Inertia **no ar** (`Index.tsx`), servida pela rota `comunicacao-visual.index`. Não é stub.

- ⚠️ A rota hoje entrega **só `bizName`**: `materiais` e `podeCriar` nunca chegam, então o seletor de material fica em "Sem catálogo" mesmo com catálogo semeado — UC-CV-07 (vermelho esperado). O conserto é no backend da rota, fora deste arquivo.
- Salvar orçamento e enviar PDF ainda não existem na UI (a API `POST …/api/orcamentos` existe).

## Próximo (não está na tela — não assuma que está)

Os 3 widgets que este charter descrevia até 2026-09-23 **não existem na tela**; ficam como próximo, cada um na sua US:

- Orçamentos pendentes de aprovação do cliente — depende de salvar orçamento (UC-CV-11)
- CRUD de materiais — US-COMVIS-002
- OS em produção / PCP em miniatura — US-COMVIS-003
- Apontamentos do dia (m² produzido + drift) — US-COMVIS-004

## Anti-padrões (Tier 0)

- ⛔ Auto-refresh polling — usar Centrifugo subscription quando ativar
- ⛔ Carregar listas grandes sem `Inertia::defer()` (skill `inertia-defer-default`)
- ⛔ Renderizar `business_id` no HTML — usar slug/contexto session

## Fase MWART aplicável

- F2 backend baseline: ✅ API JSON (`/comunicacao-visual/api/*`)
- F3 frontend: ✅ calculadora no ar — os 3 widgets do "Próximo" não
- F4 QA: parcial — trio completo (charter · casos · teste), UC-CV-07 vermelho esperado
- F5 cutover: aguarda piloto (ADR 0105)

## Wave histórica

- Wave 25 (2026-05-16): charter criado pra fundação MWART futura.
- 2026-09-23: Objetivo/Estado reescritos pro que a tela faz (playbook comunicacao-visual thread 01, R7). Até aqui se declarava stub com UI não ativada e listava 3 widgets inexistentes.
