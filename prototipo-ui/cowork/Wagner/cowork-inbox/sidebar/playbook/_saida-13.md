---
sessao: "13"
titulo: RODAPÉ · presença clicável e persistida (4 estados)
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main bb1543aff (a ficha 13 chegou no import do handoff 39, PR #7959)
---

# _saida-13 · Presença

## Aberta com o placar em `pendente`

O índice do `main` ainda não tinha a thread 13: ela veio no handoff 39, importado no #7959. Com o índice novo, `placar.mjs --thread 13` deu **`pendente`** só pela falta do trabalho (pré-condições `Invisível` / `Não perturbe` e a prova `execucao`). As dependências 07 e 12 estão feitas e a decisão RESIDUO-6 foi respondida por [W] em 2026-09-25. Este PR deve entrar **depois** do #7959.

## Feito

Entregue **3 de 3** itens do "Faz":

| # | o que | onde |
|---|---|---|
| 1 | coluna `users.ui_presence` (nullable, `string(12)`) + `updatePresence` com `Rule::in` dos 4 ids + rota `POST /user/preferences/presence` ao lado da do tema | migration `2026_09_25_140000` · `UserPreferencesController` · `routes/web.php` |
| 2 | `auth.user.ui_presence` na prop compartilhada, junto do `ui_theme` (null vira `disponivel`) | `HandleInertiaRequests` · `Types/index.ts` |
| 3 | trigger lê ponto + rótulo do estado atual; subpainel com 4 `<button aria-pressed>` e ✓ no atual; clique persiste por `fetch` (mesma forma do `useTheme`) | `Sidebar.tsx` `SidebarUserMenu` |

- Literais do protótipo (`sidebar.jsx` PRESENCAS): ids, rótulos e as 4 cores oklch, copiados sem mudança.
- `Não perturbe` saiu: o protótipo não tem esse estado.
- Sem CSS novo; o ✓ reusa o estilo do `VibesSubpanel`.

## Recibos

- **Pré-condição:** `Sidebar.tsx` contém `Invisível` e não contém `Não perturbe`.
- **Feature test** `tests/Feature/Sidebar/PresencaPreferenciaTest.php`: os 4 válidos gravam a coluna; `nao-perturbe`, `online` e vazio dão 422 sem tocar a coluna; exige login; e o enum do PHP é o mesmo conjunto do `PRESENCAS` do `Sidebar.tsx`. Entrou na lane sqlite. Lá monta uma `users` sintética, para não virar skip-as-pass. No MySQL usa o tenant 98. **Não rodei local** (sem PHP aqui; teste só no CT 100/CI): o veredito é o do CI deste PR.
- **Render** `tests/Feature/Sidebar/presenca.spec.tsx` (vitest): 4 casos, **4 passed**, junto com os 7 do `sidebarAparencia.spec.tsx` (11 passed). **Mordida:** com o `Sidebar.tsx` do main, **os 4 falham**. Restaurei por cópia e o sha256 bateu (`7e0c4e6d0d127ff8`).
- **contrato-de-tela:** `Ocupado` e `Invisível` entraram em `sb-rodape`. `--contract` dá ✅ limpo.
- **tsc:** a catraca `typecheck:baseline:check` fica sem regressão (306 contra 333 do baseline). Os 2 erros em `Sidebar.tsx` (434 e 756) já existiam no main.

## Não feito, e por quê

- **Consumidor da presença** (Atendimento/Equipe): fica fora, como a ficha manda. Por enquanto a presença só grava e mostra.
- **Prova `execucao` (`${REC}/13-execucao.json`):** não escrevi o JSON, pelo mesmo motivo das threads 10 e 12: o avaliador de recibo não foi portado (ADR 0397). Os dois testes acima são a execução real.
- **Runtime no browser:** não medi. Fica para o smoke pós-merge, que precisa da migration aplicada em prod.

## Descobertas

- **`Disponível` ficou com menos sítios de código.** O `_nota_o_que_NAO_prova` do contrato contava 3 sítios e chamava a trava dessa copy de carimbo. Agora ela vive num sítio só (o `PRESENCAS`). Não re-medi a mutação de sítio único; quem endurecer o contrato deve re-rodar a bateria (c).

## Prefixo tocado

`resources/js/Components/cockpit/Sidebar.tsx` · `database/migrations/` · `routes/web.php` · `app/Http/` (controller + middleware) · `tests/Feature/Sidebar/` · `governance/design/contracts/cockpit-sidebar.contract.json` · este `_saida-13.md`. Fora do prefixo, só `resources/js/Types/index.ts` (o tipo da prop, 3 linhas) e `.github/ci-sqlite-pest.list` (para o teste rodar). Nada do `nao_toca` foi alterado.
