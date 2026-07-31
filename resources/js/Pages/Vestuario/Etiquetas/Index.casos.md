---
id: resources-js-pages-vestuario-etiquetas-index-casos
casos: Etiquetas TAG vestuário · /vestuario/etiquetas
irmaos: Index.charter.md (lei) · Index.tsx (código) · RUNBOOK-etiqueta-tag.md (MWART F1)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a etiqueta é o elo entre a peça física e o POS — um EAN-13 inválido não vende a peça, e um texto que estoura 50×30mm desperdiça a mídia. Esses contratos não mudam quando a tela ganhar busca por nome.
owner: wagner
related_us: [US-VEST-020]
related_cu: [CU-VEST-01, CU-VEST-02, CU-VEST-03, CU-VEST-04, CU-VEST-05, CU-VEST-06, CU-VEST-07, CU-VEST-08]
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Modules Pest → Pest Vestuario"
---

# Casos de Uso & Aceite — Etiquetas TAG vestuário (`/vestuario/etiquetas`)

> **Âncora:** os UC derivam dos CU do
> [SDD §6.1](../../../../../memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md) —
> `CU-VEST-01` … `CU-VEST-08` — **nunca do `Index.tsx`**: teste derivado do código é tautológico
> e trava o desvio em vez de pegá-lo ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-06-05).
>
> **Por que este arquivo nasce agora:** completa o trio da tela (o charter existe desde 2026-07-11;
> `npm run screen:files -- Vestuario/Etiquetas/Index` acusava `✗ .casos.md`). É o chip da **Onda 4**
> do [passo 5](../../../../../memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md)
> e fecha a **única lacuna** que `node scripts/governance/requisitos-status.mjs Vestuario` nomeava.
>
> ⚠️ **Módulo do cliente piloto.** ROTA LIVRE (`business_id=4`, Larissa) roda vestuário em produção
> há 2+ anos. **Nenhum teste daqui usa biz=4** — biz=1 (Wagner) e biz=99 (adversário),
> [ADR 0101](../../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md).

