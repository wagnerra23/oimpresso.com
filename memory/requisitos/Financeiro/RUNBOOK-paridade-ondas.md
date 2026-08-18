---
title: "Paridade protótipo↔tela do módulo Financeiro — manual de ondas, uma tela por onda"
module: Financeiro
owner: W
status: ativo
last_validated: '2026-08-18'
preconditions:
  - "Preview do protótipo no ar e portão fail-closed verde (--preview-ds exit 0)"
  - "Onda 0 fechada antes de qualquer onda de tela (as 7 âncoras do módulo hoje NÃO são mensuráveis)"
  - "Leitura obrigatória de prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md — este módulo já teve um batch F3 rejeitado inteiro"
steps:
  - "Onda 0 — destravar âncora, map e frescor (bloqueante, não toca .tsx)"
  - "Por tela: medir os 2 lados, classificar direção, decidir, aplicar região, travar, registrar"
  - "1 onda = 1 tela = 1 PR; telas sem âncora fecham por conformidade de Padrão de Tela, não por repintura"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0282-protocolo-v2-colapso-ratificacao
  - 0299-figma-nao-e-fonte-de-design
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
---

# Paridade protótipo↔tela — Financeiro, por ondas

> **Uma tela por onda. Uma onda por PR.** O módulo tem **21 telas vivas**; o protótipo cobre **6 delas** mais 2 módulos vizinhos. Isso não é um defeito a corrigir — é o mapa que decide quais ondas são *de design* e quais são *de conformidade*.

---

## 0. O que este manual é — e o que ele NÃO é

**NÃO é** um protocolo novo. O protocolo de aplicação de protótipo já tem dono e é executável:

| Camada | Dono | Como se lê |
|---|---|---|
| política, autoridade, invariantes | [`prototipo-ui/PROTOCOL.md`](../../../prototipo-ui/PROTOCOL.md) | ler |
| fases, IDs de projeto, comandos vigentes | `node prototipo-ui/protocolo.config.mjs` | **rodar** (é o painel; nunca copiar comando pra outro doc) |
| execução guiada | skill `aplicar-prototipo` | invocar |
| método anti-regressão | [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](../../../prototipo-ui/PROCESSO_MEMORIA_CC.md) §5 + [`memory/LICOES_CC.md`](../../LICOES_CC.md) | ler antes de tocar design-memory |
| anti-padrões deste módulo | [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) | **obrigatório** — 6 meta + 15 técnicos |

**É** o plano de sequenciamento das 21 telas do Financeiro dentro desse protocolo: qual tela em qual onda, com que fonte, com que direção de paridade, e a ficha que cada onda tem de preencher.

---

## 1. Retrato medido em 2026-08-18 (com o comando de cada número)

Nenhum número aqui é estimado. Cada linha traz a porta viva que o reproduz — se um número incomodar, **re-rode o comando**, não edite o número.

| O que | Valor | Como reproduzir |
|---|---|---|
| Telas vivas do módulo | **21** | `npm run screen-coverage:report` (linha `Financeiro`) |
| Charter | **21/21** | idem |
| Scorecard | **21/21** | idem |
| E2E (Browser ∪ VRT) | **2/21** | idem |
| VRT pixel · L2 estados | **2** · **1** | idem |
| `.casos.md` | **21/21** | `git ls-files` sobre `Pages/Financeiro/**` filtrando `.casos.md` |
| UC declarados nos casos.md | **90** | `grep -cE '^## UC-'` somado nos 21 |
| UC **citados por teste** (⛓) | **33** | `npm run casos:report` — régua do gate; ≠ dos 90 acima |
| Telas com âncora Cowork declarada | **7/21** | `node prototipo-ui/ancora.mjs <Mod/Tela>` |
| …dessas, âncoras **mensuráveis** | **0/7** | idem — todas saem `⚠️ NÃO MEDIDO` (ver §4.1) |
| `*-visual-comparison.md` no módulo | **8** | `ls memory/requisitos/Financeiro/*visual-comparison.md` |
| …stale medidos | **4** (Dre 80d · Fluxo 53d · Caixa 46d · Unificado 32d) | `node scripts/governance/visual-comparison-staleness.mjs` |
| `unificado.map.json` | **STALE** (protótipo re-exportou) | `node scripts/governance/design-code-map-check.mjs --check` |
| Linha do Financeiro no quadro de frescor | **ausente** | [`prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md`](../../../prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md) |

