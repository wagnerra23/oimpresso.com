# Plano de PRs — módulo Ponto (F1 → F3)

> **Para quem:** [CL] Claude Code, no `main`. Escrito por [CC] em 20/08/2026 a partir do protótipo
> deste projeto Cowork (`ponto-*.jsx/css`) e da leitura do espelho local do repo (`Modules/Ponto/**`).
> Nada aqui está commitado — a ponte é [W] colar 1× ou a Issue `cowork-intake`.

## Premissas de tamanho (a limitação do Code por PR)

Cada PR abaixo respeita, por construção:

- **1 assunto por PR.** Uma tela, ou uma peça de infra, nunca as duas.
- **≤ 8 arquivos tocados** e **≤ ~350 linhas de diff** (fora `.md`/`.json` de governança).
- **Sem migration + UI no mesmo PR.** Schema anda sozinho, com seed e teste.
- **Sem refactor de vizinho.** Se um arquivo fora do escopo pedir mudança, vira PR próprio.
- **Verde nas lanes required** antes de seguir: `Casos-coverage · ratchet`, `Unit`, Pest Financeiro/NfeBrasil,
  `cowork-ssot-guard`, `prototipo-readiness`.
- **Ordem importa:** um PR só entra quando o `Depende de` está mergeado. Onde há `⛔ [W]`, o PR
  **não abre** antes da decisão — abrir é inventar lei.

Legenda de risco: 🟢 mecânico · 🟡 tem regra de domínio · 🔴 toca imutabilidade/multi-tenant.

---

## Onda A — fundação (3 PRs)

### PR-01 · Rota + shell do módulo `/ponto` em Inertia 🟢
- **Faz:** `Ponto/Index.tsx` com AppShellV2 + `ModuleTopNav` das 13 abas (só navegação e estados vazios).
- **Arquivos:** `Http/Controllers/DashboardController.php` (render Inertia), `Pages/Ponto/Index.tsx`,
  `Resources/menus/topnav.php` (acrescenta Fechamento/Conformidade/REP-P), rota.
- **Aceite:** abre com `ponto.access`; sem permissão 403; nenhuma aba quebra; 0 erro de console.
- **Depende de:** —

### PR-02 · Primitivos da tela (pílulas, KPI, tabela densa, paginação) 🟢
- **Faz:** porta `ponto-ui.jsx` para `Components/ponto/*` usando o DS vivo (nada de CSS novo global).
- **Arquivos:** 6 componentes + 1 barrel.
- **Aceite:** Storybook/visreg do estado default; zero cor fora de token (lane `scheme`/`dsih`).
- **Depende de:** PR-01

### PR-03 · Contratos de Tela + âncoras 🟢
- **Faz:** entra com os 4 `*.contract.json` e as âncoras `data-contract` nas telas já existentes.
- **Arquivos:** `prototipo-ui/contrato/ponto-{painel,espelho,fechamento,rep-p}.contract.json` + âncoras.
- **Aceite:** `contrato-de-tela` (advisory) roda e aponta só o que ainda não existe.
- **Depende de:** PR-01

---

## Onda B — leitura (4 PRs)

### PR-04 · Painel 🟡
- **Faz:** 6 KPIs + nota "o que trava o fechamento" + fila de aprovações + atividade recente.
- **Regra:** KPI e fila saem da **mesma fonte** que as abas (estado no shell, não por aba) —
  regressão UC-PTF-06.
- **Props:** `Inertia::defer` em `kpis` e `aprovacoes`.
- **Aceite:** contrato `ponto-painel` verde; nenhum número hardcoded; empty state real quando 0.
- **Depende de:** PR-02, PR-03

### PR-05 · Espelho — lista 🟡
- **Faz:** filtros (competência, escala, busca, só-divergência) + tabela com Trabalhado/HE/Saldo BH/divergências + paginação.
- **Regra:** toda coluna de apuração é guardada por `controla_ponto` (quem não bate ponto mostra `—`).
- **Aceite:** paginação server-side; filtro em query string, não em session.
- **Depende de:** PR-04

