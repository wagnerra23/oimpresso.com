---
id: requisitos-fisio-pesquisa-visual-comparison
title: "Comparação design × produção — Fisio/Pesquisa/Index"
module: Fisio
tela: Fisio/Pesquisa/Index
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `Fisio/Pesquisa/Index`

> **Âncora computada** (`node scripts/design/ancora.mjs Fisio/Pesquisa/Index`): `prototipo-ui/cowork/Wagner/prototipos/fisio/Pesquisa.dc.html`,
> declarada em `related_prototype` do charter. Medido em **2026-11-23** (auditoria do Fisio de 2026-11-05, item e8, lote 7).
> Snapshots: [`governance/design/targets/medidas/Fisio--Pesquisa--Index/`](../../../governance/design/targets/medidas/Fisio--Pesquisa--Index/).

## Veredito: **NÃO COMPARÁVEL no conteúdo** — os dois lados mostram estados diferentes da tela; o estilo do título não diverge

O protótipo abre um estudo (`Fibromialgia e exercício`, com participantes, exportação e assistente); a produção abre a lista de estudos vazia (`Pesquisa`, primário `Novo estudo`), porque o staging não tem estudo. O que a sonda compara (tamanho e peso do título) é igual; texto do título, primário e seções diferem pelo estado.

## Itens

| Item | Produção | Protótipo | Veredito |
|---|---|---|---|
| Título (h1), tamanho e peso — D4 | 22px · peso 600 | 22px · peso 600 | igual |
| Texto do título | `Pesquisa` | `Fibromialgia e exercício` | diverge |
| Botão primário, presença — D6 | presente | ausente | diverge (só de um lado) |
| Rótulo do botão primário | `Novo estudo` | ausente | diverge |
| KPI — D2/D4/D8 | nenhum | nenhum | sem KPI nos dois lados; nada a comparar |
| Linha da tabela | sem tabela medida | sem tabela medida | sem dado nos dois lados |
| Botões e links visíveis no conteúdo | 1 | 9 | contagem; depende do dado, não é veredito |
| Campos de formulário visíveis | 0 | 0 | igual |
| Rótulos de botão que não aparecem em nenhum texto do outro lado | `Novo estudo` | `Assistente`, `Eventos adversos`, `Exportação`, `Participantes`, `Pendências`, `Registrar saída`, `Ver` | não comparável: os rótulos do protótipo (`Participantes`, `Exportação`, `Eventos adversos`, `Assistente`, `Registrar saída`, `Ver`, `Pendências`) são do estudo aberto |
| Seções nomeadas (h2/h3/legend) | nenhuma | `Participantes` | não comparável (estados diferentes) |
| Regiões `data-contract` — D9 | `fisio-pesquisa` | nenhuma | sem dado: o protótipo não marca regiões |

## Notas

- Para medir: criar um estudo no business de medida e abrir o mesmo estado nos dois lados.

## Saída do `--compare`

`node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/Fisio--Pesquisa--Index/prod.json governance/design/targets/medidas/Fisio--Pesquisa--Index/design.json --check` (exit 0, 0 `DIVERGE(bug)`):

```text
  DESIGN-DIFF — prod(light) × design(light)

  ✓ [D2] layout: prod=ok · design=ok → IGUAL
  ✓ [D4] tipografia: prod=ok · design=ok → IGUAL
  ✓ [D6] cor: prod=ok · design=ok → IGUAL
  ✓ [D8] kpi align: prod=ok · design=ok → IGUAL
  ⬜ [D9] fisio-pesquisa: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-alcas: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-corpo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-modos: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-rodape: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-topo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D4] linha da tabela: prod=não medido · design=não medido → SEM-DADO (passe `tableRow` em __DD_ROLES nos DOIS lados)
  ⬜ [SHELL] shell: prod=não medido · design=não medido → SEM-DADO (declare window.__SB_ROLES nos DOIS lados — node scripts/design/design-diff.mjs --shell-roles imprime o mapa)
  ✗ [SAÚDE] tokens.prod: prod=--accent --pos --neg --warn --text-dim --sunken --border · design=— → NÃO MEDI (token de cor não resolve na raiz — a cascata está incompleta e toda cor cai em fallback)
  ✗ [SAÚDE] tokens.design: prod=— · design=--accent --pos --neg --warn --text-dim --sunken --border → NÃO MEDI (token de cor não resolve na raiz — a cascata está incompleta e toda cor cai em fallback)

  ✗ DIVERGE(bug): 0
  ▲ SHELL a classificar: 0

exit=0
```