> **Sobre "90 UC" × "33 ⛓":** medem coisas diferentes. 90 é quanto está **escrito** nos casos.md; 33 é quanto está **citado por teste** — e é este que o `casos-gate` cobra. Não somar, não trocar um pelo outro.

---

## 2. A regra que muda tudo: paridade tem TRÊS direções

O erro mais caro deste loop não é errar a cor — é **repintar uma tela que já passou o protótipo**. O quadro de frescor registra o precedente em outro módulo: a Caixa Unificada viva realiza tudo do protótipo *mais* filas em DB, broadcast LGPD, IA real e SLA — e por isso está marcada como **o OURO: não repintar**.

Antes de tocar em qualquer `.tsx`, a onda classifica a tela em uma das quatro:

| Direção | Significa | O que a onda entrega |
|---|---|---|
| 🟠 **produção ATRÁS** | o protótipo tem capacidade que a tela não tem | catch-up real — é aqui que se escreve código de UI |
| 🔵 **produção À FRENTE** | a tela evoluiu além do protótipo | **canal reverso**: o design puxa o vivo. A onda escreve no `FRESCOR`, **não** no `.tsx` |
| ⚪ **empate de governança** | divergem em fundação (token, shell, PT) | PR sequencial isolado de fundação — nunca junto com tela |
| ✅ **paridade** | batem | trava (VRT/contrato) e fecha |

**Corolário duro:** uma onda que classifica 🔵 e mesmo assim edita a tela está regredindo produção com aparência de zelo. O veredito da direção é **medido** (§5 passo 2), nunca no olho.

---

## 3. Mapa protótipo → tela viva (medido no runtime, não no grep)

A subnav do protótipo foi lida no runtime em `http://localhost:5577/oimpresso.com.html`. O runtime mostra **8 entradas**, e não as 6 que o `FIN_SUB` do `financeiro-page.jsx` declara — o shell injeta Cobrança e Assinaturas ao lado. Grep no jsx teria dado a resposta errada.

| Tela no protótipo | Fonte (arquivo do espelho) | Tela viva | Âncora declarada no charter |
|---|---|---|---|
| Visão unificada | `financeiro-page.jsx` | `Unificado/Index.tsx` (3090 ln) | ✅ declarada |
| Fluxo de caixa | `financeiro-telas-extras.jsx` → `TelaFluxo` | `Fluxo/Index.tsx` | ✅ declarada |
| Conciliação | `financeiro-telas-extras.jsx` → `TelaConciliacao` | `Conciliacao/Index.tsx` | ✅ declarada |
| DRE / Relatórios | `financeiro-telas-extras.jsx` → `TelaDRE` | `Dre/Index.tsx` (+ `Relatorios/Index.tsx`?) | ✅ no Dre · ❌ **n/a** no Relatorios |
| Plano de contas | `financeiro-telas-extras.jsx` → `TelaPContas` | `PlanoContas/Index.tsx` | ❌ **n/a** — divergência, ver §4.3 |
| Impostos & obrigações | `financeiro-telas-extras.jsx` → `TelaImpostos` | `Impostos/Index.tsx` | ✅ declarada |
| Cobrança | `prototipos/payment-gateway-ui/cobranca-page.jsx` | `Cobranca/Index.tsx` | ✅ declarada |
| Prova Viva | `Financeiro - Prova Viva (primitivos).html` | `ProvaViva.tsx` | ✅ declarada |
| Assinaturas | `cobranca-recorrente-page.jsx` | **fora do módulo** → `RecurringBilling` | — |

**Sem correspondente no protótipo (13 telas):** `Advisor/Dashboard`, `Advisor/Login`, `AssinaturaAtualizar`, `Caixa`, `Categorias`, `Configuracoes/Contador`, `ContasBancarias`, `ContasPagar`, `ContasReceber`, `Dashboard`, `Extrato`, `Relatorios`, `Unificado/Novo`. Os charters delas declaram `n/a` com razão explícita (herda PT-01/PT-04, ou nasceu fora do loop Cowork). **Ausência declarada não é dívida** — essas fecham por conformidade de Padrão de Tela (Onda 9), não por repintura.

### Capacidades transversais do protótipo (aplicam a várias telas)

Vivem em 5 arquivos e o preview confirma que todas registram no `window`:

