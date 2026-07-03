---
casos: Retorno CNAB · /settings/payment-gateways/{id}/cnab-retorno
irmaos: CnabRetorno.charter.md (lei · draft)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Retorno CNAB

> ⛔ Tela Tier-0 **DINHEIRO** — o upload de retorno CNAB **reconcilia cobranças** (marca `paga`/`cancelada`/`vencida` + dispara baixa no Financeiro via `CnabRetornoProcessor`). Persona: **Wagner/operador financeiro**.
> Passo 3 do [template-onda-modulo] — complementa a [CAPTERRA-FICHA](../../../../../memory/requisitos/PaymentGateway/CAPTERRA-FICHA.md) (nota 67).
>
> **Status:** ✅ passa (UC-id no manifesto) · 🧪 comportamento tem teste Feature sem UC-id (débito G-2) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Baseline de valor forte, rastreabilidade zero.** O motor `CnabRetornoProcessor` já é bem coberto (8 casos Feature/MySQL verdes no CT100), incluindo idempotência e Tier-0 scope. O débito é a G-2 ([ADR 0264]): nenhum teste cita `UC-PG-NN`. Promoção = 1 linha por caso.
>
> 🔴 **Dente de cálculo (D1 · REGRA MESTRE valor):** o upload move VALOR (quita títulos) **sem preview antes→depois** — processa em background direto. É a exposição Tier-0 nº 1 desta tela (ver gap no scorecard). Um UC de "dry-run/preview do impacto antes de aplicar" é candidato P0.

---

## Backlog de casos (sem id — entram quando um teste citar o UC-id)

> Regra G-2: UC em heading `## UC-*` sem teste que o cite = órfão → quebra `casos-gate`. Bullets até o id ser wired.

- **[BACKLOG · 🧪 tem teste · 💰 valor] Retorno com ocorrência 06 quita título** — Dado arquivo CNAB com liquidação (06) · Quando processo · Então dispara `CobrancaPaga` pros títulos liquidados + incrementa `qtd_paga`. _Coberto por `CnabRetornoProcessorTest::dispatcha CobrancaPaga (ocorrência 06)`._
- **[BACKLOG · 🧪 tem teste · 💰 valor] Baixa sem pagamento (09) cancela** — dispara `CobrancaCancelada`. _Coberto por `...::dispatcha CobrancaCancelada (ocorrência 09)`._
- **[BACKLOG · 🧪 tem teste · 💰 valor] Entrada (02) com vencimento passado marca vencida** — dispara `CobrancaVencida`. _Coberto por `...::dispatcha CobrancaVencida`._
- **[BACKLOG · 🧪 tem teste · 💰 idempotência] Reprocessar não duplica baixa** — Dado `paga_em` já setado · Quando reprocesso · Então NÃO dispara `CobrancaPaga` de novo. _Coberto por `...::é idempotente quando paga_em já setado`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Não atualiza cobrança de outro business** — [ADR 0093]. _Coberto por `...::respeita business_id global scope`._
- **[BACKLOG · 🧪 tem teste] Grava `CnabRetornoUpload` com métricas** — qtd_paga/cancelada/vencida/registrada após processar. _Coberto por `...::grava CnabRetornoUpload com métricas`._
- **[BACKLOG · 🧪 tem teste] Arquivo inexistente não quebra** — degrada com erro, não 500. _Coberto por `...::não quebra quando arquivo de retorno não existe`._
- **[BACKLOG · ⬜ sem teste · UI] Validação de extensão/tamanho no front espelha o `validate()` do Controller** — G2 do charter; sem teste E2E ainda.
- **[BACKLOG · ⬜ sem teste · 🔴 P0 valor] Preview antes→depois antes de aplicar** — Dado arquivo carregado · Quando confirmo · Então vejo N títulos e valores que serão baixados ANTES de commitar (REGRA MESTRE). Hoje inexistente — gap de exposição Tier-0.
- **[BACKLOG · charter draft] Non-Goals a resolver com Wagner** — reprocessamento (NG1), download do arquivo/relatório (NG2), upload em lote (NG3), AuditLog por processamento. Charter ainda `draft` — resolver antes de fechar a catraca.

## Como rodar a suíte
1. **Pest (MySQL real):** lane do PaymentGateway no CT100 ([ADR 0062]) — `CnabRetornoProcessorTest` verde (8 casos).
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 (régua por tela). Baseline de valor forte (8 casos Feature) mas 0 UC-id; charter ainda `draft` com Non-Goals abertos; falta preview antes→depois (D1 valor). Nota UX 76 (Advanced).

[template-onda-modulo]: ../../../../../memory/requisitos/_Governanca/programa-ondas/template-onda-modulo.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
