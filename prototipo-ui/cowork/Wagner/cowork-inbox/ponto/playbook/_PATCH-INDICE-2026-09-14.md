# _PATCH-INDICE-2026-09-14.md — Ponto · âncora, frescor e alvo (patch do 00-INDICE)

> **Forma:** PATCH, não índice. O `00-INDICE.md` deste playbook tem **25.155 B** no `main` e está à frente da minha cópia — editar o meu e mandar descer sobrescreveria erratas e `_saida-*`. Aplique os objetos abaixo em `§7.threads` / `§7.decisoes` e as linhas em `§2`.
> **Emitido por:** [CC] · 2026-09-14 · árvores lidas no turno: `73182439581f` → `420b061817e0` → `32af4af112a4`.
> **Não é pedido de pixel.** O eixo de LARGURA do alvo **não foi medido** (ver D-ALVO-1280) — nenhuma thread daqui autoriza mudança de layout.

---

## §2 · linhas de estado (acrescentar)

- **Âncora verificável criada no build:** `ponto-telas.jsx` tinha **0** `data-contract`; agora tem **26**, um por Card, derivados de `função + título` (`aprovacoes-fila-de-aprovacoes`, `bancohoras-saldos-por-colaborador`, …). Antes disso, as 18 telas do arquivo **não tinham âncora verificável nenhuma** — o `map.json` ancora por `data-contract`, não por linha.
- **Auditoria de contrato do Painel: zero drift.** Os 4 ids do `dashboard-index.map.json` (`painel-nota-fechamento`, `painel-kpis`, `painel-fila-aprovacoes`, `painel-atividade`) estão **todos presentes no DOM** do protótipo, medido com T1 estável (755=755, após `__oiLazyDone`).
- **Contratos órfãos declarados:** `ponto-fechamento.jsx` (5: `conformidade-regras`, `fechamento-{acoes,passos,pre-checagem,totais}`) e `ponto-mobile.jsx` (4: `repp-{gps,tipos,fila-validacao,nota-regras}`) têm contrato e **não têm receptor** — não existe `Pages/Ponto/{Fechamento,Conformidade}` nem REP-P.
- **a11y do alvo (A1–A12, rota `ponto`, dark, T1 estável):** A1 botão sem nome **0** · A2 anel de foco **presente** (`outline 2px` + `box-shadow oklch(0.32 0.06 295) 0 0 0 3px`, `:focus-visible`) · A3 `svg` anônimo **1** (era 3 — 2 consertados no build; o que sobra é do **bundle do DS**) · A4 `div` clicável **0** · A5 img sem alt **0** · A6 campo sem rótulo **0** · A7 **1 h1** · **A8 FALHA: h1 → h3** (dono é o `Widget` do DS) · A9 `aria-live` **1** · A10 `tabindex>0` **0** · **A11 `<main>` = 1 ✓ (corrigido no host daqui)** · A12 `lang="pt-BR"`.

---

## §7.threads · objetos a acrescentar

