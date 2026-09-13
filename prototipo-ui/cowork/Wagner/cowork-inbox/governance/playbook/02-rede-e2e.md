---
sessao: "02"
titulo: Rede — 2 specs E2E de governança (dashboard + toggle de política)
dono: "[CL]"
base: 0d159eb84a10
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: e2e/governance-dashboard.spec.ts (CRIAR) · e2e/governance-policies.spec.ts (CRIAR)
nao_toca: Pages/governance/** · Modules/Governance/** · os 57 Feature tests (oráculo)
depende: — (vaga 1)
antes:  e2e/ tem 17 specs e nenhum de governança
depois: 2 specs verdes 3× seguidas
---
# 02 · Rede E2E

## ÂNCORA (recorte — não abrir tela inteira)
```
ler      Policies.charter.md      3.675 B   (a tela toda tem 4.889 B — pode ler inteira)
ler      Dashboard.charter.md     8.118 B   ← o charter, NÃO o Dashboard.tsx
NÃO ler  Dashboard.tsx (42.343 B) · ModuleGradeService.php (91.289 B) ·
         ScopedScorecardEvaluator.php (31.015 B)
oráculo  GovernanceRotasCanGateTest · CrossTenantPolicyTest · InertiaDeferAuditTest
```

## A · Casos a provar
| # | QUANDO → O SISTEMA DEVE | por que importa |
|---|---|---|
| 1 | `GET /governance` → **redirecionar 302 pra `/ia`** | é canon [W] 2026-05-22, e é a primeira coisa que quebra numa refatoração de rota |
| 2 | `GET /governance/dashboard` → renderizar `governance/Dashboard` | o painel legado tem endereço próprio |
| 3 | props `<Deferred>` → **não consumir `mcp` fora do Deferred** | incidente real de 2026-05-25: `TypeError undefined.find` em produção. `InertiaDeferAuditTest` é o oráculo |
| 4 | `compliancePct` → aparecer rotulado **"auto-declarado"** + régua (7 plenos · 2 parciais · 1 pendente) | é soma literal do controller; a tela **não pode** apresentá-lo como apurado |
| 5 | alternar política → **sem modal de confirmação** (ação reversível; atrito é proibido pelo charter) e o aviso "Alternar não deixa rastro" visível | `mcp_governance_rule_history` não existe |
| 6 | toggle → respeitar `throttle:10,1` | operação sensível: afeta enforcement em runtime |
| 7 | usuário **sem** `governance.policies.edit` → toggle barrado | ⚠️ ver PARAR SE (c): admin de business passa pelo `Gate::before` |

## B · Não inventar
- **Reusar** `e2e/global-setup.ts` e o formato dos specs vivos mais próximos.
- **As tabelas `mcp_*` são cross-tenant POR DESIGN** — exceção formal ao Tier 0 (Constituição Art. 6+8, `CrossTenantPolicyTest`). Um spec que "prove isolamento por empresa" aqui está **errado**, e provaria o contrário do desenho.
- Nenhuma escrita em `mcp_audit_log`: é **append-only** (ADR 0084). O spec lê, nunca semeia por UPDATE.
- Zero `waitForTimeout` como sincronismo.

## Execução
```
PASSO   1) gh pr list --state open × e2e/ e workflows
        2) governance-dashboard.spec.ts — casos 1..4
        3) governance-policies.spec.ts — casos 5..7
        4) rodar 3× (flake é reprovação) · placar no PR · _saida-02.md
PARAR SE (a) o caso 3 exigir mexer no Dashboard.tsx → PARE: o spec observa, não conserta
         (b) semear política de teste exigir tocar mcp_audit_log → use o caminho
             que os 57 Feature tests já usam; não invente factory
         (c) o caso 7 passar com admin de business: NÃO é bug do seu spec — é a
             D-GATE (Gate::before, ADR 0392 passo 1, decisão [W] aberta).
             Registre no _saida e deixe o caso marcado como conhecido
```

## Checklist de saída
1. os 2 arquivos · 2. caso 1 (302 pra `/ia`) verde · 3. caso 4 (auto-declarado) verde · 4. caso 5 sem modal · 5. 3 execuções sem flake · 6. veredito do caso 7 (passou? é D-GATE) · 7. placar no PR