### PR-06 · Espelho — individual + drawer do dia 🔴
- **Faz:** cabeçalho legal, 6 totalizadores, tabela dia-a-dia, grade do mês, drawer com marcações
  (NSR, origem, REP, hash) e **anulação append-only**.
- **Regra:** botão Anular cria registro de anulação (nunca UPDATE/DELETE); desabilitado em competência fechada.
- **Aceite:** teste que prova que anular **não** altera a linha original; contrato `ponto-espelho` verde.
- **Depende de:** PR-05

### PR-07 · Folha de impressão do espelho 🟢
- **Faz:** liga a view `reports/espelho-pdf` ao botão (ou porta a folha para print CSS na tela nova).
- **Aceite:** 15 colunas + totais + DSR + assinaturas; divergência destacada; cabe em A4 e Letter.
- **Depende de:** PR-06

---

## Onda C — decisão (3 PRs)

### PR-08 · Aprovações + decisão em lote 🟡
- **Faz:** filtros estado/tipo, seleção múltipla, barra de lote com **motivo único** obrigatório na rejeição.
- **Regra:** só `PENDENTE` entra no lote; aprovar dispara reapuração do dia (job), não recalcula na tela.
- **Aceite:** rejeitar sem motivo não muda nada (guarda testada).
- **Depende de:** PR-04

### PR-09 · Intercorrências — lista, form, ficha 🟡
- **Faz:** CRUD do rascunho, submeter/cancelar, ficha com rastreio (solicitante, aprovador, motivo).
- **Regra:** sem "Dia todo", início e fim são obrigatórios; nada de registro sem janela.
- **Aceite:** nenhuma célula imprime `null`; máquina de estados respeitada (rascunho edita, aprovada não).
- **Depende de:** PR-08

### PR-10 · Banco de horas + ajuste manual 🔴
- **Faz:** totais, saldos, extrato por colaborador, ajuste manual com observação obrigatória.
- **Regra:** ajuste é lançamento novo no ledger (append-only); teto/piso do `config.banco_horas`.
- **Aceite:** teste que prova imutabilidade do movimento anterior.
- **Depende de:** PR-04

---

## Onda D — cadastro e admin (5 PRs)

### PR-11 · Escalas (lista + form + turnos em leitura) 🟢 — depende de PR-02
### PR-12 · Colaboradores (busca, filtros incl. **sem PIS**, configuração de ponto) 🟡 — depende de PR-02
### PR-13 · Importações (histórico, upload, diagnóstico, amostra de erros, progresso) 🟡 — depende de PR-02
### PR-14 · Relatórios (catálogo + wizard de filtros + fila de pedidos) 🟡 — depende de PR-02
- **Regra:** o que não existe em `ReportService` sai marcado `NAO_IMPLEMENTADO` — a tela não promete 501 como sucesso.
### PR-15 · Configurações (read-only) + cadastro de REPs 🟢 — depende de PR-02

---

## Onda E — fechamento (⛔ decisão antes)

### PR-16 · ADR + schema do estado da competência 🔴 ⛔ [W]
- **Bloqueado por:** decisões 1, 2 e 4 do `HANDOFF-ponto.md` (onde vive o estado, permissão, reabrir).
- **Faz:** ADR + migration `ponto_competencias` (business_id, competência, estado, exceções, autor, timestamps) + seed + policy.
- **Aceite:** só schema e teste; **nenhuma** tela neste PR.

### PR-17 · Tela de Fechamento 🔴
- **Faz:** trilha de 4 passos, pré-checagem com grau e atalho, consolidar / consolidar com exceções / fechar / reabrir.
- **Regra:** pré-checagem conta **só a competência selecionada** (UC-PTF-04); consolidar não recalcula (UC-PTF-05).
- **Aceite:** contrato `ponto-fechamento` verde; UC-PTF-01/02/04/05 com teste.
- **Depende de:** PR-16, PR-08, PR-06

