---
sessao: "00"
titulo: SINCRONIZAR Patrimônio — índice do playbook (fonte da máquina em §7)
autor: "[CC]"
criado: 2026-09-08
base: wagnerra23/oimpresso.com@main (árvore cb475c0ca2f4 · lida 2026-09-08 11:25 UTC)
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md
destino_no_main: prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/
---

# SINCRONIZAR Patrimônio — playbook

> **Absorve** `COLAR-NO-CODE-patrimonio-ondas.md` (que vira ponteiro de 2 KB). Primeiro módulo emitido pelo fluxo do §13 — **ficha antes de escrever**.
> **O módulo é 100% Blade:** 6 `Route::resource` sob o prefixo `asset`, **zero `Inertia::render`**, e a busca por `(?i)(patrimonio|asset)` em `resources/js/Pages/` bateu **0 de 794**. Nenhuma tela React existe. A D-ENDERECO **foi respondida** em 2026-09-08 (`Pages/Patrimonio/**`, ADR 0394) — as telas passam a nascer pelo MWART da ADR 0104, uma thread por tela.

## 0 · O passo 0 (RELER) mudou o pedido de 04/09

- ~~**D1 caiu.**~~ ⚠️ **ERRATA [CL] 2026-09-08 — D1 NÃO caiu; esta linha estava errada.** O texto original dizia que o `&&` *"não se reconfirmou"*, citando 40 ocorrências do padrão `! (can('superadmin') || hasThePermissionInSubscription(...))`. A varredura de 04/09 leu o padrão **majoritário** e concluiu sobre o arquivo que é a **exceção**. Medido por varredura contada na thread 04: `superadmin ||` aparece **18× em 5 arquivos** e **0×** no `AssetMaitenanceController`; o padrão com `&&` aparece **7×, todas nele**. Confirmado independentemente aqui: o arquivo (15.724 B, `rc=0`) **não contém a string `superadmin`**, e suas guardas são `! ((can('asset.view_all_maintenance') && can('asset.view_own_maintenance')) || …)` em `:63`, `:208`, `:243`, `:286`, `:322`. O `&&` exige **as duas** permissões, então quem tem só `view_own_maintenance` — o técnico — é bloqueado das próprias manutenções. **D1 vive, em 6 sítios**, e volta a ser candidato a PR. Recibo: `_saida-04.md` ([PR #7009](https://github.com/wagnerra23/oimpresso.com/pull/7009)).
- **D4 ganhou linha exata, e é pior do que estava escrito.** `AssetAllocationService.php:112`: a subconsulta `SELECT SUM(...) FROM asset_transactions AS AR WHERE (AR.asset_id=assets.id AND AR.transaction_type='revoke')` **não filtra `business_id`**, enquanto a consulta externa filtra (`:107`). → **thread 01**.

O passo 0 pagando por si: um pedido morreu por falta de prova, e um vazamento Tier 0 ganhou endereço.

## 1 · LEVANTAR — 4 denominadores

**D1 rota** `Routes/web.php` (1.843 B): `assets` · `allocation` · `revocation` · `settings` (as `asset`) · `asset-maintenance` + `GET asset/dashboard`. Todas Blade, `throttle:60,1`.
**D2 nav legado**: a sidebar trata Patrimônio como ghost de Estoque (ADR 0180) — é a raiz da D-ENDERECO.
**D3 protótipo** `patrimonio-page.jsx`: 7 abas (Painel · Bens · Alocações · Manutenções · Garantias · Auditoria · Config). Nós medidos em 04/09: Painel 999 · Manutenções 916 · Bens 905 · Auditoria 855 · Config 831 · Garantias 797.
**D4 runtime**: **0 `Inertia::render`** no módulo. Toda tela é 🟠 desenvolver — nenhuma é 🔵 puxar.

## 2 · Threads — ficha (§13.2) → veredito

| # | thread | leitura | escrita | prefixo | símb. | dec. | veredito |
|---|---|---:|---:|---:|---:|---:|---|
| 01 | **Tenant na subconsulta de revoke** (vazamento Tier 0) | ~5 KB | ~6 ln | 2 | 1 | 0 | **CABE** |
| 02 | **Trava de saldo na alocação** | ~9 KB | ~35 ln | 3 | 2 | 0 | **CABE** |
| 03 | **Guarda `asset.view` no índice** | ~4 KB | ~8 ln | 2 | 2 | 0 | **CABE** |
| 04 | **Remedir D1/D5 e os não-lidos** (frente 0) | ~25 KB | 0 | 0 | — | 0 | **CABE** (medição) |
| 05 | **Job de retenção LGPD** `assetmanagement:retention-purge` | ~6 KB | ~120 ln | 3 | 2 | 0¹ | **CABE** |
| **07** | **Painel** — cria o `_shared` da frente | ~6 KB | ~180 ln | 3 | 2 | 0 | **CABE** · 1ª da frente |
| **08** | **Bens** — o CRUD principal | ~9 KB | ~250 ln | 2 | 2 | 0 | **CABE** · atrás da 07 |
| **09** | **Alocações** — funde `allocation`+`revocation` | ~12 KB | ~280 ln | 3 | 3 | **1** | **CABE** se a fusão for só de tela |
| **10** | **Manutenções** — carrega o D1 | ~8 KB | ~220 ln | 2 | 2 | 0 | **CABE** · não corrige o D1 |
| **11** | **Configurações** | ~5 KB | ~150 ln | 2 | 2 | 0 | **CABE** · menor da frente |
| **12** | **Garantias** — tela nova, dado existente | ~6 KB | ~200 ln | 2 | 2 | **1** | **BLOQUEADA** por D-GARANTIAS |
| **13** | **Auditoria** | — | — | 0 | — | **1** | **BLOQUEADA** por D-AUDITORIA |

¹ [W] 10 decide **quando ligar em canary**, não se o código nasce — o próprio doc de 04/09 diz "o código pode nascer já". Nasce com `enabled=false`.

**Backend (01-05):** 01 e 03 e 04 feitas; 02 atrás da 01; 05 barrada pela lápide §5.
**Frente de UI (07-13), aberta pela ADR 0394:** a **07** vai sozinha — ela cria o `_shared/PatrimonioSubNav.tsx` que as outras importam, e errar ali custa seis telas. Depois **08 ∥ 09 ∥ 10** (prefixos disjuntos), então **11**. A **12** espera D-GARANTIAS e a **13**, D-AUDITORIA.
**A ordem não é gosto:** 01 vem primeiro porque é multi-tenant em produção — Tier 0 fura antes de qualquer verniz.

## 2-bis · ESTADO — derivado, nunca escrito
`node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md --root . --proximo`

⚠️ **ERRATA [CL] 2026-09-08 — o comando e o render esperado estavam ambos errados.**
- **Caminho:** era `node scripts/qa/placar-indice.mjs`, que **não existe no repo**. Esse é o *destino sugerido* da ponte, escrito no docblock do próprio script — não um caminho vivo. O script mora em `prototipo-ui/design-docs/cowork-inbox/_scripts/`. (O irmão `ponto/playbook` herda o mesmo ponteiro podre.)
- **Render:** era `Patrimônio: entregue 0 de 6 · próximo 5 · bloqueada 1`. O formato real do `resumo` inclui `em curso` e `pendente`, e o `modulo` do §7 é `Patrimonio`, sem acento. O `próximo 5` só saía por causa do typo `depende_thread` (o placar lê `depende_threads`), que apagava a dependência 02→01.

**Não decore o número: rode o comando.** O estado é derivado por construção — qualquer valor escrito aqui apodrece no primeiro merge.

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: gh pr list --state open e cruze com os arquivos do seu prefixo.
Leia, do main: (1) CONSTITUICAO-COWORK.md — C1–C12, citada e não copiada
(2) este índice §1/§2/§7  (3) o seu NN-*.md  (4) memory/requisitos/AssetManagement/SCOPE.md
    ^ ERRATA 08/09: era "requisitos/Patrimonio/SCOPE.md", que NAO EXISTE. O modulo
      e AssetManagement; nao ha diretorio Patrimonio em memory/requisitos/.
(5) a faixa de linhas da sua ÂNCORA — e SÓ ela.
(6) os _saida-NN.md das threads JÁ FECHADAS desta pasta, e em especial o campo
    `invalida:` de cada um. É por ali que uma thread corrige o plano das outras —
    e sem este passo o canal só existe de quem escreve, nunca de quem recebe.
NÃO leia: as 17 views Blade, os 9 Pest inteiros. O patrimonio-page.jsx é alvo de UI —
não leia numa thread de backend; nas threads de tela (frente 06) ele é a fonte visual.
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Terminou: escreva o _saida e pare.
```

## 4 · VERIFICAR
Thread `feito` = `_saida-NN.md` + provas verdes lendo o `main`. **Reusar, não recriar:** os 9 Pest do módulo (`CrossTenantAssetTest`, `MultiTenantIsolationTest` e `LgpdComplianceTest` são os oráculos das threads 01, 02 e 05) · `AssetService`/`AssetAllocationService` · `OtelHelper::spanBiz` · `AssetUtil`.

## 6 · RESÍDUO — 10 decisões de [W] ainda abertas (a 1ª foi respondida em 08/09)
**1** ~~Módulo próprio ou seção do Estoque?~~ **RESPONDIDA [W] 2026-09-08** → `Pages/Patrimonio/**`, módulo próprio ([ADR 0394](../../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md)) · **2** prefixo de permissão: `asset.*` (código) ou `assetmanagement.*` (SCOPE)? · **3** custo de manutenção entra (não há coluna)? · **4** Garantias é tela ou filtro de Bens? · **5** Auditoria é aba daqui ou do `Modules/Auditoria`? · **6** depreciação: linear ou SAC, com que fonte contábil? (a coluna já existe e é gravada, mas nunca calculada) · **7** baixa/disposal: `status` ou tabela própria? (hoje "dar baixa" = **deletar o bem**) · **8** transferência entre locais: transação ou edição do `location_id`? · **9** QR + scan mobile entra ou vira Non-Goal escrito? · **10** quando ligar o purge LGPD em canary? · **11** placa veicular: Patrimônio e Oficina falam do mesmo veículo?

Dívida sistêmica, fora deste playbook: grade do DS sem `th scope` — **4º módulo** com o mesmo achado (CRM, Repair, HRM, Patrimônio). Vira pedido do DS, não onda daqui.

## 7 · Fonte da máquina
```json
{
  "modulo": "Patrimonio",
  "sha": "cb475c0ca2f4",
  "gerado": "2026-09-08",
  "absorve": [
    "prototipo-ui/COLAR-NO-CODE-patrimonio-ondas.md"
  ],
  "constituicao": "CONSTITUICAO-COWORK.md",
  "decisoes": [
    {
      "id": "D-ENDERECO",
      "pergunta": "Patrimonio e modulo proprio (Pages/Patrimonio/**) ou secao do Estoque (Pages/Estoque/Patrimonio/**)? ADR 0180 x ADR 0182 x SCOPE bloqueado-escopo.",
      "respondida": true,
      "resposta": "Pages/Patrimonio/** (modulo proprio) — [W] 2026-09-04, ratificado 2026-09-08; ADR 0394; SCOPE.md migracao_ui liberado",
      "destrava": [
        "06"
      ],
      "custo": "44 arquivos / 20 PRs"
    },
    {
      "id": "D-CANARY-LGPD",
      "pergunta": "Quando ligar assetmanagement:retention-purge em canary? (nao bloqueia escrever o job com enabled=false)",
      "respondida": false,
      "afeta": [
        "05"
      ]
    },
    {
      "id": "D-GARANTIAS",
      "pergunta": "Garantias e tela propria ou filtro da tela de Bens? (RESIDUO 4). A tabela asset_warranties existe e ja e lida pelo dashboard; o prototipo desenhou aba propria.",
      "respondida": false,
      "destrava": [
        "12"
      ]
    },
    {
      "id": "D-AUDITORIA",
      "pergunta": "Auditoria do Patrimonio e aba deste modulo ou deep-link para o Modules/Auditoria ja filtrado? (RESIDUO 5). Duplicar cria dois donos do mesmo tema.",
      "respondida": false,
      "destrava": [
        "13"
      ]
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Tenant na subconsulta de revoke (vazamento Tier 0)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-tenant-subquery-revoke.md",
      "prefixo": [
        "Modules/AssetManagement/Services/AssetAllocationService.php",
        "Modules/AssetManagement/Tests/Feature/CrossTenantAssetTest.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Http/Controllers/",
        "resources/js/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Services/AssetAllocationService.php",
          "padrao": "AR.business_id"
        },
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Tests/Feature/CrossTenantAssetTest.php",
          "padrao": "quantidadeDisponivel"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Trava de saldo na alocacao",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "02-trava-de-saldo.md",
      "prefixo": [
        "Modules/AssetManagement/Services/AssetAllocationService.php",
        "Modules/AssetManagement/Http/Requests/StoreAssetAllocationRequest.php",
        "Modules/AssetManagement/Tests/Feature/Wave27AssetManagementPolishTest.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Services/AssetMaintenanceService.php"
      ],
      "depende_thread": [
        "01"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Services/AssetAllocationService.php",
          "padrao": "quantidadeDisponivel"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Guarda asset.view no indice",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "03-guarda-asset-view.md",
      "prefixo": [
        "Modules/AssetManagement/Http/Controllers/AssetController.php",
        "Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Services/",
        "Modules/AssetManagement/Http/Controllers/AssetAllocationController.php"
      ],
      "nota_provas": "ERRATA 2026-09-08: o padrao era \"asset.view\", que ja passava ANTES do trabalho por casar prefixo com asset.view_all_maintenance (AssetController.php:145, linha que so desenha botao). Prova de carimbo. Trocado por can('asset.view'), medido com controle positivo: nao passa hoje, passa quando a guarda entrar.",
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Http/Controllers/AssetController.php",
          "padrao": "can('asset.view')"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Remedir D1/D5 e os nao-lidos (frente 0)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "04-remedir-frente-0.md",
      "prefixo": [],
      "nao_toca": [
        "*"
      ],
      "nota_provas": "thread de MEDICAO: nao escreve codigo. Prova = _saida-04.md com veredito por defeito (confirmado com linha / nao existe / ja corrigido).",
      "provas": []
    },
    {
      "id": "05",
      "titulo": "Job de retencao LGPD (nasce com enabled=false)",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "05-retencao-lgpd.md",
      "prefixo": [
        "Modules/AssetManagement/Console/Commands/",
        "Modules/AssetManagement/Config/retention.php",
        "Modules/AssetManagement/Tests/Feature/LgpdComplianceTest.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Services/",
        "Modules/AssetManagement/Http/"
      ],
      "afeta_decisoes": [
        "D-CANARY-LGPD"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Config/retention.php",
          "padrao": "enabled"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Painel do Patrimonio — cria o _shared da frente",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "07-painel.md",
      "prefixo": [
        "resources/js/Pages/Patrimonio/Index.tsx",
        "resources/js/Pages/Patrimonio/_shared/",
        "Modules/AssetManagement/Http/Controllers/AssetController.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Services/",
        "Modules/Auditoria/"
      ],
      "nota_provas": "primeira da frente: cria o PatrimonioSubNav que as 08-12 importam.",
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Index.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Index.charter.md"
        },
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Http/Controllers/AssetController.php",
          "padrao": "Inertia::render('Patrimonio/Index'"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Bens — o CRUD principal",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "08-bens.md",
      "prefixo": [
        "resources/js/Pages/Patrimonio/Bens/",
        "Modules/AssetManagement/Http/Controllers/AssetController.php"
      ],
      "nao_toca": [
        "resources/js/Pages/Patrimonio/_shared/"
      ],
      "depende_threads": [
        "07"
      ],
      "nota_provas": "a guarda can('asset.view') do PR #7008 tem de SOBREVIVER a migracao — mas ela nao entra como prova: ja passa hoje, entao marcaria a thread como 'em curso' sem trabalho nenhum (carimbo). Fica no checklist, onde o humano confere.",
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Bens/Index.tsx"
        }
      ]
    },
    {
      "id": "09",
      "titulo": "Alocacoes — funde allocation + revocation numa aba",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "09-alocacoes.md",
      "prefixo": [
        "resources/js/Pages/Patrimonio/Alocacoes/",
        "Modules/AssetManagement/Http/Controllers/AssetAllocationController.php",
        "Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Services/AssetAllocationService.php"
      ],
      "depende_threads": [
        "07"
      ],
      "nota_provas": "o prototipo funde 2 rotas numa aba; fundir a ROTA e decisao [W].",
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Alocacoes/Index.tsx"
        },
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Http/Controllers/AssetAllocationController.php",
          "padrao": "Inertia::render"
        }
      ]
    },
    {
      "id": "10",
      "titulo": "Manutencoes — e o D1 que ainda vive",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "10-manutencoes.md",
      "prefixo": [
        "resources/js/Pages/Patrimonio/Manutencoes/",
        "Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php"
      ],
      "nao_toca": [
        "resources/js/Pages/Patrimonio/_shared/"
      ],
      "depende_threads": [
        "07"
      ],
      "nota_provas": "D1 vive em 6 sitios deste controller (&& onde deveria ser ||). NAO corrigir aqui: registrar.",
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Manutencoes/Index.tsx"
        },
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php",
          "padrao": "Inertia::render"
        }
      ]
    },
    {
      "id": "11",
      "titulo": "Configuracoes — prefixos e notificacoes",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "11-configuracoes.md",
      "prefixo": [
        "resources/js/Pages/Patrimonio/Configuracoes/",
        "Modules/AssetManagement/Http/Controllers/AssetSettingsController.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Config/retention.php"
      ],
      "depende_threads": [
        "07"
      ],
      "nota_provas": "retention.php e da thread 05, BARRADA pela lapide 5 de 2026-07-27.",
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Configuracoes/Index.tsx"
        },
        {
          "tipo": "contem",
          "path": "Modules/AssetManagement/Http/Controllers/AssetSettingsController.php",
          "padrao": "Inertia::render"
        }
      ]
    },
    {
      "id": "12",
      "titulo": "Garantias — tela nova sobre dado que ja existe",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "12-garantias.md",
      "prefixo": [
        "resources/js/Pages/Patrimonio/Garantias/",
        "Modules/AssetManagement/Routes/web.php"
      ],
      "nao_toca": [
        "Modules/AssetManagement/Http/Controllers/AssetController.php"
      ],
      "depende_threads": [
        "07"
      ],
      "depende_decisoes": [
        "D-GARANTIAS"
      ],
      "nota_provas": "asset_warranties EXISTE (1 migration) e ja e lida no dashboard; falta rota e tela.",
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Patrimonio/Garantias/Index.tsx"
        }
      ]
    },
    {
      "id": "13",
      "titulo": "Auditoria — aba daqui ou do Modules/Auditoria?",
      "dono": "W",
      "arquivo": "13-auditoria-bloqueada.md",
      "prefixo": [],
      "nao_toca": [
        "*"
      ],
      "bloqueio": "Decisao [W] 5 do RESIDUO: o Modules/Auditoria ja e dono da trilha por-registro; abrir uma segunda aqui cria dois donos do mesmo tema (LC-19). A opcao barata e deep-link para a tela dele ja filtrada por subject_type=Asset — e ai nao ha tela a construir.",
      "depende_decisoes": [
        "D-AUDITORIA"
      ],
      "provas": []
    }
  ]
}
```