## Como foi medido

- **Sonda:** a canônica (`node scripts/design/design-diff.mjs --probe`), injetada sem alteração nos dois lados, com o mesmo Chromium (Playwright), viewport 1280×900 e tema claro forçado nos dois. Papéis por lado: protótipo `title=.ph-h1`, `primary=.btn-p`, `kpi=section[aria-label="Indicadores"] div[style*="padding: 16px"]`, `tableRow=main table tbody tr`; produção `title=main h1`, `primary=main [data-slot="button"][data-variant="default"]`, `kpi=main [data-slot="kpi-card"]`, `tableRow=main table tbody tr`. Papel que não casa elemento sai SEM-DADO, nunca igual.
- **Contagens e rótulos** (botões, campos, seções, rótulos só de um lado): uma segunda função, a mesma nos dois lados, roda depois da sonda. Os rótulos "só de um lado" são os que não aparecem em nenhum ponto do texto visível do outro lado (`assinatura` do snapshot).
- **Protótipo:** `prototipo-ui/` servido por http local; aberto `cowork/Wagner/prototipos/fisio/Pesquisa.dc.html` e medido depois que o DOM parou de mudar (4 leituras iguais a 500 ms). O `design-diff-lote.mjs` não foi usado: ele renderiza o protótipo pelo shell `oimpresso.com.html`, e as telas do Fisio são páginas `.dc.html` avulsas, fora dessa rota.
- **Produção:** `https://staging.oimpresso.com/fisio/pesquisa` (checkout `56f45a3c52` em `main`, 2026-11-21; entre ele e o `main` de 2026-11-23 nenhum arquivo de `Modules/Fisio/Resources/js/` mudou). Login pela rota env-guarded `/_visreg-login` com o token do `.env` do staging; ninguém usou senha.
  - Para a tela abrir, foi montada uma fixture **temporária** no business 1 (CI Biz, fictício): um usuário de medida com `fisio.access`/`fisio.editar` (as duas permissões não existiam no staging), um pacote e uma assinatura com `fisio_module` (o business 1 não tinha assinatura), 3 pacientes fictícios com 3 atendimentos no dia, 1 tipo de atendimento e 1 protocolo. **Tudo apagado ao fim** e conferido: 0 usuário de medida, 0 assinatura e 0 pacote no business 1, 0 paciente, 0 contato, 0 tipo e 0 protocolo da fixture.
  - O dado da produção é mínimo e o do protótipo é mock. Contagem de botões, seções e linhas depende disso; os vereditos de estilo (tamanho, peso, alinhamento, presença do primário) não.
- **Saúde (vale para as 9 telas do lote):** a sonda acusou `tokens.prod` e `tokens.design` NÃO MEDI: `--accent --pos --neg --warn --text-dim --sunken --border` não resolvem na raiz em nenhum dos dois lados (no protótipo os tokens moram em `.oi`, na produção dentro do shell). **A dimensão cor (D6) não é conclusiva**; os valores de cor ficam registrados, não usados como veredito.
- **Não medido:** D1 (rede/partial reload: o protótipo é estático), D3 (ícones), D5 (rodapé), D7 (densidade), shell/sidebar (`__SB_ROLES` não declarado) e tema escuro.
- Nenhum screenshot: comparação por medida.
