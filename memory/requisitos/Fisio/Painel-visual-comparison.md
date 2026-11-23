---
id: requisitos-fisio-painel-visual-comparison
title: "Comparação design × produção — Fisio/Painel"
module: Fisio
tela: Fisio/Painel
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `Fisio/Painel`

> **Âncora computada** (`node scripts/design/ancora.mjs Fisio/Painel`): `prototipo-ui/cowork/Wagner/prototipos/fisio/Main.dc.html`,
> declarada em `related_prototype` do charter. Medido em **2026-11-23** (auditoria do Fisio de 2026-11-05, item e8, lote 7).
> Snapshots: [`governance/design/targets/medidas/Fisio--Painel/`](../../../governance/design/targets/medidas/Fisio--Painel/).

## Veredito: **DIVERGE** — 5 `DIVERGE(bug)` na sonda (valor do KPI + 4 sinais de célula) e o botão primário só no protótipo

Título igual em tamanho, peso e texto. O que a sonda pega: o valor do KPI é 22px na produção e 30px no protótipo, e a linha da tabela diverge em 6 colunas (11 sinais: 4 `bug`, 4 `fonte`, 3 `dado?`). Fora da sonda: o primário `Prescrever` não existe na produção, o 4º KPI é outro, e a seção `Agenda de hoje` só existe no protótipo.

## Itens

| Item | Produção | Protótipo | Veredito |
|---|---|---|---|
| Título (h1), tamanho e peso — D4 | 22px · peso 600 | 22px · peso 600 | igual |
| Texto do título | `Painel da fisioterapia` | `Painel da fisioterapia` | igual |
| Botão primário, presença — D6 | ausente | presente | diverge (só de um lado) |
| Rótulo do botão primário | ausente | `Prescrever` | diverge |
| KPI, quantidade — D2 | 4 | 4 | igual |
| KPI, tamanho do valor — D4 | 22px | 30px | diverge (bug na sonda) |
| KPI, alinhamento — D8 | start | start | igual |
| KPI, rótulos (14 primeiros caracteres) | Adesão média 7 / Pacientes ativ / Dor média 7 di / Registros em 7 | Adesão média 7 / Pacientes ativ / Dor média 3,4  / Faltas evitada | diverge no 4º KPI: produção `Registros em 7 dias`, protótipo `Faltas evitadas`; os outros três batem |
| Linha da tabela, por coluna — D2/D4/D6/D8 | ver bloco do `--compare` | ver bloco do `--compare` | 11 sinais divergentes, abaixo |
| Botões e links visíveis no conteúdo | 3 | 9 | contagem; depende do dado, não é veredito |
| Campos de formulário visíveis | 0 | 0 | igual |
| Rótulos de botão que não aparecem em nenhum texto do outro lado | `Ana Teste Lote7`, `Bruno Teste Lote7`, `Carla Teste Lote7` | `Abrir agenda`, `Alta próxima · 7`, `Avançar fase`, `Em risco · 5`, `Gerar laudo`, `Ligar`, `Revisar carga`, `Revisar dose`, `Todos · 64` | os da produção são nomes de paciente da fixture (dado). Os do protótipo são estrutura: filtros da fila (`Todos`, `Em risco`, `Alta próxima`), ações da fila (`Avançar fase`, `Revisar carga`, `Revisar dose`, `Gerar laudo`, `Ligar`) e `Abrir agenda`. Na produção a fila de alerta estava vazia, então as ações por paciente não podiam aparecer; os filtros e `Abrir agenda` não dependem disso — diverge |
| Seções nomeadas (h2/h3/legend) | `Alerta`, `Nenhum alerta no momento`, `Risco de abandono`, `Todos registraram nos últimos dias`, `Pacientes` | `Fila de atenção · 5`, `Agenda de hoje`, `Pacientes ativos` | diverge: o protótipo agrupa em `Fila de atenção` + `Agenda de hoje` + `Pacientes ativos`; a produção em `Alerta` + `Risco de abandono` + `Pacientes`. `Agenda de hoje` não tem par na produção |
| Regiões `data-contract` — D9 | `fisio-painel-sem-registro`, `fisio-painel-tabela` | nenhuma | sem dado: o protótipo não marca regiões |

## Notas

- A tabela foi medida nos dois lados (5 linhas no protótipo, 3 na produção). Os sinais `(fonte)` dizem que a classe da célula é outra (link × texto, mono × não-mono), e os `(dado?)` podem ser render condicional pelo dado: na fixture nenhum paciente tinha programa ativo, então adesão, dor e registro saíram `—`.
- A produção mostra o aviso `fisio-painel-sem-registro` porque nenhum paciente da fixture registrou check-in; é estado de dado, não divergência de desenho.

## Saída do `--compare`

`node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/Fisio--Painel/prod.json governance/design/targets/medidas/Fisio--Painel/design.json --check` (exit 1, 5 `DIVERGE(bug)`):

