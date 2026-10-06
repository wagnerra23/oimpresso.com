---
sessao: "12"
titulo: ALVO nfe-brasil--tributacao + âncora nos charters
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806, que trouxe o fiscal-tributacao.jsx)
commit: 58a63a8872
medido_em: espelho servido por servirEstatico (render-proto-baseline.mjs), localhost, chromium do alvo.mjs, dark, 1280×900
prefixo_tocado: governance/design/targets/ · os 2 charters da thread · este arquivo
---
# _saida-12 · ALVO da Tributação e âncora promovida

**Resposta curta:** o alvo existe e o `pedido.mjs` saiu do exit 2 para `NfeBrasil/Tributacao`. A âncora dos dois charters agora é `prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx`, com D-ANCORA citada. Ficou de fora uma edição que a thread pede (§2).

## 1 · Feito (commit `58a63a8872`)

| arquivo | o quê |
|---|---|
| `governance/design/targets/nfe-brasil--tributacao.{secoes,alvo}.json` | aba default **Saúde fiscal**, 6 seções: `header` · `tabs` · `abas` · `saude` · `pendencias` (`data-contract="saude-fiscal"`) · `rodape` |
| `governance/design/targets/nfe-brasil--tributacao--operacoes.{secoes,alvo}.json` | aba **Operações** (2º chip), 3 seções: `abas` · `comecar` · `operacoes` |
| `governance/design/targets/nfe-brasil--tributacao--excecoes.{secoes,alvo}.json` | aba **Exceções** (id `regras`, 3º chip), 5 seções: `abas` · `cascata` · `toolbar` · `regras` · `decisao` |
| `governance/design/targets/nfe-brasil--tributacao--simulador.{secoes,alvo}.json` | aba **Simulador** (4º chip), 2 seções: `abas` · `simulador` |
| `governance/design/targets/README.md` | 4 linhas na tabela "Alvos exportados", com o comando que reproduz cada uma |
| `resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md` | `related_prototype: n/a (hub…)` → `prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx` + linha de comentário YAML citando D-ANCORA |
| `resources/js/Pages/NfeBrasil/Tributacao/RegraForm.charter.md` | `related_prototype: n/a (herda PT-02…)` → idem |

**Por que 4 alvos e não 1:** a thread pede as 4 abas. No protótipo cada aba substitui o conteúdo da anterior (`FxTributacaoPage`, `fiscal-tributacao.jsx:1004-1036`), e a medição só alcança uma aba por vez (`--clicar`). As três abas que exigem clique ganharam slug próprio. O `--rota`/`--clicar` vão para o JSON, e o `secao-check` os repassa. O slug principal é o que o `pedido.mjs` acha para `NfeBrasil/Tributacao`.

**Comentário no charter, em vez de chave nova:** o `ancora.mjs` lê o valor de `related_prototype` como caminho. Texto ao lado do valor, ou uma chave que não está no schema, quebraria a âncora ou o `memory-schema-gate`. A citação ficou numa linha de comentário YAML logo abaixo.

