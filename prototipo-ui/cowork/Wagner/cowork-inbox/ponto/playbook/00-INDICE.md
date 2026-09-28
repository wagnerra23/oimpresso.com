---
sessao: "00"
titulo: SINCRONIZAR Ponto — índice do playbook (fonte da máquina embutida em §7)
autor: "[CC]"
criado: 2026-09-06
reescrito: 2026-09-28
base: wagnerra23/oimpresso.com@main (árvore 438b6992ed4b · lida 2026-09-28 18:03 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/
regra: este índice é PEDIDO (lista de threads a executar, com sha), não inventário. Ninguém escreve estado — ele é derivado (§2-bis). A pasta inteira é a unidade de descida.
---

# SINCRONIZAR Ponto — playbook (reescrita de 2026-09-28)

> **Por que reescrito, e não patch:** o índice de 06/09 tinha só as threads 01–12. As 13–31 nasceram em 09/09 e 14/09 como arquivos soltos + 2 patches (`_delta-indice-13a15.md`, `_PATCH-INDICE-2026-09-14.md`) que **nunca entraram no §7** — o placar (`placar.mjs --indice`) não as via. Esta versão **absorve** os dois patches, o `_DECISOES-W-2026-09-24.md` e a `ATA-DECISOES-2026-09-14.md` (que continua sendo o dicionário dos ids D-*). Os arquivos-patch ficam na pasta como histórico; **a fonte é este §7**.
> **Conferido antes de escrever:** a cópia local deste índice era equivalente à do `main` (`c3720f998791`, mesmo conteúdo — a diferença de bytes era só escape de `>`). Nada do `main` se perde com a descida.

## 0 · Landing
Fonte da máquina = 1º bloco ```json deste arquivo (§7). Rodar: `node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/00-INDICE.md --root . --proximo`. Abertura de chip = `_SESSAO-FRIA.md` (1 thread = 1 chip) + `ATA-DECISOES-2026-09-14.md`.

## 1 · LEVANTAR — medido em `438b6992ed4b`

| o quê | medido | mudou desde 06/09 |
|---|---|---|
| Pages | **23** `.tsx` com trio (charter + casos) — 21 + `Fechamento/Index` + `Conformidade` | +2 (threads 04 e 05 entregues) |
| rotas web | `/fechamento` GET+POST (`ponto.fechar`, `routes.php:91-95`) · `/conformidade` (`:102`) | nasceram |
| contratos | **4**: `ponto-painel` · `ponto-espelho` · `ponto-fechamento` · `ponto-conformidade` | +2 · falta `ponto-rep-p` |
| alvos (`governance/design/targets/`) | **0 do Ponto** (existem jana · financeiro · sidebar) | — ⇒ toda thread de FORMA sai `exit 2 NÃO MEDI` sem ALVO antes |
| `data-contract` nas Pages | 18: Painel 4 · Espelho/Show 5 · Fechamento 4 · Conformidade 4 · (Espelho/Index 0) | as 18 telas de `ponto-telas.jsx` seguem **sem** par vivo (thread 17) |
| gap.md (`memory/requisitos/Ponto/`) | 3: dashboard-index · espelho-index · espelho-show | as 8 propostas 16/20–26 **não aplicadas** |
| API REP-P | **7 closures `abort(501)`** (`routes.php:129-139`) · `MobileMarcacaoController` sem rota | igual — espera W10 |
| relatórios legais | `RelatorioController.php:102` — 7 chaves `abort(501)` | igual — thread 12 destravada (W7) |
| e2e | `e2e/ponto-{dashboard,espelho,espelho-show,conformidade}.spec.ts` | +1 |
| rota `/react` (Welcome) | `routes.php:34` — viva | W8 aberta |
| DS átomos que as threads de forma pedem | `shared/Toolbar.tsx` · `shared/KpiCard.tsx` · `ui/card.tsx` existem; `ds-atomos` `_saida-01/02/03` no `main` | **destrava 13–15** |

## 2 · Placar honesto — quantos PRs faltam

| bloco | threads | PRs | trava |
|---|---|---:|---|
| **Entregue** | 01 · 02 · 03 · 04 (6 PRs) · 05 (3 PRs) · 08 · 09 · 10 · 11 | — | — |
| **A · Funcional** | 06 REP-P (ValidacaoMobile → API → Page+contrato) · 07 contratos required · 12 AFD → AEJ | **6** | 06/07: **W10** · 12: nenhuma |
| **B · Âncora e governança** | 16 · 20–26 (8 gap.md) · 17 (`data-contract` no vivo, 3 lotes ≤8 arquivos) · 27 (charters · casos · guards) | **14** | 17 e 27 depois dos gaps |
| **C · Paridade de forma** | 32–34 ALVO (Painel · Espelho lista · Aprovações) + 13–15 forma · depois 17 ALVO + 18 forma das outras telas (não emitidas) | **6 + 35 = 41** | 13: W14 · 15: **W15** · 16+: **W11** · telas de detalhe: 28 |
| **[CC] build (não é PR do Code)** | 28 rota própria — 5 ondas, 1 por símbolo | 5 ondas | — |
| **Fora do placar** | 18 (absorvida por 06/07) · 29 (respondida) · 30 (absorvida: PR1→05, PR2→04, PR3–4→06, PR5→12) · 31 (recibo) — tiradas do §7, arquivos ficam | 0 | — |
| **Total do Code** | | **61** (20 sem forma) | |

**Leitura rápida:** se [W] responder **W10**, os blocos A+B (**20 PRs**) têm tudo o que precisam. O bloco C é o grosso (41) e só o começo (6) está emitido — o resto se emite quando entrar em vaga (playbook antecipado envelhece).

## 2-bis · ESTADO — derivado, nunca escrito
> `_saida-NN.md` presente **e** provas verdes = `feito`; sem `_saida` = não feito mesmo com PR mergeado.

**Render simulado (28/09):** rodei a lógica do `placar-indice.mjs@main` portada, com o disco trocado por fatos lidos no `main` neste turno (não é o script real):
`Ponto: entregue 9 de 29 · próximo 13 · em curso 0 · pendente 7 · bloqueada 0 · 0 sem recibo`
- **feito (9):** 01 · 02 · 03 · 04 · 05 · 08 · 09 · 10 · 11
- **próximo (13):** 12 · 16 · 20 · 21 · 22 · 23 · 24 · 25 · 26 · 28 [CC] · 32 · 33 · 34
- **pendente (7):** 06 (W10) · 07 (←06) · 13 (W14 ←32) · 14 (←33) · 15 (W15 ←34) · 17 (←gaps) · 27 (←17)
- **fora do §7 (4):** 18 · 29 · 30 · 31 — o placar ignora `sem_pr` e as poria em `próximo`.

### Vagas (Lei 1 — prefixos não se cruzam dentro da vaga)
- **Vaga 1 (agora):** 12 (AFD) ∥ 16 ∥ 20 ∥ 21 ∥ 22 ∥ 23 ∥ 24 ∥ 25 ∥ 26 (cada gap é um arquivo próprio em `memory/requisitos/Ponto/`) ∥ 32 ∥ 33 ∥ 34 (cada ALVO é um slug próprio) · [CC] 28.
- **Vaga 2:** 17 (3 lotes) ∥ 13 ∥ 14 ∥ 15 ∥ 12 (AEJ) — 27 **depois** de 17 (as duas tocam o que o gap nomeou; a ata manda 27 como UMA onda).
- **Vaga 3:** ALVO + forma das 17 telas restantes (W11) · 06 → 07 (W10).
Ordem que [W] fixou em 14/09 continua valendo: **28 + ALVO primeiro**, depois 22 · 20 · 23, depois 27, depois 21 · 24 · 25 · 26.

### Fluxo (6 passos, iguais para toda thread)
```
1 ABRIR    sessão limpa · gh pr list --state open × arquivos do prefixo · colar _SESSAO-FRIA · ler NN-*.md + âncora no main
2 MEDIR    (forma) o ALVO da tela tem de existir: governance/design/targets/ponto--<pasta>--<page>.alvo.json — senão PARE (exit 2)
3 GERAR    (só 06) criar-tela.mjs Ponto/Mobile → tsx+charter+casos+e2e+contrato juntos
4 APLICAR  1–3 arquivos do prefixo · reusar átomos · PARAR SE vale mais que terminar
5 PROVAR   provas do NN verdes · placar no corpo do PR · lane ponto-pest verde
6 FECHAR   _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) → parar
```

## 3 · Abertura de thread
Use o prompt de `_SESSAO-FRIA.md`. Leis do módulo que não se renegociam: marcação append-only (Portaria MTP 671/2021) · apuração só em `ReapurarDiaJob` · NSR server-authoritative · artigo literal na copy legal · 501 nunca é sucesso · sem biometria (ADR 0383).

## 4 · VERIFICAR
`PLACAR Ponto` = `node scripts/qa/placar.mjs --indice <este arquivo> --proximo`, ou [CC] lendo o `main` no turno. T7 (`design-diff --compare --check`), CI e `casos:report` **não são visíveis daqui** — o placar afirma "arquivos verdes", nunca "igual ao design".

## 5 · O que esta reescrita corrigiu (e onde eu posso estar errado)
- **03 tinha prova errada:** exigia a string `sr-only` em `MonthHeatmap.tsx`; o trabalho foi feito por glifo + `aria-label` (#6407 · #6777, axe 28 passed). A prova agora mede o comportamento (`aria-label`), e a 03 conta como feita — é o conserto que o `_saida-03` pediu a quem emite o índice.
- **04 e 05 tinham `provas: []`** (o `_DECISOES` avisou que o `_saida` sozinho as contaria). Ganharam provas de arquivo que já são verdadeiras nesta sha.
- **07** perdeu `ponto-fechamento` do prefixo (já existe) e ganhou a `ponto-conformidade` como guarda; falta só `ponto-rep-p`.
- **13–15** deixaram de depender só de `ds-atomos` (feito) e passaram a depender do **ALVO** (32–34), que não existia. Sem isso o `/onda` sai `exit 2`.
- **Slug do ALVO segue o caminho da Page, não o nome da aba.** `pedido.mjs::acharAlvo` casa `--tela Ponto/Dashboard/Index` com o arquivo ignorando separadores — `ponto--painel` **nunca** seria achado. Os 3 alvos são `ponto--dashboard--index` · `ponto--espelho--index` · `ponto--aprovacoes--index`.
- **Toda seção do `secoes.json` precisa de `.dado`** (Model/Service/campo real) ou o `pedido.mjs` REPROVA (exit 1). As sementes 32–34 saem com o `dado` lido nos 3 controllers neste turno.
- **15 tinha prova já verdadeira:** `BulkActionBar` está em `Aprovacoes/Index.tsx:52` antes de qualquer PR da thread — pela regra do `/onda` ("desconfie da prova") daria `feito` sem trabalho. E o "motivo do lote" que ela pede **não tem endpoint**: o controller só tem `aprovarEmLote`. Virou **W15**.
- **Espelho · lista:** o protótipo mostra Escala · Trabalhado · HE · Saldo BH · Controla ponto; o `EspelhoController@index` entrega só `id · matricula · cpf · nome · email` (paginado 25). A 33 declara essas colunas como `campo inexistente` — a 14 não pode prometê-las sem backend.
- **O seletor `.cli-ph` dos alvos só existe depois que o build de 28/09 descer** (`cli-pagehead.jsx?v=cp14`). Medir antes do import = `exit 2` na seção header — está no PARAR SE das 3.
- **Adversário (28/09) — lido no `placar-indice.mjs@main`:** (1) `guarda` **não existe** mais no avaliador: prova de preservação conta como prova comum — tirei o campo. (2) Thread com `provas: []` e sem `_saida` vira `próximo`: 18/29/30/31 saíram do §7. (3) Thread cuja única prova já é verdadeira vira `sem recibo` assim que as dependências fecham — a 15 ficou com `provas: []` pelo mesmo motivo. (4) O avaliador não conhece `pergunta`/`prs`/`sem_pr` — ignora, não reprova.
- **Não verifiquei:** o conteúdo dos 8 gap propostos contra os `.tsx` vivos (as próprias threads declaram "lado vivo TODO"), o estado do CI, e se a `ValidacaoMobile` (PR 3 da 30) tem algum código no `main` — procurei só por nome de controller.

## 6 · RESÍDUO — fila de decisão [W]
Respondidas e riscadas: W1–W7 (ADR 0413 / 0383). Abertas:
1. **W8** `/ponto/react` (Welcome piloto): manter ou remover? — só a parte `/react` da 11.
2. **W9** Navegação: 13 abas de área (protótipo) × `PontoSubNav` 5 + ⋯ (produção, ADR 0182). *Nota 28/09:* o protótipo agora rola até a aba ativa e sublinha só em roxo — a forma das abas convergiu; a **quantidade** ainda diverge.
3. **W10** Ratificar o REP-P sem selfie: 3 telas do colaborador (bater · meu espelho · justificar) + fila do gestor, 7 rotas → `MobileMarcacaoController`. **Trava 6 PRs.**
4. **W11** As listas viram `DataGrid` no cliente ou seguem `LengthAwarePaginator` no servidor? **Trava 35 PRs de forma.**
5. **W14** Label do KPI-filtro: accent 13.3px/400 (bundle) ou 11px/600 uppercase (ADR 0110)? Trava 13.
6. **W15** Rejeitar em lote em Aprovações: criar endpoint (só existe `aprovarEmLote`) ou a 15 fica só com "Aprovar N"? Trava 15.

## 7 · Fonte da máquina
```json
{
  "modulo": "Ponto",
  "sha": "438b6992ed4b",
  "gerado": "2026-09-28",
  "absorve": [
    "_delta-indice-13a15.md (2026-09-09)",
    "_PATCH-INDICE-2026-09-14.md",
    "_DECISOES-W-2026-09-24.md",
    "COLAR-NO-CODE-ponto-ondas.md (2026-09-04)"
  ],
  "variaveis": {
    "PAGES": "resources/js/Pages/Ponto",
    "COWORK": "prototipo-ui/cowork/Wagner",
    "REQ": "memory/requisitos/Ponto",
    "ALVOS": "governance/design/targets"
  },
  "decisoes": [
    {
      "id": "W1",
      "pergunta": "Estado da competência: tabela ponto_competencias ou derivado das apurações?",
      "respondida": true,
      "resposta": "tabela ponto_competencias gravada uma vez — ADR 0413"
    },
    {
      "id": "W2",
      "pergunta": "Permissão do fechamento: nova ou reusa ponto.configuracoes.manage?",
      "respondida": true,
      "resposta": "ponto.fechar própria — ADR 0413"
    },
    {
      "id": "W3",
      "pergunta": "Exceções assinadas: onde persistem? bloqueiam AFD?",
      "respondida": true,
      "resposta": "bloqueios na linha da competência; não bloqueiam AFD — ADR 0413"
    },
    {
      "id": "W4",
      "pergunta": "Reabrir competência fechada: com auditoria ou definitivo?",
      "respondida": true,
      "resposta": "sem Reabrir na v1 — ADR 0413"
    },
    {
      "id": "W5",
      "pergunta": "REP-P com GPS ruim: bater mesmo assim?",
      "respondida": true,
      "resposta": "ADR 0383: accuracy > 500 m recusa; geofence sinaliza"
    },
    {
      "id": "W6",
      "pergunta": "Copy da selfie (LGPD)",
      "respondida": true,
      "resposta": "morta — ADR 0383: sem selfie"
    },
    {
      "id": "W7",
      "pergunta": "Ordem de AFD/AFDT/AEJ em ReportService",
      "respondida": true,
      "destrava": [
        "12"
      ],
      "resposta": "AFD → AEJ; AFDT sai da exportação — ADR 0413"
    },
    {
      "id": "W8",
      "pergunta": "/ponto/react (Welcome piloto): manter ou remover?",
      "respondida": false
    },
    {
      "id": "W9",
      "pergunta": "Navegação: 13 abas de área × PontoSubNav 5+⋯ (ADR 0182)?",
      "respondida": false
    },
    {
      "id": "W10",
      "pergunta": "Ratificar REP-P sem selfie: 3 telas + fila, 7 rotas → MobileMarcacaoController",
      "respondida": false,
      "destrava": [
        "06"
      ]
    },
    {
      "id": "W11",
      "pergunta": "Listas: DataGrid no cliente ou LengthAwarePaginator no servidor?",
      "respondida": false,
      "destrava": [
        "forma 16+"
      ]
    },
    {
      "id": "W14",
      "pergunta": "Label do KPI-filtro: accent 13.3px/400 ou 11px/600 uppercase (ADR 0110)?",
      "respondida": false,
      "destrava": [
        "13"
      ]
    },
    {
      "id": "W15",
      "pergunta": "Rejeitar em lote na fila de Aprovações: criar endpoint (hoje só existe aprovarEmLote) ou a 15 fica só com Aprovar N?",
      "respondida": false,
      "destrava": [
        "15"
      ]
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Rede mínima: E2E de fumaça",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-rede-e2e.md",
      "prefixo": [
        "e2e/ponto-dashboard.spec.ts",
        "e2e/ponto-espelho.spec.ts",
        "e2e/ponto-espelho-show.spec.ts"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "e2e/ponto-dashboard.spec.ts"
        },
        {
          "tipo": "arquivo",
          "path": "e2e/ponto-espelho.spec.ts"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Desamarrar UC ⛓",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "02-uc-desamarrar.md",
      "prefixo": [
        "Modules/Ponto/Tests/Feature/"
      ],
      "nao_toca": [
        "${PAGES}/**/*.tsx"
      ],
      "provas": [],
      "nota_provas": "prova = _saida-02.md com o número do casos:report"
    },
    {
      "id": "03",
      "titulo": "a11y: sinal não-cor na divergência",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "03-a11y-divergencia.md",
      "prefixo": [
        "${PAGES}/_components/MonthHeatmap.tsx"
      ],
      "nao_toca": [
        "Modules/Ponto/"
      ],
      "depende_threads": [
        "01"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${PAGES}/_components/MonthHeatmap.tsx",
          "padrao": "aria-label",
          "nota": "corrigida 2026-09-28: o comportamento é glifo + aria-label (#6407/#6777), não sr-only"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Fechamento da competência",
      "dono": "CL",
      "arquivo": "04-fechamento-bloqueada.md",
      "prefixo": [
        "${PAGES}/Fechamento/",
        "Modules/Ponto/Http/Controllers/FechamentoController.php"
      ],
      "nao_toca": [
        "Modules/Ponto/Services/ApuracaoService.php"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Fechamento/Index.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/ponto-fechamento.contract.json"
        },
        {
          "tipo": "contem",
          "path": "Modules/Ponto/Http/routes.php",
          "padrao": "FechamentoController"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Conformidade CLT (read-only)",
      "dono": "CL",
      "arquivo": "05-conformidade-bloqueada.md",
      "prefixo": [
        "${PAGES}/Conformidade.tsx",
        "Modules/Ponto/Http/Controllers/ConformidadeController.php"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Conformidade.tsx"
        },
        {
          "tipo": "contem",
          "path": "Modules/Ponto/Http/routes.php",
          "padrao": "ConformidadeController"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "REP-P sem selfie — 3 PRs: ValidacaoMobile (D3) → 7 rotas → MobileMarcacaoController → Page Mobile + contrato ponto-rep-p",
      "dono": "CL",
      "vaga": 3,
      "prs": 3,
      "arquivo": "06-rep-p.md",
      "prefixo": [
        "Modules/Ponto/Http/routes.php (só o bloco /ponto/api)",
        "Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php",
        "${PAGES}/Mobile/",
        "governance/design/contracts/ponto-rep-p.contract.json"
      ],
      "nao_toca": [
        "Modules/Ponto/Services/MarcacaoService.php",
        "Modules/Ponto/Services/NsrService.php",
        "Modules/Ponto/Database/"
      ],
      "depende_decisoes": [
        "W10"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/Ponto/Http/routes.php",
          "padrao": "MobileMarcacaoController"
        },
        {
          "tipo": "um_de",
          "paths": [
            "${PAGES}/Mobile/Index.tsx",
            "${PAGES}/Mobile/Marcar.tsx"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/contracts/ponto-rep-p.contract.json",
          "chaves": [
            "alvo",
            "secoes"
          ]
        },
        {
          "tipo": "nao_contem",
          "path": "Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php",
          "padrao": "selfie"
        }
      ],
      "nota": "absorve os PRs 3 e 4 da thread 30; recusar = Marcacao::anular() (nunca UPDATE)"
    },
    {
      "id": "07",
      "titulo": "Contratos do Ponto → required (5/5)",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "07-contratos-required.md",
      "prefixo": [
        "gate de contrato (onde o repo declara required)"
      ],
      "nao_toca": [
        "governance/design/contracts/ponto-painel.contract.json",
        "governance/design/contracts/ponto-espelho.contract.json"
      ],
      "depende_threads": [
        "06"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/ponto-rep-p.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/ponto-conformidade.contract.json"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "PUXAR Painel + Espelho → protótipo",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "08-puxar-painel-espelho.md",
      "prefixo": [
        "${COWORK}/ponto-page.jsx"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": []
    },
    {
      "id": "09",
      "titulo": "PUXAR as 11 telas restantes → protótipo",
      "dono": "CC",
      "vaga": 2,
      "arquivo": "09-puxar-11-telas.md",
      "prefixo": [
        "${COWORK}/ponto-telas.jsx"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "depende_threads": [
        "10"
      ],
      "provas": []
    },
    {
      "id": "10",
      "titulo": "Build: REP-P do protótipo sem selfie",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "10-build-mobile-sem-selfie.md",
      "prefixo": [
        "${COWORK}/ponto-mobile.jsx"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${COWORK}/ponto-mobile.jsx",
          "padrao": "selfie"
        }
      ]
    },
    {
      "id": "11",
      "titulo": "Limpeza: blades mortas + nav legado (+ /react se W8)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "11-limpeza-blades-inbox.md",
      "prefixo": [
        "Modules/Ponto/Resources/views/ (exceto reports/)"
      ],
      "nao_toca": [
        "Modules/Ponto/Resources/views/reports/espelho-pdf.blade.php"
      ],
      "provas": [
        {
          "tipo": "ausente",
          "path": "Modules/Ponto/Resources/views/layouts/module.blade.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Ponto/Resources/views/reports/espelho-pdf.blade.php"
        }
      ]
    },
    {
      "id": "12",
      "titulo": "Relatórios legais — 2 PRs: AFD (e afdt sai do catálogo) → AEJ",
      "dono": "CL",
      "vaga": 1,
      "prs": 2,
      "arquivo": "12-relatorios-legais-bloqueada.md",
      "prefixo": [
        "Modules/Ponto/Services/ReportService.php",
        "Modules/Ponto/Tests/Feature/RelatorioLegalContratoTest.php",
        "Modules/Ponto/Http/Controllers/RelatorioController.php (só trocar o 501 pela chamada)"
      ],
      "nao_toca": [
        "${PAGES}/Relatorios/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Ponto/Tests/Feature/RelatorioLegalContratoTest.php"
        }
      ],
      "nota": "absorve o PR 5 da thread 30; W7 respondida — o nome do arquivo ainda diz 'bloqueada', o estado não"
    },
    {
      "id": "13",
      "titulo": "Forma — Painel",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "13-forma-painel.md",
      "prefixo": [
        "${PAGES}/Dashboard/Index.tsx"
      ],
      "nao_toca": [
        "${PAGES}/_components/"
      ],
      "depende_threads": [
        "32"
      ],
      "depende_decisoes": [
        "W14"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${PAGES}/Dashboard/Index.tsx",
          "padrao": "variant=\"filter\""
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/_components/PresenceStrip.tsx"
        }
      ]
    },
    {
      "id": "14",
      "titulo": "Forma — Espelho · lista",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "14-forma-espelho-lista.md",
      "prefixo": [
        "${PAGES}/Espelho/Index.tsx"
      ],
      "nao_toca": [
        "${PAGES}/Espelho/Show.tsx"
      ],
      "depende_threads": [
        "33"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${PAGES}/Espelho/Index.tsx",
          "padrao": "shared/Toolbar"
        }
      ]
    },
    {
      "id": "15",
      "titulo": "Forma — Aprovações",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "15-forma-aprovacoes.md",
      "prefixo": [
        "${PAGES}/Aprovacoes/Index.tsx"
      ],
      "nao_toca": [
        "resources/js/Components/shared/BulkActionBar.tsx"
      ],
      "depende_threads": [
        "34"
      ],
      "depende_decisoes": [
        "W15"
      ],
      "provas": [],
      "nota_provas": "sem prova de arquivo honesta: BulkActionBar (:52), Textarea (:44) e PageFilters (:49) JÁ existem no main — qualquer um deles como prova faria a 15 aparecer 'sem recibo' assim que a 34 fechar. Prova = _saida-15 + AprovacaoTest. Guarda (não medida pelo placar): PageFilters continua importado. Se W15 = criar endpoint, a prova vira contem routes.php '/aprovacoes/rejeitar-lote'."
    },
    {
      "id": "16",
      "titulo": "gap.md — Aprovações",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "16-gap-aprovacoes.md",
      "prefixo": [
        "${REQ}/aprovacoes-index-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/aprovacoes-index-gap.md"
        }
      ]
    },
    {
      "id": "17",
      "titulo": "data-contract no .tsx — 3 PRs (≤8 arquivos cada): Aprovações+Intercorrências+BH · Escalas+Colaboradores+Relatórios · Importações+Configurações",
      "dono": "CL",
      "vaga": 2,
      "prs": 3,
      "arquivo": "17-data-contract-no-tsx.md",
      "prefixo": [
        "${PAGES}/{Aprovacoes,Intercorrencias,BancoHoras,Escalas,Colaboradores,Importacoes,Relatorios,Configuracoes}/*.tsx (só o atributo)"
      ],
      "nao_toca": [
        "governance/design/contracts/"
      ],
      "depende_threads": [
        "16",
        "20",
        "21",
        "22",
        "23",
        "24",
        "25",
        "26"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${PAGES}/Aprovacoes/Index.tsx",
          "padrao": "data-contract=\"aprovacoes-fila-de-aprovacoes\""
        },
        {
          "tipo": "contem",
          "path": "${PAGES}/Configuracoes/Index.tsx",
          "padrao": "data-contract="
        }
      ]
    },
    {
      "id": "20",
      "titulo": "gap.md — Intercorrências (4 telas)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "20-gap-intercorrencias.md",
      "prefixo": [
        "${REQ}/intercorrencias-index-gap.md",
        "${REQ}/intercorrencias-create-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/intercorrencias-index-gap.md"
        }
      ]
    },
    {
      "id": "21",
      "titulo": "gap.md — Banco de horas",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "21-gap-banco-horas.md",
      "prefixo": [
        "${REQ}/banco-horas-index-gap.md",
        "${REQ}/banco-horas-show-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/banco-horas-index-gap.md"
        }
      ]
    },
    {
      "id": "22",
      "titulo": "gap.md — Escalas",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "22-gap-escalas.md",
      "prefixo": [
        "${REQ}/escalas-index-gap.md",
        "${REQ}/escalas-form-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/escalas-index-gap.md"
        }
      ]
    },
    {
      "id": "23",
      "titulo": "gap.md — Colaboradores",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "23-gap-colaboradores.md",
      "prefixo": [
        "${REQ}/colaboradores-index-gap.md",
        "${REQ}/colaboradores-edit-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/colaboradores-index-gap.md"
        }
      ]
    },
    {
      "id": "24",
      "titulo": "gap.md — Importações (3 telas)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "24-gap-importacoes.md",
      "prefixo": [
        "${REQ}/importacoes-index-gap.md",
        "${REQ}/importacoes-create-gap.md",
        "${REQ}/importacoes-show-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/importacoes-index-gap.md"
        }
      ]
    },
    {
      "id": "25",
      "titulo": "gap.md — Relatórios",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "25-gap-relatorios.md",
      "prefixo": [
        "${REQ}/relatorios-index-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/relatorios-index-gap.md"
        }
      ]
    },
    {
      "id": "26",
      "titulo": "gap.md — Configurações",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "26-gap-configuracoes.md",
      "prefixo": [
        "${REQ}/configuracoes-index-gap.md",
        "${REQ}/configuracoes-reps-gap.md"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${REQ}/configuracoes-index-gap.md"
        }
      ]
    },
    {
      "id": "27",
      "titulo": "Emendas de charter + guards — 3 PRs: charters · casos · testes (UMA onda, ata bloco 4 + R1)",
      "dono": "CL",
      "vaga": 2,
      "prs": 3,
      "arquivo": "27-emendas-e-guards.md",
      "prefixo": [
        "${PAGES}/**/*.charter.md",
        "${PAGES}/**/*.casos.md",
        "Modules/Ponto/Tests/Feature/"
      ],
      "nao_toca": [
        "${PAGES}/**/*.tsx"
      ],
      "depende_threads": [
        "17"
      ],
      "provas": [],
      "nota_provas": "prova = _saida-27 com as 8 emendas × arquivo:linha e os 2 guards Pest nomeados"
    },
    {
      "id": "28",
      "titulo": "Rota própria — 5 ondas de build [CC], 1 por símbolo (Intercorrências · BancoHoras · Escalas · Colaboradores · Importações)",
      "dono": "CC",
      "vaga": 1,
      "prs": 5,
      "arquivo": "28-rota-propria.md",
      "prefixo": [
        "${COWORK}/ponto-page.jsx (daRota)",
        "${COWORK}/ponto-telas.jsx"
      ],
      "nao_toca": [
        "${PAGES}/",
        "${COWORK}/app.jsx"
      ],
      "provas": [],
      "nota_provas": "prova = o render: __go('pt-intercorrencias-<id>') abre a página, não o drawer; hoje daRota só reconhece espelho-<id>"
    },
    {
      "id": "32",
      "titulo": "ALVO ponto--dashboard--index (mede o protótipo, grava secoes+alvo)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "32-alvo-painel.md",
      "prefixo": [
        "${ALVOS}/ponto--dashboard--index.secoes.json",
        "${ALVOS}/ponto--dashboard--index.alvo.json",
        "${ALVOS}/README.md (linha na tabela)"
      ],
      "nao_toca": [
        "${PAGES}/",
        "${COWORK}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/ponto--dashboard--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "33",
      "titulo": "ALVO ponto--espelho--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "33-alvo-espelho-lista.md",
      "prefixo": [
        "${ALVOS}/ponto--espelho--index.secoes.json",
        "${ALVOS}/ponto--espelho--index.alvo.json"
      ],
      "nao_toca": [
        "${PAGES}/",
        "${COWORK}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/ponto--espelho--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "34",
      "titulo": "ALVO ponto--aprovacoes--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "34-alvo-aprovacoes.md",
      "prefixo": [
        "${ALVOS}/ponto--aprovacoes--index.secoes.json",
        "${ALVOS}/ponto--aprovacoes--index.alvo.json"
      ],
      "nao_toca": [
        "${PAGES}/",
        "${COWORK}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/ponto--aprovacoes--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    }
  ],
  "fila_nao_emitida": {
    "nota": "emitir quando entrar em vaga — ALVO + forma, 1 PR cada; todas dependem de W11 e as de detalhe da 28",
    "telas": [
      "espelho-show (forma em 2: 16a/16b)",
      "intercorrencias-index",
      "intercorrencias-create",
      "intercorrencias-edit",
      "intercorrencias-show",
      "banco-horas-index",
      "banco-horas-show",
      "escalas-index",
      "escalas-form",
      "colaboradores-index",
      "colaboradores-edit",
      "importacoes-index",
      "importacoes-create",
      "importacoes-show",
      "relatorios-index",
      "configuracoes-index",
      "configuracoes-reps"
    ],
    "prs": 35
  },
  "fora_do_placar": {
    "nota": "threads-leitura/absorvidas: o placar ignora 'sem_pr' e as poria em 'proximo' (provas [] + sem _saida). Ficam na pasta como contexto, fora do §7.",
    "ids": {
      "18": "absorvida por 06/07",
      "29": "respondida (leitura)",
      "30": "absorvida: PR1→05 · PR2→04 · PR3–4→06 · PR5→12",
      "31": "recibo da bateria B1–B8"
    }
  }
}
```
