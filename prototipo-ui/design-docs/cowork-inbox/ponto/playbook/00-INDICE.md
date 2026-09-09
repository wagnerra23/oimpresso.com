---
sessao: "00"
titulo: SINCRONIZAR Ponto — índice do playbook (fonte da máquina embutida em §7)
autor: "[CC]"
criado: 2026-09-06
base: wagnerra23/oimpresso.com@main (tree e86130722de1 · lida 2026-09-06 01:06–01:09 UTC)
destino_no_main: prototipo-ui/design-docs/cowork-inbox/ponto/playbook/
regra: este índice é PEDIDO (lista de threads a executar, com sha), não inventário. Ninguém escreve estado — ele é derivado (§2-bis). Nunca em prototipo-ui/cowork/ (guard R1). A pasta inteira é a unidade de descida.
---

# SINCRONIZAR Ponto — playbook

> **Absorve, não duplica:** `COLAR-NO-CODE-ponto-ondas.md` (doc único de 04/09: 8 frentes · 45 arquivos · RESÍDUO 1–7) + `cowork-inbox/ponte/COLAR-NO-CODE-ponto.md` e `_pedido-CL-ponto-teste-pratico.md` (23/08 — **executados**: `PontoDashboardContratoTest.php` 28 KB existe) + `cowork-inbox/ponto-dashboard/Index.casos.md` (movido; a cópia no inbox é resíduo). Onde o doc de 04/09 diverge desta sha, **a sha manda** — três frentes dele já envelheceram em 2 dias (§5 R4).
> **Ponto é o módulo mais à frente do repo:** 21 rotas web → **21 `Inertia::render`** → 21 Pages com charter **e casos.md 21/21** · 44 testes Feature · lane `ponto-pest.yml`. Aqui SINCRONIZAR é sobretudo **PUXAR** (produção → protótipo) e **fechar rede + 3 telas que não existem** — nunca repintar tela viva.

