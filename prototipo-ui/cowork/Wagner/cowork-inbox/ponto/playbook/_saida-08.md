---
thread: "08 · PUXAR Painel + Espelho"
dono: "[CC]"
estado: feito (contratos + 1 comportamento) · leitura parcial declarada
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: ponto-page.jsx (Espelho: modo de visão) · oimpresso.com.html (?v=pt26sp-c)
---
# _saida-08 · Painel + Espelho

## Como li (e o que não li)
Busca dirigida de `data-contract=`/`data-testid=` em `resources/js/Pages/Ponto/**` + busca de `modo|Tabela|Grade` em `Espelho/**`. **Não** abri os 3 `.tsx` inteiros (20 + 7 + 25 KB) nem os 2 `.contract.json` — o diff abaixo cobre âncoras e o seletor de visão, não a copy literal de cada seção.

## Âncoras `data-contract` — produção × protótipo
| contrato | produção | protótipo antes | agora |
|---|---|---|---|
| `painel-nota-fechamento` | `Dashboard/Index.tsx:138` | ✅ | ✅ |
| `painel-kpis` | `:240` (`KpiGrid cols=6`) | ✅ | ✅ |
| `painel-fila-aprovacoes` | `:333` | ✅ | ✅ |
| `painel-atividade` | `:365` | ✅ | ✅ |
| `espelho-dados-colaborador` | `Espelho/Show.tsx:179` | ✅ | ✅ |
| `espelho-totais` | `:220` | ✅ | ✅ |
| `espelho-modo-visao` | `:243` (`Inline print:hidden`) | ❌ **faltava** | ✅ `<span>` em volta do seletor |
| `espelho-apuracao-diaria` | `:281` | ✅ | ✅ |
| `espelho-folha-impressao` | `:389` | ✅ | ✅ |

**9/9.** Painel já estava 4/4; Espelho foi de 4/5 para 5/5.

## Comportamento puxado da produção
- **Modo de visão não persiste mais.** O protótipo guardava "Tabela/Grade do mês" em `localStorage`; a produção usa `useState('tabela')` de propósito — `Show.casos.md:168` registra como BACKLOG deliberado: *o documento abre sempre na tabela*. Protótipo alinhado. (A chave `oimpresso.ponto.espelho.modo` pode ter ficado no navegador de quem usou o protótipo; não é lida mais.)

## Protótipo × produção — declarado, não resolvido
- **W9 (navegação):** protótipo com 13 abas de área × `PontoSubNav` 5 + `⋯ Mais`. Sem resposta de [W], **TabBar intocada**.
- Produção posiciona o seletor de visão **fora** do card (linha própria, `print:hidden`); o protótipo, **dentro** do `acao` do card. Layout — fica.
- Polling do Painel (`only:[kpis, presenca_agora, …]`) e `Inertia::defer`: é runtime, não tem par no protótipo.

## Não verificado
- Copy literal das seções contra os 2 `.contract.json` (`ponto-painel`, `ponto-espelho`).
- Estados reais (`com-pendencia/sem-pendencia/so-divergencia`; `com-pendentes/vazio`) e o `_pendente_w` de "Presentes agora".
- T1 e A1–A12 nas 2 abas.
→ Continuação natural: `08b`, 1 sessão, lendo os 2 contratos + `Dashboard/Index.tsx`.
