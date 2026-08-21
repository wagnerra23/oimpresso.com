---
id: resources-js-pages-ponto-fechamento-charter
page: /ponto/fechamento
component: resources/js/Pages/Ponto/Fechamento.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [aberto, consolidado, fechado]
related_prototype: prototipo-ui/cowork/ponto-fechamento.jsx
contrato: prototipo-ui/contrato/ponto-fechamento.contract.json
tier: A
charter_version: 1
---

# Page Charter — /ponto/fechamento (competência como fluxo)

> **Tela nova.** Não existe no Blade: `Modules/Ponto/Resources/views/` tem dashboard, espelho,
> aprovações, intercorrências, banco-horas, escalas, importações, relatórios, colaboradores e
> configurações — nenhuma delas fecha o mês. Hoje o fechamento acontece **na cabeça de quem opera**:
> a pessoa abre o espelho colaborador por colaborador, olha a fila de aprovações, lembra que faltou
> PIS de alguém e decide gerar o AFD. Este charter existe para transformar isso em estado da
> competência, com pré-checagem e trava.
>
> **Persona:** Eliana (financeiro/RH, 1440px, tabelas densas) fecha; **Wagner** assina exceção.
> Larissa e o técnico nunca veem esta tela.

---

## Mission (1 frase)

Levar uma competência de **aberta → consolidada → fechada** com pré-checagem que nomeia cada
bloqueio (divergência, intercorrência aberta, PIS ausente, importação em andamento, violação dura
de CLT), impedindo fechar por engano e registrando exceção quando fechar é decisão consciente.

---

## Goals — faz

- Seletor de competência + pílula de situação (`aberto` / `consolidado` / `fechado`)
- Trilha de 4 passos: pré-checagem → consolidar apuração → fechar competência → gerar AFD/AEJ
- Pré-checagem apurada **dos dados da própria competência**, com grau `bloqueia` (grave) × `conferir`
  e atalho para a tela que resolve cada item
- `Consolidar apuração` desabilitado enquanto houver bloqueio grave
- `Consolidar com exceções`: caminho explícito, com confirmação, que registra N exceções assinadas
- `Fechar competência` com confirmação — depois disso a correção é **anulação + nova marcação**
- Totais da competência (trabalhado / HE / faltas / banco de horas) só de quem controla ponto
- Lista de divergências por colaborador com atalho para o espelho
- Painel de Conformidade irmão: 6 verificações (jornada sem fechamento, interjornada Art. 66,
  intrajornada Art. 71, HE Art. 59, NSR fora de sequência, colaborador sem PIS) com caso a caso

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO recalcula apuração** — consolidar carimba o que a apuração já produziu. Reapurar é ação
  explícita do módulo (`ReapurarDiaJob`), não efeito colateral do fechamento
- ❌ **NÃO edita marcação** em nenhum estado — competência fechada só aceita anulação com trilha
- ❌ **NÃO gera AFD/AFDT/AEJ aqui** — o passo 4 leva a Relatórios; a geração é do `ReportService`
- ❌ **NÃO fecha sem confirmação** e **não consolida com bloqueio grave** sem o caminho de exceção
- ❌ **NÃO inventa número**: se a competência não tem apuração, os totais mostram vazio, não zero
- ❌ **NÃO decide intercorrência** — a fila é da tela de Aprovações; aqui só conta e leva pra lá

---

## UX targets

- Eliana entende **por que não pode fechar** em ≤ 5s (grau + frase que nomeia a consequência)
- Bloqueio sempre com destino: nenhum item da pré-checagem sem ação que resolve
- Estado da competência visível sem scroll, no topo da tela
- 0 erro de console; nenhuma cor fora dos tokens do DS

---

## Anti-hooks (sinais de drift)

- ⚠️ Aparecer botão que **fecha direto** (pulando consolidado) — a trilha é o que garante a checagem
- ⚠️ Aparecer **reabertura de competência fechada** sem ADR e sem registro de auditoria
- ⚠️ Aparecer **recálculo automático** no consolidar — vira caixa-preta e mente sobre o dado
- ⚠️ Aparecer **contador que não bate** com a aba de origem (Aprovações/Conformidade) — sinal de
  estado duplicado; o estado é do shell do módulo, não da aba
- ⚠️ Aparecer **exceção sem autor** — exceção anônima é o mesmo que não ter regra
- ⚠️ Aparecer **mock/`rand()`** na pré-checagem: todo número tem de derivar da apuração

---

## Dependências de decisão ([W])

1. **Onde vive o estado da competência** — tabela própria ou derivado do estado das apurações do mês?
   (o protótipo usa localStorage, que é cache de tela, não verdade)
2. **Permissão** — `ponto.fechamento.manage` nova, ou reusa `ponto.configuracoes.manage`?
3. **Exceções assinadas** — persistência e se bloqueiam a geração do AFD
4. **Reabrir competência fechada** — existe (com auditoria) ou é definitivo?
