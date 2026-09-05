---
sessao: "04"
titulo: Metas de venda — Page
dono: "[CL]"
base: 159e572dd448
prefixo: <PAGES>/Hrm/Metas/** · Modules/Essentials/Http/Controllers/SalesTargetController.php (@index · @setSalesTarget)
nao_toca: @saveSalesTarget (validação de faixas fechada no #6799) · PayrollController (comissão de meta é da folha — thread 10, bloqueada) · Pages/Essentials/** · DS
depende: — (vaga 1) · <PAGES> (RESÍDUO 1)
---
# 04 · Metas de venda

## A · Identidade
- **alvo (layout):** `hrm-extras.jsx` (`Metas`) — medido 04/09: 896 nós · `os-table` **7 colunas** (colaborador · faixa de · faixa até · comissão % · vigência · situação · ações) · form "Definir meta" em `hrm-forms.jsx`.
- **âncora (código):** `SalesTargetController@index` + `@setSalesTarget/{id}` (form por usuário) · rotas **existem**.
- arquétipo PT-01 + drawer/form PT-02 · persona Wagner · permissão `essentials.access_sales_target`.

## B · Não inventar
- Dados (lidos 04/09): `essentials_user_sales_targets.{user_id,target_start,target_end,commission_percent}` · **gate Tier 0** já existe no controller (`User::where('business_id',…)->findOrFail($request->user_id)`) — preservar.
- Validação (já no `main`, #6799): sem sobreposição de faixas · `target_end > target_start` · `commission_percent` 0–100. A Page **mostra** os erros; não revalida à parte.
- Copy: "Meta de venda", "Faixa", "Comissão", "Vigência". Sem emoji.

## C · Comportamento (EARS)
| elemento | TAG | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|---|
| linha do colaborador | TR `role=button` | clique → abre form com as faixas dele | — | `esc` | drawer monta |
| Adicionar faixa | BUTTON | clique → nova linha de faixa no form | — | Remover | contagem de faixas |
| Salvar | BUTTON | enviado → POST `/hrm/save-sales-target`; erro 422 aparece por campo | grava | não | Pest do #6799 |
Invariantes: permissão nega antes · `user_id` validado por tenant · sem número inventado.

## Execução
```
ARQUIVOS A EDITAR : <PAGES>/Hrm/Metas/Index.tsx (CRIAR via criar-tela.mjs Hrm/Metas PT-01)
                    <PAGES>/Hrm/Metas/_components/DefinirMeta.tsx (CRIAR)
                    SalesTargetController.php (@index e @setSalesTarget → Inertia::render / JSON)
PASSO A PASSO     : 1) whats-active 2) criar-tela.mjs 3) Inertia::render com usuários + faixas
                    4) tabela 7 col na ordem 5) form de faixas com erros do 422 6) _saida-04.md
PARAR SE          : (a) "situação" da meta não existir como coluna → derivar de target_start/end (vigente/encerrada) ou "—"
                    (b) <PAGES> não resolvido → RESÍDUO 1
```

## Prova
- `<PAGES>/Hrm/Metas/Index.tsx` + charter + casos (UC citado por teste) · teste de faixas (#6799) verde
- `_saida-04.md` · placar no PR · T7 não verificável daqui
