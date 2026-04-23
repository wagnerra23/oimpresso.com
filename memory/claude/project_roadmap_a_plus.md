---
name: Roadmap A+ — observabilidade, testes, CI/CD
description: Melhorias "estado-da-arte-plus" decididas adiar em 2026-04-22, para entrar depois do setup React atual
type: project
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Wagner pediu para guardar o planejamento "A+" para executar depois. O stack atual (Inertia+React+shadcn+TW4 rodando sobre Laravel 9.51/Hostinger) é "A" para o contexto dele. Para virar "A+" faltam 3 peças:

## 1. Observabilidade

- **Sentry** para erros (grátis até 5k eventos/mês). Instala via `sentry/sentry-laravel`. Configurar `SENTRY_LARAVEL_DSN` em `.env`. Cobre tanto PHP quanto React (via `@sentry/react`)
- **Laravel Pulse** (ou **Telescope** para dev) — métricas de request, query lenta, queue jobs, cache hits
- **Logs estruturados JSON** (`LOG_CHANNEL=stack` + driver JSON) — hoje é texto. Facilita shipping p/ Loki/ELK quando migrar infra

**Why:** Sem isso, quando algo quebra em produção a única pista é `storage/logs/laravel.log`. Em ambiente multi-tenant (business_id) fica impossível rastrear qual cliente foi afetado.

**How to apply:** Entrar como fase F0.5 antes da primeira tela React real, OU adiar para depois do piloto Intercorrência + IA. Wagner escolhe a ordem na próxima sessão.

## 2. Testes

- **Pest v3** substituindo PHPUnit — sintaxe mais concisa, DX melhor. Migração gradual: testes antigos PHPUnit convivem
- **Laravel Dusk ou Playwright** para E2E de fluxos críticos (login, POS/create, NFe emissão, boleto geração)
- **Target:** 70%+ coverage em módulos críticos (fiscal, PontoWR2, POS). Módulos admin aceitam coverage menor
- **Testes do frontend:** Vitest + React Testing Library para componentes shadcn customizados

**Why:** Hoje só PontoWR2 tem 9 testes unitários (sessão 08). Todo o core UltimatePOS está sem cobertura. Qualquer refactor é "commit and pray".

## 3. CI/CD

- **GitHub Actions workflow** por PR: lint PHP (pint), typecheck TS, rodar Pest, rodar Vitest, build Vite
- **Deploy automatizado:** hoje é `git pull` manual no servidor. Adicionar Envoyer/Deployer ou GitHub Actions SSH deploy
- **Proteção de branch:** bloquear merge no `main` sem CI verde
- **Semantic versioning tags** para releases (`v6.7.x`) — hoje todos os commits são "6.7" ou "atualização 6.7", impossível rollback dirigido

**Why:** Deploy via git pull manual é anos-2015. Sem CI, merge pode quebrar produção silenciosamente. Semantic versioning dá referência estável para rollback quando breakage ocorrer.

**How to apply:** CI antes de deploy automation. Pode ir junto com migração de PHP 8.4 (quando Wagner fizer). Estimativa: 1 sessão para CI básico + 1 sessão para deploy automatizado.

---

**Ordem sugerida de execução** (depois das telas React do PontoWR2 estarem prontas):
1. Sentry (1h, imediato retorno de valor)
2. CI básico no GitHub Actions (3h)
3. Pest migração + primeiros testes novos módulos (ongoing, 1 sessão inicial)
4. Deploy automatizado (2h, depois de CI estabilizado)
5. Pulse/Telescope + logs estruturados (quando migrar para Forge/Cloud)

Custo total: ~8 horas de setup + ongoing commit por ciclo.