```json
[
  {
    "id": "16",
    "titulo": "gap.md das 18 telas de ponto-telas.jsx — onda 1: Aprovações",
    "arquivo": "16-gap-aprovacoes.md",
    "estado": "cabe",
    "porque": "as 3 telas de ponto-page.jsx já têm gap+map (gerados 2026-09-14); as 18 de ponto-telas.jsx não têm nenhum dos dois. O map.json NÃO se escreve à mão — gerar-map.mjs o deriva do gap.md com grep -n real ('nunca fabricar'), então o que falta é o gap, e ele é do design.",
    "entrega": "proposta de memory/requisitos/Ponto/aprovacoes-index-gap.md, com as regiões derivadas do protótipo (ponto-telas.jsx :13-110) e do charter; lado vivo fica TODO para o grep do Code.",
    "fila": "17 telas restantes, 3 por onda, na ordem do §7.fila-gap"
  },
  {
    "id": "17",
    "titulo": "data-contract no .tsx das telas que ganharam âncora no protótipo",
    "arquivo": "17-data-contract-no-tsx.md",
    "estado": "cabe",
    "porque": "o _doc do map.json declara: 'range de linha do lado vivo é INFORMATIVO (frágil); a âncora verificável é vivo.ancora: true + data-contract no .tsx (declarada e ausente = DRIFT)'. Eu criei os 26 ids do lado do protótipo neste turno; sem o par no .tsx a âncora não fecha.",
    "prova": "design-code-map-check.mjs acusa; e o id tem de ser o MESMO string dos dois lados.",
    "depende_de": "16 (o gap nomeia as regiões que viram id)"
  },
  {
    "id": "18",
    "titulo": "Contrato órfão: fechamento e REP-P sem receptor",
    "arquivo": "18-contratos-orfaos-bloqueada.md",
    "estado": "bloqueada — desbloqueia com os PRs 1-4 da thread 30 ([W] ratificou as 5 em 14/09)",
    "porque": "9 data-contract existem no build (5 fechamento + 4 REP-P) e não há Page nem rota. Overlay/tela sem receptor só depois de [W] declarar rota — é a mesma trava das frentes 5-7.",
    "nao_fazer": "não criar Page para hospedar contrato; isso inventaria lei (o header do fechamento depende das decisões 1-4)."
  },
  {
    "id": "19",
    "titulo": "DS: Widget nasce h3 e ícone warn sai anônimo",
    "arquivo": "(cross-ref — o arquivo vive em cowork-inbox/ds-atomos/playbook/06-widget-nivel-titulo.md, porque o dono é o primitivo, não o Ponto)",
    "estado": "cabe",
    "arquivo_cross": "cowork-inbox/ds-atomos/playbook/06-widget-nivel-titulo.md",
    "porque": "A8 do alvo falha por causa do primitivo, não da tela: o Widget do bundle emite h3 sob um h1, e o ícone warn (_ds_bundle.js:730) não tem nome acessível. Espelho não se edita — o dono é Components/ui/card.tsx + shared no main.",
    "papel_nao_classe": "PAPEL = moldura de painel com título. DONO = resources/js/Components/ui/card.tsx. Pedido é prop de nível de título (h2 por padrão sob PageHeader), não CSS."
  },
  {
    "id": "20",
    "titulo": "gap.md de Intercorrências — onda 2 (4 telas, 2 símbolos)",
    "arquivo": "20-gap-intercorrencias.md",
    "estado": "cabe",
    "achado_que_muda_o_eixo": "o charter de Create declara um classificador de IA (endpoint aiClassify, throttle 10/min, cache, badge 'IA desligada no servidor') e o prototípo NÃO tem uma linha disso ergo esta tela e catch-up MEU, nao de producao. Seria a 4a vez que a hipotese 'producao atras' cairia.",
    "corrigido_no_build": "paginação da lista 15 -> 25/pag (Goal do charter)",
    "abre": ["D-INTERC-ACOES", "D-INTERC-DRAWER", "D-INTERC-ANEXO"]
  },
  {
    "id": "21",
    "titulo": "gap.md de Banco de Horas — onda 3 (2 telas, 1 símbolo)",
    "arquivo": "21-gap-banco-horas.md",
    "estado": "cabe",
    "corrigido_no_build": "paginação 15 -> 30/pag · ordenação por saldo desc (Goal) · observação do ajuste passa a exigir 5 chars (o charter diz que ela e auditada)",
    "prototípo_a_frente": "as KPIs de Teto do acordo e Prazo de compensacao respondem a pendencia aberta do charter Index ('confirmar regra de expiracao de credito exibida ao usuario') — sobe como emenda de charter, nao como pedido de codigo",
    "guarda": "nao portar a soma local de saldo do prototípo — viola o Non-Goal 'nao recalcula/reescreve o saldo'",
    "abre": ["D-BH-KPI", "D-BH-ROTA"]
  }
]
```

## §7.decisoes · objetos a acrescentar

