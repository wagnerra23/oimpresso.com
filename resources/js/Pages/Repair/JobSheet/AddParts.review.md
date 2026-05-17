---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/JobSheet/AddParts
file: resources/js/Pages/Repair/JobSheet/AddParts.tsx
charter_present: true
charter_file: AddParts.charter.md
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-jobsheet-add-parts.md
append_only: true
---

# Review estática — `Repair/JobSheet/AddParts.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader shared ✓ · charter+RUNBOOK ✓ (compliance MWART completo)
- `useForm` (Inertia) ✓ · `useState` pra `parts` editável ✓
- Sem `Deferred` (form pequeno — OK não deferir)
- `status_dropdown`, `status_template_tags`, `status_update_data` props — moderado tamanho
- Notas SMS/email opt-in (`send_sms`, `send_email`) — risco LGPD se default true

## Riscos Tier 0

1. **LGPD/M2 — SMS/email opt-in sem checagem visível `Contact::canReceive*Notification()`**: validar que checkbox default=false E backend respeita ([proibicoes.md FSM § "Mail::raw"/WhatsApp]).
2. **VALIDATION/L3 — Sem validação `quantity > 0` aparente**: estado inicial `quantity: 1` mas usuário pode digitar 0/negativo. Backend deve validar mas UX feedback ausente.
3. **CHARTER/L4 — Confirmar charter atualizado**: validar drift no round 2.
4. **PARTS/L3 — `variation_id: null` inicial**: form deixa adicionar peça sem variation_id selecionado — submit pode falhar silenciosamente.
5. **ICONS/L4 — Mix lucide-react direto sem `<Icon>` helper canonical** (mesma incoerência módulo).

## Top 5 recomendações

1. P0 — Validar LGPD opt-in SMS/email (Pest GUARD `Contact::canReceiveEmailNotification` respeitado).
2. P1 — Client-side validation: `quantity > 0` + `variation_id` obrigatório antes de habilitar Save.
3. P2 — Toast/inline error se backend rejeitar (UX).
4. P2 — Padronizar `<Icon>` helper.
5. P3 — Validar charter drift round 2.
