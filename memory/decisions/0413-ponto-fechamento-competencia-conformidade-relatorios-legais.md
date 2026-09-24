---
slug: 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
number: 413
title: "Ponto — fechamento da competência, painel de Conformidade e relatórios legais (D0–D4 + W1/W3/W7)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-24"
module: pontowr2
tags: [ponto, portaria-671, fechamento, conformidade, afd, aej, append-only]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

# ADR 0413 — Ponto: fechamento da competência, Conformidade e relatórios legais

## Contexto

As threads 04 (Fechamento), 05 (Conformidade) e 12 (AFD/AFDT/AEJ) do playbook Ponto
(`prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/`) estavam bloqueadas por decisões de
[W] (W1–W4, W7). Abrir essas telas sem as decisões seria **inventar lei**: fechar uma competência,
recusar marcação e gerar arquivo fiscal têm consequência na fiscalização (Portaria MTP 671/2021).

Parte já tinha sido decidida em **2026-09-14** (D0–D4, [W]: *"sim aceito as 5"*), registrada na
proposal `ponto-contratos-retidos` — que ficou `status: open` no repo porque o flip nunca foi
aplicado. O restante (W1, W3, W7) foi decidido por [W] em **2026-09-24**. Esta ADR consolida as
oito respostas num lugar só.

## Decisão

| id | decisão | data |
|---|---|---|
| **D0** | Cria a rota `/ponto/conformidade`, **somente leitura**. Não depende do fechamento nem das outras decisões. | 2026-09-14 |
| **D1** | Fechar competência exige permissão própria **`ponto.fechar`** (não `ponto.access`). **"Reabrir" não existe na v1**: depois de fechada, correção só por anulação com trilha. (responde W2 e W4) | 2026-09-14 |
| **D2** | O termo "exceção assinada" deixa de existir. O fechamento registra **quem consolidou, quando e quais bloqueios aceitou**. Assinatura digital (ICP) do ato fica fora; se for necessária, exige ADR nova. | 2026-09-14 |
| **D3** | "Recusar" marcação = **marcação nova** com `ORIGEM_ANULACAO` (`Marcacao::anular()`), apontando a original, com autor e motivo. **Nunca UPDATE/DELETE** em `ponto_marcacoes`, que é append-only por força da Portaria MTP 671/2021. | 2026-09-14 |
| **D4** | AFD/AEJ **ficam fora** do fluxo de fechamento. A geração fica na tela de Relatórios. | 2026-09-14 |
| **W1** | O estado da competência fica numa **tabela nova `ponto_competencias`** (`business_id` indexado + FK, mês de referência, `fechada_por`, `fechada_em`, bloqueios aceitos), com unicidade `(business_id, mês)`. A linha é **gravada uma vez**, sem UPDATE/DELETE, o que é coerente com D1. Derivar das apurações foi descartado porque não guarda quem fechou nem quando, e a D2 exige isso. | 2026-09-24 |
| **W3** | Os bloqueios aceitos ficam **na própria linha da competência**. Eles **não bloqueiam** o AFD: o AFD é o arquivo bruto das marcações, entregue à fiscalização, e deve poder ser gerado a qualquer momento, independentemente do fechamento. | 2026-09-24 |
| **W7** | Ordem dos relatórios legais: **AFD → AEJ**, 1 por PR, gerados de `ponto_marcacoes` (nunca da apuração). O **AFDT sai do catálogo de exportação**, porque é formato da Portaria 1510/2009, que a 671/2021 substituiu pelo AEJ. A **importação** de AFDT legado continua (`ORIGEM_AFDT`, `ImportAfdCommand`). | 2026-09-24 |

## Consequências

- **Thread 05 (Conformidade) destravada por D0.** A dependência "04 + W1" do índice do playbook
  foi superada: a Conformidade só lê.
- **Thread 04 (Fechamento) destravada**: W1–W4 respondidas. Ordem: PR de migration + guard
  append-only da `ponto_competencias`, sem UI; depois a tela.
- **Thread 12 destravada**: AFD primeiro. As chaves sem destino continuam com `abort(501)` até a
  vez delas (**501 nunca é sucesso**). A chave `afdt` sai do catálogo no PR do AFD.
- Os contratos `ponto-fechamento` e `ponto-rep-p` só entram **no mesmo PR da tela**. Contrato cujo
  `alvo` não existe fica vermelho de forma permanente (motivo da proposal de 21/08).
- A marcação no `00-INDICE.md` é do Cowork: vai pelo canal `cowork-inbox/` (DesignSync). O
  espelho no repo não é editado.

## Como se reconhece violação

- Rota, botão ou método de **reabrir** competência sem ADR nova.
- `UPDATE`/`DELETE` em `ponto_competencias` ou em `ponto_marcacoes`.
- Geração de AFD condicionada ao fechamento da competência.
- Geração de AFD/AEJ lendo `ponto_apuracao_dia` em vez de `ponto_marcacoes`.
- Qualquer escrita a partir de `/ponto/conformidade`.
