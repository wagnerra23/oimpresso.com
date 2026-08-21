# /modulos — pacote de entrega [CC] → [CL]

**Comece por `PEDIDO-PARA-CODE.md`** (7 PRs na ordem + 5 decisões [W] + checklist pós-merge).
Patches comentados um a um: `PATCHES.md` (P1..P8).

## `repo/` = espelho da árvore do `main`

Cada arquivo já está **no caminho de destino**. Aplicar = copiar `repo/` sobre a raiz do repositório
(ou arrastar arquivo por arquivo); nenhum arquivo precisa ser renomeado ou realocado.

```
repo/
├─ resources/js/Pages/Modules/
│  ├─ Index.charter.md          v2 — substitui o draft v1 (R1–R11, A1–A4, 4 pendências [W])   PR-2
│  ├─ Index.casos.md            NOVO — 16 UC Dado/Quando/Então + rastreabilidade              PR-2
│  └─ Index.tsx                 patch P3 — marcador "sem menu" (has_datacontroller)           PR-3
├─ routes/web.php                 P7 — remove as 3 rotas de manage-modules (mantém upload-module)   PR-7
├─ app/
│  ├─ Http/Middleware/AdminSidebarMenu.php     P7 — menu aponta route('modules.index') + active 'modulos'   PR-7
│  ├─ Services/ModuleManagerService.php          P1 revert no catch · P2 error alcançável · P4 versão instalada   PR-3
│  ├─ Http/Controllers/ModuleManagementController.php   P5 — can('manage_modules')            PR-4
│  └─ Jobs/InstalarModuloJob.php                 NOVO — P6 install fora do request            PR-5
├─ tests/
│  ├─ Feature/Modules/ModuleManagementTest.php   UC-MOD-01..04 / 11..15                       PR-2
│  └─ Unit/Services/
│     ├─ ModuleManagerServiceTest.php            UC-MOD-05..07 + heurística de área            PR-2
│     └─ ModuleErroFixtureTest.php               prova do P2 (fixture Modules/__ErrFixture__)  PR-3
└─ prototipo-ui/contrato/modulos.contract.json   7 âncoras + copy literal + estados            PR-6
```

Os `.php`/`.tsx` são **cópias do `main` com o patch já dentro**, marcado por comentário `// P<n>` —
diffar contra o `main` mostra exatamente o que muda. Os `.md` e o `.json` são arquivos novos ou
substituições integrais.

## Fora de `repo/`

- `PEDIDO-PARA-CODE.md` — o pedido (contexto, PRs, decisões, checklist)
- `PR-8-REMOVER-LEGADO.md` — o que apagar do Blade/controller legado, o que **não** mexer e o smoke
- `PATCHES.md` — os 8 patches em diff com sintoma → causa → mudança → prova
- `../MODULOS-F1-2026-08-19.md` — trio de prontidão F1 (charter reforçado + UC + testes)
- `../MODULOS-F3-ONDAS-PARA-CODE.md` — ondas MOD-O0..O6 + "o que mais precisa"
- `../../prototipo-ui/cowork/modulos/modulos-page.{jsx,css}` — build F1 de referência (não vai para `app/`)

## Limites

Nada commitado — as tools de GitHub deste projeto são read-only. A ponte é cola zero-toque ou Issue
`cowork-intake`. Duas coisas **não verifiquei**: se há fila com worker em produção (PR-5) e se
`activity_log` já cobre ação app-wide (auditoria da onda MOD-O3).
