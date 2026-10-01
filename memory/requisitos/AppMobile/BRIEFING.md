---
module: AppMobile
status: em-construcao
status_nota: "Base do shell /m entregue (PR #8465); telas de negócio ainda por fazer."
updated_at: "2026-10-01"
distilled_at: "2026-10-01"
distilled_by: "manual [CL] — porta criada junto com a base /m (PR #8465), a partir do RUNBOOK-shell-mobile e do MAPA-DE-DADOS-v1 (#8462)."
owner: W
---

# BRIEFING — App das lojas (`/m`)

## O que é

O app do oimpresso nas lojas (Google Play e App Store, Capacitor `com.oimpresso.app`) mostra as
telas do **protótipo Mobile** (handoff design-v3), servidas pelo próprio ERP como páginas
Inertia/React sob **`/m`**. Decisão [W] 2026-10-01: *"não gostei, foi pego o site e emulado. eu
quero o Mobile mesmo"* → escolha "Telas no ERP (/m)". O app abre `https://oimpresso.com/m`.

Reaproveita sessão, permissões Spatie, `business_id` e os Services do ERP — sem API JSON nova; trocar
tela não exige reenvio às lojas.

## Estado

| Peça | Estado |
|---|---|
| Tokens do protótipo (`resources/css/cowork-mobile-bundle.css`) | ✅ no `main` (#8463) |
| Shell `/m` — `MobileShell`, tab bar 5 abas, tema do aparelho, safe-area | 🟡 PR #8465 |
| Início · Mais (mínimos) · marcador "Em construção" (Tarefas/Pedidos/Produção) | 🟡 PR #8465 |
| Telas v1: Início completo, Tarefas, Pedidos (+ detalhe com etapas), Produção, Pessoas, Ponto | ⬜ sessões de tela |
| v2: Produtos, Venda rápida, Finanças | ⬜ backlog |

## Onde ler

- **Como montar uma tela `/m`:** [RUNBOOK-shell-mobile.md](RUNBOOK-shell-mobile.md).
- **De onde vem cada dado de cada tela:** [MAPA-DE-DADOS-v1.md](MAPA-DE-DADOS-v1.md).
- **Encaixe do Ponto (pedido ao Design):**
  `prototipo-ui/cowork/Wagner/cowork-inbox/app-lojas/playbook/01-mobile-com-ponto.md`.

## Regras que não mudam

- Tier 0: todo dado vem do business do usuário autenticado (ADR 0093); teste cross-tenant no
  tenant fictício 98 (ADR 0358).
- Ponto sem câmera/biometria (ADR 0383).
- Valor/estoque nas telas mobile seguem a regra mestre (dupla prova + antes→depois + aprovação [W]).
