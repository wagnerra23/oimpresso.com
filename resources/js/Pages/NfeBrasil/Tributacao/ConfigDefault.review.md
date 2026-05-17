---
review_round: W31-R1
tela: /nfe-brasil/tributacao/config-default
component: resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.tsx
charter: PRESENTE (ConfigDefault.charter.md)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 285
---

# Review estático — NfeBrasil/Tributacao/ConfigDefault

## Cabeçalho
- US: US-NFE-010 fase 2 (Form Nivel 4 — defaults business)
- Charter presente (não lido — assumido)

## Pontos fortes
- Wizard "Aplicar pelo regime" pré-popula CSOSN/CST + alíquotas via `REGIME_DEFAULTS` map — UX onboarding
- Hint inline em cada regime (mei/simples/lucro_presumido/lucro_real)
- Toggle CSOSN/CST mutuamente exclusivo (limpa o outro no submit)
- Cards segregados: Regime → Defaults fiscais → Alíquotas
- NCM input com `replace(/\D/g, '').slice(0, 8)` — máscara live ok
- Alíquotas em decimal (0.18 = 18%) com hint claro
- `step="0.0001"` permite 0.0065 (PIS Lucro Presumido)
- Defaults conservadores em comentário sinalizam "ICMS 18% SP — outros estados configurar em Nível 2/3"

## Riscos / gaps
1. `form.setData('cst', '')` no submit — Inertia `setData` é async (queue) e logo abaixo `form.post()`; pode submeter com valor antigo. P1 RACE
2. Sem confirm dialog em "Aplicar pelo regime" se já há valores não-default — perde edição do user silenciosamente. P1
3. NCM `'00000000'` placeholder + accept submit — 8 zeros NÃO é NCM válido. Backend deve rejeitar mas client não previne. P2
4. CFOP `'5102'` hardcoded default — assume venda intra-estadual mercadoria. Para vendas inter ou serviço outro CFOP. Não há hint sobre isso. P2
5. Sem validação client `csosn` ∈ {101,102,103,201,202,300,400,500,900} — accept qualquer 3 digitos. P2
6. IPI campo opcional sem assinalar com `(opcional)` no Label (`*` ausente correto mas user não sabe se pode deixar 0). P3
7. `td.cfop_default ?? td.cfop` fallback dual — sintoma de migração de schema antiga. Documentar ou remover legado. P3
8. Sem botão "Restaurar últimos valores salvos" — user que mudou e quer cancelar precisa abandonar. P3

## Multi-tenant
- POST `/nfe-brasil/tributacao/config-default` deve scopear business_id no Controller. Não verificável aqui.

## Recomendação
1. Fix `form.setData` race antes do post — limpar via `form.transform()` ou separar payload (P1)
2. Confirm dialog antes de "Aplicar pelo regime" se form `isDirty` (P1)
3. Validar NCM ≠ 00000000 client-side (P2)
4. Validar CSOSN/CST contra set conhecido (P2)
