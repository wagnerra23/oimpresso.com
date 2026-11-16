---
slug: estrategia-testes
status: proposto
authority: canonical
decided_by: [W]
decided_at: "2026-11-15"
tags: [testes, pest, phpstan, playwright, k6, lighthouse, smoke, lgpd]
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
  - regras-negocio-usabilidade
---

# Estratégia de testes — pirâmide 6 camadas

> Cada camada bloqueia a próxima se falhar. Documenta o `what/when/who/gate` de cada tipo de teste.

## 1️⃣ Dev local (antes do commit)

- Linter + typecheck — `npm run lint` (ESLint) + `npm run typecheck` (TS strict). Zero erro novo além do baseline.
- Pest do arquivo tocado — no CT 100, nunca na máquina local: `tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=<Teste>"`.
- PHPStan nível 5 do arquivo tocado — no mesmo container do CT 100: `vendor/bin/phpstan analyse <file> --memory-limit=1G`. O hook `block-test-fora-ct100` bloqueia a execução local.
- Diff review próprio — ler o próprio PR antes de empurrar.

Dono: dev (humano ou agente). Gate: pre-commit hook recomendado.

## 2️⃣ CI por PR (GitHub Actions, blocker de merge)

- Pest feature do módulo tocado
- Isolamento multi-tenant `*IsolationTest.php` (ADR 0093)
- Casos-coverage-guard (tela React tem `.casos.md`, UC aparece em teste)
- PHPStan nível 5 suite inteira (baseline)
- Visual regression pixel-diff (telas tocadas) — gate ratchet
- Required contexts 45+ (hoje gap: 21 "não nasceram")
- Design System gate (classes sem token quebram)
- Preflight módulo (estrutura padrão)
- Governance manifesto (commit message + `Refs:`)

Dono: GitHub Actions. Gate: squash merge só se required verde. Nenhum `--admin`.

## 3️⃣ CI noturno / main (bloqueia release)

- Pest FULL SUITE no CT 100 (13.669 testes). Hoje parado há 39 dias — reativar é bloqueador
- Load/stress k6 nas rotas críticas
- Mutação/fuzzing em services críticos
- Secrets scan (gitleaks + trufflehog)
- Dependency audit (`composer audit` + `npm audit`)
- Accessibility axe-core nas telas públicas (WCAG 2.1 AA)

Dono: cron no CT 100. Gate: falha noturna = alerta Wagner, bloqueia deploy.

## 4️⃣ Pré-deploy staging (CT 100 FrankenPHP)

- Smoke manual no `staging.oimpresso.com`: login, 1 fluxo por módulo mergeado
- Diff migration `php artisan migrate --pretend`
- Backup DB antes de migration real
- E2E Playwright viewport mobile + desktop
- Checksum vendor/ + `npm run build`
- Health check endpoint `/health` retorna 200

Dono: agente Deploy Pipeline + Wagner valida. Gate: smoke 100% verde antes de prod.

## 5️⃣ Pré-deploy produção (Hostinger + CT 100)

- Autorização [W] explícita (histórico 3 crashes prod)
- Janela de deploy fora de pico
- Backup snapshot MySQL imediato
- Deploy em sequência: staging → canary (1 cliente) → prod geral
- Smoke canary 10min antes de abrir pra todos
- Rollback script pronto

Dono: agente Deploy Pipeline + Wagner clica. Gate: smoke canary verde, zero 500 em 10min.

## 6️⃣ Pós-deploy produção (contínuo, 24/7)

- Smoke na empresa 1 (oimpresso) a cada deploy + a cada hora. Nunca na empresa 4 (ROTA LIVRE) nem em empresa de cliente: a R6 proíbe smoke e teste nelas
- Uptime monitor 1min (5xx alerta Slack/email)
- Latência P95 por rota crítica
- Erro 5xx por severidade e volume
- Multi-tenant leak (query sem business_id = alarme vermelho)
- LGPD audit log (leitura de dado sensível registrada)
- IA custo (tokens Anthropic por módulo, diário; stack da ADR 0035)
- Fila/queue (jobs atrasados)
- Backup diário + restore test semanal

Dono: agente Monitoramento + agente de Onboarding. Gate: incidente em prod → alerta + rollback automático se >10 5xx/min.

## 🎯 Testes específicos por risco

### 🔴 Tier 0 multi-tenant (ADR 0093)
- Toda nova Entity tem `global scope business_id`
- Teste `*IsolationTest.php` com 2 empresas
- Hoje: 6 vazamentos achados por agente RBAC — bloqueador. Fix em curso.

### 🔴 LGPD
- Dado sensível nunca em log/commit/PR
- Direito de portabilidade testado; pedido de exclusão vai para decisão humana, sem rotina automática (§5 2026-07-27)
- Consentimento por finalidade com data + versão
- Append-only, sem retenção nem anonimização por prazo (§5 2026-07-27)

### 🔴 Conteúdo jurídico Uniafibro
- Toda afirmação legal com fonte oficial (Planalto, INSS, FCEE)
- Disclaimer "orientação geral, não consultoria" em toda página
- Revisão humana (advogado) antes de publicar
- Changelog público quando lei mudar

### 🟡 Visual/responsivo
- Mobile 375x812 + tablet 768x1024 + desktop
- Dark mode + fonte ajustável
- Contraste WCAG AA (axe-core)
- Touch target ≥44x44px em mobile

### 🟡 Boletos/NFe/Fiscal
- Teste contra homologação SEFAZ antes de prod
- Valores em `decimal` no banco, nunca float na aritmética
- CNAB 240 contra retorno real do banco

## 🧮 Checklist "pronto pra produção"

- [ ] Camadas 1-3 verdes no main
- [ ] Noturno rodando (hoje: ❌ parado 39d)
- [ ] 6 vazamentos Tier 0 corrigidos
- [ ] Casos-gate verde no main
- [ ] Smoke na empresa 1 configurado e verde
- [ ] Rollback testado
- [ ] Backup diário verificado
- [ ] Monitoramento + alertas ligados
- [ ] RUNBOOK deploy + incident response escritos
- [ ] Consentimento LGPD implementado nas telas sensíveis
- [ ] E2E Playwright fluxos críticos verdes
- [ ] A11y ≥90 Lighthouse telas públicas
- [ ] Load test k6 cenário Portal Associada passou

Hoje: ~30% do checklist. As 17 sessões rodando cobrem exatamente esses itens.

## 👥 Agentes responsáveis (rodada 17 sessões 2026-11-15)

| Camada teste | Agente(s) |
|---|---|
| Unit + Feature Pest | #6 Tests full-suite |
| Isolamento multi-tenant Tier 0 | #5 Permissões/RBAC + #6 Tests |
| PHPStan + ESLint + TypeScript | #6 Tests |
| Visual regression pixel-diff | #6 Tests |
| E2E fluxo completo usuário | #15 E2E Playwright |
| Acessibilidade WCAG 2.1 AA | #16 A11y Lighthouse |
| Performance Core Web Vitals | #16 A11y Lighthouse |
| Carga / stress | #17 Load k6 |
| Smoke contínuo pós-deploy | #13 Onboarding |
| LGPD + Security SAST | #9 Security review |
| Teste real em prod (canary) | #7 Deploy pipeline |
| Monitor 24/7 pós-deploy | #8 Monitoramento |

---

**Última atualização:** 2026-11-15 (Cowork orchestrator, autorizado por Wagner).
