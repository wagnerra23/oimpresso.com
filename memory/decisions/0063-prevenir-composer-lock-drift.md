---
slug: 0063-prevenir-composer-lock-drift
number: 63
title: "Prevenir composer.lock drift permanentemente"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-04-30
module: infra
quarter: 2026-Q2
tags: [composer, ci, drift, deploy, lock]
supersedes: []
superseded_by: []
related:
  - 0062-separacao-runtime-hostinger-ct100
  - 0053-mcp-server-governanca-como-produto
pii: false
review_triggers:
  - composer_split_implementado
  - hostinger_migrar_pra_dedicado
---

# ADR 0063 — Prevenir composer.lock drift permanentemente

## Contexto

`composer.json` foi editado em commits passados pra adicionar `laravel/mcp ^0.7.0` + `laravel/octane ^2.15` (decisão ADR 0053 + ADR 0058) sem rodar `composer update`. Resultado: `composer.lock` ficou fora de sincronia.

**Sintoma em produção:** `composer install` falha com:
```
Required package "laravel/mcp" is not present in the lock file.
Required package "laravel/octane" is not present in the lock file.
```
Quebra deploy Hostinger e CT 100.

**Sintoma em CI:** workflow `ci.yml` job "PHP / Pest (Unit)" + `adr-lint.yml` falham em `composer install --no-scripts` com exit code 4.

**Tentativa errada (2026-04-30):** Claude criou workflow `composer-lock-sync.yml` com comando `composer update --lock <packages>` — Composer rejeita: *"You cannot simultaneously update only a selection of packages and regenerate the lock file metadata."* Flags são mutuamente exclusivas.

## Decisão

Tripé permanente:

### 1. Workflow corrigido `composer-lock-sync.yml`

Comando correto pra sincronizar lock de pacotes específicos sem instalar vendor/:
```bash
composer update <pkg1> <pkg2> --no-install --no-interaction --no-progress --no-scripts
```
- `--no-install` evita baixar vendor/ no runner CI (não precisamos lá)
- Pacotes mencionados têm lock entry atualizado
- Outras deps ficam intactas (sem cascade update)

### 2. Gate anti-drift em `ci.yml`

Substituir o `composer validate --no-check-publish || true` (lenient) por:
```yaml
- name: Validate composer.json + lock em sync (gate anti-drift)
  run: composer validate --strict --no-check-publish
```
- `--strict` aborta em warnings, **incluindo** lock fora de sync.
- PR que toca `composer.json` sem regenerar lock → CI vermelho → merge bloqueado.
- Mensagem clara: dev sabe disparar `composer-lock-sync` workflow ou rodar `composer update <pkgs> --no-install` local.

### 3. (Futuro) Split `composer.json` — ADR 0063b ou subsequente

Solução final pra ADR 0062 (Hostinger ≠ CT 100): pacotes específicos do CT 100 saem do `/composer.json` raiz. CT 100 ganha `/docker/oimpresso-mcp/composer.json` independente. Hostinger nunca mais vê `laravel/mcp`/`laravel/octane`.

**Não-bloqueante pra esta ADR.** O gate (#2) já protege drift; o split adiciona separação física mas requer:
- Decisão sobre `wikimedia/composer-merge-plugin` vs duas instalações
- Refator do `Dockerfile.octane` pra build com 2 composer.json
- Migração do clone `/opt/oimpresso-mcp/code` no CT 100

Será tratado em ADR separada quando Wagner ou outro dev ativar.

## Justificativa

**Por que `--strict` em vez de `--no-check-publish || true`?**
- Comportamento atual silencia o erro (`|| true` mascara). Drift entra em main sem CI piscar.
- `--strict` produz exit code 2 em warnings → fail-fast.
- Adiciona ~3s ao CI. Aceitável.

**Por que `--no-install` no workflow?**
- Workflow gera apenas o `composer.lock` (commit num PR via peter-evans/create-pull-request).
- `vendor/` no runner CI é descartado. Instalar ele desperdiça 1-3min.
- Atalho seguro porque nenhum test roda nesse workflow.

**Por que dois passos (gate + sync workflow) em vez de "apenas regenerar no CI sempre"?**
- Lock determinístico exige aprovação humana. Auto-regenerar em qualquer PR pode mascarar bug de versão.
- Sync workflow é `workflow_dispatch` — disparado intencionalmente quando dev confirma que o drift é desejado.
- Gate strict no CI principal alerta dev no momento do PR; ele decide acionar o sync.

## Consequências

**Positivas:**
- Drift detectado no PR, não em deploy.
- Workflow `composer-lock-sync` funciona (corrigido).
- Tooling team-wide: Felipe/Maíra/Luiz/Eliana enxergam erro CI direto, podem disparar workflow.

**Negativas / Trade-offs:**
- PR mais "burocrático" — adicionar pacote = 2 PRs (composer.json + lock sync). Mitigação: doc o fluxo em CLAUDE.md/skill.
- `--strict` pode pegar warnings antigas não-críticas (ex.: `name` field ausente). Aceitar e corrigir incrementalmente.

**Riscos mitigados:**
- Hostinger/CT 100 deploy parando por drift → impossível com gate ativo.
- Claude (ou outro agente) tentando workaround errado de novo → ADR explicita o erro original e a correção.

## Referências

- ADR 0062 — Separação dura de runtime Hostinger/CT 100
- ADR 0053 — MCP server governança
- ADR 0058 — Reverb→Centrifugo (origem da inclusão de `laravel/octane`)
- Composer docs: https://getcomposer.org/doc/03-cli.md#update-u
- Incidente origem: 2026-04-30 PR #81 CI falhando + tentativa errada de `composer update --lock <packages>`
