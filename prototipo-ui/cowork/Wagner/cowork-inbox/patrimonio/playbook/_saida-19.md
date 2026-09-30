---
sessao: "19"
titulo: Saída da thread 19 — Manutenções em drawer; create/edit deixam o Blade
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 58f6614ed (origin/main fresco)
executada_fora_da_ordem: "sim — [W] 2026-09-30 liberou a 19 antes da 16 (ver §Ordem)"
prs: "#8260 (Tier 0, pré-requisito) · PR do drawer (empilhado sobre o #8260)"
---

# 19 · Manutenções — escrita sai do Blade e vai para o drawer

## Ordem: por que a 19 rodou antes da 16
O índice faz a 19 depender da 16 (`00-INDICE.md`, `depende_threads: ["16"]`). A 16 espera o drawer de detalhe do bem e a thread 18 (`_saida-16b`). **Não há dependência técnica medida**: a 19 toca `Manutencoes.tsx` e `AssetMaitenanceController`; a 16, a aba de Alocações. Nenhum arquivo nem contrato em comum. [W] confirmou em 2026-09-30 executar a 19 agora. **O índice não foi editado no espelho** — a correção da aresta 19→16 é do Cowork.

## O playbook estava desatualizado em dois pontos (medido, não herdado)
- **§B/§C.1 "D1 vivo em 6 sítios" — FALSO no `main` de hoje.** As guardas do controller usam `||` desde o conserto de 2026-09-08 (docblock da classe explica os dois defeitos que o `if` tinha). Linhas no `main` fresco: `:297`, `:338`, `:376`, `:418`, `:456` (+ `index`). O técnico com só `view_own_maintenance` **não** é barrado. Não havia D1 a nomear como vivo; registrado aqui como fato datado em vez de repetir a frase.
- **§C.3 `show()` apontando view fantasma — já resolvido** pelo [#7904](https://github.com/wagnerra23/oimpresso.com/pull/7904) (a rota `show` saiu: `Route::resource(...)->except(['show'])`).

## Achado no destino do drawer — virou PR próprio ([#8260](https://github.com/wagnerra23/oimpresso.com/pull/8260))
Dois furos Tier 0 no que o drawer posta:
- `update()` → `AssetMaintenanceService::atualizar` fazia `AssetMaintenance::find($id)` **sem `business_id`** — o `edit()` escopava, o `update()` (que grava) não.
- `store()` gravava o `asset_id` do formulário sem conferir de quem é o bem.

Consertados com 404 antes do `try` + escopo no serviço. UC-MANU-05, Pest 98 × 99 com controle da própria empresa. Separado do PR de UI para não misturar risco (mesmo raciocínio que o playbook dava para o D1).

## O que o PR do drawer faz
- `create`/`edit` devolvem a Page `Patrimonio/Manutencoes` com o drawer aberto via **um método só** (`renderManutencoes`, o mesmo desenho do `renderBens` da thread 17). O ramo `if (request()->ajax()) return view(...)` saiu inteiro dos dois.
- `store`/`update` **inalterados** neste PR — seguem sendo o destino.
- As 2 Blades `asset_maintenance/{create,edit}.blade.php` foram **removidas** (sem chamador: `git grep` só achava as duas linhas do controller). Censo Blade→React: catraca OK.
- Entradas na UI: CTA **"+ Enviar bem pra manutenção"** no rodapé (é o da âncora, `patrimonio-page.jsx:536`; entrou no contrato de tela como seção `rodape`) e **lápis por linha** (botão com `router.get`, não `<a href>` — UC-MANU-04 continua valendo).

## Campo a campo — Blade → drawer
| Blade `create` | Drawer (envio) | Blade `edit` | Drawer (edição) |
|---|---|---|---|
| bem (fixo, via `?asset_id`) + selo de garantia | **Bem** (select só da empresa; `?asset_id` pré-seleciona) + selo "Em garantia até …" / "Fora da garantia" | — (título com o código) | Título "Manutenção {código}" + bem no subtítulo |
| `status` (placeholder) | Situação ("Sem situação" = vazio) | `status` | Situação |
| `priority` (placeholder) | Prioridade | `priority` | Prioridade |
| `attachments[]` | Anexos (múltiplos) | `attachments[]` + tabela de mídia com excluir | Anexos (novos) + **lista dos já enviados** (links). ⚠️ Excluir anexo pelo drawer **não entrou** — é endpoint de mídia de outro dono. |
| `maintenance_note` | Nota da manutenção | `maintenance_note` (readonly) | Nota da manutenção **só-leitura** |
| `details` (readonly) | — (o `store` não grava `details`) | `details` | Detalhes do envio |
| — | — | `assigned_to` (select2) | Atribuído a |

## Fora, por desenho (e onde está declarado)
- **Custo / "vira título a pagar no Financeiro"** — o `ManutencaoForm` do protótipo desenha; `asset_maintenances` **não tem coluna de valor** (resíduo §6 item 3; decisão [W] 2026-09-08; UC-MANU-03). Nenhum campo de dinheiro no drawer, logo a REGRA MESTRE de valor **não foi acionada**.
- **Prestador, enviado/devolvido em** — sem coluna. Charter Non-Goals.
- **Envio em lote** — o `store()` grava um bem por vez. `[BACKLOG]` no casos.
- **Escopo de escrita por dono** (`view_own` edita a de outro na mesma empresa) — resíduo [W] já declarado no docblock da classe; não mexido.
- **Ação "Enviar pra manutenção" a partir de Bens** — `Bens.tsx` é de outra sessão hoje (drawer de detalhe do bem). O destino agora existe: `/asset/asset-maintenance/create?asset_id={id}`.

## Validação (§D do playbook)
1. `create`/`edit` renderizam a Page com drawer — **Pest UC-MANU-06** (com `X-Requested-With`, o header que o cliente Inertia manda sempre).
2. Toque ≥44px — `min-h-11` nos controles, travado no vitest; **medição em runtime no smoke pós-merge** (a declarar no PR).
3. Campo a campo — tabela acima.
4. D1 — **não está vivo** (ver acima); registrado como fato datado.
5. A11y — rótulo por `htmlFor`, foco e `aria-modal` do `Sheet` (Radix Dialog), erro em `role="alert"`.
6. PLACAR — no corpo do PR.

## Prova estrutural
`AssetMaitenanceController.php` com `renderManutencoes` usado em `index`/`create`/`edit` · 0 `view('assetmanagement::asset_maintenance.{create,edit}')` · 2 Blades removidas · UC-MANU-05 (#8260) e UC-MANU-06.
