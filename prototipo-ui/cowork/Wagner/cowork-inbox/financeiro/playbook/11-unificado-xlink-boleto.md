# 11 · Unificado — vínculo `#BL-` aponta pra rota que não existe

> Emitida pelo [CC] em 2026-10-06, depois de `SINCRONIZAR Boletos` + passada adversarial §16 (o ataque B achou este defeito). Mesmo playbook do Financeiro (anti-scatter): o módulo já tem índice, então não nasce doc novo.
> Base lida: `wagnerra23/oimpresso.com@main` 591ce81d2b33 / 23c3f080aa94 (2026-10-06). **Reler no turno.**
> **Sessão:** limpa · **dono:** [CL] · **vaga:** 1 · **não precisa de ALVO:** muda o destino do clique, não o layout (nenhum px, cor ou nó novo).

## Por que existe
- `Modules/Financeiro/Routes/web.php:173`: `Route::redirect('/boletos', '/financeiro/cobranca', 301)`. Só casa o caminho exato `/boletos`. Fora isso, existe apenas `POST /boletos/{remessaId}/cancelar` (`:174`).
- Os vínculos `#BL-NNNN` montam `GET /financeiro/boletos/NNNN`, que **não tem rota**:
  - `resources/js/Pages/Financeiro/Unificado/_components/FinCrossLinkify.tsx:32`: `href: (n) => \`/financeiro/boletos/${n}\`` (o comentário em `:10` já diz "placeholder").
  - `resources/js/Pages/Financeiro/Unificado/Index.tsx:803`: `FIN_XLINK_DEFS`, chip "Boleto #N" da lente Vínculos, mesmo href.
- A tela Boletos foi aposentada em 2026-05-19 (ADR 0144 + 0170) e a Cobrança a substitui (`Pages/Financeiro/Cobranca/Index.charter.md:16` `supersedes: /financeiro/boletos`).
- **Não testei no navegador.** O 404 é inferido da leitura das rotas. A thread confirma (item 1 da PROVA) antes de editar.

## Alvo de comportamento (o protótipo responde *como*)
No build Cowork (`financeiro-page.jsx`, lente Vínculos + Linkify do título do drawer), clicar em "Boleto #N" ou `#BL-N` abre a **Cobrança** (`window.__go("cobranca")`), o equivalente de `/financeiro/cobranca`. As outras referências (`#V-`, `#OS-`, `#PC-`, `#R-`, `#P-`) ficam como estão.

## Mudança (≤ 10 linhas, 2 arquivos)
1. `FinCrossLinkify.tsx:32`: `href: () => '/financeiro/cobranca'`. Atualizar o comentário de `:10` para `#BL-NNNN → /financeiro/cobranca (Boletos aposentado 2026-05-19, ADR 0144)`.
2. `Unificado/Index.tsx:803`: mesmo href em `FIN_XLINK_DEFS`. Label, ícone e classe ficam.
3. **Manter** o regex `#BL-` nos dois lugares (guarda abaixo).

## NÃO toca
`Routes/web.php` (a rota 301 e o `POST cancelar` são decisão [W], não desta thread) · `BoletoController.php` · `Pages/Financeiro/Cobranca/**` · os outros 5 padrões do linkify · CSS.

## PROVA
1. Antes de editar: `php artisan route:list --path=financeiro/boletos` mostra **só** `boletos.index` (redirect) e `boletos.cancelar` (POST). Se aparecer um GET `boletos/{id}`, **parar** (premissa caiu).
2. `Modules/Financeiro/Tests/Feature/Onda7OutputR3Test.php` segue verde. A `:40` exige `toContain('#BL-')`, e é por isso que o regex fica.
3. Grep: `FinCrossLinkify.tsx` e `Index.tsx` **não contêm** `/financeiro/boletos/`.
4. `_saida-11.md` com os 5 itens + o resultado do item 1 colado.

## PARAR SE
- O item 1 mostrar rota GET para `boletos/{id}`.
- Algum teste além do `Onda7OutputR3Test` afirmar o href antigo: listar quais e devolver, sem reescrever teste pra caber.
- O chip da lente Vínculos não vier de `FIN_XLINK_DEFS` (`:799`): registrar de onde vem e devolver.

## O que esta thread NÃO resolve (bloco 7)
- **`?busca=N` não entra.** `CobrancaController.php:72` aceita `busca` da URL, mas o `#BL-N` só existe nos dados de demonstração (`FinanceiroDemoSeeder.php:273/280/288`); `TituloAutoService` gera só `#V-`/`#PC-` (charter do Unificado `:120`). O número não corresponde a nenhum nosso número real, então a busca abriria a Cobrança vazia. Ligar o vínculo à cobrança certa exige definir o que `#BL-N` identifica. Isso é decisão de dado, fora daqui.
- O `POST /boletos/{id}/cancelar` (carência de 60 dias vencida em 2026-07-18) continua decisão [W].
- O charter do Unificado `:123` ainda cita "Boletos" como destino do linkify. Atualizar o charter é passo do PR da thread (mesmo PR, uma linha), não pedido separado.
