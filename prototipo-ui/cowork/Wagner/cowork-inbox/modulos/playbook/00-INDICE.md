---
sessao: "00"
titulo: Gerenciador de módulos (/modulos) — índice
autor: "[CC]"
criado: 2026-10-06
base: wagnerra23/oimpresso.com@main 2fe69ddc0280 (lido 2026-10-06 17:08 UTC)
---
# Gerenciador de módulos (/modulos) — índice

**4 threads.** Absorve `../PEDIDO-PARA-CODE.md` (PR-1…PR-8), `../PATCHES.md` (P1…P8) e `../PR-8-REMOVER-LEGADO.md` — não duplica, aponta. `../repo/` é espelho de 2026-08-19 e está **atrás** do `main` em vários arquivos: não copiar por cima.

**Já no `main` @2fe69ddc0280 (lido no turno, sai do escopo):** PR-2 trio (`resources/js/Pages/Modules/Index.{tsx,charter.md,casos.md}` — charter 8.887 B e casos 21.094 B, maiores que os do `repo/`) · P3 "sem menu" (`Index.tsx:316-324`) · P5 permissão `can('manage_modules')` (`ModuleManagementController.php:37`) · PR-6 contrato (`governance/design/contracts/modulos.contract.json`, 2.645 B) · testes `tests/Feature/Modules/ModuleManagementTest.php` + `ModuleManagerServiceTest.php` (este em `Feature/`, não em `Unit/` como o pedido dizia).

**Não verificado:** se o P1 (`setActive($name, false)` no `catch`) já entrou — a busca no turno foi parcial. A thread 01 confere antes de mexer. P7 (skills) **descartado**: relido no turno, `cockpit-runbook` e `sidebar-menu-arch` já não citam `/manage-modules`; `criar-modulo/SKILL.md:26` já diz que o legado redireciona, e as linhas 46/151 + `migrar-modulo/SKILL.md:144` descrevem sintoma histórico — some sozinho quando a 04 apagar a rota. PR-7 (DS vivo) fica fora: abre depois do A1 medido.

```json
{
  "modulo": "Modules",
  "sha": "2fe69ddc0280",
  "gerado": "2026-10-06",
  "decisoes": [
    {
      "id": "D1",
      "texto": "versão exibida: system.<alias>_version (recomendado) — P4",
      "dono": "W",
      "respondida": true,
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: mostrar system.<alias>_version (o que de fato rodou na instalação); module.json só como dica quando divergir",
      "quando": "2026-10-07"
    },
    {
      "id": "D4",
      "texto": "install em fila só se houver worker em produção",
      "dono": "W",
      "respondida": true,
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: não — instalação continua síncrona (Hostinger proíbe daemon/worker; job sem worker deixaria \"instalando\" pra sempre). Thread 02 cancelada.",
      "quando": "2026-10-07"
    },
    {
      "id": "D5",
      "texto": "remover chaves órfãs do modules_statuses.json — P8",
      "dono": "W",
      "respondida": true,
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: sim — apagar chaves órfãs do modules_statuses.json (módulo que não existe no disco não pode aparecer como ativo)",
      "quando": "2026-10-07"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "P1+P2: install falho volta a inativo e \"Com erro\" acende (com prova)",
      "dono": "CL",
      "arquivo": "01-install-falho-e-erro.md",
      "prefixo": [
        "app/Services/ModuleManagerService.php",
        "tests/Unit/Services/ModuleErroFixtureTest.php",
        "tests/Feature/Modules/"
      ],
      "nao_toca": [
        "resources/js/Pages/Modules/",
        "routes/web.php"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "tests/Unit/Services/ModuleErroFixtureTest.php"
        },
        {
          "tipo": "contem",
          "path": "app/Services/ModuleManagerService.php",
          "padrao": "setActive($name, false)"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "D4: install em fila (InstalarModuloJob) — decisão [W] antes",
      "dono": "W",
      "arquivo": "02-install-em-fila.md",
      "prefixo": [
        "app/Jobs/InstalarModuloJob.php",
        "app/Services/ModuleManagerService.php",
        "app/Http/Controllers/ModuleManagementController.php"
      ],
      "nao_toca": [
        "resources/js/Pages/Modules/Index.charter.md"
      ],
      "provas": [],
      "depende_decisoes": [
        "D4"
      ],
      "bloqueio": "cancelada — D4 = não (sem worker em produção). Não executar."
    },
    {
      "id": "04",
      "titulo": "PR-8: remover o legado /manage-modules (último, com portão)",
      "dono": "CL",
      "arquivo": "04-remover-legado.md",
      "prefixo": [
        "routes/web.php",
        "app/Http/Middleware/AdminSidebarMenu.php",
        "app/Http/Controllers/Install/ModulesController.php",
        "app/Http/Controllers/BaseModuleInstallController.php",
        "resources/views/install/modules/"
      ],
      "nao_toca": [
        "resources/js/Pages/Modules/",
        "app/Services/ModuleManagerService.php"
      ],
      "provas": [
        {
          "tipo": "ausente",
          "path": "resources/views/install/modules/index.blade.php"
        },
        {
          "tipo": "nao_contem",
          "path": "routes/web.php",
          "padrao": "Route::resource('manage-modules'"
        }
      ],
      "depende_threads": [
        "01"
      ]
    },
    {
      "id": "A1",
      "titulo": "ALVO modulos--index (medir antes de qualquer onda de layout)",
      "dono": "CL",
      "arquivo": "A1-alvo.md",
      "prefixo": [
        "governance/design/targets/"
      ],
      "nao_toca": [
        "resources/",
        "app/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/targets/modulos--index.alvo.json"
        }
      ]
    }
  ]
}
```
