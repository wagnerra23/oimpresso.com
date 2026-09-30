---
date: "2026-09-30"
time: "14:14 BRT"
slug: patrimonio-thread-20-settings-remocao
tldr: "Patrimônio thread 20 fechada como REMOÇÃO, não migração: /asset/settings só com index e store; create/show/edit (views inexistentes) e update/destroy (corpo vazio) saem. UC-CFG-05 verde na lane MySQL (135 passed · 635 assertions). Executada fora do placar por decisão [W]."
prs: [8272]
decided_by: [W]
related_adrs: [0414-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react, 0409-zero-baseline-de-tolerancia-conformidade-absoluta, 0358-doutrina-de-teste-tenant-98-supersede-0101]
next_steps:
  - "Cowork, projeto copia: subir o _saida-20.md (sessão da Maiara)"
  - "Cowork: _saida-18 e _saida-19 seguem pendentes no plano do pendentes-cowork.mjs — são de outras threads"
  - "Decisão [W]: baseline de pixel do Governance/Dashboard (2,4051% > τ_alto, herdado da thread 07)"
---

# Patrimônio thread 20 · /asset/settings só com index e store

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas pra @wr23.
- `sessions-recent limit:3`: 3 logs de estado-da-arte de 2026-08-22 (indexados hoje), sem relação com o Patrimônio.
- `decisions-search "patrimonio formulários drawer settings"`: ADR 0414 é a vigente; nada novo depois dela.

## O que aconteceu
1. `/onda Patrimonio --thread 20`. O playbook ainda marca a thread como bloqueada por D-FORMS, mas a D-FORMS foi respondida (ADR 0414, 24/09). A `_saida-15` já tinha medido os 3 sítios (`create` :150, `show` :228, `edit` :239) como views inexistentes e inalcançáveis pela UI.
2. Medido no `main`: Configurações é **um registro por empresa** (`business.asset_settings` + 2 `NotificationTemplate`). A Page do índice já é o formulário e grava por `POST /asset/settings` → `store()`. Não havia sub-tela para migrar. Achado extra: `update()` e `destroy()` tinham corpo vazio.
3. A 20 aparecia atrás da 16 no placar. [W] decidiu no chat: executar agora e tirar `update`/`destroy` junto.
4. PR #8272: `Route::resource('settings', …)->only(['index', 'store'])`, os 5 métodos saem do controller e o `SmokeRoutesTest` deixa de fixar `asset.settings.show` (perdedor corrigido no mesmo PR). Entram os 2 `it()` do UC-CFG-05, com casos, charter e `_saida-20.md`.
5. CI: o Pest caiu no `UC-BENS-10`, que era herdado do `main` (assert `?? 'ausente'`), já consertado pelo #8271. Trouxe o `main` por merge (o hook barrou o force-push do rebase). A lane não roda em `synchronize`, então foi disparada à mão: **135 passed · 635 assertions**, com os 2 casos do UC-CFG-05 nomeados no log (run 36745625995).
6. `_saida-20.md` subido ao Cowork (projeto w) com opt-in [W] ("sobe pro design-sync"): `written: 1`. O `get_file` de volta veio completo (`truncated: false`) e com o texto igual ao local. Não houve `--conferir` por hash, porque o arquivo voltou inline e salvá-lo à mão seria transcrever.
7. Merge por [W] às 17:13Z (`ffc377fee`). 48 required verdes. Único vermelho: `visual-regression` (não required).

## Artefatos gerados
- `Modules/AssetManagement/Routes/web.php` · `Modules/AssetManagement/Http/Controllers/AssetSettingsController.php`
- `Modules/AssetManagement/Tests/Feature/ConfiguracoesContratoTest.php` (UC-CFG-05) · `SmokeRoutesTest.php` (controle)
- `resources/js/Pages/Patrimonio/Configuracoes.casos.md` (UC-CFG-05 🧪) · `.charter.md` (nota datada no Non-Goal "NÃO cria rota")
- `prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/_saida-20.md`

## Persistência
- git: #8272 mergeado. O índice do playbook é do Cowork e não foi editado no espelho.
- Cowork: `_saida-20` no projeto w; projeto copia pendente.
- BRIEFING do AssetManagement não tocado: só código morto saiu, nenhuma capacidade mudou.
- Smoke em prod: **não feito**. As URLs removidas exigem login, e `curl` sem sessão só mostra o redirect. Quem prova o 404 é o UC-CFG-05, e o deploy do `ffc377fee` não foi conferido.

## Próximos passos pra retomar
Os 3 do frontmatter. Nenhum depende desta thread para avançar o Patrimônio.

## Lições catalogadas
- Lane de módulo sem `synchronize`: depois de um push, o check some do head em vez de ficar verde. `workflow_dispatch` resolve, mas só vale lendo as *assertions* no log.
- O `visual-regression` do `Governance/Dashboard` repetiu **2,4051%** em dois runs. É diferença determinística, e a baseline dele está defasada desde a thread 07 (#8236/#8243/#8250). Regeneração aposentada (ADR 0409).
- Escrita com barra invertida por heredoc Python falhou no assert antes de gravar (LC-26). Troquei pela ferramenta Edit, e nada foi corrompido.

## Pointers detalhados
- `_saida-20.md` (lista das 7 chaves + 2 templates) · PR body do #8272 (Infra Contract e medições) · `_saida-15.md` (medição original dos sítios).