**Caminho citado no comentário:** a decisão fala em `prototipos/nfe-tributacao/` (PR #7145). Os três arquivos desse diretório **não estão mais no `main`**: `git ls-files | grep nfe-tributacao` devolve só `memory/reference/prototipo-ui/sources/Wagner/nfe-tributacao.md`. O comentário cita esse doc e o número do PR, não o diretório que sumiu.

### Provas (saídas literais)

- **Determinismo:** re-medir o alvo principal e o do simulador com `--saida` e comparar com `cmp` → `IDENTICO` · `IDENTICO2`.
- **Tema / proveniência:** `base.theme = "dark"` · `nos_totais = 561` · `rota = "fiscal-tributacao"`. `__oiLazyDone` mais 2000 ms quieto vêm do próprio `alvo.mjs`.
- **Proof da thread (`pedido.mjs`):**
  - **antes**, com o alvo renomeado temporariamente: `NÃO MEDI: sem alvo medido pra "NfeBrasil/Tributacao"…` → **rc=2**
  - **depois**, `--tela NfeBrasil/Tributacao --secoes` lista as 6 seções → **rc=0**
  - **depois**, `--secao saude` sai com **rc=1** e `REPROVADO: bloco B.dados VAZIO pra "saude"`. É a reprovação esperada: o `.dado` é responsabilidade da onda que for usar a seção (§3).
- **`secao-check --url` contra o espelho:** `conforme` · rc=0 nos 4 slugs.
- **Controle negativo:** `--injetar-falha ".fx-page > .trb-saude"` → a medida **MUDOU**; `secao-check --medido` sobre ela → `REGREDIU` · **rc=1**.
- **Âncora:** `node scripts/design/ancora.mjs NfeBrasil/Tributacao/{Index,RegraForm}` → `âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx` · frescor verificado contra o Cowork vivo em 2026-10-06T17:04:33Z. O mesmo comando avisa que o espelho não cobre o vivo (29 de 1288 arquivos não desceram). Isso fala do espelho inteiro, não deste arquivo.
- **YAML estrito:** `node scripts/memory-schemas/validate.mjs` → 2 OK `[charter.schema.json]`; `yaml.safe_load` → `'prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx'` nos dois.
- **Gates que leem charter:**
  - `anchor-content-check --check`: 0 podres · rc=0
  - `charter-refs --check`: 0 · rc=0
  - `casos-coverage-guard`: sem violações novas · rc=0

## 2 · Não feito, e por quê

- **`memory/reference/prototipo-ui/sources/Wagner/nfe-tributacao.md` §1 não foi editado.** A thread pede para registrar ali que o #7145 vira material de comparação, mas o pedido desta sessão restringiu a escrita a `governance/design/targets/`, aos 2 charters e a este arquivo. Hoje o §1 diz "NÃO promover a `related_prototype` nos charters sem decisão [W]". A decisão veio, e a frase ficou desatualizada. Texto proposto no §3.
- **`.dado` das seções vazio.** Ele vem do Model/Service real do `main` (§3-bis), e preenchê-lo é tarefa da onda que consome a seção (05 · 08 · 14), não deste papel de medição.
- **Drawers não medidos:** regra, operação e onboarding pelo certificado só existem depois de clicar numa linha.
- **As outras 9 abas** (Bateria · Contador · Vínculo · Por estado · Serviços · Importação · Devoluções · Entradas · Jana) não foram medidas porque a thread nomeia só 4.

## 3 · Pedido literal pro [CL]/[W]

> No `memory/reference/prototipo-ui/sources/Wagner/nfe-tributacao.md` §1, acrescentar logo abaixo do título da seção:
> `**Atualização 2026-10-06 — D-ANCORA [W]:** o alvo de forma da Tributação passou a ser o protótipo do Cowork (\`fiscal-tributacao.jsx\`, rota \`fiscal-tributacao\`). Este desenho fica como material de comparação, não como alvo. Os charters \`Index\` e \`RegraForm\` foram promovidos na thread 12 do playbook Fiscal (\`_saida-12.md\`). Os avisos abaixo são o estado anterior à decisão.`

## 4 · Descobertas que mudam outra sessão

- **Thread 05 (RegraForm):** o `RegraForm.charter.md` agora aponta para o `fiscal-tributacao.jsx`. Nesse arquivo o formulário de regra é o **drawer** `TrRegraDrawer` (`:162-211`), não uma página PT-02. Quem for executar a 05 precisa decidir qual seção do alvo vale para a página `/nfe-brasil/tributacao/regras/create`. Nenhum dos 4 alvos mede o drawer.
- **Thread 08:** o slot do simulador está em `nfe-brasil--tributacao--simulador`, não no slug principal. O `/onda` para a seção `simulador` precisa apontar esse slug.
- **Thread 14:** a seção `saude`/`pendencias` está no slug principal.
- **Porta 5591** estava ocupada por outro processo nesta máquina. Esta sessão serviu o espelho na 5733.
