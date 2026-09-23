---
id: requisitos-mwart-onda-2-clientes-plano
module: Mwart
doc_type: plano-onda
onda: 2
title: "Onda 2 — Clientes & contatos · plano de layout em ondas (Code × Design)"
status: f1-plan
owner: wagner
author: "[CL]"
created: "2026-09-23"
adversario_cd: "Attio"
parent_roadmap: "memory/requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md"
related_adrs: [0093, 0104, 0114, 0277, 0409]
---

# Onda 2 — Clientes & contatos · plano de layout (Code × Design)

> **O que é:** o plano para alinhar as telas de Cliente ao protótipo do Claude Design (Cowork),
> dividido em etapas pequenas e com o trabalho repartido entre **Code** `[CL]` e **Design** `[CC]`.
> É a Fase 1 (PLAN) do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
> aplicada à **Onda 2** do [roadmap](ROADMAP-ONDAS-BLADE-ADVERSARIOS.md). Nenhum código foi mudado.
>
> **Origem:** workflow `migracao-layout-em-ondas`, modo `plano`, run `wf_bb75f82e-fb9`
> (2026-09-23, base `e49b761eaf1` = `origin/main`), mais a conferência feita pelo `[CL]` na mesma sessão.
> Os números de censo vêm das saídas daquele run; os fatos marcados **(conferido)** foram medidos de
> novo pelo `[CL]` antes de entrar aqui.
>
> **Por que Clientes primeiro:** é a família de telas que [W] já colocou em produção
> ([W] 2026-09-23: *"clientes que seria o contatos é a única tela que consegui colocar em produção"*).

## 1. Estado em produção (conferido)

`governance/prod-flags.json` (gerado por `php artisan governance:prod-flags` a partir do `.env` de
produção; último commit do arquivo em 2026-09-15) lista as **7 telas de Cliente em React para todos
os tenants** (`["*"]`): Index, Show, Create, Edit, Import, Map, Ledger.

Consequências:

- Trocar o layout **chega a todos os clientes** no deploy. Não existe canary por flag: a
  reversão é o `git revert` do `.tsx`, ou desligar a env `MWART_CLIENTE_*` no Hostinger (volta pro Blade).
- ⚠️ **Correção ao run do workflow:** ele concluiu "flag desligada = alcance zero" lendo o
  *default* de `config/mwart.php`. O estado real é o do `.env` de produção, e ali tudo está ligado.
  O próprio `config/mwart.php` já avisava: *"o `false` abaixo é só fallback de ausência da env"*.
- O estado muda quando alguém edita o `.env`. Antes de executar qualquer etapa, re-rode o produtor
  (ou leia o `prod-flags.json` do dia). Não cite este parágrafo como estado vivo.

## 2. Defeitos já em produção (conferidos) — não são de layout

| # | Tela | Defeito | Evidência |
|---|---|---|---|
| D1 | Import | **Erro de importação some na versão React.** No erro, o POST volta com `->with('notification', …)`; o ramo Inertia do GET só preenche `notification` quando falta a extensão zip; o middleware `HandleInertiaRequests` compartilha `status.*`/`success`/`error`, não `notification`. Sucesso aparece (vai pro index com `status`); **falha não**. | `ContactController.php:2803-2815` e `:3066` · `HandleInertiaRequests.php:100-115` |
| D2 | Import | **Link do modelo provavelmente dá 404.** React aponta `/uploads/sample_files/sample_contact.xlsx` (não versionado); Blade usa `public/files/import_contacts_csv_template.xls` (versionado). Pode existir só no servidor — confirmar com `curl -sI` em produção antes de corrigir. | `Import.tsx:166` · `public/files/` |
| D3 | Import | Copy "Tamanho máximo: 10 MB" sem validação no backend; extensões na copy (`.xlsx, .csv`) diferem do `accept` (`.xlsx,.xls,.csv`). | `Import.tsx:181,217` |
| D4 | Import, Map, Create | "Voltar"/"Cancelar" apontam `/contacts/customer`, que não casa rota dedicada (cairia em `resource show('customer')`). A lista canônica é `/cliente`. **Confirmar em runtime** (`route:list` no CT 100 ou `curl`) antes de corrigir. | `Import.tsx:123,252` · `Map.tsx:82` · `Create.tsx:90,114` |
| D5 | todas | **Nenhum teste de Cliente roda em lane de PR.** 64 arquivos em `tests/Feature/Cliente/` (conferido com `git ls-files`), nenhuma `.github/*.list` ou workflow os referencia. A lane foi removida em 2026-07-27 (`659a6bd02f9`: todos em 403 por falta de permissão semeada). Os `casos.md` de Map/Import ainda dizem "passa no CI". | `git grep "Feature/Cliente" -- .github` = vazio |

