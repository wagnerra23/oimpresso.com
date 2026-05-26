# Financial Module Layout

_Started 2026-05-19 23:07 UTC_

---

## User

confira omodulo financeiro, o que falta para ajustar para code fazer correto, ele tinha se baseado em um versão errada de template, agora esta dando um belo trabalho para ajustar os detalhes. pode guiar ele para informar o que ele tem que fazer para ajustar o layout. verifique o estado que esta e o que ele tem que fazer para ajustar

## User

isso cobre todas telas do modulo

## User

isso cobre todas telas do modulo

## Assistant

_[tool: grep]_

_[tool: grep]_

Confirmado. **Os 9 arquivos cobrem 100% das telas do módulo.** Detalhamento:

## Mapa tela → arquivo

| # | Tela | Rota Inertia | Arquivo no patch |
|---|---|---|---|
| 1 | **Visão unificada** (default) | `/financeiro` | `financeiro-app.jsx` |
| 2 | **Fluxo de caixa** | `/financeiro/fluxo-caixa` | `financeiro-telas-extras.jsx` → `TelaFluxo` |
| 3 | **Conciliação** | `/financeiro/conciliacao` | `financeiro-telas-extras.jsx` → `TelaConciliacao` |
| 4 | **DRE / Relatórios** | `/financeiro/dre` | `financeiro-telas-extras.jsx` → `TelaDRE` |
| 5 | **Plano de contas** | `/financeiro/plano-de-contas` | `financeiro-telas-extras.jsx` → `TelaPContas` |

## Overlays e sub-fluxos (todos cobertos)

- **Drawer de detalhe** + abas Detalhes/✦ IA → `financeiro-app.jsx`
- **⌘K Command palette** → `financeiro-app.jsx`
- **Resumir mês** (modal IA) → `financeiro-curation.jsx`
- **Apresentar** (digest mensal slides) → `financeiro-output.jsx`
- **Trilha de onboarding** (Conferir/Conciliar/Categorizar) → `financeiro-curation.jsx`
- **Fechamento mensal** → `financeiro-curation.jsx`
- **FSM stepper inline** (5 bolinhas por lançamento) → `fsm-stepper.jsx`
- **Anomalia banner IA** + **frescor pills** + **audit timeline** → `financeiro.css` + `financeiro-app.jsx`

## ⚠️ Ressalva importante pro Wagner

Os 9 arquivos cobrem o **protótipo visual** das 5 telas. Mas **Code pode não ter criado as rotas Inertia das 4 extras ainda** (`Fluxo.tsx`, `Conciliacao.tsx`, `DRE.tsx`, `PlanoContas.tsx`).

Vale Wagner perguntar pro Code no mesmo PR:

> "Confirma que as rotas `/financeiro/fluxo-caixa`, `/financeiro/conciliacao`, `/financeiro/dre`, `/financeiro/plano-de-contas` existem em `Modules/Financeiro/Routes/web.php` e têm Pages Inertia? Se faltarem, sinaliza como gap pra próximo PR — NÃO inventa controllers nem Models (ver LICOES_F3_FINANCEIRO_REJEITADO.md)."

Se faltarem, **abre PR separado** depois — ler Entities reais (`Titulo`, `Categoria`, `ContaBancaria`) antes de escrever controller, seguindo o checklist da seção 3 das lições.

Quer que eu adicione essa ressalva direto no GAPS_v3 pra ficar no prompt único?

## User

crie outro ja executando o v3

## Assistant

_[tool: write_file]_

_[tool: show_to_user]_

Pronto. **`GAPS_v4_FINANCEIRO_PRA_CODE.md`** criado.

## O que mudou em relação ao v3

| | v3 | v4 |
|---|---|---|
| Sync visual (9 arquivos) | ✅ | ✅ |
| Auditoria das 4 telas extras | ❌ | ✅ (script bash dentro do prompt) |
| Reporta gaps em `CODE_NOTES.md.append` | ❌ | ✅ |
| Regras de ouro anti-LICOES_F3 | parcial | explícitas no fim do prompt |
| PR description estruturado | curto | template completo com `gh pr create` |

## Fluxo zero-touch único

Wagner cola o prompt **uma vez** no Claude Code. Code:
1. Cria branch + sobrescreve os 9 arquivos via `curl`
2. Verifica linha-conta (`wc -l`, `grep -c`)
3. Roda audit bash que percorre `routes/`, `Modules/Financeiro/Http/Controllers/`, `resources/js/Pages/Financeiro/`, `Modules/Financeiro/Entities/`
4. Gera relatório por tela com `✅` / `🔴 falta`
5. Commit + push + `gh pr create` com PR body já preenchido
6. Reporta de volta a URL do PR + o conteúdo do audit

## O que isso te dá

- **PR-A (este):** conserta o visual quebrado em prod
- **CODE_NOTES.md.append** do PR vai conter o status real das 4 telas → eu leio depois e escrevo PR-B (rotas/Pages faltantes) + PR-C (seeder) com briefings rigorosos seguindo as lições.

**Próximo passo:** cola o prompt da seção "Prompt único" no Claude Code. Quando ele responder com URL do PR + audit, me cola que eu escrevo o PR-B já com diagnóstico das telas que faltam.