```json
[
  {
    "id": "D-ALVO-1280",
    "pergunta": "Quem roda a passada de largura do alvo do Ponto?",
    "medido": "Tentei forçar 1280/1440/375 no .cockpit deste lado: as três larguras devolveram os MESMOS números (647px em todas as 3 seções) — o botão não gira. O eixo de TEMA gira (cor foi de oklch(0.94 0.005 90) para oklch(0.22 0.01 80)). Logo o eixo de largura exige o dispatch local com browser (fingerprint-harness / ADR 0290: captura é LOCAL).",
    "consequencia": "sem essa passada, o vetor sai com 2 de 3 eixos e nenhuma thread de layout é legítima.",
    "dono": "[W] decide se roda; execução é local, não daqui."
  },
  {
    "id": "D-PRINT-TINTA",
    "pergunta": "O amarelo #fff3cd da folha de prova vira token ou fica tinta literal?",
    "medido": "ponto-page.css tem 0 token de cor bespoke (o predicado 'paleta inventada' do ds-guard, >=4, NÃO dispara) e 8 cores cruas, TODAS dentro do @media print de .pt-folha: #222 #111 #eee #555 #777 #f5f5f5 (cinzas de tinta) + #fff3cd (amarelo de destaque de divergência).",
    "opcoes": ["manter literal e declarar 'tinta de papel' como exceção nomeada", "trocar por um tom do DS resolvido em sRGB para impressão"],
    "nao_fiz": "não repintei por conta — cor de impressão é decisão, e 'sem cor crua' é lei de UI de tela.",
    "dono": "[W]"
  },
  {
    "id": "D-ORFAO-HERDADO",
    "pergunta": "Quem responde pelo arquivo de build que nenhum charter aponta?",
    "medido": "5 dos 7 arquivos do Ponto (ponto-ui, ponto-data, ponto-page.css, ponto-fechamento, ponto-mobile) não são related_prototype de nenhum charter. O --check-orfaos é DELTA por decisão medida (absoluto dava ~90% de falso-positivo), então órfão HERDADO é invisível por desenho.",
    "dono": "[W] — ou se declara aceito, ou ganha vigia."
  }
]
```

---

## §7.fila-gap · a ordem das 17 restantes (símbolo + faixa medidos em `ponto-telas.jsx`, 982 linhas)

| onda | telas | símbolo :: faixa |
|---|---|---|
| **1** | Aprovações Index | `Aprovacoes :: 13-110` ✅ **thread 16** |
| **2** | Intercorrências Index · Create · Edit · Show | `Intercorrencias :: 168-288` · `FormIntercorrencia :: 111-167` (serve Create **e** Edit — 1 símbolo, 2 telas) ✅ **thread 20** |
| **3** | BancoHoras Index · Show | `BancoHoras :: 289-394` — 1 símbolo, 2 telas (o `if (sel)` é o Show) ✅ **thread 21** |
| **4** | Escalas Index · Form | `Escalas :: 395-444` · `EscalaForm :: 445-491` ✅ **thread 22** |
| **5** | Colaboradores Index · Edit | `Colaboradores :: 492-579` · `ColaboradorForm :: 580-634` ✅ **thread 23** |
| **6** | Importações Index · Create · Show | `Importacoes :: 635-767` — 1 símbolo, 3 telas ✅ **thread 24** |
| **7** | Relatórios Index | `Relatorios :: 768-852` ✅ **thread 25** |
| **8** | Configurações Index · Reps | `Configuracoes :: 856-987` — 1 símbolo, 2 telas ✅ **thread 26** |

**FILA FECHADA:** 18 de 18 telas cobertas, 8 threads (16 · 20 · 21 · 22 · 23 · 24 · 25 · 26).

**Por que por símbolo:** é o padrão bom já reconhecido no corpus (`Sells/Caixa` e `Purchase/{Index,Show}`). `Dl`/`Li` (853-855) são helpers, não tela.

---

## §8 · O que este patch NÃO resolve

1. **Largura do alvo** — D-ALVO-1280. Sem ela, nada de pixel.
2. **A8 (h1→h3)** — é do DS; a thread 19 pede no primitivo, mas o `_ds_bundle.js` daqui segue emitindo h3 até o Code mexer no `card.tsx`.
3. **Os 2 ids feios** que a derivação gerou em Cards sem título: `intercorrencias-card` e `escalaform-card`. Ficam declarados — quando o gap nomear a região, o id vira o nome da região.
4. **Frescor por seção** das 18 telas — o quadro abaixo classifica por **existência e âncora**, não por comparação de seção renderizada (isso exige os dois renders, T7).
5. **Nada foi lido do `.tsx` vivo das 18 telas neste turno** — o gap da thread 16 deriva do protótipo + charter, e marca o lado vivo como TODO. Quem executar **mede o grep**.

---

## §9 · FRESCOR do Ponto — a rodada que faltava (o quadro canon não cobre o módulo)

> O `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` tem 2 quadros (2026-06-23 e OficinaAuto/Vehicles) e joga o Ponto em *"RESTO DO WORKSPACE … não assuma"*. Isto é a rodada do módulo, **por existência/âncora/contrato** — não por seção renderizada.

