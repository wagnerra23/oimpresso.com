---
sessao: "A3"
titulo: "Remedir Logs Index e Timeline — saída da thread"
autor: "[CL]"
data: 2026-10-05
base: origin/main 81a585242e
thread: A3-remedir-logs.md
veredito: "NÃO MEDI — o lado design agora abre a vista certa (override oi-log), mas o lado prod não é medível como a ficha pede e a Timeline não tem vista própria. Parei e reporto, como a ficha manda."
---

# _saida-A3 · Remedir Logs

## O que saiu
- `governance/design/targets/roles/Officeimpresso--Logs--Index.json`: override com `token: "oi-log"`
  e `tableRow` nos dois lados, como o `_saida-A1` pediu. Fora do prefixo da A3 (`medidas/`): é o
  único jeito de o driver aceitar a rota certa, e sem ele a próxima medida repete o erro.
  Com o override, `design-diff-lote --dry` mostra `shell route=oi-log (override)`.
- **Nenhuma medida gravada.** `medidas/Officeimpresso--Logs--{Index,Timeline}/` seguem as de
  2026-09-18, ainda inválidas pelo motivo do `_saida-A1` (mesmo `design.json`, blob `0369a411b2`,
  vista `empresas`).

## Lado design: resolvido
Rodei `design-diff-lote --tela Officeimpresso/Logs/Index --incluir-comparadas --base-url
https://staging.oimpresso.com` três vezes. O `design.json` passou a ser a vista de log: a
assinatura começa em *"Log de acesso · 13 eventos · append-only"*, com 4 KPIs e a grade de eventos.
As três leituras são iguais em tudo menos o campo `url`, que leva a porta efêmera do servidor
local do protótipo (`127.0.0.1:61624` × `127.0.0.1:64729`). Byte a byte, portanto, nunca saem
idênticas: o `resultado.json` também carrega `medidoEm`. A comparação que vale é sem esses dois
campos.

## Lado prod: não medível hoje (medido)
1. **A rota serve a Blade, não a tela Inertia.** `LicencaLogController::index` só renderiza
   `Officeimpresso/Logs/Index` com a flag `useV2OfficeimpressoLogs` ligada. No staging ela está
   desligada para o usuário de medida: a assinatura do `prod.json` é o shell AdminLTE
   (*"Today's Profit"*, *"Application Tour"*) com a página *"Máquinas Cadastradas"*.
2. **O CSS da Blade chegou vazio.** O próprio `resultado.json` marca `SAÚDE · NÃO MEDI`: 3 folhas
   (`app.css`, `vendor.css`, `app.css`) com zero regras, 7 tokens de cor sem resolver. Tema `light`
   contra `dark` do design. Cor e espaçamento desse lado não são confiáveis.
3. **Sem dado.** A base do staging tem 0 máquinas: KPIs zerados e grade vazia.
4. **A tela de produção não é a mesma do protótipo**, e isso vale também para a versão Inertia. A
   `Logs/Index.tsx` (como a Blade) lista **máquinas** (`MaquinasTable`, KPIs "Máquinas cadastradas",
   "Máquinas bloqueadas"…), enquanto a vista `oi-log` do protótipo lista **eventos** do
   `licenca_log` (Quando · Evento · Origem · Rota · Latência). Medir uma contra a outra mede
   identidade, não defeito: o run deu `DIVERGE · 5 bugs` (`kpi.count` 0 × 4, colunas 1 × 7,
   `table-layout`…) e nenhum deles é acionável. A tela também não tem contrato D0.

## Timeline: sem vista própria (inalterado)
O cabeçalho do `officeimpresso-page.jsx:6` segue mapeando `licenca_log/index + timeline → view
"log"`, e a rota viva é parametrizada (`licenca_log/timeline/{licenca_id}`). Um `design.json`
próprio da Timeline seria ou o mesmo da `oi-log` ou a timeline do drawer da licença
(`officeimpresso-page.jsx:717`), que é outro alvo. É decisão do Cowork.

## O que destrava
- **Cowork:** dizer qual vista do protótipo é a `Logs/Index` de produção (lista de máquinas ou
  log de eventos) e qual é a da Timeline. Sem isso, nenhuma medida dos Logs diz algo.
- **[W]:** ligar `useV2OfficeimpressoLogs` no staging para o usuário de medida, ou medir pelo
  CI do `visual-regression`. Ligar flag é decisão sua.
- Base com máquinas e eventos no staging, para o lado prod ter conteúdo.

## Placar
entregue 0 de 1 — remedir 0 de 2 telas. A thread 07 (Logs) segue dependendo desta. O
`00-INDICE.md` não foi editado.
