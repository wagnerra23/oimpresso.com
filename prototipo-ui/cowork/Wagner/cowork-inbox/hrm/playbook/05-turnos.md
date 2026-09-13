---
sessao: "05"
titulo: Turnos — Page
dono: "[CL]"
base: 159e572dd448
prefixo: resources/js/Pages/Essentials/Turnos.tsx OU Turnos/Index.tsx (+ charter/casos) · ShiftController.php · prototipo-ui/contrato/essentials-turnos.contract.json · e2e/essentials-turnos.spec.ts · lane essentials-pest.yml
nao_toca: AttendanceController (cede ao Ponto — thread 09) · Pages/Essentials/** · DS
depende: thread 09 (ADR 0014 emendada — Shift = horário contratual, Ponto dono das batidas) · RESÍDUO 3 (destroy) — vaga 2. Caminho = resources/js/Pages/Essentials/ (a árvore respondeu). Irmã golden: Essentials/Metas.tsx (#6869)
---
# 05 · Turnos

## Por que esta thread continua existindo depois de D1
D1 tira a **presença** do HRM, não o **turno**: a ADR 0014 (`memory/decisions/0014-essentials-pontowr2-integracao.md`, arquivada) desenha Shift como fonte do horário contratual que o Ponto lê. Enquanto a 0014 não for emendada/supersedida (thread 09), esta Page nasce **sem** nenhum campo de marcação — só cadastro e atribuição.

## A · Identidade
- **alvo (layout):** `hrm-extras.jsx` (`Turnos`) — medido 04/09: 840 nós · `os-table` **7 colunas** (nome · tipo fixo/flexível · início · fim · tolerância · colaboradores · ações) · 3 linhas de exemplo · form em `hrm-forms.jsx`.
- **âncora (código):** `ShiftController` resource + `shift/assign-users/{shift_id}` (GET) e `POST shift/assign-users` · blades que saem: `attendance/{shift_modal,add_shift_users,avail_shifts}.blade.php`.
- arquétipo PT-01 + drawer PT-02 (atribuir colaboradores) · persona Wagner.

## B · Não inventar
- Dados: ler `ShiftController` **no turno** (não lido em 04/09 nem hoje). Campos prováveis pelas blades: nome · tipo · início/fim · `is_allowed_auto_clockout` · `auto_clockout_time` · tolerâncias de `essentials_settings` — **só entra o que o controller expuser**; o resto é `—`.
- Copy: "Turno", "Fixo / Flexível", "Atribuir colaboradores". Vocabulário: **colaborador**, nunca "empregado".

## C · Comportamento (EARS)
| elemento | TAG | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|---|
| Novo turno | BUTTON | enviado → cria | grava | não | Pest |
| Atribuir | BUTTON (linha) | clique → drawer com colaboradores; salvo → POST `shift/assign-users` | grava | não | Pest: vínculo criado |
| Excluir | BUTTON (linha) | clique → `destroy`; **com vínculo ou marcação → 422 dizendo quantos travam** | grava | não | Pest (a criar se RESÍDUO 3 = "responde 200") |
Invariantes: permissão nega antes · `user_id` do body validado por tenant · sem número inventado.

## Execução
```
ARQUIVOS A EDITAR : resources/js/Pages/Essentials/Turnos{.tsx|/Index.tsx} (CRIAR via criar-tela.mjs Essentials/Turnos PT-01)
                    resources/js/Pages/Essentials/Turnos/_components/AtribuirColaboradores.tsx (CRIAR — ou inline se flat)
                    ShiftController.php (@index → Inertia::render; @getAssignUsers → JSON)
PASSO A PASSO     : 1) gh pr list --state open × estes arquivos 2) LER ShiftController inteiro 3) criar-tela.mjs 4) Inertia::render
                    5) tabela 7 col na ordem 6) drawer atribuir 7) _saida-05.md
PARAR SE          : (a) ShiftController::destroy responder 200 sem apagar → primeiro a guarda (PR próprio, ≤300 ln), depois a Page
                    (b) qualquer campo de marcação/jornada pedir para entrar → não: é do Ponto (D1)
                    (c) ADR 0014 não emendada → a Page nasce sem "horário contratual lido pelo Ponto"; registrar
```

## Prova
- `resources/js/Pages/Essentials/Turnos.tsx` **ou** `Turnos/Index.tsx` + charter + casos · `contrato/essentials-turnos.contract.json` · `e2e/essentials-turnos.spec.ts` · teste Feature de `destroy` com guarda · controller contém `Inertia::render('Essentials/Turnos`
- `_saida-05.md` · placar no PR · T7 não verificável daqui
