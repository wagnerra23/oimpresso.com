# Sessão 2026-06-10 (f½) — Benchmark honesto vs melhores ([W]: "comparado aos melhores o que falta? analise tudo")

## Placar (15 dimensões · referências Linear/Stripe/Mercury/Front/Attio/Vercel/Cron)
| Dimensão | Nós | Nota |
|---|---|---|
| Craft visual de 1 tela | telas-norte fechadas | 9 ✓ |
| Consistência entre módulos | 3 gerações visuais coexistindo | 6,5 🔴 |
| Confiança do clique | CTAs primários mortos (corrigidos no fluxo) | 6 🔴→melhorou |
| Velocidade percebida + estados de rede | sem loading/skeleton/erro/otimista (mock esconde) | 5 🔴 |
| Teclado ponta a ponta | ilhas; sem vocabulário único | 6 |
| Empty states que ensinam | "nada encontrado" | 4 |
| Microcopy/voz | sem guia | 6 |
| A11y | sem focus trap; contraste --text-4 duvidoso | 5 |
| Touch/tablet (persona Técnico) | inexistente | 5,5 |
| Dataviz | estáticos | 6 |
| Motion | curva única ✓; falta rota/FLIP/countup | 7,5 |
| IA acionável | mock estático (window.claude não ligado) | 5 |
| Colaboração | comments ✓; sem menções/presença | 5 |
| Print/export | oficina ✓; fin export corrigido no fluxo | 6 |
| **Costura entre módulos (fluxo)** | corrigida no protótipo na sessão seguinte | 6→melhorou |

**Síntese:** ganhamos o jogo da TELA; sistema ~6,5 — gaps 🔴 são de SISTEMA e FLUXO. Pergunta-guia derivada (feita e executada): "Larissa fecha o ciclo venda→dinheiro→imposto sem mouse e sem botão morto?" → sessão `2026-06-10-roda-o-fluxo.md`.

## Backlog derivado (ordem de impacto)
1. ~~Costura + botões mortos + produção fantasma + teclado sidebar~~ (fluxo ✓)
2. Consistência: ondas W (Compras → re-passada Vendas/Oficina → W3/W4 + hub Vendas/Manufacturing).
3. Estados de rede (skeleton/erro/otimista) como componentes ds-v6 + matriz do probe.
4. Vocabulário único de teclado (1 página ds-v6) por onda.
5. Empty states padrão + guia de voz (1 página).
6. A11y: focus trap nos 3 overlays do shell + contraste (G10 candidato).
7. Chart component ds-v6 (tooltip/hover) p/ Fluxo/DRE.
8. IA real: ligar window.claude.complete no digest (1 caso-prova).
9. Touch/persona Técnico = decisão [W] (agora ou fase 2).
