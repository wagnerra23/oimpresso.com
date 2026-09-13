---
thread: "02"
modulo: shell-usermenu
dono: "[CL]"
prefixo: resources/js/Components/cockpit/Sidebar.tsx
base: remedir antes de escrever
---
# 02 · "Sair" não sai

## Problema
O item **Sair** não tem handler. É o item mais consequente do menu e é o único que não faz nada — pior que ausente, porque parece pronto.

## O que fazer
1. Confirmação **inline no próprio menu** ("Encerrar a sessão?" · Encerrar / Cancelar). Não abrir modal full-screen — proibido pra este tipo de decisão no canon do Cockpit.
2. **Encerrar** dispara o logout real do app (a rota de logout que o UltimatePOS já expõe — use a que o layout legado usa, não invente endpoint).
3. **Cancelar** volta ao menu sem efeito nenhum.

## Atenção — diferença deliberada com o protótipo
No protótipo daqui **não há auth**, então "Encerrar" recarrega a página. Isso é um **stand-in declarado**, não o comportamento alvo. Copiar o `window.location.reload()` pro `main` seria exportar a limitação do protótipo como se fosse design.

## Prova
- `npm run lint && npx tsc --noEmit` → exit 0.
- Runtime: Cancelar não muda nada; Encerrar termina a sessão de verdade (volta pro login).
- **Controle negativo:** com a confirmação aberta, clicar fora fecha o menu **sem** encerrar.

## Parar se
- Não houver rota de logout evidente no layout legado → **pare e reporte**; não crie rota nova numa onda de UI.