- `financeiro-ops.jsx` — baixa (`FinBaixaSheet`), anexos, aprovação, **OCR de boleto**, combobox de cliente
- `financeiro-curation.jsx` — comentários, conferido, edição inline, **trilha de auditoria**, frescor, régua de cobrança
- `financeiro-ai.jsx` — banner de anomalia, contexto da contraparte, painel IA, **digest do mês**
- `financeiro-output.jsx` — botão de problema, **trilha de fechamento**, favoritos, **modo apresentação**
- `financeiro-page.jsx` — 3 lentes, KPI strip, ageing, filtros, período, tabela, footer de seleção, drawer, ⌘K

> ⚠️ **Não trate essa lista como backlog.** Sonda de string no `.tsx` mede **presença**, não comportamento — e uma sonda malformada inverte o resultado. Na primeira passagem desta sessão, um `grep -ciE` com `\|` (alternância inválida em ERE) devolveu **0** para lente, ⌘K, auditoria e aprovação; refeito com sintaxe correta, os mesmos termos deram **46 · 28 · 6 · 46**. A tela viva tinha tudo. O veredito de capacidade sai do **passo 2 da receita**, nunca de grep.

---

## 4. Onda 0 — fundação (bloqueante, não toca `.tsx`)

Nenhuma onda de tela abre antes desta fechar. São quatro itens, todos medidos hoje.

### 4.1 Destravar as 7 âncoras — `0/7` são mensuráveis

`ancora.mjs` resolve a âncora de **todas** as 7 com o aviso:

```
⚠️ NÃO MEDIDO — o arquivo da âncora não pôde ser LIDO neste path.
   Causa comum: sufixo entre parênteses entrando no path.
```

**Controle positivo** (a prova de que o resolvedor funciona e o defeito é de forma): `node prototipo-ui/ancora.mjs Compras/Index` → `âncora ✓`. O charter do Compras declara o path **limpo**, sem comentário anexado.

**Alcance medido no repo inteiro:** dos 20 charters com `related_prototype` não-`n/a`, **12 carregam parênteses** no valor — as 7 do Financeiro estão entre eles. Os arquivos-alvo **existem todos** (`financeiro-page.jsx`, `financeiro-telas-extras.jsx`, `cobranca-page.jsx`, o HTML da Prova Viva). Não falta fonte; falta forma.

> **Enquanto isso não fecha, o portão de âncora do módulo está cego** — e âncora cega é ausência de medição, não saúde. Este é o primeiro item da Onda 0 justamente porque todos os outros dependem de medir contra a fonte certa.

### 4.2 Regenerar o `unificado.map.json` (STALE)

O gate reporta `prototipo_sha salvo='sha256:3c66ba0f55fe' · atual='sha256:944a15dad18c'` — o protótipo re-exportou desde o mapeamento. O caminho é `gerar-map.mjs --atualizar` (preserva o que já foi preenchido), **nunca** editar o sha à mão.

### 4.3 Resolver as duas divergências `n/a` × protótipo existente

| Tela | Charter declara | Mas o protótipo tem |
|---|---|---|
| `PlanoContas/Index` | `n/a (herda PT-01 Lista)` | `window.TelaPContas` |
| `Relatorios/Index` | `n/a (bespoke, não casa PT)` | a aba "DRE / Relatórios" |

Isto **não é** automaticamente um erro do charter — pode ser decisão declarada de não seguir aquele protótipo. Mas é divergência entre dois artefatos canônicos, e a regra de precedência manda **corrigir o perdedor no mesmo PR**. Como envolve escolher a fonte de design de uma tela, é **decisão [W]**, não conserto silencioso.

### 4.4 Inscrever o Financeiro no quadro de frescor

`FRESCOR-PRODUCAO-vs-PROTOTIPO.md` é o dono do tema "quem está à frente/atrás" e **não tem nenhuma linha do Financeiro**. Cada onda a partir daqui acrescenta ou atualiza a linha da sua tela lá — é assim que o Cowork descobre que não deve re-exportar uma tela 🔵.

---

## 5. A receita de uma onda — idêntica para toda tela

Oito passos. O que muda entre ondas é a tela, nunca a receita.

**1. Abrir o portão.** `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds` — é *fail-closed*: exit ≠ 0 **proíbe** editar produto. Depois `node prototipo-ui/protocolo.config.mjs` para os comandos vigentes.

**2. Medir os dois lados — nunca no olho.** Este é o passo que decide a direção da §2, e ele é **medido**:
- `node prototipo-ui/ancora.mjs <Mod/Tela>` → tem de sair `âncora ✓`. Saiu `⚠️ NÃO MEDIDO`? **pare** — você está prestes a comparar contra nada.
- `node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>`
- `node prototipo-ui/design-diff.mjs --compare prod.json design.json --check` — mesma sonda nos dois lados, computed style, nunca screenshot no olho (skill `comparar-design-prod`).

