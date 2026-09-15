---
sessao: "05"
titulo: Painel de Conformidade CLT — BLOQUEADA (depende de 04 e W1)
dono: "[W]"
base: e86130722de1
prefixo: nenhum até 04 fechar. Depois: Services/ConformidadeService.php (novo) · resources/js/Pages/Ponto/Conformidade/** · Tests/Feature/ConformidadeContratoTest.php
nao_toca: ApuracaoService (só lê) · Colaborador (só lê PIS) · escalas (só lê)
depende: thread 04 · W1
---
# 05 · Conformidade — bloqueada

## Por quê
As 6 verificações do painel (Art. 66 · Art. 71 · Art. 59 · NSR · jornada aberta · ativo sem PIS) só têm sentido **por competência** — sem o estado da competência (W1) não há "mês a apontar". Por isso espera a 04.

## Alvo de layout (medido 04/09 — 910 nós)
**3 seções nesta ordem:** `.pt-nota.danger` (2) · `.pt-kpis` (**6 KPIs** = as 6 verificações) · `SECTION.pt-card` (2) · 1 tabela (6 `th`) · 28 botões · 0 campos. Fonte: `prototipo-ui/cowork/ponto-fechamento.jsx` (`Conformidade`, função `achados(mes)` — a apuração das violações do protótipo, **referência de regra, não de código**).

## Leis
- Cada apontamento cita o artigo **literal**; sem artigo, não é apontamento.
- Read-only: o painel não escreve. A correção acontece em Intercorrências/Espelho.
- Reusar `ApuracaoService` (19 KB) para os dias; **não** reimplementar apuração no Service novo.

## Quando destravar
```
1) 04 feita (estado da competência existe)
2) [CL] criar-tela.mjs Ponto/Conformidade PT-05 → Service (só leitura + as 6 regras) + Page + trio + Pest
PARAR SE : uma regra exigir dado que ApuracaoService não expõe → "—" + linha no PR, nunca query nova em controller
```

## Prova (quando destravar)
- `${PAGES}/Conformidade/Index.tsx` + trio · `ConformidadeContratoTest` verde · `_saida-05.md`
