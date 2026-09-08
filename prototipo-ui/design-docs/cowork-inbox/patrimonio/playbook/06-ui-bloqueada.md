---
sessao: "06"
titulo: A UI — DESTRAVADA em 2026-09-08; vira frente de 5–7 threads, uma por tela
dono: "[W]"
base: cb475c0ca2f4
prefixo: — (nenhum; cada thread-filha declara o seu)
nao_toca: resources/js/Pages/** enquanto a frente não for aberta thread a thread
depende: — (D-ENDERECO respondida)
---
# 06 · UI — DESTRAVADA (era: bloqueada)

> 📌 **A frente foi ABERTA em 2026-09-08 [CL] — são 7 threads, e o `§7` do índice não lista mais a `06`.**
>
> | thread | tela | rota hoje | estado |
> |---|---|---|---|
> | [`07-painel.md`](07-painel.md) | Painel | `dashboard` | **1ª** — cria o `_shared/PatrimonioSubNav.tsx` |
> | [`08-bens.md`](08-bens.md) | Bens | `assets` | atrás da 07 |
> | [`09-alocacoes.md`](09-alocacoes.md) | Alocações | `allocation` **+** `revocation` | atrás da 07 · **fusão de 2 rotas numa aba** |
> | [`10-manutencoes.md`](10-manutencoes.md) | Manutenções | `asset-maintenance` | atrás da 07 · **carrega o D1** |
> | [`11-configuracoes.md`](11-configuracoes.md) | Configurações | `settings` | atrás da 07 |
> | [`12-garantias.md`](12-garantias.md) | Garantias | **nenhuma** | **bloqueada** por `D-GARANTIAS` |
> | [`13-auditoria-bloqueada.md`](13-auditoria-bloqueada.md) | Auditoria | **nenhuma** | **bloqueada** por `D-AUDITORIA` |
>
> **A conta não é 7 telas migradas.** São **5 migradas** (as que têm rota Blade), **1 tela nova sobre
> dado que já existe** (`asset_warranties` tem migration e já é lida no `dashboard()` — falta rota e
> tela) e **1 que talvez não deva existir**: o `Modules/Auditoria` já é dono da trilha por-registro,
> e abrir uma segunda aqui cria dois donos do mesmo tema.
>
> **A 07 vai sozinha, e isso não é cautela**: ela cria o `_shared` que as outras cinco importam.
> Errar ali custa seis telas, não uma.

> ⚠️ **Este arquivo mudou de natureza em 2026-09-08.** Era o registro de um bloqueio; virou o
> ponto de partida de uma frente. O histórico do bloqueio fica abaixo, datado — não apagado.

## A decisão que destravou

**Endereço: `resources/js/Pages/Patrimonio/**` — módulo próprio.**
[W] em **2026-09-04**, ratificado em **2026-09-08**. Registrada em
[ADR 0394](../../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md);
`SCOPE.md:4` saiu de `bloqueado-escopo` para o endereço decidido.

**O agrupamento de sidebar não muda:** Patrimônio segue **ghost de Estoque** no grupo `operar`
([ADR 0180](../../../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)), com o
item apontando para `AssetController::dashboard` (`DataController.php:109-138`). Endereço de
pasta e agrupamento de menu são **eixos independentes** — foi a confusão entre os dois que
fez a 0180 e a 0182 parecerem divergentes.

## O estado, medido em `c7bd83944f42` (2026-09-08)

- `resources/js/Pages/`: `(?i)(patrimonio|asset)` = **0 arquivos**.
- `Modules/AssetManagement`: **0 `Inertia::render`**. As 6 rotas seguem `Route::resource` Blade.
- Nada dos 46 arquivos existe. **Destravado ≠ começado.**

## Por que isto NÃO é uma thread

**7 telas não cabem numa thread só.** A fonte visual
(`prototipo-ui/cowork/patrimonio-page.jsx:835`) declara 7 abas de topo:

| # | aba | `key` |
|---|---|---|
| 1 | Painel | `painel` |
| 2 | Bens | `bens` |
| 3 | Alocações | `alocacoes` |
| 4 | Manutenções | `manutencoes` |
| 5 | Garantias | `garantias` |
| 6 | Auditoria | `auditoria` |
| 7 | Configurações | `config` |

Os 46 arquivos: 7 Pages + 7 charters + 7 casos + 2 `_shared` + 6 `_components` (**29**) ·
7 `.contract.json` · 6 controllers → Inertia + `Routes/web.php` · 3 testes de tela · a ADR +
o `SCOPE.md` (estes dois **já entregues**).

**Cada tela vira uma thread**, com a ficha do §13.2 e o veredito CABE/NÃO CABE. Onda nunca
maior que 1 PR ≤300 linhas.

## O que continua valendo (não caducou com a decisão)

- **Não pular o MWART** ([ADR 0104](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)):
  `RUNBOOK-<tela>.md` **antes** do `.tsx` — o hook `block-mwart-violation` bloqueia em runtime
  e **não tem override**. Charter + casos ao lado do `.tsx`. O merge do `.tsx` segue humano.
- **Ter protótipo não é ter autorização de escopo.** O `patrimonio-page.jsx` é **alvo**, não
  decisão de produto: 4 das perguntas abertas do §6 do índice (Garantias é tela ou filtro?
  Auditoria é daqui ou do `Modules/Auditoria`? depreciação? baixa?) mudam o que cada tela
  contém. **Decidir a tela antes da pergunta dela é retrabalho.**
- **Ordem sugerida, não imposta:** as telas cujas perguntas de produto estão fechadas primeiro
  (Bens, Alocações); as que dependem de decisão aberta por último (Garantias, Auditoria).

## Histórico — o bloqueio, e por que durou

A pergunta era: `Pages/Patrimonio/**` ou `Pages/Estoque/Patrimonio/**`? Errar custava refazer
~12 arquivos (import, rota, breadcrumb, sidebar, caminho dos charters).

**A decisão existia desde 04/09 e o playbook não sabia.** Estava escrita no cabeçalho do
`.github/workflows/modules-pest.yml:36` (commit `d6457184ea`, PR #6784), com a lane de CI já
apontando para `resources/js/Pages/Patrimonio/**` — enquanto o `SCOPE.md`, que é o dono
canônico, seguia dizendo `bloqueado-escopo`. **Os dois se contradiziam no `main` por 4 dias.**

A thread 04 achou a contradição ao medir e escalou em vez de decidir sozinha
(`_saida-04.md §8`); [W] ratificou em 08/09. **Lição de processo:** decisão registrada só em
comentário de workflow não alcança quem lê o dono canônico — o `SCOPE.md` é onde a próxima
sessão olha, e era ele que precisava mudar.