**3. Classificar a direção** (🟠/🔵/⚪/✅) e **escrever a linha no `FRESCOR`**. Se der 🔵, a onda **termina aqui**: o entregável é a linha do canal reverso, e o `.tsx` não é tocado.

**4. Registrar o gap.** `<tela>-gap.md` → `node prototipo-ui/gerar-map.mjs <gap.md>` → `<tela>.map.json`. O map é a ponte persistente design↔código; `consumir-map.mjs` depois abre **só** os ranges mapeados (é o que impede reler 3090 linhas).

**5. Contratar a região.** `gerar-contrato.mjs` → `scripts/contrato-de-tela.mjs --contract ... --contract-alvo Pages/Financeiro/<Tela>.tsx` → `recortar-regiao.mjs`. **Região**, não tela inteira: o batch F3 rejeitado deste módulo tentou 5 telas de uma vez.

**6. Aplicar.** Antes do primeiro Edit, o pré-flight do módulo é obrigatório (SPEC + RUNBOOK da tela + charter + ADRs). Aplicar **só** dentro do contrato.

**7. Travar.** `layout-primitives-guard` · `casos-coverage-guard` · `lint:baseline:check` · `tsc --noEmit` · `ds-guard <arquivos tocados>` · `cowork-ssot-guard`. Ganho que não vira UC citado por teste **não está travado** — volta no próximo refactor.

**8. Fechar o loop.** `anchor-lint --check memory/requisitos/Financeiro/SPEC.md` · `design-code-map-check --check --strict` · atualizar o `<tela>-visual-comparison.md` (o staleness cobra) · **smoke real em prod com screenshot** antes de dizer "pronto".

---

## 6. Sequenciamento das ondas

Critério: âncora primeiro (só se pode comparar quem tem fonte), risco Tier 0 dita o ritmo, telas sem âncora fecham em lote por Padrão de Tela.

| Onda | Tela | Fonte | Risco | Por que nesta posição |
|---:|---|---|---|---|
| **0** | — (fundação) | — | — | sem ela as 7 medições saem cegas |
| **1** | `Unificado/Index` | `financeiro-page.jsx` | 🔴 **valor** | maior (3090 ln), 9 UC sem prova, é a tela que a Eliana abre toda manhã |
| **2** | `Fluxo/Index` | `TelaFluxo` | 🔴 **valor** (projeção/saldo) | visual-comparison 53d stale |
| **3** | `Conciliacao/Index` | `TelaConciliacao` | 🔴 **valor** | 13 UC já com prova — melhor base do módulo |
| **4** | `Dre/Index` | `TelaDRE` | 🔴 **valor** | visual-comparison 80d — o mais stale do módulo |
| **5** | `Impostos/Index` | `TelaImpostos` | 🔴 **valor + fiscal** | 2 UC sem prova |
| **6** | `PlanoContas/Index` | `TelaPContas` ⚠️ | 🟡 | **depende da decisão [W] de §4.3** |
| **7** | `Cobranca/Index` | `cobranca-page.jsx` | 🔴 **valor** | fronteira com PaymentGateway — escopo a confirmar |
| **8** | `ProvaViva` | HTML primitivos | 🟡 | 2 UC sem prova |
| **9** | as 13 sem âncora | Padrão de Tela + DS | 🟡 | conformidade PT-01/PT-04, em lote por PT — **não** repintura |

> As ondas 1–5 e 7 tocam telas que exibem **valor**. Cada uma delas herda a regra-mestre de cálculo: nada mergeia sem **dupla confirmação por dois caminhos independentes** e sem **tabela antes→depois** apresentada ao [W]. Isso não é opcional e não é acelerável.

---

## 7. Ficha da onda (gabarito — uma por tela, no PR)

```
ONDA <n> · <Mod/Tela>
1. portão preview-ds ......... exit 0 em <data>
2. âncora .................... ✓ <arquivo>            (se ⚠️ NÃO MEDIDO → PARE)
   fingerprint ............... <veredito por campo>
   design-diff --check ....... <saída>
3. direção ................... 🟠 atrás | 🔵 à frente | ⚪ empate | ✅ paridade
   → linha escrita no FRESCOR? sim/não
   → se 🔵: onda encerra aqui. Justificativa: ...
4. gap.md .................... <path>       map.json: <path> (sha do protótipo: ...)
5. contrato .................. <path>       região: <seletores>
6. arquivos tocados .......... <lista>      linhas: <n>  (1 tela, 1 PR)
7. gates ..................... primitives/casos/lint/tsc/ds-guard/ssot: <resultados>
   UC novos citados por teste . <ids>       (⛓ do módulo: antes → depois)
8. anchor-lint / map --strict  <saída>
   visual-comparison atualizado <path> (data)
   smoke prod + screenshot .... <link/anexo>
   [valor?] antes→depois ...... <tabela> + 2º caminho de conferência
```

