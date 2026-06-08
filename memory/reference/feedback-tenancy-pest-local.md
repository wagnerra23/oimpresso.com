---
name: Mudanças tenancy exigem Pest local antes de PR
description: Wagner não autoriza mudanças scope/Controller/Model multi-tenant baseadas em análise estática — exige Pest verde rodado localmente pelo dev
type: feedback
---
**Regra:** qualquer mudança em código que toque tenancy (`HasBusinessScope`, `ScopeByBusiness`, `business_id` em Controller/Model/Migration, queries que dependem de `session('user.business_id')`) **só** pode ser proposta em PR depois de Pest verde rodado localmente. Análise estática contra padrão canônico não é suficiente, mesmo quando o change é claramente defensivo (ex: remover input do client + injetar via session).

**Why:** Wagner declarou em 2026-05-09 que "vazar dados é o erro maior que posso cometer". Sessão da vistoria Jana teve 2 sinais de fragilidade que justificaram o conservadorismo:
1. Minha primeira análise levantou ALERT Tier 0 falso (Meta já tinha `HasBusinessScope`; eu não verifiquei)
2. Worktree não tinha vendor; tentativa de junction falhou (vendor da main repo também vazio); commit foi feito sem Pest verde

Mesmo o change real (`store()` parando de aceitar `business_id` do client) sendo *defensivo*, Wagner pediu reverter — preferência por zero-risk sobre tenancy mesmo quando a mudança reduz risco.

**How to apply:**
- Antes de Edit em `Modules/*/Http/Controllers/*Controller.php`, `Modules/*/Entities/*.php`, ou migration que toca `business_id`: confirmar com Wagner se vai rodar Pest local
- Se worktree não tem `vendor/`: pedir Wagner pra rodar `composer install` na main repo OU operar a partir da main repo direto (sem worktree)
- Se Pest verde local não é factível na sessão: parar antes do commit, entregar PLAN + Pest file pendente em PR de docs separada, esperar Wagner rodar Pest manualmente
- Memória boa pra cross-reference: cliente-rotalivre.md (biz=4 = 99% volume — vazamento devastaria operação)
- ADRs canon: 0093 (multi-tenant Tier 0), 0094 (Constituição v2 princípio duro #6)