### PR-18 · Painel de Conformidade 🟡
- **Faz:** 6 verificações (Art. 66 · Art. 71 · Art. 59 · NSR Anexo I · jornada aberta · sem PIS) com caso a caso.
- **Regra:** cada apontamento cita o artigo, o apurado e o limite — número sem lei não entra.
- **Aceite:** UC-PTF-07 com teste por regra (fixtures: almoço 35 min, HE 2h30, NSR fora de ordem, ativo sem PIS).
- **Depende de:** PR-17

---

## Onda F — REP-P (3 PRs)

### PR-19 · API: fechar o contrato mobile 🔴
- **Faz:** rota Sanctum + escopo `ponto:marcar`, validação anti-cheat já escrita no Service, endpoint
  `pendentes-validacao`. Sem tela.
- **Aceite:** 422 nos três anti-cheat (selfie < 100KB, accuracy > 500m, drift > 30s); geofence **sinaliza**, não recusa; log sem PII.
- **Depende de:** —

### PR-20 · App do colaborador (bater ponto · meu espelho · justificar) 🟡
- **Regra:** alvos ≥ 44px; NSR é server-authoritative; justificar cria intercorrência PENDENTE de verdade.
- **Aceite:** teste de que a justificativa aparece na fila do gestor; nenhuma marcação com NSR fora de sequência.
- **Depende de:** PR-19, PR-09
- **⛔ [W]** decisões 5 e 6 (GPS ruim persistente e copy da selfie) — se ficarem abertas, o PR entra **sem**
  o caminho "bater mesmo assim" e com a copy atual.

### PR-21 · Fila de validação do gestor 🟡
- **Faz:** lista dos últimos 7 dias com NSR, device, lat/lng, precisão, hash truncado + validar/recusar.
- **Aceite:** recusar deixa o dia em divergência (não apaga marcação); contrato `ponto-rep-p` verde.
- **Depende de:** PR-19, PR-20

---

## Onda G — governança (2 PRs)

### PR-22 · Trio das telas 🟢
- **Faz:** `Fechamento.charter.md` + `Fechamento.casos.md` (já escritos) e charter/casos das outras telas que
  o `prototipo-readiness` acusar sem trio.
- **Aceite:** `screen:files -- Ponto/*` sem `✗`; `Casos-coverage · ratchet` não regride.

### PR-23 · Contrato de Tela required para Ponto 🟢
- **Faz:** promove `contrato-de-tela` de advisory a required **apenas** para os 4 contratos do Ponto.
- **Aceite:** os 4 verdes por 3 execuções seguidas antes de promover.
- **Depende de:** PR-03 e todas as telas dos contratos mergeadas.

---

## Ordem enxuta (caminho crítico)

```
A: 01 → 02 → 03
B: 04 → 05 → 06 → 07
C: 08 → 09 ; 10 (paralelo)
D: 11 · 12 · 13 · 14 · 15 (paralelos, qualquer ordem)
E: [W] → 16 → 17 → 18
F: 19 → 20 → 21 (paralelo a D)
G: 22 → 23 (fecha)
```

23 PRs. Só 3 são 🔴 de imutabilidade (06, 10, 19) e um é 🔴 de schema (16) — esses vão sozinhos,
com teste de append-only no próprio PR. As ondas D e F correm em paralelo à C sem colidir de arquivo.

## O que NÃO fazer em nenhum PR

- Recalcular apuração fora de `ReapurarDiaJob`
- `UPDATE`/`DELETE` em `ponto_marcacoes` ou em movimento de banco de horas
- Filtro em session storage (é query string)
- Mock/`rand()` em controller ou Page
- CSS global novo ou cor fora dos tokens do DS vivo
- Misturar migration com UI, ou duas telas no mesmo PR
