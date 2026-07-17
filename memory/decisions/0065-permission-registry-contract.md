---
slug: 0065-permission-registry-contract
number: 65
title: "Permission Registry — contrato declarativo de permissions per-módulo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-04"
module: null
quarter: 2026-Q2
tags: [permission, iam, governance, modularização, superadmin, registry, auto-discovery]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0064-modularizacao-split-teammcp-kb-superadmin360, 0027-gestao-memoria-roles-claros, 0053-mcp-server-governanca-como-produto]
pii: false
review_triggers:
  - "Quando um módulo precisar permissions com lifecycle (expira, supersede) → adicionar campos status/expires_at no contrato"
  - "Quando o Spatie permission name divergir do Registry key em mais de 5 módulos → ADR de rename consolidado com migration"
---

# ADR 0065 — Permission Registry — contrato declarativo de permissions per-módulo

## Contexto

Wagner relatou dor histórica de **falta de visibilidade IAM**: "funcionários roubaram recursos da empresa porque não conseguia ver o todo". Permissions hoje vivem espalhadas:

- Spatie (`users_permissions`, `roles`) — nome técnico opaco, sem nível de risco
- Scopes ADS (`mcp_user_module_access`) — per-user × module
- Tokens MCP (`mcp_tokens`) — quota + revogação
- Quotas Copiloto (`mcp_quotas`) — consumo IA mensal
- Hardcoded em controllers/middleware

Pra cada módulo novo (NFSe, NfeBrasil, RecurringBilling, KB, TeamMcp), permissions são cadastradas via seeder próprio com nomes inconsistentes — sem indicação de risco operacional, sem agrupamento visual, sem auto-descoberta.

A tela Usuário 360° (ADR 0064) precisava de **fonte agregadora** das permissions de cada módulo pra mostrar num lugar só. Sem padrão, viraria scan ad-hoc por seeder de cada módulo.

## Decisão

Criado **service utilitário global** `app/Services/PermissionRegistry.php` (não-módulo, fica em `app/Services/` neutro) que faz **auto-discovery** de arquivos `Modules/*/Resources/permissions.php` declarados pelos módulos.

Contrato do `permissions.php`:

```php
<?php
return [
    'group' => 'NFSe',                       // label do grupo na tela 360°
    'icon'  => 'file-invoice',               // lucide-react icon name
    'permissions' => [
        [
            'key'         => 'nfse.view',     // alinhar com Spatie permission name (idealmente)
            'label'       => 'Ver notas fiscais',
            'description' => 'Lista e detalhe de NFSe emitidas',
            'risk'        => 'low',           // low | medium | high | critical
            'requires'    => [],              // dependências ('nfse.view' antes de 'nfse.emit')
        ],
        [
            'key'         => 'nfse.emit',
            'label'       => 'Emitir nota fiscal',
            'risk'        => 'high',
            'requires'    => ['nfse.view'],
        ],
        [
            'key'         => 'nfse.cancel',
            'label'       => 'Cancelar nota',
            'risk'        => 'critical',
        ],
    ],
];
```

API do service:

- `discover(): Collection` — todos módulos com permissions declaradas (cache 5min via `Cache::remember`)
- `forUser(int $userId): array` — agrupado por módulo, com flag de quais permissions o user tem (cruzando com Spatie)
- `forUserModule(int $userId, string $module): array` — drill-down de um módulo

Risk visual na tela 360°:

| Nível | Cor | Exemplo |
|---|---|---|
| `low` | cinza | Ver/listar |
| `medium` | amarelo | Editar/criar |
| `high` | laranja | Emitir NFSe / publicar / sharing |
| `critical` | vermelho | Cancelar/deletar/superadmin |

Dois pilotos foram entregues no PR `feat/usuario-360` (mergeado 2026-05-04):

- `Modules/NFSe/Resources/permissions.php` — 4 permissions
- `Modules/Copiloto/Resources/permissions.php` — 19 permissions
- `Modules/KB/Resources/permissions.php` — 4 permissions (declarado mas Spatie real é `copiloto.mcp.memory.manage` — divergência aceita como dívida técnica)

## Justificativa

**Por que service utilitário global, não dentro do Superadmin?** Permission Registry serve qualquer consumidor (tela 360°, possível futura Permission Matrix, audits programáticos, validações inline). Móvel mais que telas Superadmin.

**Por que arquivo PHP retornando array, não atributos PHP 8 ou interface?** Pragmatismo:

- Array PHP é zero-dependency, zero-runtime-cost, autoloader-free — basta `require`
- Auto-discovery via `glob('Modules/*/Resources/permissions.php')` é determinístico
- Versionável trivialmente (git diff legível)
- Permite metadados não-Spatie (icon, risk, group, requires) sem inflar tabelas

**Por que declarativo separado de Spatie real?** Spatie permission name é binding técnico. Registry adiciona **camada semântica** (risk, label PT-BR, icon). As 2 fontes podem divergir (caso KB hoje) — Registry alinha visualmente quando Spatie estiver pronto pra rename.

**Por que cache 5min?** Auto-discovery faz IO em N módulos. 5min equilibra fresh-enough × custo. Wagner pode forçar via `php artisan cache:clear` ou `app(PermissionRegistry::class)->flush()`.

**Por que NÃO criar permission Spatie automaticamente?** Spatie sync requer migration + business logic (per-business). Registry é **só leitura agregada**. Criar permission é decisão explícita do módulo via seeder, mantida fora do Registry.

## Consequências

**Positivas:**

- Módulo novo declara 1 arquivo `permissions.php` e ganha agregação na tela 360° de graça
- Dor histórica do Wagner (falta de visibilidade IAM) endereçada com 1 fonte unificada
- Suporta evolução futura (lifecycle, expiry) só estendendo o array sem migration
- Cache desligável trivial pra debug (`flush()`)

**Negativas / Trade-offs:**

- Risk classification (`low/medium/high/critical`) é subjetivo — varia por módulo. Padrão a refinar quando 5+ módulos pilotarem
- Divergência Spatie name × Registry key (caso KB) cria 2 fontes de verdade até rename consolidado
- Auto-discovery via filesystem pode ficar lento em projetos com 50+ módulos (não é o caso hoje — 26 módulos)
- Sem validação automática de `requires` (depender de permission inexistente não bloqueia)

**Riscos mitigados:**

- 360° não vira tela frágil dependente de cada módulo cadastrar permissions com nome consistente — Registry agrega o que existe e mostra "Sem registry" pros módulos que não declararam
- Cada permission tem `risk` documentado — Wagner enxerga visualmente o que é crítico (vermelho) antes de conceder
- `requires` documenta dependência implícita pra ferramentas futuras de validação

## Referências

- ADR 0064 — Modularização (estabelece TeamMcp/KB/Superadmin 360° contexto onde Registry mora)
- ADR 0027 — Gestão memória, papéis claros
- PR `feat/usuario-360` (merged 2026-05-04 commit 980d218f) — implementação inicial + 2 pilotos
- `app/Services/PermissionRegistry.php` — implementação canônica
- `resources/js/Pages/superadmin/Usuario360/Show.tsx` — consumidor primário
- Caminho dos pilotos: `Modules/NFSe/Resources/permissions.php`, `Modules/Copiloto/Resources/permissions.php`, `Modules/KB/Resources/permissions.php`
