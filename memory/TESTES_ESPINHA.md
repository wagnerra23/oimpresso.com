# TESTES_ESPINHA.md — Auto-auditoria da memória/espinha [CC]

> Espelha o espírito de `jana:health-check` (PROTOCOL §6). Rode quando quiser saber se a
> espinha está sã. [CC] roda T1–T6/T9 sozinho; T7 é Wagner; T3/T8 o Code valida no curl.
> Última execução: 2026-05-30 · [CC].

## Como rodar (rápido)
- **T1/T2/T4:** `grep` nos arquivos da espinha (instruções por teste abaixo).
- **T6:** abrir cada HTML → `done` → verifier (console limpo).
- **T7:** abrir chat novo e colar a pergunta-teste (§ no fim).
- **T3/T8:** o Code reporta no `curl`/reconciliação ao processar a ponte.

## Suíte + último resultado

| # | Teste | Como verifica | Resultado 2026-05-30 |
|---|-------|---------------|----------------------|
| **T1** | Sem referência órfã (deletei CONSTITUICAO) | `grep CONSTITUI` → só ADR 0201/LICOES (registro de remoção) | ✅ PASS |
| **T2** | Proposta ≠ firme (STATUS/Painel) | `grep "firme"` → só D-01 (princípio); D-02/03/04 = proposta | ✅ PASS |
| **T3** | Ponte válida (URLs servem) | Code roda `curl -L` → HTTP 200 | ⏳ valida no Code |
| **T4** | Lições rastreáveis | cada `L-0N` tem Erro·Sintoma·Regra·Ref | ✅ PASS |
| **T5** | Índice temático cobre + marca gap | T1–T9 + "gap 0042–0189 pro Code" presente | ✅ PASS |
| **T6** | HTMLs sem erro de console | `done` + verifier por arquivo | ✅ PASS |
| **T7** | Chat novo lê a espinha | pergunta-teste (§ abaixo) responde citando arquivos | ⏳ Wagner roda |
| **T8** | ADR monotônico sem colisão | Code: `ls memory/decisions \| sort \| tail -1` | ⏳ valida no Code |
| **T9** | CARTA ancorada na constituição real | cita ADR 0094 + UI-0013 (não inventa) | ✅ PASS (evoluído hoje) |

## Pergunta-teste do T7 (colar em chat novo, neste projeto)
> "Sem eu explicar nada: leia sua espinha e me diga — (1) qual é a sua constituição e o que
> ela proíbe sobre paleta, (2) o estado atual da tela Vendas e o próximo passo, (3) um erro
> que você não pode repetir, com o L-ID."

**Passa se** a resposta citar `CARTA_DESIGN_CC.md` (subordinada a ADR 0094/UI-0013) + "não
inventar paleta", Vendas 9.5/piloto, e um L-ID (ex.: L-02). **Falha se** vier genérico.

## Quando evoluir a suíte
- Achou referência órfã nova → vira T-novo.
- Toda lição nova (L-NN) entra no T4.
- Quando o Code criar `jana:health-check` design (T1/T2/T7 automatizados) → migra pra lá.
