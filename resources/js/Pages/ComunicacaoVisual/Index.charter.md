# Charter — Pages/ComunicacaoVisual/Index.tsx

> Charter MWART F1.5 (ADR 0107 visual-comparison gate) — Comunicação Visual landing.
> Atualizado 2026-05-31: stub → calculadora de m² funcional (board SCREEN-GRADE 2026-05-30 nota 54 → alvo ≥70).

## Persona-alvo

Larissa-equivalente — dona/operadora gráfica pequena (1-5 funcionários, ~R$ 30k/mês). Monitor 1280px típico. Não-técnica, density-first. Cenário: chega no balcão, precisa fechar um orçamento por m² em <2min sem abrir Excel.

## Objetivo desta página

Entregar **valor real na v1**: a **calculadora de orçamento por m²** (US-COMVIS-001).
- Larissa monta peças (material + descrição + largura × altura × qtd + preço/m²).
- Cálculo instantâneo no cliente (área e subtotal por peça + total geral) pra feedback imediato.
- Botão **"Conferir no servidor"** chama `POST /comunicacao-visual/api/calcular` (authoritative) — o backend recalcula com as regras da loja e respeita `business_id` (Tier 0).
- Seletor de material puxa o catálogo do business (preço/m² preenche sozinho); sem catálogo, digita preço avulso.

Áreas ainda não migradas (OS/PCP · Materiais · Apontamentos) aparecem como "em breve" honesto, em PT-BR caloroso — sem `api_hint` técnico.

## Estado atual

🟢 **Funcional (calculadora m²)** — montada no AppShellV2 + PageHeader, tokens DS v4. Ligada à API real Sprint 1.
🟡 Salvar orçamento (`POST /api/orcamentos`) + PDF/WhatsApp + telas PCP/Materiais/Apontamento = Sprint 2 (aguarda cliente piloto — ADR 0105).

## Anti-padrões (Tier 0)

- ⛔ Cálculo client-side como fonte de verdade — o servidor é authoritative (R-COMVIS-001). O preview local é só conveniência; o valor oficial vem do `/calcular`.
- ⛔ Cor crua (zinc/amber/sky/blue-NNN), `#hex`, `oklch()` inline, `style={{}}` de cor — só tokens DS.
- ⛔ Jargão técnico/`api_hint` na UI pra Larissa.
- ⛔ Renderizar `business_id` no HTML — usar slug/contexto session.
- ⛔ Auto-refresh polling — usar Centrifugo subscription quando ativar.
- ⛔ Carregar listas grandes sem `Inertia::defer()` (catálogo de materiais é pequeno; revisitar se crescer).

## Fase MWART aplicável

- F1.5 visual-comparison: calculadora ativada (gate visual a rodar quando Sprint 2 escalar telas próprias).
- F2 backend baseline: ✅ API JSON `/calcular` + `/orcamentos` prontas.
- F3 frontend: 🟢 calculadora m² (esta entrega); 🟡 PCP/Materiais/Apontamento pendentes.
- F4 QA: aguarda Pest de smoke da página + e2e calculadora.
- F5 cutover: aguarda piloto.

## Wave histórica

- Wave 25 (2026-05-16): charter criado pra fundação MWART futura.
- 2026-05-26: stub honesto de 4 cards "em breve" (Wagner reportou módulo quebrado/404 no sidebar).
- 2026-05-31: stub → **calculadora de m² funcional** no AppShellV2 + tokens DS (board SCREEN-GRADE 2026-05-30).
