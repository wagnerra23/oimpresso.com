---
review_round: W31-R1
tela: /nfe-brasil/tributacao/import
component: resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.tsx
charter: AUSENTE
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 264
---

# Review estático — NfeBrasil/Tributacao/ImportCsv

## Cabeçalho
- US: US-NFE-010 fase 3 (Import CSV em massa)

## Pontos fortes
- Two-step UX: Upload → Pré-visualização → Aplicar (não submete cego)
- Idempotente declarado: "regras existentes são atualizadas pela chave (NCM + UF origem + UF destino)"
- Preview tabela com sticky header + amostras + count válidas/erros segregado
- `<details>` colapsível pros erros com motivo por linha (mostra `linha + motivo`)
- Cabeçalho esperado documentado inline com exemplo concreto: `49019900,SP,,5102,102,,0,0.0065,0.03,0`
- Hints: `uf_destino` vazio = "todas", `csosn` OU `cst` exclusivo, alíquotas decimal
- `confirm()` antes de aplicar com contador de regras
- Aceita `.csv,.txt`

## Riscos / gaps
1. **CHARTER AUSENTE** — P1
2. `flash.preview` no Inertia flash — preview é stateful via session, perde no F5. User refresh = volta pro upload. P1 STATE LOSS
3. `confirm()` nativo (não AlertDialog) — fluxo destrutivo em produção. Aplicar 1000+ regras = grande mudança. P1
4. Sem rollback declarado — se metade aplicar e quebrar transação, estado inconsistente. Backend precisa garantir all-or-nothing. Frontend não comunica. P1
5. Max 5MB hint mas sem validação client antes do submit. P2
6. Amostra exibe primeiras N linhas mas não diz **quais** ficam de fora — se total_validas > amostras.length, user não vê tudo. P2
7. Sem botão "Download template CSV" — user tem que construir cabeçalho manual. P2 UX
8. Sem progresso real do aplicar (se 5000 regras, demora; sem progress bar). P2
9. `accept=".csv,.txt"` aceita `.txt` mas exemplo é CSV — confunde. P3
10. Idempotência: se user remove linhas do CSV e re-aplica, regras antigas NÃO são deletadas (só upsert). Não há aviso. P1 SEMANTICS

## Multi-tenant
- POST scoped backend. Sem cross-tenant visível.

## Recomendação
1. Persistir preview em DB (não session flash) (P1)
2. Trocar `confirm()` por AlertDialog com aviso "regras removidas do CSV NÃO são deletadas" (P1)
3. Adicionar download de template CSV (P2)
4. Criar charter (P1)