## 3. Quem faz o quê

A regra que separa, pela [ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
e pela [regra de precedência](../../proibicoes.md):

- **FORMA** (layout, hierarquia, cor, tipografia, rótulo, estado visual) → o **protótipo manda**.
  Decisão de forma é do **Design** `[CC]`, registrada no protótipo, e o Code copia.
- **COMPORTAMENTO** (o que a tela faz, dados, permissões, validação, `business_id`) → manda o
  **teste verde**, depois `casos.md` → charter → SPEC. Protótipo não cria comportamento. Mudança de
  comportamento é decisão **[W]**; quem implementa é o **Code** `[CL]`.

| Quem | Faz | Não faz |
|---|---|---|
| **Design `[CC]`** | Corrige o protótipo onde ele promete o que o sistema não faz (§4). Decide a forma pendente (cabeçalho, contagem, estados vazios). Regenera o bundle ao fim do ciclo (rotina obrigatória, decisão [W] 2026-09-06). | Não edita `resources/js/**` nem o espelho `prototipo-ui/cowork/**` do repo (o espelho só recebe pela máquina). |
| **Code `[CL]`** | F0, F1 e as etapas de layout (§5). Baixa o protótipo pela rota do painel (`node scripts/design/protocolo.config.mjs`). Mede antes/depois com `design-diff.mjs`. Reporta em `HANDOFF.md` + `SYNC_LOG.md` a cada PR. | Não inventa forma que o protótipo não tem; não porta dado de demonstração (`CM_CIDADES`) nem função que o backend não tem. |
| **[W]** | Aprova cada etapa antes da execução, decide comportamento (§6) e faz o merge. | — |

## 4. O que o Design precisa ajustar no protótipo (antes da etapa de layout)

Pedido formal: [`CODE_NOTES.prompt-cowork-onda2-clientes-2026-09-23.md`](../../reference/prototipo-ui/CODE_NOTES.prompt-cowork-onda2-clientes-2026-09-23.md).

1. **`cliente-import.jsx` promete importação parcial** ("N importados · M linhas com erro",
   "Baixar as linhas com erro", `:37-39,112`). O backend é **tudo ou nada** (`DB::beginTransaction`
   → `rollBack`) e não gera arquivo de retorno. Tirar essa promessa, ou marcá-la como proposta de
   funcionalidade futura, fora da tela a copiar.
2. **`cliente-mapa.jsx`**: a frase "a posição vem do CEP" descreve geocodificação, que o charter do
   Map proíbe (Non-Goal). Ajustar a copy.
3. **`cliente-mapa.jsx`** não tem "Ver detalhes" nem o celular do contato, que a tela em produção tem.
   Incluir no cartão flutuante, ou declarar que saem.
4. **Cabeçalho das telas utilitárias** (Map, Import, Create/Edit): o protótipo usa `.os-page-h` plano
   com "← Clientes"; o repo tem o `PageHeader` canônico, sem slot de voltar. Decidir no protótipo qual
   padrão vale para as telas de Cliente.
5. **Contagem "M sem posição" do Map:** `todos − com posição` ≠ `sem coordenada válida` (posição
   preenchida mas inválida conta como "com posição"). Dizer qual número a tela mostra.

## 5. Etapas (uma por execução, cada uma com aprovação [W])

Nomes F0/F1/L1 para não colidir com a numeração da [ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md),
onde Clientes já é a Onda 2. Trocar layout do React **não** conta como "migrado" no `module-surface --migracao`
(o Blade continua respondendo se a env for desligada).

| Etapa | Escopo | Quem | Depende de | Reversão |
|---|---|---|---|---|
| **F0** | Voltar a rodar os testes de Cliente em lane de PR: semear permissões na base limpa, migrar os testes para o tenant 98 (99 como adversário), listar na `.list`, corrigir o "passa no CI" dos `casos.md`. Sem tela. | Code | aprovação [W] | tirar da `.list` |
| **F1** | Consertar D1 (erro visível no Import, de preferência no controller — raio local) e D2 (modelo). POST e saldo de abertura **intocados**. Teste novo: planilha com colunas erradas → mensagem aparece. | Code | F0 | revert |
| **L1** | Layout de **Cliente/Map + Cliente/Import** pelo protótipo corrigido (§4). Corrige D3/D4 junto. | Code | F0, F1, §4 feito pelo Design | revert do `.tsx` |
| L2 | Layout de **Cliente/Create + Cliente/Edit** (mesmo `cliente-form.jsx`). Exige inventário campo a campo antes. | Design + Code | L1 | revert |
| L3 | Layout de **Cliente/Index** (lista + drawer 760). Maior raio: é a tela mais usada. | Design + Code | L2 | revert |
| — | **Cliente/Ledger** fica fora: mostra valores → regra mestre VALOR (dupla prova + antes→depois + aprovação [W]). | — | decisão [W] | — |

Limite: no máximo 2 telas por etapa ([PROTOCOL §8](../../reference/prototipo-ui/PROTOCOL.md)).

**Como validar cada etapa de layout** (donos que já existem, nada novo):
`ancora.mjs <Tela> --staging prototipo-ui/cowork` → `design-diff.mjs --probe` antes e depois
(medido, nunca no olho — skill `comparar-design-prod`) → `casos-coverage-guard` → Pest no CT 100
(tenant 98/99) → smoke manual em biz=1 com screenshot pós-deploy, conferindo o **bundle servido**
e não só o SHA. Nunca biz=4. Baseline visual **não se regrava** ([ADR 0409](../../decisions/0409-zero-baseline-de-tolerancia-conformidade-absoluta.md)).

**Guardar sempre:** `business_id` da sessão nas queries, `abort(403)` por `customer.*`/`supplier.*`,
POST de import byte-idêntico, `coordsDe()`/`osmEmbedSrc()` do Map, a acessibilidade atual do Import
(teclado, `aria-label` dinâmico) e a âncora de texto do visreg ("Mapa de clientes").

## 6. Decisões [W] abertas

1. **Ordem:** começar por F0 → F1 → L1 (Map + Import, menor risco) ou pular para o Index, que é a tela mais usada?
2. **F0:** autoriza voltar a lane de Cliente com seed de permissão?
3. **Import:** importação parcial com arquivo de erros entra como funcionalidade (mexe em saldo de abertura = VALOR) ou sai do protótipo? Recomendação: sai.
4. **Map:** cliente sem coordenada passa a ser clicável (como no protótipo) ou continua desabilitado? A seleção inicial é o primeiro da lista ou nenhum?
5. **Map:** recuperar o filtro de vários contatos do Blade (`contacts[]`) ou seguir o charter (um por vez)?
6. **Visreg:** autoriza trocar a âncora de texto do Import se a copy "Passo 1 — Baixe o template" mudar?

## 7. Fora deste plano

Sells/*, Produto/*, Manufacturing/Recipes, Cliente/Ledger (VALOR/ESTOQUE); telas só-Blade (Crm,
Woocommerce, Spreadsheet…); as 65 telas com mapeamento ambíguo do censo. Ficam no relatório do run
`wf_bb75f82e-fb9` até alguém responder a dúvida de cada uma.
