---
sessao: "08"
titulo: PUXAR Painel + Espelho (as 2 telas com contrato) → protótipo
dono: "[CC] read-only → build"
base: e86130722de1
prefixo: prototipo-ui/cowork/ponto-page.jsx (Painel · Espelho index/show/imprimir vivem aqui) · oimpresso.com.html (bump ?v=)
nao_toca: resources/js/Pages/** (produção é o dono) · ponto-telas.jsx · ponto-data.jsx · ponto-mobile.jsx (threads 09/10) · contratos
depende: — (vaga 1). W9 (navegação) não bloqueia: sem resposta, a divergência é declarada, não resolvida.
---
# 08 · PUXAR Painel + Espelho

## Estado
Produção 🔵 à frente: `Dashboard/Index.tsx` (20 KB, 4 `data-contract`, polling `only:[kpis, presenca_agora, atividade_recente, alertas, server_time]`, `Inertia::defer`) · `Espelho/Index.tsx` (7 KB) · `Espelho/Show.tsx` (25 KB, 5 `data-contract`: `espelho-dados-colaborador · espelho-totais · espelho-modo-visao · espelho-apuracao-diaria · espelho-folha-impressao`). Meu `ponto-page.jsx` é **import das blades de jun/2026** — anterior a tudo isso.

## O que esta thread faz (medição → build; nunca o contrário)
1. Ler as 3 Pages + os 2 contratos + `PontoSubNav.tsx` no `main` (no turno; sha no `_saida`).
2. Medir o protótipo (T1: 901 nós no Painel em 04/09; duas leituras iguais).
3. **Diff produção → protótipo** (entra no build): os `data-contract` (o protótipo do Painel já tem 4; conferir os 5 do Espelho) · átomos/`aria-*`/`data-testid` · estados reais (`com-pendencia/sem-pendencia/so-divergencia`; `com-pendentes/vazio`) · a copy literal dos contratos · o `_pendente_w` do `ponto-painel` ("Presentes agora" em tempo real).
4. **Diff protótipo → produção** (só vira pedido se for comportamento sem VALOR): listar, não aplicar.
5. **Navegação (W9):** o protótipo tem 13 abas de área; a produção `PontoSubNav` (5 + `⋯ Mais`). Sem resposta de [W], **declarar** no `_saida`; não mudar a TabBar.
6. Bump `?v=`; T1 de novo; A1–A12 nas 2 abas.
7. `_saida-08.md` com o diff nos dois sentidos.

## PARAR SE
- Qualquer item exigir número de apuração real no protótipo → é VALOR: fica `—`.
- Diff exigir mudar a Page viva → não é desta thread; pedido de 1 arquivo com UC do `casos.md` da tela.

## Prova
- `_saida-08.md` (diff 2 sentidos · sha · divergência W9 declarada) · `ponto-page.jsx` no espelho `prototipo-ui/cowork/` com os átomos puxados