⚖️ **FORÇA DO VEREDITO — leia antes de confiar no status.** A lane que roda estes testes é o job
`Pest Vestuario` do workflow `Modules Pest` (`.github/workflows/modules-pest.yml`, matrix
compartilhada com Arquivos/ComunicacaoVisual/Fiscal/NfeBrasil/Repair). Ela **não** consta em
[`governance/required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json)
— as lanes Pest **required** são Financeiro, NfeBrasil e Unit. Portanto: **ADVISORY — reprova
visível, não bloqueia merge.** O que bloqueia merge aqui é o `Casos-coverage · ratchet` (G-1 trio +
G-2 UC↔teste) e o `anchor-lint ADR 0273`, esses sim required.

> 🔬 **A lane roda sqlite `:memory:` SEM migrar** (o cabeçalho do workflow explica: migrations
> UltimatePOS são MySQL-only). Teste que depende de tabela **pula** — e teste que pula **não prova
> nada** ([lápide §5 2026-07-24](../../../../../memory/proibicoes.md)). Por isso os UC **novos** deste
> PR são pure-logic com **controle-positivo** + **guarda anti-vácuo**; os UC marcados
> *"skip gracioso"* abaixo só produzem veredito real na lane MySQL / CT 100.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora (SDD §6) | Teste | Status |
|----|-------------|------|-----------------|-------|--------|
| UC-VET-01 | Settings de etiqueta de um business não vazam pro outro | must `[T0]` | `CU-VEST-01` item 3 · `CU-VEST-05` item 3 | `UsVest020EtiquetaTagControllerTest` | 🧪 *(skip gracioso sem tabela)* |
| UC-VET-02 | Business sem settings recebe defaults; config exposta não carrega URL do cliente | must | `CU-VEST-01` itens 2 e 4 | `UsVest020EtiquetaTagControllerTest` · `EtiquetaTagContratoTest` | 🧪 |
| UC-VET-03 | Todo EAN-13 impresso passa no check GS1 | must | `CU-VEST-03` itens 1-3 | `EtiquetaTagContratoTest` · `W27EtiquetaGradeTest` | 🧪 |
| UC-VET-04 | Lote de N itens gera N etiquetas; lote vazio é rejeitado | must | `CU-VEST-02` itens 1-3 | `W27EtiquetaGradeTest` | 🧪 |
| UC-VET-05 | Etiqueta cabe em 50×30mm: trunca, mantém acento, respeita a config | must | `CU-VEST-04` itens 1-4 | `EtiquetaTagContratoTest` | 🧪 |
| UC-VET-06 | Gerar etiqueta NÃO grava nada e não lê preço do produto | must `[V0]` | `CU-VEST-07` itens 1-2 | `EtiquetaTagContratoTest` | 🧪 |
| UC-VET-07 | QR Code liga por business e não vaza | should `[T0]` | `CU-VEST-05` itens 1-2 | `UsVest020EtiquetaTagControllerTest` | 🧪 *(skip gracioso sem tabela)* |
| UC-VET-08 | PDF A4 traz as N etiquetas com os mesmos campos do ZPL | must | `CU-VEST-06` itens 1-4 | `UsVest020EtiquetaTagControllerTest` | 🧪 |
| UC-VET-09 | Endpoints de lote exigem sessão autenticada | must `[T0]` | `CU-VEST-08` item 3 | `UsVest020EtiquetaTagControllerTest` | 🧪 |

> 🧪 **Nenhum status aqui é afirmação de verde.** Este PR **não executou teste algum** (CT 100/CI —
> [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). O veredito
> vem da lane ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-15).

---

## UC-VET-01 · Settings de etiqueta de um business não vazam pro outro · `must` `[T0]`

- **Persona:** Wagner (biz=1) e um adversário (biz=99). **Nunca biz=4** — é a ROTA LIVRE viva.
- **Aceite:** Dado que o business 1 configurou `etiqueta.width_dots` e `etiqueta.qr_enabled` ·
  Quando a configuração do business 99 é resolvida · Então o 99 recebe os **defaults**, e nenhum
  valor configurado pelo 1 aparece na resposta dele.
- **Teste:** [`UsVest020EtiquetaTagControllerTest`](../../../../../Modules/Vestuario/Tests/Feature/UsVest020EtiquetaTagControllerTest.php)
  — *"settings biz=1 não vazam pra biz=99 (cross-tenant adversário)"*.
- **Contrato:** `CU-VEST-01` item 3 + `CU-VEST-05` item 3 do SDD ·
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  charter §Non-Goals (*"NÃO cruza tenants — `business_id` scope (Tier 0)"*).
- **Regressão que defende:** `VestuarioSettingsResolver::loadSettings` usa
  `withoutGlobalScopes(['business_id'])` (com o comentário `// SUPERADMIN:` exigido pelo Tier 0) e
  **re-aplica** o `where('business_id', …)` logo abaixo. Se um refactor futuro remover essa
  cláusula confiando no global scope — que ali está desligado —, a configuração de uma loja passa a
  valer para todas as outras.
- **Status: 🧪** — o teste existe e cita o UC. Ele **pula** na lane sqlite (sem `vestuario_settings`);
  o veredito real vem da lane MySQL / CT 100.

## UC-VET-02 · Business sem settings recebe defaults; a config exposta não carrega URL do cliente · `must`

- **Persona:** qualquer loja que nunca abriu a configuração de etiqueta.
- **Aceite:** Dado um business sem linha em `vestuario_settings` · Quando a tela é montada · Então
  a config vem completa (largura, altura, dpi, margem, QR) nos valores padrão de 50×30mm @203dpi
  com QR desligado; **e** nenhum campo dessa config carrega a URL de consulta do cliente.
- **Teste:** [`UsVest020EtiquetaTagControllerTest`](../../../../../Modules/Vestuario/Tests/Feature/UsVest020EtiquetaTagControllerTest.php)
  — *"getPublicConfig retorna defaults quando business sem settings"* ·
  [`EtiquetaTagContratoTest`](../../../../../Modules/Vestuario/Tests/Feature/EtiquetaTagContratoTest.php)
  — *"UC-VET-02: a config exposta pro front NÃO carrega o template de URL do QR"*.
- **Contrato:** `CU-VEST-01` itens 2 e 4 do SDD · RUNBOOK §"Settings configurable"
  (*"Defaults preservam comportamento atual"*).
- **Regressão que defende:** `qr_data_template` pode conter uma URL **customizada do cliente**;
  `getPublicConfig` a omite de propósito. O assert não procura a chave pelo nome — procura
  **qualquer valor** que pareça URL ou template, porque renomear o campo não pode fazer o
  vazamento passar.
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

## UC-VET-03 · Todo EAN-13 impresso passa no check GS1 · `must`

- **Persona:** `rota.vendas-04` no balcão — se o dígito verificador estiver errado, a leitora
  recusa e **a peça não vende**.
- **Aceite:** Dado um SKU qualquer (numérico, alfanumérico ou 100% alfabético) · Quando a etiqueta
  é gerada · Então o EAN-13 tem 13 dígitos e o check digit fecha pelo cálculo GS1 mod-10 ·
  **E** um EAN-13 informado com check errado é **recusado**, não impresso.
- **Teste:** [`EtiquetaTagContratoTest`](../../../../../Modules/Vestuario/Tests/Feature/EtiquetaTagContratoTest.php)
  — *"UC-VET-03: todo EAN-13 emitido passa no check GS1 recalculado por caminho independente"* +
  *"…com 13 inválido é RECUSADO"* + *"…o EAN que aparece no ZPL é o mesmo que o service declara"*;
  reforçado por [`W27EtiquetaGradeTest`](../../../../../Modules/Vestuario/Tests/Feature/W27EtiquetaGradeTest.php)
  (vetores GS1 conhecidos).
- **Contrato:** `CU-VEST-03` itens 1-3 do SDD · SPEC [US-VEST-020](../../../../../memory/requisitos/Vestuario/SPEC.md)
  (*"código barras"*) · RUNBOOK §Acceptance criteria.
- **Regressão que defende:** **dupla-confirmação por 2 caminhos** — o teste recalcula o check
  somando **da direita** (peso 3 no último dígito do payload), enquanto o service soma **da
  esquerda** por paridade de posição. Um espelho do algoritmo passaria mesmo com o algoritmo
  errado; dois caminhos independentes têm que concordar. Cobre também o fallback CRC32 do SKU
  100% alfabético, que é onde um EAN inválido nasceria em silêncio.
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

## UC-VET-04 · Lote de N itens gera N etiquetas; lote vazio é rejeitado · `must`

- **Persona:** Larissa etiquetando uma arara inteira.
- **Aceite:** Dado 3 itens no lote · Quando o ZPL é gerado · Então saem exatamente 3 etiquetas
  bem-formadas (3 pares `^XA`/`^XZ`), com o nome de cada peça · **E** um lote vazio é rejeitado
  em vez de produzir arquivo em branco.
- **Teste:** [`W27EtiquetaGradeTest`](../../../../../Modules/Vestuario/Tests/Feature/W27EtiquetaGradeTest.php)
  — *"gerarLote concatena múltiplas etiquetas ZPL"* + *"gerarLote rejeita array vazio"*.
- **Contrato:** `CU-VEST-02` itens 1-3 do SDD · SPEC US-VEST-020 (*"Geração lote: selecionar
  produto + variação → imprime N etiquetas"*).
- **Regressão que defende:** ZPL é concatenação de blocos. Um `^XZ` a menos e a impressora térmica
  fica esperando o fim da etiqueta — trava o rolo. Um lote vazio enviado à impressora consome
  papel sem imprimir nada.
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

## UC-VET-05 · Etiqueta cabe em 50×30mm: trunca, mantém acento, respeita a config · `must`

- **Persona:** Larissa cadastrando "Camiseta Básica Algodão Pima Coleção Verão 2026" — nome que
  não cabe numa TAG de 5cm.
- **Aceite:** Dado um nome de 46 caracteres, uma cor de 40 e uma coleção de 40 · Quando a etiqueta
  é gerada · Então **nenhum** dos três textos aparece inteiro no ZPL, mas o começo de cada um
  continua legível · **E** o ZPL declara UTF-8 (`^CI28`) e o conteúdo permanece codificado
  corretamente · **E** as dimensões impressas seguem a configuração efetiva do business.
- **Teste:** [`EtiquetaTagContratoTest`](../../../../../Modules/Vestuario/Tests/Feature/EtiquetaTagContratoTest.php)
  — *"UC-VET-05: nome/cor/coleção longos são truncados"*, *"…acento sobrevive"*,
  *"…truncagem é mb-safe"*, *"…dimensões do ZPL seguem a configuração efetiva"*.
- **Contrato:** `CU-VEST-04` itens 1-4 do SDD · RUNBOOK §"Layout ZPL térmico 50×30mm @203dpi
  (Argox/Zebra)" · charter §UX targets.
- **Regressão que defende:** duas de uma vez. (a) Trocar `mb_substr` por `substr` parte o
  caractere multibyte ao meio e a térmica cospe lixo justamente em nome de produto brasileiro —
  o teste usa 40 caracteres acentuados e valida a codificação do ZPL inteiro, não a string do
  campo. (b) Remover o `^CI28` "porque não faz nada" faz TODO acento virar lixo na impressora,
  algo que nenhum teste de conteúdo pegaria (a string em PHP continua certa).
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

## UC-VET-06 · Gerar etiqueta NÃO grava nada e não lê preço do produto · `must` `[V0]`

- **Persona:** Larissa — etiquetar a arara **não pode** mexer no estoque nem no preço das peças.
- **Aceite:** Dado um lote de 2 itens · Quando o ZPL é gerado · Então **nenhuma** escrita
  (`INSERT`/`UPDATE`/`DELETE`/`REPLACE`/`TRUNCATE`/`DROP`/`ALTER`) é emitida no banco · **E** o
  preço que aparece na etiqueta é exatamente o que o operador informou, formatado em pt-BR.
- **Teste:** [`EtiquetaTagContratoTest`](../../../../../Modules/Vestuario/Tests/Feature/EtiquetaTagContratoTest.php)
  — *"UC-VET-06: gerar lote NÃO emite nenhuma escrita no banco"* + *"…o preço impresso é o que o
  operador informou"*.
- **Contrato:** `CU-VEST-07` itens 1-2 do SDD · charter §Non-Goals (*"NÃO altera preço/estoque do
  item (etiqueta é saída, não mexe em valor/estoque)"*) + §Anti-hooks (*"NÃO altera estoque/valor
  do produto ao gerar etiqueta"*, *"NÃO grava nada em GET"*) ·
  [proibicoes §REGRA MESTRE valor/estoque](../../../../../memory/proibicoes.md).
- **Regressão que defende:** é o Non-Goal mais caro da tela. O caminho natural de "melhorar a
  etiqueta" é buscar o preço da variação no banco (é o que a tela **legada** faz — SDD §5.4 D-4).
  No dia em que alguém fizer isso, a mudança **tem** que passar pela REGRA MESTRE
  (dupla-confirmação + tabela antes→depois + aprovação [W]), e não entrar de carona num PR de
  layout. Este teste é o alarme.
- **Desenho anti-vácuo (por que ele não passa no vazio):** teste que afirma "não escreveu" passa
  trivialmente quando **nada** aconteceu. Por isso ele (1) executa um `select` sentinela e exige
  que o listener o capture — **controle-positivo**, prova que a observação está armada — e (2)
  exige que 2 etiquetas tenham realmente saído antes de olhar as queries.
- **⚠️ `[V0]` — dupla-confirmação:** este UC **contrata a AUSÊNCIA** de movimento de valor/estoque;
  ele não altera cálculo nenhum, logo não há tabela antes→depois a apresentar. A dupla-confirmação
  fica **exigida do lado oposto**: qualquer PR que faça a tela ler ou gravar valor/estoque precisa
  dela para poder mudar este UC.
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

## UC-VET-07 · QR Code liga por business e não vaza · `should` `[T0]`

- **Persona:** loja que quer QR de consulta na etiqueta; a loja vizinha que **não** quer.
- **Aceite:** Dado `etiqueta.qr_enabled = true` no business 1 · Quando a etiqueta dele é gerada ·
  Então o ZPL contém a instrução de QR e o payload · **E** o business 99, sem configuração,
  continua sem QR.
- **Teste:** [`UsVest020EtiquetaTagControllerTest`](../../../../../Modules/Vestuario/Tests/Feature/UsVest020EtiquetaTagControllerTest.php)
  — *"ZPL contém instrução ^BQ quando qr_enabled=true via settings"* + *"ZPL NÃO contém ^BQ por
  default"* + *"settings biz=1 não vazam pra biz=99"*.
- **Contrato:** `CU-VEST-05` itens 1-2 do SDD · RUNBOOK §"QR Code — instrução ZPL" ·
  charter §Goals (*"Geração de EAN-13 + QR Code por item"*).
- **Regressão que defende:** o QR ocupa o canto direito da etiqueta de 50×30mm. Ligá-lo por
  engano para todos os clientes empurra o EAN-13 e estraga a impressão de quem nunca pediu QR —
  regressão física, invisível em qualquer teste de payload.
- **Status: 🧪** — o caso do `qr_enabled=true` **pula** na lane sqlite (precisa da tabela); o caso
  do default roda. Veredito real da lane MySQL / CT 100.

## UC-VET-08 · PDF A4 traz as N etiquetas com os mesmos campos do ZPL · `must`

- **Persona:** loja sem impressora térmica (ou a térmica quebrada num sábado).
- **Aceite:** Dado 10 itens · Quando o PDF é gerado · Então as 10 etiquetas aparecem no documento,
  cada uma com nome, tamanho, cor, coleção, preço em pt-BR, EAN-13 e SKU · **E** o documento
  declara a contagem.
- **Teste:** [`UsVest020EtiquetaTagControllerTest`](../../../../../Modules/Vestuario/Tests/Feature/UsVest020EtiquetaTagControllerTest.php)
  — *"blade vestuario::etiquetas.pdf compila e renderiza HTML válido"* + *"blade pdf renderiza 10
  etiquetas em grid (acceptance criteria)"*.
- **Contrato:** `CU-VEST-06` itens 1-4 do SDD · SPEC US-VEST-020 (*"Test Pest: gera PDF com 10
  etiquetas, valida campos presentes"*) · RUNBOOK §"PDF fallback (DomPDF + milon/barcode)".
- **Regressão que defende:** o histórico do próprio teste conta a história — ele já mascarou um
  erro real (EAN-13 com check digit inválido em 9 de 10 iterações disparava
  `WrongCheckDigitException` **antes** de a assertion rodar). O PDF renderiza barcode de verdade;
  qualquer EAN inválido estoura no render em vez de sair torto na folha.
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

## UC-VET-09 · Endpoints de lote exigem sessão autenticada · `must` `[T0]`

- **Persona:** um visitante anônimo tentando `POST /vestuario/etiquetas/lote/{zpl,pdf}`.
- **Aceite:** Dado nenhuma sessão · Quando o POST é feito · Então a resposta é redirecionamento
  para login ou 401 — **nunca** um arquivo gerado.
- **Teste:** [`UsVest020EtiquetaTagControllerTest`](../../../../../Modules/Vestuario/Tests/Feature/UsVest020EtiquetaTagControllerTest.php)
  — *"endpoint /vestuario/etiquetas/lote/zpl exige autenticação"* + *"…/lote/pdf exige autenticação"*.
- **Contrato:** `CU-VEST-08` item 3 do SDD · RUNBOOK §Rotas (stack de middleware canônica
  UltimatePOS) · `Modules/Vestuario/Routes/web.php`.
- **Regressão que defende:** sem `business_id` de sessão o service cai nos defaults e geraria
  etiqueta para "nenhum tenant" — é o degrau anterior a um vazamento. O middleware é a única
  coisa entre o endpoint e a internet, porque a checagem de **permissão** ainda não bloqueia
  (ver `[BACKLOG]` abaixo).
- **Status: 🧪** — teste cita o UC; veredito pendente da lane (advisory).

---

## Backlog — achados sem contrato em 2 fontes, ou sem chokepoint testável neste chip

> Regra dura: vira **UC com id** só o que tem contrato em **≥2 fontes** *e* pode ganhar teste agora.
> O resto fica como frase visível, **sem id** — UC com id e sem teste é órfão, e o `casos-gate` G-2
> (required) **bloqueia o merge de quem for atendê-lo** ([proibicoes §5](../../../../../memory/proibicoes.md)
> 2026-07-16). 7 UC ancorados valem mais que 30 órfãos.

- `[BACKLOG]` **Cópias multiplicam o lote:** `N` itens × `C` cópias devem produzir `N×C` etiquetas
  (`CU-VEST-02` item 4). A expansão vive em `EtiquetaTagController::expandItems`, que é **privado**,
  e exercitá-la exige request autenticada — que a lane sqlite não sobe. Virar UC exige lane MySQL
  ou tornar a expansão testável; **o chip está proibido de editar o Controller**.
- `[BACKLOG]` **Permissão `vestuario.etiqueta.create` deveria BLOQUEAR, e hoje só loga.**
  `EtiquetaTagController::authorizeAccess` emite `Log::warning('…permission_check_missing')` e
  segue o fluxo (o código declara: *"Sprint 3 vira hard-block"*). O RUNBOOK e o charter anunciam a
  permissão **sem essa ressalva** — divergência registrada no SDD §9 D-1. Ligar o hard-block é
  decisão de [W]; escrever o UC antes disso criaria um caso vermelho por design.
- `[BACKLOG]` **Achar a peça por nome/SKU em vez de digitar `product_id` numérico** — o maior gap
  de adoção (SDD §5.4 D-1/D-2). A tela legada `/labels/show` tem autocomplete; a nova não. Não é
  regressão (as duas coexistem por decisão do RUNBOOK), é o que falta para o cutover.
- `[BACKLOG]` **Pré-carga por `?purchase_id=`** — etiquetar a arara logo após receber a compra
  (SDD §5.4 D-3). Existe no legado; ausente na tela nova.
- `[BACKLOG]` **Preço vindo do grupo de preço de venda** (`[V0]` — SDD §5.4 D-4). Hoje é digitado.
  Qualquer implementação passa pela REGRA MESTRE, e **inverte** o UC-VET-06 item 2.
- `[BACKLOG]` **Preview antes de imprimir.** O charter §UX targets promete *"Preview/edição de
  itens antes de imprimir (evita desperdício de etiqueta)"* — há **edição**, não há **preview**
  (o clique baixa o arquivo). **Divergência aberta registrada nos dois lados** (SDD §9 D-2):
  podar a promessa ou construir o preview é decisão de produto ([W]), não conserto silencioso.
- `[BACKLOG]` **Teto de 500 itens × 100 cópias = 50.000 etiquetas por POST**, com `Log::info` por
  etiqueta (SDD §9 R-2). Nenhum incidente registrado; sem contrato em 2 fontes.