---

## 8. Regras duras que valem em toda onda

1. **Tier 0 valor/estoque** — tela que exibe total, saldo, projeção, imposto ou baixa: dois caminhos independentes de prova + antes→depois ao [W] antes de aplicar.
2. **`business_id`** — toda query nova scopada; teste cross-tenant no tenant fictício **98** (biz=4 é cliente e **não** entra em teste, fixture ou smoke).
3. **1 onda = 1 tela = 1 PR ≤300 linhas.** O batch rejeitado deste módulo eram 5 telas e 11 arquivos num PR.
4. **Não inventar Model/Service.** Os reais são `Titulo`, `TituloBaixa`, `ContaBancaria`, `Categoria`. O batch rejeitado inventou `FinancialEntry`, `BankAccount`, `ChartOfAccount`, `BaixaService`.
5. **Middleware é o canon UPOS** — `['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin']`. O batch rejeitado usou um `tenant` que não existe.
6. **Zero mock em Controller.** `rand()` em KPI quebra cache e comparativo.
7. **Espelho é leitura.** `prototipo-ui/cowork/**` é retrato do Cowork: edição local some no próximo `--export-from`. Conteúdo remoto entra **pela máquina**, nunca transcrito à mão.
8. **Fonte de design ≠ Figma.** Fonte = protótipo Cowork + Design System + charter.

---

## 9. Definition of Done de uma onda

- [ ] Ficha do §7 preenchida **inteira** no corpo do PR (campo vazio = onda não fechou)
- [ ] Direção classificada por **medição**, e a linha correspondente escrita no `FRESCOR`
- [ ] `<tela>-visual-comparison.md` atualizado — o staleness cobra por data
- [ ] `casos-gate` verde e **⛓ do módulo subiu** (ganho não citado por teste não está travado)
- [ ] Gates do passo 7 verdes; `gh pr checks` **conferido** antes de propor merge
- [ ] Smoke real em prod com screenshot relatado — sem ele a tela não está "pronta"
- [ ] Se a tela toca valor: antes→depois apresentado e **aprovado** pelo [W]

---

## 10. O que NÃO fazer nesta campanha

- **Não repintar tela 🔵.** Produção à frente vira linha no `FRESCOR`, não Edit.
- **Não abrir plano paralelo.** Este manual é o único plano de ondas do módulo; gap/map/visual-comparison por tela ficam em `memory/requisitos/Financeiro/`.
- **Não derivar caso de teste do `.tsx`.** UC nasce do SDD/CU (`SDD-tela-financeiro-v1.0.md`) — teste derivado do código é tautológico.
- **Não medir paridade no olho.** Computed style, mesma sonda nos dois lados.
- **Não afirmar ausência por grep estreito.** Claim negativa exige repo inteiro (`rg --hidden`) **mais** o dono do inventário — e no eixo design os donos são **dois**: `ancora.mjs` + `DesignSync`.
- **Não tratar `0 failed` como prova.** Suíte que pula sai exit 0; leia *assertions*.
- **Não rodar Pest/PHPStan local nem no Hostinger.** CT 100, sempre.

---

## 11. Como reproduzir este retrato

```bash
node prototipo-ui/protocolo.config.mjs
node scripts/governance/cowork-mirror-freshness.mjs --preview-ds
npm run screen-coverage:report
npm run casos:report
node scripts/governance/visual-comparison-staleness.mjs
node scripts/governance/design-code-map-check.mjs --check
node prototipo-ui/ancora.mjs Financeiro/Unificado
```

O protótipo abre em `http://localhost:5577/oimpresso.com.html` (preview `cowork-jana-2`). Em 2026-08-18 os 10 arquivos do Financeiro servidos nessa porta foram conferidos **byte-a-byte idênticos** aos deste worktree — o que o [W] vê é o que este manual mediu.

---

_Criado em 2026-08-18. Retrato datado: os números do §1 envelhecem por construção — re-rode o §11 em vez de editá-los._