```text
  DESIGN-DIFF — prod(light) × design(light)

  ✓ [D2] layout: prod=ok · design=ok → IGUAL
  ✗ [D4] kpi valor font-size: prod=22px · design=30px → DIVERGE (bug) (Δ 8px · banda tipografia ±0px)
  ✓ [D6] cor: prod=ok · design=ok → IGUAL
  ✓ [D8] kpi align: prod=ok · design=ok → IGUAL
  ⬜ [D9] fisio-painel-sem-registro: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] fisio-painel-tabela: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-alcas: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-corpo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-modos: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-rodape: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-topo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ✗ [D8] col0.alcancavel: prod=A · design=sem elemento clicavel → DIVERGE (fonte)
  🟡 [D2] col0.blocos de texto: prod=2 · design=3 → DIVERGE (dado?) (pode ser render condicional dirigido por dado — confira a célula antes de tratar como defeito)
  ✗ [D6] col1.cor: prod=cor própria · design=herda a da linha → DIVERGE (fonte)
  ✗ [D4] col2.mono: prod=mono · design=sem mono → DIVERGE (fonte)
  🟡 [D2] col2.blocos de texto: prod=1 · design=0 → DIVERGE (dado?) (pode ser render condicional dirigido por dado — confira a célula antes de tratar como defeito)
  ✗ [D6] col3.cor: prod=herda a da linha · design=cor própria → DIVERGE (bug)
  🟡 [D2] col3.blocos de texto: prod=1 · design=2 → DIVERGE (dado?) (pode ser render condicional dirigido por dado — confira a célula antes de tratar como defeito)
  ✗ [D4] col4.mono: prod=mono · design=sem mono → DIVERGE (fonte)
  ✗ [D6] col4.cor: prod=herda a da linha · design=cor própria → DIVERGE (bug)
  ✗ [D6] col4.pílula: prod=ausente · design=presente → DIVERGE (bug)
  ✗ [D6] col5.cor: prod=herda a da linha · design=cor própria → DIVERGE (bug)
  ⬜ [SHELL] shell: prod=não medido · design=não medido → SEM-DADO (declare window.__SB_ROLES nos DOIS lados — node scripts/design/design-diff.mjs --shell-roles imprime o mapa)
  ✗ [SAÚDE] tokens.prod: prod=--accent --pos --neg --warn --text-dim --sunken --border · design=— → NÃO MEDI (token de cor não resolve na raiz — a cascata está incompleta e toda cor cai em fallback)
  ✗ [SAÚDE] tokens.design: prod=— · design=--accent --pos --neg --warn --text-dim --sunken --border → NÃO MEDI (token de cor não resolve na raiz — a cascata está incompleta e toda cor cai em fallback)

  ✗ DIVERGE(bug): 5
  ▲ SHELL a classificar: 0

exit=1
```

## Como foi medido

- **Sonda:** a canônica (`node scripts/design/design-diff.mjs --probe`), injetada sem alteração nos dois lados, com o mesmo Chromium (Playwright), viewport 1280×900 e tema claro forçado nos dois. Papéis por lado: protótipo `title=.ph-h1`, `primary=.btn-p`, `kpi=section[aria-label="Indicadores"] div[style*="padding: 16px"]`, `tableRow=main table tbody tr`; produção `title=main h1`, `primary=main [data-slot="button"][data-variant="default"]`, `kpi=main [data-slot="kpi-card"]`, `tableRow=main table tbody tr`. Papel que não casa elemento sai SEM-DADO, nunca igual.
- **Contagens e rótulos** (botões, campos, seções, rótulos só de um lado): uma segunda função, a mesma nos dois lados, roda depois da sonda. Os rótulos "só de um lado" são os que não aparecem em nenhum ponto do texto visível do outro lado (`assinatura` do snapshot).
- **Protótipo:** `prototipo-ui/` servido por http local; aberto `cowork/Wagner/prototipos/fisio/Main.dc.html` e medido depois que o DOM parou de mudar (4 leituras iguais a 500 ms). O `design-diff-lote.mjs` não foi usado: ele renderiza o protótipo pelo shell `oimpresso.com.html`, e as telas do Fisio são páginas `.dc.html` avulsas, fora dessa rota.
- **Produção:** `https://staging.oimpresso.com/fisio` (checkout `56f45a3c52` em `main`, 2026-11-21; entre ele e o `main` de 2026-11-23 nenhum arquivo de `Modules/Fisio/Resources/js/` mudou). Login pela rota env-guarded `/_visreg-login` com o token do `.env` do staging; ninguém usou senha.
  - Para a tela abrir, foi montada uma fixture **temporária** no business 1 (CI Biz, fictício): um usuário de medida com `fisio.access`/`fisio.editar` (as duas permissões não existiam no staging), um pacote e uma assinatura com `fisio_module` (o business 1 não tinha assinatura), 3 pacientes fictícios com 3 atendimentos no dia, 1 tipo de atendimento e 1 protocolo. **Tudo apagado ao fim** e conferido: 0 usuário de medida, 0 assinatura e 0 pacote no business 1, 0 paciente, 0 contato, 0 tipo e 0 protocolo da fixture.
  - O dado da produção é mínimo e o do protótipo é mock. Contagem de botões, seções e linhas depende disso; os vereditos de estilo (tamanho, peso, alinhamento, presença do primário) não.
- **Saúde (vale para as 9 telas do lote):** a sonda acusou `tokens.prod` e `tokens.design` NÃO MEDI: `--accent --pos --neg --warn --text-dim --sunken --border` não resolvem na raiz em nenhum dos dois lados (no protótipo os tokens moram em `.oi`, na produção dentro do shell). **A dimensão cor (D6) não é conclusiva**; os valores de cor ficam registrados, não usados como veredito.
- **Não medido:** D1 (rede/partial reload: o protótipo é estático), D3 (ícones), D5 (rodapé), D7 (densidade), shell/sidebar (`__SB_ROLES` não declarado) e tema escuro.
- Nenhum screenshot: comparação por medida.
