<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "bateria B1–B8").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 31 · Bateria de COMPORTAMENTO rodada (B1–B8) — os recibos das decisões de [W]

> **Origem:** [W] 2026-09-14 — *"os comportamentos e eventos já são conferidos? tem regra no protocolo?"*
> **Resposta honesta era NÃO.** A **§5** do protocolo declarava o contrato de comportamento como **tabela**, e nenhuma máquina o executava: **T1–T7** medem forma e diff, **A1–A12** medem a11y estática. Comportamento não tinha bateria — e foi exatamente por aí que o `D-ESC-DESTROY` passou quebrado.
> **O que mudou no protocolo:** nasceu o **§20 · BATERIA DE COMPORTAMENTO (B1–B8)**, e a **§5 ganhou 2 colunas obrigatórias**: `caso que exercita` (o estado do mock em que a linha roda) e `executada?` (✅ rodei o gatilho no protótipo servido e medi o efeito).
> **Condições:** protótipo servido, tema dark, T1 estável, após `__oiLazyDone`. Nenhum `localStorage` de [W] foi escrito nem apagado.

---

## Recibos — formato do §20

```
B Escalas · Remover (caso COM vínculo) · caso: 4 escalas com colaborador vinculado
  antes: 5 botões → depois: 4 disabled + motivo VISÍVEL em texto ("3 colaboradores vinculados — desvincule antes")
  reverso: n/a · ✅   [B1 ✅ B4 ✅]

B Escalas · Remover (caso SEM vínculo) · caso: EST-30 (escala criada no mock para o ramo existir)
  antes: 5 linhas → clique → Modal do DS com [Cancelar | Remover escala] → confirmar → 4 linhas, modal fecha
  reverso: Cancelar fecha sem remover · ✅   [B1 ✅ B2 ✅ B3 ✅ B5 ✅]

B Aprovações · checkbox do cabeçalho · caso: 2 pendentes na página, 0 desabilitados
  antes: 0 marcados · 0 barra de lote → depois: 2 marcados · 1 barra de lote
  reverso: clicar de novo → 0 marcados · 0 barra · ✅   [B2 ✅ B5 ✅ B7 ✅]

B Intercorrências · "Ver" na linha · caso: lista com registros, após D-INTERC-ACOES
  ações na linha: ["Ver"] — Editar/Submeter AUSENTES (o Non-Goal ratificado, provado por ação)
  antes: 1 overlay → depois: 2 overlays (o detalhe abriu) · ✅   [B2 ✅]

B REP-P · "Bater ponto" · caso: GPS ok, relógio ok, após remoção da biometria (ADR 0383)
  botão HABILITADO (antes travava em "Tire a selfie para registrar") · selfie na tela: 0 menções · ✅   [B1 ✅ B2 ✅]

B Colaboradores · filtro "Sem PIS cadastrado" · caso: 8 ativos, 2 sem PIS no mock
  antes: 8 linhas → com filtro: 2 linhas → reverso ("Ativos"): 8 linhas · CPF mascarado na lista ✅
  ✅   [B2 ✅ B5 ✅]

B Fechamento · estado consolidado · caso: competência aberta com bloqueios graves
  botões presentes: ["Consolidar aceitando os bloqueios", "Consolidar apuração", "Relatórios"]
  "Reabrir": AUSENTE (D1 [W]) · passo 4 = "AFD / AEJ — em Relatórios" (D4 [W]) · ✅   [B4 ✅]
  ⛔ NÃO EXECUTEI o clique de consolidar: ele grava em `localStorage` (`oimpresso.ponto.fechamento.<mes>`),
     que é estado de [W]. Medido por presença/ausência de controle, não por transição.
```

---

## Placar da bateria

| trava | resultado |
|---|---|
| **B1 · o caso existe** | ✅ nos 6 — e **1 caso teve de nascer** (`EST-30`, escala sem vínculo). Sem ele o ramo do Modal continuaria sem nunca rodar |
| **B2 · o gatilho roda** | ✅ 6 de 6 medidos por evento disparado, não por leitura de código |
| **B3 · assinatura lida** | ✅ `Modal({ open, onClose, title, children, footer, width })` — lida do bundle **depois** de eu ter inventado 4 props que ele ignorava em silêncio |
| **B4 · disabled comunica** | ✅ motivo virou **texto na célula** (tooltip em `disabled` é inalcançável: não emite hover, não recebe foco) |
| **B5 · o reverso roda** | ✅ 3 medidos (checkbox do lote · filtro de PIS · Cancelar do modal) |
| **B6 · persistência no reload** | ⛔ **não rodada** — a única chave em jogo é a do fechamento, e ela é estado de [W] |
| **B7 · efeito colateral** | ✅ 1 medido (marcar no cabeçalho **faz nascer** a barra de lote) |
| **B8 · evento aninhado** | ⛔ **não rodada** — a célula de ação de Intercorrências tem `stopPropagation` **declarado no fonte** e eu **não provei por clique**. Fica como dívida nomeada, não como ✅ |

**6 comportamentos provados · 2 travas não rodadas, ambas declaradas.** O que eu **não** medi não vira ✅ — foi essa confusão que produziu o defeito de origem.

---

## O que isto muda no pacote, daqui pra frente

1. **Toda decisão de [W] aplicada no build entra na bateria** (regra 6 do §20). Foi uma decisão ratificada que quebrou — não um detalhe de layout.
2. **A §5 de cada pedido passa a sair com `caso que exercita` e `executada?` preenchidos.** Linha sem `✅` conta como **declarada**, não entregue.
3. **`data.jsx` vira parte do pedido quando o caso não existe.** O mock deixou de ser "dado de exemplo" e virou **cobertura**: se a regra vale num estado que o mock não tem, o estado entra.

## PARAR SE

- alguém marcar uma linha da §5 como entregue sem `executada: ✅` ⇒ **recusar o pacote**;
- a bateria exigir escrever em `localStorage` de [W] ⇒ **medir por presença/ausência de controle** e declarar, como fiz no Fechamento;
- um clique de teste mudar estado que não volta sozinho ⇒ **restaurar ou não clicar** — protótipo não é ambiente de escrita.
