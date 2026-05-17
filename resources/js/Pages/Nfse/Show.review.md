---
review_round: W31-R1
tela: /nfse/:id
component: resources/js/Pages/NFSe/Show.tsx
charter: AUSENTE
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NFSe
status: live
loc: 307
---

# Review estático — NFSe/Show

## Cabeçalho
- US: US-NFSE-006
- Permissões: `nfse.view` + `nfse.cancel`

## Pontos fortes
- Layout 2 colunas com aside "Venda vinculada" + botão "Abrir venda →" (CTA crossnav)
- `AlertDialog` shadcn pra confirmar cancelamento — fluxo correto pra ação IRREVERSÍVEL
- Validação client `motivo.length >= 15` (alinhada com SEFAZ 15 chars min)
- Counter `{cancelMotivo.length}/255`
- StatusBadge + spinner `RefreshCw` quando `status===processando`
- `AlertTriangle` + erro_mensagem destacado quando `status===erro`
- Botão "Nova emissão" só aparece quando `status===erro` (correto — rascunho não tem rota separada)
- `InfoRow` helper bem encapsulado, dl semântica

## Riscos / gaps
1. **CHARTER AUSENTE** — P1
2. `useForm({ motivo: '' })` declara hook mas o submit usa `router.post(..., { motivo: cancelMotivo })` direto — `cancelPost` nunca chamado. **Dead code** + erros validação backend não vão pra `errors` Inertia. P1
3. Não trata estado `cancelando` — flag `canceling` vem do `useForm` morto (sempre false). Botão nunca mostra "Cancelando…". P1
4. Sem polling/auto-refresh quando `status===processando` (texto diz "atualize a página em instantes") — UX poderia ter `setInterval router.reload({ only: ['nfse'] })`. P2
5. PDF abre em `window.open` (nova aba) — fine, mas sem detecção de bloqueio popup. P3
6. Aside venda sem aria-label semântico (`<aside>` raw com div interno). P3
7. Sem botão "Reenviar email cliente" se `tomador_email` existir. P2 (feature gap)

## Multi-tenant
- `nfse` resolvido por route model binding (backend deve usar global scope `business_id`). Cross-tenant 404 esperado mas não verificável via Pest aqui.

## Recomendação
1. Criar charter (P1)
2. **Refatorar cancelar** pra usar `useForm` corretamente: `cancelPost(`/nfse/${nfse.id}/cancelar`)` com `motivo` no data inicial OU remover `useForm` morto e tipar `router.post` (P1)
3. Adicionar polling 5s em `processando` (P2)
