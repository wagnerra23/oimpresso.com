# Help — situacao no batch 7 de testes (legados)

> **Anotado em 2026-04-26** — Wagner pediu Pest 6 para Help; modulo nao existe na branch atual.

## Diagnostico

- `Modules/Help/` **nao existe** em `6.7-bootstrap` (confirmado por `find Modules -maxdepth 2 -iname "help*"`).
- `memory/modulos/Help.md` (gerado por `module:spec` em 2026-04-22) ja registra "NAO EXISTE na branch atual (so em branches antigas — migracao perdida?)".
- `preference_modulos_prioridade.md` lista Help entre os "Modulos perdidos na migracao 3.7 -> 6.7" com decisao **avaliativa** ("Docs/treinamento — substituir por docs externas").
- Mencionado pelo usuario como "Help (iframes)" — bate com o pattern usado pelo Officeimpresso (`/officeimpresso/docs` que renderiza iframe pra https://docs.officeimpresso.com.br).

## Decisao do batch 7

**Skip ativo** — sem modulo, sem teste. Recomendacao:

- **Deprecar formalmente** atualizando `modules_statuses.json` se ainda houver entrada Help residual.
- Substituir UX por **link externo / iframe Officeimpresso** (pattern ja existente em `Officeimpresso\Routes\web.php`):
  ```php
  Route::get('/docs', fn () => view('superadmin.iframe', ['url' => 'https://docs.officeimpresso.com.br']));
  ```
- Quando/se re-importar do branch `main-wip-2026-04-22`, abrir nova ADR e gerar tests de smoke (rota `/help`, iframe URL valida, permission gating).

## Acao deste PR

- Sem testes para Help (nao ha codigo).
- SPEC.md nao criada dentro do modulo (nao existe).
- Esta nota documenta a decisao para auditorias futuras.
