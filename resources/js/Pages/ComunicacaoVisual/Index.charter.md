---
id: resources-js-pages-comunicacao-visual-index-charter
page: /comunicacao-visual
component: resources/js/Pages/ComunicacaoVisual/Index.tsx
bundle_source: prototipo-ui/cowork/Wagner/comunicacao-visual-page.jsx  # 2026-09-06 [C]: porte REVERSO do vivo no Cowork (shell: "Telas que existiam no vivo e faltavam no Cowork") — fonte de bundle, não design aprovado (§5 2026-08-28)
related_us: [US-COMVIS-001, US-COMVIS-020, US-COMVIS-021, US-COMVIS-022]
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
3b. **Salvar e reabrir** (US-COMVIS-021) — "Salvar orçamento" só depois de conferir; a URL ganha `?orcamento={id}` e o cartão "Orçamentos salvos" reabre qualquer um. Reaberto e alterado, "Salvar alterações" regrava o mesmo número; aprovado/recusado/expirado não muda mais (UC-CVSAL-01..06).
3c. **Aprovar e criar OS** (US-COMVIS-020 · UC-FLX-01) — orçamento gravado e igual à tela troca "Salvar" por "Aprovar e criar OS"; o segundo clique confirma. A OS sai com o total gravado; o reaberto mostra o número dela (UC-CVOS-08).
3d. **Arquivos do orçamento** (US-COMVIS-020) — com o orçamento salvo, "Anexar arquivo" grava a arte/foto em `media`; ao aprovar vai para a OS. Depois da OS não se anexa nem remove (UC-CVARQ-01..06).
3e. **Mandar ao cliente** (US-COMVIS-023) — orçamento gravado, igual à tela e ainda não aprovado mostra "Enviar no WhatsApp": gera o link de aceite (7 dias, uma resposta) e oferece "Copiar link" e "Abrir no WhatsApp" (o WhatsApp do próprio vendedor, com a mensagem pronta; nada sai do sistema). O orçamento passa a enviado (UC-CVACE-03).
3e. **Custo e margem por peça** (US-COMVIS-022 · UC-FLX-03) — ao conferir, quem gerencia materiais vê custo e margem de cada peça e do orçamento (peças menos desconto). Peça sem custo cadastrado fica sem margem. Preço e total não mudam (UC-CVMRG-01..06).
4. **"Em breve nesta tela"** — 3 cartões honestos (Ordens de serviço · Materiais · Apontamentos), sem ação.

Contrato comportamental: [`Index.casos.md`](Index.casos.md).

## Estado atual

Tela Inertia **no ar** (`Index.tsx`), servida pela rota `comunicacao-visual.index`. Não é stub.

- A rota entrega `bizName`, `materiais`, `podeCriar`, `orcamento` (reaberto, eager) e `recentes` (deferido). Até 2026-09-23 entregava só `bizName`.
- Salvar e reabrir existem desde 2026-10-21. O link de aceite pelo WhatsApp existe desde 2026-10-26; o PDF do orçamento ainda não.

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
