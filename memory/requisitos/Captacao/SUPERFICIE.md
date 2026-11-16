---
name: "SUPERFÍCIE — Captacao"
description: "Índice GERADO dos artefatos do módulo Captacao reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Captacao
---

# 🗺️ Superfície de código — Captacao

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Captacao --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Captacao/**` + `resources/js/Pages/Captacao/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 18 arquivos em 10 papéis.

## Controllers — 2

- [DataController.php](../../../Modules/Captacao/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Captacao/Http/Controllers/InstallController.php)

## Middleware — 1

- [CaptacaoLiberado.php](../../../Modules/Captacao/Http/Middleware/CaptacaoLiberado.php)

## Services — 1

- [CaptacaoAcesso.php](../../../Modules/Captacao/Services/CaptacaoAcesso.php)

## Models / Entities — 4

- [Aporte.php](../../../Modules/Captacao/Entities/Aporte.php)
- [Comprovante.php](../../../Modules/Captacao/Entities/Comprovante.php)
- [Fonte.php](../../../Modules/Captacao/Entities/Fonte.php)
- [Projeto.php](../../../Modules/Captacao/Entities/Projeto.php)

## Providers — 2

- [CaptacaoServiceProvider.php](../../../Modules/Captacao/Providers/CaptacaoServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Captacao/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Captacao/Routes/web.php)

## Migrations (schema) — 1

- [2026_11_14_000001_create_captacao_tables.php](../../../Modules/Captacao/Database/Migrations/2026_11_14_000001_create_captacao_tables.php)

## Config — 1

- [config.php](../../../Modules/Captacao/Config/config.php)

## Testes (Pest) — 1

- 1 em [Modules/Captacao/Tests/Feature/](../../../Modules/Captacao/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 4

- [captacao.php](../../../Modules/Captacao/Resources/lang/pt-BR/captacao.php)
- [composer.json](../../../Modules/Captacao/composer.json)
- [module.json](../../../Modules/Captacao/module.json)
- [SCOPE.md](../../../memory/requisitos/Captacao/SCOPE.md)