| tela (21 charters) | protótipo | vivo | frescor | base da classificação |
|---|---|---|---|---|
| Dashboard/Index | `ponto-page.jsx` | `Dashboard/Index.tsx` | ✅ **paridade** (4 regiões) + **3 `decidir-w`** | `dashboard-index.map.json`, lido |
| Espelho/Index · Show | `ponto-page.jsx` | `Espelho/{Index,Show}.tsx` | ⚪ **map existe, não li** | `espelho-{index,show}.map.json` (5.525 / 5.264 B) |
| Aprovações/Index | `Aprovacoes :: 13-110` | `Aprovacoes/Index.tsx` (23.762 B) | 🟠 **sem gap, sem map** | charter lido; `.tsx` não lido |
| Intercorrências ×4 | `Intercorrencias` + `FormIntercorrencia` | 4 Pages | 🟠 **sem gap, sem map** | árvore + charter |
| BancoHoras ×2 · Escalas ×2 · Colaboradores ×2 · Importações ×3 · Relatórios · Configurações ×2 | `ponto-telas.jsx` | Pages existem | 🟠 **sem gap, sem map** | árvore |
| Welcome | **n/a declarado** | `Welcome.tsx` | ✅ ausência declarada na fonte | charter (`related_prototype: n/a` com motivo) |
| Fechamento · Conformidade · REP-P | `ponto-fechamento.jsx` · `ponto-mobile.jsx` | **não existem** | ⚪ **sem receptor** | árvore de `Pages/Ponto/` |

**Cobertura declarada:** 21 charters · **1** medido por região (map lido) · **2** com map não lido · **17** classificados só por existência+âncora · **3** superfícies de protótipo sem receptor. **`status: draft` em 21 de 21.** Contratos de CI: **2** (`ponto-painel`, `ponto-espelho`) para 21 telas.

---

## §10 · Defeitos MEUS que os gaps acharam — corrigidos no build neste turno

> O princípio é o §5-bis: **o alvo não é sagrado**. Onde o charter declara um número e o meu protótipo diz outro, o defeito é meu e se conserta aqui — não vira pedido.

| # | tela | o charter declara | o protótipo tinha | agora |
|---|---|---|---|---|
| 1 | Aprovações/Index | lista paginada **20/pág** | 15 | **20** |
| 2 | Aprovações/Index | motivo de rejeição **mín. 5 chars** | qualquer texto não vazio (e sem `trim`) | **≥ 5 + `trim()`** |
| 3 | Intercorrências/Index | lista paginada **25/pág** | 15 | **25** |
| 4 | BancoHoras/Index | lista paginada **30/pág** | 15 | **30** |
| 5 | BancoHoras/Index | **ordenada por saldo desc** | sem ordenação | **desc por `saldo_minutos`** |
| 6 | BancoHoras/Show | observação do ajuste é **auditada** (Non-Goal: "não faz ajuste sem observação") | só "não vazio" | **≥ 5 chars** |
| 7 | Escalas/Index | lista paginada **20/pág** | 15 | **20** |
| 8 | Colaboradores/Index | lista paginada **25/pág** | 15 | **25** |
| 9 | Importações/Index | lista paginada **20/pág** | 15 | **20** |
| 10 | Configurações/Index | fonte é **`config/pontowr2.php`** via `config('pontowr2')` | a tela dizia `Modules/Ponto/Config/config.php` — **caminho que eu nunca li** | **corrigido na tela** |
| 11 | Configurações/Reps | identificador = **CNPJ (14) + sequencial (3)**, Anexo I | help/placeholder/erro diziam `AAAAMMDDHHMMSSNNN` — **formato inventado**, e os 17 chars escondiam o erro | **corrigido: CNPJ+seq, placeholder `00000000000191001`** |

**3 números de paginação errados em 4 telas** — vira **6 em 6** com as ondas 4-6: eu uso 15 por hábito e o charter declara o número por tela. **Paginação é contrato, não preferência de densidade.**

**E os 2 últimos são de outra classe: copy factual errada NA TELA.** Um caminho de arquivo que eu nunca li e um formato de identificador legal que eu inventei — este último é **conformidade**: quem preenchesse pelo meu placeholder cadastraria REP inválido, e a rejeição só apareceria na importação do AFD. A regra "não afirmar caminho de memória" valia para o meu raciocínio; passa a valer para **o texto que a tela mostra**.
