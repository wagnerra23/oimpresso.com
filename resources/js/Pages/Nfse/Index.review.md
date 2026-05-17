---
review_round: W31-R1
tela: /nfse
component: resources/js/Pages/NFSe/Index.tsx
charter: AUSENTE
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NFSe
status: live
loc: 361
---

# Review estático — NFSe/Index

## Cabeçalho
- US: US-NFSE-008
- ADRs satélite: tech/0001 service-adapter-dto, tech/0002 erros-nfse
- Permissão: `nfse.view`

## Pontos fortes
- Atalhos teclado canon: J/K navegar, N nova, / busca, Enter abrir — alinhado com Manifestação/Cockpit
- `localStorage` persistência filtros (`LS_KEY = 'oimpresso.nfse.filters'`)
- DataTable + PageFilters + EmptyState + StatusBadge — todos shared components canon
- Tooltip por ação (Eye/Download/Cancelar/Emitir rascunho) com `aria-label` — accessibility OK
- Status `erro` mostra `erro_mensagem` truncado + Tooltip completo
- Chips ativos com onRemove granular
- KBD hint visível no botão "Emitir NFSe" + footer ("Atalhos: ...")

## Riscos / gaps
1. **CHARTER AUSENTE** — P1. Tela live sem charter viola MWART F3 (ADR 0104).
2. Ação "Cancelar" navega pra Show com `data: { cancelar: true }` — semântica obscura; cancelamento de NFSe é IRREVERSÍVEL e exige motivo (Show tem AlertDialog). Aqui só abre tela. P2 (apenas confuso)
3. `setStatus('')` no reset não dispara reload imediato — chama `applyFilters()` mas usa estado antigo (closure). Bug latente. P1
4. `notas.total` no PageHeader — Controller precisa garantir paginação coerente; se total > página visível, pode confundir.
5. Sem `Inertia::defer` declarado (frontend) mas `notas` paginate é prop pesada — verificar Controller. P2
6. Atalho `Enter` em `focusedIdx` mas não há highlight visual da linha focada na DataTable — usuário não sabe qual abrir. P1 (UX broken)
7. Sem badge "rascunho/erro pendente" no PageHeader pra alertar fila travada. P2

## Multi-tenant
- `notas` vem scopado backend (assumido). Aside row sem cross-business_id leak visível.

## Recomendação
1. Criar charter (P1)
2. Fix focusedIdx visual highlight + reset filtros race (P1)
3. Confirmar `Inertia::defer` em `notas` no Controller (P2)
