---
sessao: "03"
titulo: "Voz do Cliente — Caixa em React atrás da flag (só leitura) — entregue em draft"
autor: "[CL]"
data: 2026-10-13
base: origin/main f5f11b25
thread: 03-voz-do-cliente.md
pr: 9160
veredito: "entregue em draft — Caixa.tsx + charter (draft) + casos (UC-VOZ-01/02/03/04/07/12/13) + teste na lane acessos-pest, atrás da flag useV2VozDoClienteCaixa (OFF). Triagem fica para o próximo PR: o motivo do fechamento não tem coluna."
---

# _saida-03 · Voz do Cliente em Inertia

Vale também para `telas-soltas/playbook/05` (ver `../../telas-soltas/playbook/_saida-05.md`).

## 0 · Portão

- PERM-VOZ respondida (07/10) e D1 de telas-soltas respondida (07/10): a tela entra em produção como no protótipo.
- A sessão "Playbook Módulos-faltantes 03" de 09/10 **não deixou nada**: caiu no limite de uso no 1º turno. Sem
  branch e sem PR.
- PRs abertos no prefixo: nenhum (4 abertos, varridos via REST). O `dup-detector` **não mediu** (`gh pr list`
  usa GraphQL, bloqueado nesta sessão), e o próprio script avisa para não concluir que ninguém toca.

## 1 · Feito ([#9160](https://github.com/wagnerra23/oimpresso.com/pull/9160), draft)

| arquivo | o quê |
|---|---|
| `Modules/VozDoCliente/Http/Controllers/SinalController.php` | `index()` virou dual: com a flag `useV2VozDoClienteCaixa` ligada para o negócio responde `Inertia::render('VozDoCliente/Caixa')`; desligada (padrão) ou com `?legacy=1`, a Blade. A permissão é checada **antes** da flag. Blade e Page usam a mesma consulta (mesma ordem). |
| `resources/js/Pages/VozDoCliente/Caixa.tsx` | PT-01 read-mostly: cabeçalho, 4 KPIs, chips de situação, tabela com trilha `urgent`/`archived`, vazio e vazio-filtrado. Âncoras `data-contract`: `cabecalho`, `kpis`, `filtros`, `lista`. |
| `resources/js/Pages/VozDoCliente/Caixa.charter.md` | `status: draft`; Non-Goals/Anti-hooks são os do [CC], ainda sem aprovação do [W]. |
| `resources/js/Pages/VozDoCliente/Caixa.casos.md` | 7 UCs com teste; os de navegador e os de triagem ficam em `[BACKLOG]`. |
| `tests/Feature/VozDoCliente/CaixaContratoTest.php` | 7 testes. 2 deles Tier 0: 403 antes da flag sem `vozdocliente.triar`, e isolamento 98 × 99 na contagem e na lista. |
| `.github/workflows/acessos-pest.yml` | o teste entra por arquivo; controller e Page entram no gatilho. |
| `memory/requisitos/VozDoCliente/RUNBOOK-caixa.md` | F1 do MWART. |
| BRIEFING · SCOPE · SPEC · SUPERFICIE do módulo | passam a dizer que a Page existe atrás da flag. |

## 2 · Diferenças do pedido, ditas

- **Caminho da Page:** `resources/js/Pages/VozDoCliente/`, como a prova do índice pede. O módulo não tem
  `Resources/js/Pages`.
- **Triagem fora desta onda.** O protótipo grava "motivo do fechamento", e `voz_sinais` não tem essa coluna.
  Triagem precisa de migration e teste de escrita próprios, então vai em PR separado. Sem ela a tela já cumpre o que a
  Blade fazia, e mais (contagem e filtro).
- **Gravidade:** o schema guarda 0–4, e o protótipo mostra Alta/Média/Baixa. A conversão usa o limiar que o módulo já
  tem (`vozdocliente.severidade_que_exige_triagem`, padrão 3): `≥ 3` Alta, `2` Média, abaixo Baixa. Está escrita no
  charter.
- **UC-VOZ-12:** sem permissão é 403 do servidor, como na Blade. A tela não chega a abrir para explicar (o protótipo
  tem o estado "sem-acesso" com LGPD; ele não existe aqui).
- **UC-VOZ-13** é id novo, porque o caso proposto não tinha isolamento entre negócios como caso próprio.
- **Flag**, não cutover direto: segue o padrão do #9113 (Notificações). Ligar a flag é ato separado.

## 3 · Não medido

- Pest: só pelo CI (lane `acessos-pest`). Não rodei local (regra do projeto), e o CT 100 não foi usado.
- `ui:lint`: não rodei (sem `vendor/`).
- Screenshot e smoke com a flag ligada em biz=1: depois do merge.
- Alvo e contrato de forma (`governance/design/contracts/voz-do-cliente.contract.json`): não feitos.

## 4 · Pendências

- [W]: aprovar Non-Goals e Anti-hooks do charter; ligar a flag para biz=1 e olhar o screenshot.
- [W]: **onde mora o "motivo do fechamento"**: coluna nova em `voz_sinais` (recomendo `triado_motivo`
  `string(200)` nullable) ou fechar sem motivo. Destrava a onda da triagem.
- Cutover: ligar a flag para todos e remover `caixa.blade.php`.

## 5 · Prefixo tocado

Dentro do prefixo: `Modules/VozDoCliente/` e `resources/js/Pages/VozDoCliente/`. Fora dele, por necessidade de
máquina: `tests/Feature/VozDoCliente/`, `.github/workflows/acessos-pest.yml`, `memory/requisitos/VozDoCliente/` e
`memory/reference/MAQUINAS-INVENTARIO.md` (derivado, regerado pelo hook ao tocar o workflow). O
`governance/design/contracts/voz-do-cliente.contract.json` do prefixo não foi criado: falta o alvo. O `nao_toca`
(`Modules/ProductCatalogue/`) não foi tocado.