## 0 · Landing
Só `.md` roteia (DesignSync `get_file` → `--export-from <dir>`); fonte da máquina = 1º bloco ```json deste arquivo (§7); schema/script = anexos A8.1/A8.2 de `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md`. Rodar: `node scripts/qa/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/ponto/playbook/00-INDICE.md --root . --proximo`.

## 1 · LEVANTAR — 4 denominadores · 4 sinais · 1 sha

**D1** `Modules/Ponto/Http/routes.php` (não `Routes/web.php`) · **D2** nav legado `Resources/views/layouts/module.blade.php` (10 itens) **e** nav vivo `Pages/Ponto/_shared/PontoSubNav.tsx` (ghosts do `shell.menu`, 5 visíveis + `⋯ Mais`, ADR 0182) · **D3** `ABAS` de `ponto-page.jsx` (**13**) · **D4** `Inertia::render(` nos controllers: **21**. Dicionário: módulo = `Modules/Ponto` (namespace/lang `pontowr2`) · Pages = `resources/js/Pages/Ponto/<Tela>/{Index,Show,Create,Edit,Form,Reps}.tsx` (pasta por tela; `Welcome.tsx` flat é a exceção-piloto) · testes = `Modules/Ponto/Tests/Feature/*ContratoTest.php` · e2e = `e2e/<mod>-<tela>.spec.ts`.

| rota `/ponto/…` | D4 render | Page + trio | blade | em curso (caminho) | estado | thread |
|---|---|---|---|---|---|---|
| `/` | `Ponto/Dashboard/Index` | ✓ charter+casos · 4/4 `data-contract` · contrato `ponto-painel` | `dashboard/index` (**morta**: 0 `return view(` no módulo) | `PontoDashboardContratoTest` (pedido 23/08 executado) | 🔵 produção | 08 · 11 |
| `/react` | `Ponto/Welcome` (closure) | ✓ (charter `draft`, pendência: piloto fica?) | — | `WelcomeContratoTest` | 🔵 piloto · **decisão W8** | 11 |
| `/espelho` · `/{colab}` · `/{colab}/imprimir` | `Espelho/Index` · `Espelho/Show` · PDF | ✓ · 5 `data-contract` · contrato `ponto-espelho` | `espelho/*` mortas · **`reports/espelho-pdf` VIVA** (PDF) | `EspelhoContratoTest` | 🔵 | 03 · 08 |
| `/aprovacoes` (+3 POST) | `Aprovacoes/Index` | ✓ | `aprovacoes/*` mortas | `AprovacaoTest` | 🔵 | 09 |
| `/intercorrencias` resource + `submeter`/`cancelar`/`ai-classify` | 4 renders | ✓ Index·Create·Show·Edit | `intercorrencias/*` mortas | `Intercorrencia{,Edit}ContratoTest` · `AIClassifierTest` | 🔵 | 09 |
| `/banco-horas` · `/{colab}` · `ajuste` | 2 | ✓ | mortas | `BancoHorasIndexContratoTest` | 🔵 | 09 |
| `/escalas` resource | `Index` · `Form` ×2 | ✓ | mortas | `EscalaIndex/FormContratoTest` · `Wave27CrossTenantEscalaTest` | 🔵 | 09 |
| `/importacoes` (5) | 3 | ✓ | mortas | `Importacao{Index,Create,Show}ContratoTest` | 🔵 | 09 |
| `/relatorios` · `/{chave}` | `Relatorios/Index` | ✓ | morta | `RelatorioCatalogoContratoTest` · **gerar: só `espelho`; 7 chaves `abort(501)`** (`RelatorioController.php:102`) | 🔵 tela · ⛔ geração | 09 · 12 |
| `/colaboradores` · `/{id}/editar` | 2 | ✓ | mortas | `ColaboradorContratoTest` | 🔵 | 09 |
| `/configuracoes` · `/reps` | 2 | ✓ | mortas | `ConfiguracaoContratoTest` | 🔵 | 09 |
| `/ponto/api/*` **7 rotas** | — | — | — | closures `abort(501)`; `Api/MobileMarcacaoController.php` **existe sem rota** (ADR 0383 mediu: "nunca rodou") · `Wave28MobileMarcacaoTest` com GUARD LGPD | **stub** | 06 |
| protótipo `fechamento` | — | — | — | `ApuracaoService` · `ponto_apuracao_dia` · **sem rota, sem Page** | só protótipo ⛔ W1–4 | 04 |
| protótipo `conformidade` | — | — | — | idem | só protótipo ⛔ W1–4 | 05 |
| protótipo `mobile` (REP-P) | — | — | — | **protótipo ainda tem selfie** (`ponto-mobile.jsx:38 useState(false)`) — viola ADR 0383 (aceito 27/08, #6393) | só protótipo · **build errado** | 10 · 06 |
| nav legado 10 itens | — | — | `layouts/module.blade.php` **morta** | — | limpeza | 11 |
| nav vivo 5+⋯ × protótipo 13 abas | | | | canon [W] 2026-06-22 (abas de área) × ADR 0182 (produção) | **divergência sem dono** | W9 |

**Medido nesta sha, contra o doc de 04/09:** `casos.md` **21/21** (era 15 — frente 4 **feita**, não entra como thread) · testes **44** (era 16) — o nº de UC ⛓ **não remedi** (`casos:report` não roda daqui) · e2e **0 `e2e/ponto-*`** (17 specs no repo, nenhum do Ponto; harness existe: `e2e/global-setup.ts` + `e2e-gate.yml`) · VRT **não verifiquei** harness · API REP-P **7** rotas 501 (o doc dizia 8) · contratos **2/4** · dupe `export-ponto/` continua resolvida · `_components/` 4 · **0 `sr-only`/`aria-live` em `Pages/Ponto/**`** (os 9 hits são `data-contract`) → a a11y não-cor da divergência (thread 03) segue aberta.

## 2 · Threads — ordem · dono · prefixo (Lei 1) · dependência

| # | thread | dono | prefixo | depende | vaga |
|---|---|---|---|---|---|
| 01 | Rede mínima: E2E de fumaça (3 telas âncora) | [CL] | `e2e/ponto-*.spec.ts` | — | 1 |
| 02 | Desamarrar UC ⛓ (docblock → `it('UC-…')`) | [CL] | `Modules/Ponto/Tests/Feature/**` · coluna Teste/Status dos 21 `casos.md` | — | 1 |
| 03 | a11y: sinal não-cor na divergência + mobile-fit | [CL] | `Pages/Ponto/Espelho/{Index,Show}.tsx` · `_components/MonthHeatmap.tsx` | 01 | 2 |
| 04 | Fechamento da competência — **BLOQUEADA** | [W] | — | W1–W4 | — |
| 05 | Conformidade CLT — **BLOQUEADA** | [W] | — | 04 · W1–W4 | — |
| 06 | REP-P sem selfie: 7 rotas → `MobileMarcacaoController` + app do colaborador + fila | [CL] | `Http/routes.php` (bloco API) · `Api/MobileMarcacaoController.php` · `Pages/Ponto/Mobile/**` · `contrato/ponto-rep-p.contract.json` | W10 | 2 |
| 07 | Contratos 4/4 → `required` | [CL] | `prototipo-ui/contrato/ponto-{fechamento,rep-p}.contract.json` · gate | 04 · 05 · 06 | 3 |
| 08 | PUXAR Painel + Espelho (as 2 com contrato) → protótipo | [CC] | `prototipo-ui/cowork/ponto-page.jsx` | — | 1 |
| 09 | PUXAR as 11 telas restantes → protótipo | [CC] | `ponto-telas.jsx` · `ponto-data.jsx` · `ponto-ui.jsx` | 10 | 2 |
| 10 | Build: REP-P do protótipo **sem selfie** (ADR 0383) | [CC] | `ponto-mobile.jsx` · `ponto-data.jsx` (só bloco mobile) · host bump | — | 1 |
| 11 | Limpeza: 26 blades mortas + nav legado + inbox residual (+ `/react` se W8) | [CL] | `Modules/Ponto/Resources/views/**` **exceto `reports/`** · `cowork-inbox/ponto-dashboard/` · `routes.php` (só se W8) | W8 só para `/react` | 1 |
| 12 | Relatórios legais AFD/AFDT/AEJ — **BLOQUEADA** | [W] | — | W7 | — |

**Vaga 1:** 01 ∥ 02 ∥ 08 ∥ 10 ∥ 11 · **Vaga 2:** 03 ∥ 06 ∥ 09 · **Vaga 3:** 07. Lei 1 respeitada: 09 só abre depois de 10 porque ambas tocariam `ponto-data.jsx`.
**Âncora de implementação** (Page nova = 06 apenas): irmã golden **`Pages/Ponto/Espelho/Show.tsx`** (24 KB, 5 `data-contract`, `EspelhoContratoTest` 20 KB, contrato `ponto-espelho`) — o pacote do Ponto é tsx · charter · casos · contrato · `*ContratoTest` · lane `ponto-pest.yml` · e2e. Alvo de layout = protótipo medido (`ponto-mobile.jsx`, 991 nós em 04/09).

## 2-bis · ESTADO — derivado, nunca escrito (o Code lê ESTA)
> Fonte = §7 + o repo. `_saida-NN.md` presente **e** provas verdes = `feito`; sem `_saida` = não feito mesmo com PR mergeado; `bloqueada` é fila de [W]. `PRÓXIMO:` = deps feitas + decisões respondidas + nenhuma variável nula.

**Render 2026-09-06 (saída do script contra repo simulado = `main` e8613072):** `Ponto: entregue 0 de 12 · próximo 5 · em curso 0 · pendente 4 · bloqueada 3` — **PRÓXIMO: 01 · 02 · 08 · 10 · 11.** Presos: 03 (01) · 06 (W10) · 09 (10) · 07 (04·05·06). Bloqueadas por [W]: 04 · 05 · 12.

### Fluxo (6 passos, iguais para toda thread)
```
1 ABRIR    sessão limpa · gh pr list --state open × arquivos do prefixo (whats-active morto) · colar §3 · ler NN-*.md + âncora no main (sha no _saida)
2 MEDIR    (Pages/build) T1 duas leituras iguais → alvo (contagem · ORDEM · tokens) — read-only
3 GERAR    (só 06) criar-tela.mjs Ponto/Mobile PT-0X → carimba tsx+charter+casos+e2e+contrato JUNTOS
4 APLICAR  1–3 arquivos do prefixo · reusar átomos/serviços listados · PARAR SE vale mais que terminar
5 PROVAR   provas do NN verdes · placar no corpo do PR · lane ponto-pest verde
6 FECHAR   _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) → parar
```

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: `gh pr list --state open` × arquivos do seu prefixo (whats-active está morto — HTTP 000).
Leia nesta ordem, do main, nunca de cópia local:
1. prototipo-ui/design-docs/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md    ← Leis 1–4
2. prototipo-ui/design-docs/cowork-inbox/ponto/playbook/00-INDICE.md          ← §1 estados · §2 seu prefixo · §7 fonte
3. prototipo-ui/design-docs/cowork-inbox/ponto/playbook/NN-<sua-thread>.md    ← escopo · alvo · dado · prova
4. memory/decisions/0383-ponto-interno-nao-coleta-biometria.md               ← lei do REP-P: sem selfie; Art. 5º II + Art. 11 (não Art. 9º)
5. resources/js/Pages/Ponto/Espelho/Show.tsx + Show.charter.md + Show.casos.md ← a irmã golden do módulo
6. prototipo-ui/PRE-FLIGHT-TELA.md · memory/proibicoes.md · memory/LICOES_CC.md
7. os arquivos da âncora listados na sua thread
Leis do módulo que não se renegociam: marcação append-only (Portaria MTP 671/2021) · apuração só em ReapurarDiaJob · NSR server-authoritative · artigo literal na copy legal · 501 nunca é sucesso.
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Não edita este índice, github.md nem memory/**.
Terminou: escreva _saida-NN.md e pare.
```

## 4 · VERIFICAR — placar da lista
Thread `feito` = `_saida-NN.md` com os 5 itens **e** provas verdes lendo o `main`. `PLACAR Ponto` = rodar o script (ou [CC] lendo o `main` no turno). **T7** (`design-diff --compare --check`, prod deployada), CI e `casos:report` **não são visíveis daqui** — o placar afirma "arquivos verdes", nunca "paridade" nem "0 UC ⛓". Parciais já no `main` que as threads **reusam**: `PontoDashboardContratoTest` · 21 casos.md · `Wave28MobileMarcacaoTest` (GUARD LGPD) · `MobileMarcacaoService` (anti-fraude: accuracy ≤500 m · skew ≤30 s · geofence sinaliza) · `e2e/global-setup.ts`.

## 5 · Revisão 3× por passo — o que reprovou e foi corrigido
| passo | R1 · fonte | R2 · falsificação | R3 · frescor | R4 · o que o doc de 04/09 já tinha errado nesta sha |
|---|---|---|---|---|
| **LEVANTAR** | `Routes/web.php` não existe no Ponto → D1 = `Http/routes.php` | nav legado × nav vivo × protótipo: 10 × 5+⋯ × 13 — três denominadores, nenhum igual | sha nova (`e8613072`) 2 dias depois do doc | **frente 4 (6 casos.md) já feita** · testes 16→44 · API 501 são **7**, não 8 |
| **PUXAR** | 13 telas 🔵; só 2 têm contrato → PUXAR em 2 threads (08 com contrato, 09 sem) | Lei 1: 09 e 10 tocavam `ponto-data.jsx` → 09 depende de 10 | protótipo é import das blades de jun/26; a produção reescreveu tudo depois | RESÍDUO 6 do doc (copy da selfie) **já estava morto** desde 27/08 — ADR 0383 |
| **REACT** | única Page nova = REP-P (06); Fechamento/Conformidade seguem ⛔ | `MobileMarcacaoController` existe **sem rota** — prova da 06 é a rota apontar pra ele, não o arquivo existir | REP-P deve ser **reescrito** sem selfie (a ADR manda) → W10 | RESÍDUO 5 (GPS ruim) **respondido pela ADR**: accuracy >500 m recusa; geofence sinaliza |
| **PLAYBOOK** | 10 nasceu ao ler a ADR: **meu build viola lei aceita** — corrige-se aqui, não vira pedido | teste do estranho na 06: 7 rotas nomeadas, controller nomeado, contrato nomeado | `whats-active` morto → `gh pr list` | 3 pedidos anteriores absorvidos (04/09 · 23/08 ×2) |
| **VERIFICAR** | prova = caminho (`e2e/ponto-*.spec.ts`, `sr-only` em `MonthHeatmap.tsx` — hoje 0) | thread 02 não tem prova de arquivo honesta → prova implícita + nº do `casos:report` no `_saida` · **2 provas já verdadeiras hoje (PDF vivo · controller sem selfie) faziam 06 e 11 nascerem "em curso"** → viraram `guarda: true` (preservação: conta para feito, nunca para em curso) | CI/T7/`casos:report` não visíveis | landing: JSON embutido, pasta inteira desce |

## 6 · RESÍDUO Ponto — fila de decisão [W]
1. **W1** Estado da competência: tabela nova `ponto_competencias` ou derivado das apurações? (trava 04·05)
2. **W2** Permissão do fechamento: `ponto.fechamento.manage` nova ou reusa `ponto.configuracoes.manage`?
3. **W3** Exceções assinadas: onde persistem? bloqueiam AFD?
4. **W4** Reabrir competência fechada: com auditoria ou definitivo?
5. ~~W5 GPS ruim~~ → **respondida por ADR 0383** (recusa >500 m; geofence sinaliza). ~~W6 copy da selfie~~ → **morta** (sem selfie; e a base legal citada era errada).
6. **W7** Ordem de AFD/AFDT/AEJ em `ReportService` (trava 12).
7. **W8** `/ponto/react` (Welcome, piloto `draft` desde 07/2026): manter ou remover? (parte da 11)
8. **W9** Navegação do protótipo: 13 abas de área (seu canon 2026-06-22) × `PontoSubNav` 5+⋯ (ADR 0182, produção). Qual vale? Sem resposta, 08/09 puxam átomos e **declaram** a divergência.
9. **W10** Ratificar o escopo reescrito do REP-P: 3 telas do colaborador (bater · meu espelho · justificar) + fila do gestor, **sem selfie**, 7 rotas → `MobileMarcacaoController` (trava 06).

## 7 · Fonte da máquina (playbook.json embutido — primeiro bloco json deste arquivo; schema em `_schema/playbook.schema.json`)
```json
{
  "modulo": "Ponto",
  "sha": "e86130722de1",
  "gerado": "2026-09-06",
  "absorve": ["COLAR-NO-CODE-ponto-ondas.md (2026-09-04)", "prototipo-ui/design-docs/cowork-inbox/ponte/COLAR-NO-CODE-ponto.md", "prototipo-ui/design-docs/cowork-inbox/ponte/_pedido-CL-ponto-teste-pratico.md"],
  "variaveis": { "PAGES": "resources/js/Pages/Ponto", "COWORK": "prototipo-ui/cowork" },
  "decisoes": [
    { "id": "W1", "pergunta": "Estado da competência: tabela ponto_competencias ou derivado das apurações?", "respondida": false, "destrava": ["04", "05"] },
    { "id": "W2", "pergunta": "Permissão do fechamento: nova ou reusa ponto.configuracoes.manage?", "respondida": false, "destrava": ["04"] },
    { "id": "W3", "pergunta": "Exceções assinadas: onde persistem? bloqueiam AFD?", "respondida": false, "destrava": ["04"] },
    { "id": "W4", "pergunta": "Reabrir competência fechada: com auditoria ou definitivo?", "respondida": false, "destrava": ["04"] },
    { "id": "W5", "pergunta": "REP-P com GPS ruim: bater mesmo assim?", "respondida": true, "resposta": "ADR 0383: accuracy > 500 m recusa (422); geofence sinaliza, não bloqueia" },
    { "id": "W6", "pergunta": "Copy da selfie (LGPD)", "respondida": true, "resposta": "morta — ADR 0383: sem selfie; base legal Art. 5º II + Art. 11, não Art. 9º" },
    { "id": "W7", "pergunta": "Ordem de AFD/AFDT/AEJ em ReportService", "respondida": false, "destrava": ["12"] },
    { "id": "W8", "pergunta": "/ponto/react (Welcome piloto): manter ou remover?", "respondida": false },
    { "id": "W9", "pergunta": "Navegação do protótipo: 13 abas de área × PontoSubNav 5+⋯ (ADR 0182)?", "respondida": false },
    { "id": "W10", "pergunta": "Ratificar escopo REP-P sem selfie: 3 telas + fila, 7 rotas → MobileMarcacaoController", "respondida": false, "destrava": ["06"] }
  ],
  "threads": [
    { "id": "01", "titulo": "Rede mínima: E2E de fumaça das 3 telas âncora", "dono": "CL", "vaga": 1, "arquivo": "01-rede-e2e.md",
      "prefixo": ["e2e/ponto-dashboard.spec.ts", "e2e/ponto-espelho.spec.ts", "e2e/ponto-espelho-show.spec.ts"],
      "nao_toca": ["${PAGES}/", "Modules/Ponto/", "e2e/global-setup.ts"],
      "provas": [
        { "tipo": "um_de", "paths": ["e2e/ponto-dashboard.spec.ts", "e2e/ponto-smoke.spec.ts"] },
        { "tipo": "arquivo", "path": "e2e/ponto-espelho.spec.ts" }
      ] },
    { "id": "02", "titulo": "Desamarrar UC ⛓ (docblock → it('UC-…'))", "dono": "CL", "vaga": 1, "arquivo": "02-uc-desamarrar.md",
      "prefixo": ["Modules/Ponto/Tests/Feature/", "${PAGES}/**/*.casos.md (só colunas Teste/Status/last_run)"],
      "nao_toca": ["${PAGES}/**/*.tsx", "${PAGES}/**/*.charter.md", "Modules/Ponto/Services/", "Modules/Ponto/Http/"],
      "provas": [], "nota_provas": "prova = _saida-02.md com o número do casos:report antes/depois (0 UC ⛓ é a meta); zero assertion nova" },
    { "id": "03", "titulo": "a11y: sinal não-cor na divergência + mobile-fit", "dono": "CL", "vaga": 2, "arquivo": "03-a11y-divergencia.md",
      "prefixo": ["${PAGES}/Espelho/Index.tsx", "${PAGES}/Espelho/Show.tsx", "${PAGES}/_components/MonthHeatmap.tsx"],
      "nao_toca": ["prototipo-ui/contrato/ponto-espelho.contract.json", "Modules/Ponto/"],
      "depende_threads": ["01"],
      "provas": [ { "tipo": "contem", "path": "${PAGES}/_components/MonthHeatmap.tsx", "padrao": "sr-only", "nota": "hoje 0 ocorrências em Pages/Ponto/** — texto para leitor de tela no dia em DIVERGENCIA" } ] },
    { "id": "04", "titulo": "Fechamento da competência — BLOQUEADA", "dono": "W", "arquivo": "04-fechamento-bloqueada.md",
      "prefixo": [], "nao_toca": ["Modules/Ponto/Services/ApuracaoService.php"], "depende_decisoes": ["W1", "W2", "W3", "W4"],
      "bloqueio": "W1–W4 sem resposta; abrir é inventar lei (competência, permissão, exceções, reabertura)",
      "provas": [], "nota_provas": "quando destravar: {arquivo ${PAGES}/Fechamento/Index.tsx} + {json_com_chaves contrato/ponto-fechamento.contract.json} + {contem routes.php 'fechamento'}" },
    { "id": "05", "titulo": "Painel de Conformidade CLT — BLOQUEADA", "dono": "W", "arquivo": "05-conformidade-bloqueada.md",
      "prefixo": [], "nao_toca": [], "depende_threads": ["04"], "depende_decisoes": ["W1"],
      "bloqueio": "depende do estado da competência (W1) e da thread 04",
      "provas": [] },
    { "id": "06", "titulo": "REP-P sem selfie: 7 rotas → MobileMarcacaoController + app do colaborador + fila do gestor", "dono": "CL", "vaga": 2, "arquivo": "06-rep-p.md",
      "prefixo": ["Modules/Ponto/Http/routes.php (só o bloco 2 · /ponto/api)", "Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php", "${PAGES}/Mobile/", "prototipo-ui/contrato/ponto-rep-p.contract.json", "Modules/Ponto/Tests/Feature/Wave28MobileMarcacaoTest.php (estender)"],
      "nao_toca": ["Modules/Ponto/Services/MarcacaoService.php", "Modules/Ponto/Services/NsrService.php", "Modules/Ponto/Database/"],
      "depende_decisoes": ["W10"],
      "provas": [
        { "tipo": "nao_contem", "path": "Modules/Ponto/Http/routes.php", "padrao": "abort(501, 'Implementar em MarcacaoApiController::marcar')", "nota": "a rota /ponto/api/marcar aponta pro controller, não pra closure" },
        { "tipo": "contem", "path": "Modules/Ponto/Http/routes.php", "padrao": "MobileMarcacaoController" },
        { "tipo": "um_de", "paths": ["${PAGES}/Mobile/Index.tsx", "${PAGES}/Mobile/Marcar.tsx"] },
        { "tipo": "json_com_chaves", "path": "prototipo-ui/contrato/ponto-rep-p.contract.json", "chaves": ["alvo", "secoes"] },
        { "tipo": "nao_contem", "path": "Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php", "padrao": "selfie", "guarda": true, "nota": "PRESERVAÇÃO — GUARD LGPD da ADR 0383; o Wave28 já falha se voltar" }
      ] },
    { "id": "07", "titulo": "Contratos 4/4 → required", "dono": "CL", "vaga": 3, "arquivo": "07-contratos-required.md",
      "prefixo": ["prototipo-ui/contrato/ponto-fechamento.contract.json", "prototipo-ui/contrato/ponto-rep-p.contract.json", "gate de contrato (onde o repo declara required)"],
      "nao_toca": ["prototipo-ui/contrato/ponto-painel.contract.json", "prototipo-ui/contrato/ponto-espelho.contract.json"],
      "depende_threads": ["04", "05", "06"],
      "provas": [
        { "tipo": "arquivo", "path": "prototipo-ui/contrato/ponto-fechamento.contract.json" },
        { "tipo": "arquivo", "path": "prototipo-ui/contrato/ponto-rep-p.contract.json" }
      ] },
    { "id": "08", "titulo": "PUXAR Painel + Espelho (as 2 com contrato) → protótipo", "dono": "CC", "vaga": 1, "arquivo": "08-puxar-painel-espelho.md",
      "prefixo": ["${COWORK}/ponto-page.jsx", "${COWORK}/oimpresso.com.html"],
      "nao_toca": ["${PAGES}/", "${COWORK}/ponto-telas.jsx", "${COWORK}/ponto-data.jsx", "${COWORK}/ponto-mobile.jsx"],
      "provas": [], "nota_provas": "read-only + build: prova = _saida-08.md com o diff nos dois sentidos (Dashboard/Index.tsx e Espelho/{Index,Show}.tsx × ponto-page.jsx) e a divergência W9 declarada" },
    { "id": "09", "titulo": "PUXAR as 11 telas restantes → protótipo", "dono": "CC", "vaga": 2, "arquivo": "09-puxar-11-telas.md",
      "prefixo": ["${COWORK}/ponto-telas.jsx", "${COWORK}/ponto-data.jsx", "${COWORK}/ponto-ui.jsx", "${COWORK}/oimpresso.com.html"],
      "nao_toca": ["${PAGES}/", "${COWORK}/ponto-mobile.jsx", "${COWORK}/ponto-fechamento.jsx"],
      "depende_threads": ["10"],
      "provas": [], "nota_provas": "prova = _saida-09.md com tabela tela × átomos puxados × divergência declarada (11 linhas)" },
    { "id": "10", "titulo": "Build: REP-P do protótipo sem selfie (ADR 0383)", "dono": "CC", "vaga": 1, "arquivo": "10-build-mobile-sem-selfie.md",
      "prefixo": ["${COWORK}/ponto-mobile.jsx", "${COWORK}/ponto-data.jsx (só o bloco mobile)", "${COWORK}/oimpresso.com.html"],
      "nao_toca": ["${COWORK}/ponto-page.jsx", "${COWORK}/ponto-telas.jsx", "${COWORK}/android-frame.jsx"],
      "provas": [
        { "tipo": "nao_contem", "path": "${COWORK}/ponto-mobile.jsx", "padrao": "selfie" },
        { "tipo": "nao_contem", "path": "${COWORK}/ponto-mobile.jsx", "padrao": "Art. 9" }
      ] },
    { "id": "11", "titulo": "Limpeza: blades mortas + nav legado + inbox residual (+ /react se W8)", "dono": "CL", "vaga": 1, "arquivo": "11-limpeza-blades-inbox.md",
      "prefixo": ["Modules/Ponto/Resources/views/ (exceto reports/)", "prototipo-ui/design-docs/cowork-inbox/ponto-dashboard/", "Modules/Ponto/Http/routes.php (só a rota /react, só se W8 = remover)"],
      "nao_toca": ["Modules/Ponto/Resources/views/reports/espelho-pdf.blade.php", "Modules/Ponto/Resources/lang/", "${PAGES}/"],
      "provas": [
        { "tipo": "ausente", "path": "Modules/Ponto/Resources/views/layouts/module.blade.php" },
        { "tipo": "ausente", "path": "Modules/Ponto/Resources/views/dashboard/index.blade.php" },
        { "tipo": "ausente", "path": "prototipo-ui/design-docs/cowork-inbox/ponto-dashboard/Index.casos.md" },
        { "tipo": "arquivo", "path": "Modules/Ponto/Resources/views/reports/espelho-pdf.blade.php", "guarda": true, "nota": "PRESERVAÇÃO — a única blade viva (PDF do espelho) tem de sobreviver" }
      ] },
    { "id": "12", "titulo": "Relatórios legais AFD/AFDT/AEJ — BLOQUEADA", "dono": "W", "arquivo": "12-relatorios-legais-bloqueada.md",
      "prefixo": [], "nao_toca": ["Modules/Ponto/Http/Controllers/RelatorioController.php"], "depende_decisoes": ["W7"],
      "bloqueio": "W7: ordem de implementação em ReportService; 7 chaves seguem abort(501) e 501 nunca é sucesso",
      "provas": [] }
  ]
}
```
