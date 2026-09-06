---
sessao: "03"
titulo: Tipos de licença — Page
dono: "[CL]"
base: 159e572dd448
prefixo: resources/js/Pages/Essentials/Tipos.tsx OU Tipos/Index.tsx (+ charter/casos) · EssentialsLeaveTypeController.php (@index · @store · @update) · prototipo-ui/contrato/essentials-tipos.contract.json · e2e/essentials-tipos.spec.ts · lane essentials-pest.yml
nao_toca: @destroy (fechado no #6789: 422 + blocked_by) · EssentialsLeaveController · Pages/Essentials/** · DS
depende: — (vaga 1). Caminho = resources/js/Pages/Essentials/ (a árvore respondeu; flat × pasta = criar-tela.mjs). Irmã golden: Essentials/Metas.tsx (#6869)
---
# 03 · Tipos de licença

## A · Identidade
- **alvo (layout):** subview "Tipos" de `Licencas` em `hrm-page.jsx` (`Seg` de 3 subviews: Licenças · Saldo por tipo · Tipos). Tabela `os-table` com colunas nome · limite (`max_leave_count`) · intervalo (`leave_count_interval` = ano/mês) · em uso · ações. Formulário em `hrm-forms.jsx`.
- **âncora (código):** `EssentialsLeaveTypeController@index` (`leave_type/index.blade.php` é a blade que sai) · rota `/hrm/leave-type` resource **existe**.
- arquétipo PT-01 (lista curta + form inline/drawer) · persona Wagner · permissão `essentials.crud_leave_type` (é `@can` no `nav_hrm`).

## B · Não inventar
- Dados: `essentials_leave_types.{leave_type,max_leave_count,leave_count_interval,business_id}` · contagem "em uso" = licenças vinculadas (é o que o `destroy` do #6789 já calcula em `blocked_by` — **reusar**, não recontar).
- Copy: "Tipo de licença", "Limite", "Por ano / Por mês", "Em uso". PT-BR sentence case.
- Componentes `@/Components/ui/*` + `shared/PageHeader` · tokens do DS.

## C · Comportamento (EARS)
| elemento | TAG | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|---|
| Novo tipo | BUTTON | enviado → cria escopado no `business_id` | grava | não | Pest: tipo aparece na lista |
| Editar | BUTTON (linha) | salvo → atualiza limite/intervalo | grava | não | Pest |
| Excluir | BUTTON (linha) | clique → chama `destroy`; **se em uso**, mostra a mensagem com `blocked_by` (não some) | grava | não | Pest do #6789 já cobre 422 |
Invariantes: permissão nega antes de renderizar · estado vazio "Nenhum tipo cadastrado — crie o primeiro" · sem número inventado.

## Execução
```
ARQUIVOS A EDITAR : resources/js/Pages/Essentials/Tipos{.tsx|/Index.tsx} (CRIAR via criar-tela.mjs Essentials/Tipos PT-01 — carimba trio+e2e+contrato essentials-tipos)
                    EssentialsLeaveTypeController.php (@index → Inertia::render)
PASSO A PASSO     : 1) gh pr list --state open × estes arquivos 2) criar-tela.mjs 3) Inertia::render com tipos + contagem em uso
                    4) tabela na ordem do alvo 5) form criar/editar 6) excluir exibindo blocked_by 7) _saida-03.md
PARAR SE          : (a) "em uso" exigir query nova além da que destroy já faz → reusar o mesmo cálculo ou "—"
                    (b) gerador sair de resources/js/Pages/Essentials/ → parar e reportar
```

## Prova
- `resources/js/Pages/Essentials/Tipos.tsx` **ou** `Tipos/Index.tsx` + charter + casos (UC citado por teste) · `contrato/essentials-tipos.contract.json` · `e2e/essentials-tipos.spec.ts` · controller contém `Inertia::render('Essentials/Tipos`
- teste Feature do `destroy` 422 continua verde (não tocar)
- `_saida-03.md` · placar no PR · T7 não verificável daqui
