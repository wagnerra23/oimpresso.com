---
id: requisitos-fisio-onboarding-visual-comparison
title: "Comparação design × produção — Fisio/Onboarding"
module: Fisio
tela: Fisio/Onboarding
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `Fisio/Onboarding`

> **Âncora computada** (`node scripts/design/ancora.mjs Fisio/Onboarding`): `prototipo-ui/cowork/Wagner/prototipos/fisio/Onboarding.dc.html`,
> declarada em `related_prototype` do charter. Medido em **2026-11-23** (auditoria do Fisio de 2026-11-05, item e8, lote 7).
> Snapshots: [`governance/design/targets/medidas/Fisio--Onboarding/`](../../../governance/design/targets/medidas/Fisio--Onboarding/).

## Veredito: **DIVERGE** — 1 `DIVERGE(bug)`: título 20px na produção × 26px no protótipo

Texto do título, subtítulo, primário (`Continuar`) e campos (2) iguais. O tamanho do título é a divergência medida. A cor do título também difere no valor medido (`oklch(0.137 0.036 258.5)` × `oklch(0.22 0.01 80)`), mas D6 não é conclusiva neste lote.

## Itens

| Item | Produção | Protótipo | Veredito |
|---|---|---|---|
| Título (h1), tamanho e peso — D4 | 20px · peso 600 | 26px · peso 600 | diverge |
| Texto do título | `Seu perfil profissional` | `Seu perfil profissional` | igual |
| Botão primário, presença — D6 | presente | presente | igual (presença); cor não conclusiva, ver saúde |
| Rótulo do botão primário | `Continuar` | `Continuar` | igual |
| Botão primário, fundo · texto · borda (computed) | `oklch(0.55 0.15 295)` · `oklch(0.984 0.003 247.9)` · `oklch(0.92 0.006 90)` | `oklch(0.55 0.15 295)` · `rgb(255, 255, 255)` · `oklch(0.45 0.15 295)` | fundo igual; texto e borda diferem no valor medido, D6 não conclusiva |
| KPI — D2/D4/D8 | nenhum | nenhum | sem KPI nos dois lados; nada a comparar |
| Linha da tabela | sem tabela medida | sem tabela medida | sem dado nos dois lados |
| Botões e links visíveis no conteúdo | 7 | 8 | contagem; depende do dado, não é veredito |
| Campos de formulário visíveis | 2 | 2 | igual |
| Rótulos de botão que não aparecem em nenhum texto do outro lado | `1Seu perfil profissional`, `2Como você atende?`, `3Como o link chega ao paciente`, `4Seus protocolos`, `5Seu primeiro paciente` | `Enviar foto` | a produção torna os 5 passos clicáveis (`1 Seu perfil profissional` … `5 Seu primeiro paciente`); no protótipo os passos são texto. O protótipo tem `Enviar foto`, que não aparece na produção — diverge nos dois sentidos |
| Seções nomeadas (h2/h3/legend) | nenhuma | nenhuma | igual (nenhum dos dois usa cabeçalho de seção) |
| Regiões `data-contract` — D9 | `fisio-onboarding-passo` | nenhuma | sem dado: o protótipo não marca regiões |

## Saída do `--compare`

`node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/Fisio--Onboarding/prod.json governance/design/targets/medidas/Fisio--Onboarding/design.json --check` (exit 1, 1 `DIVERGE(bug)`):

```text
  DESIGN-DIFF — prod(light) × design(light)

  ✓ [D2] layout: prod=ok · design=ok → IGUAL
  ✗ [D4] título font-size: prod=20px · design=26px → DIVERGE (bug) (Δ 6px · banda tituloPx ±1px)
  ✓ [D6] cor: prod=ok · design=ok → IGUAL
  ✓ [D8] kpi align: prod=ok · design=ok → IGUAL
  ⬜ [D9] fisio-onboarding-passo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-alcas: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-corpo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-modos: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-rodape: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D9] sb-topo: prod=presente · design=ausente → SEM-DADO (região só existe de um lado)
  ⬜ [D4] linha da tabela: prod=não medido · design=não medido → SEM-DADO (passe `tableRow` em __DD_ROLES nos DOIS lados)
  ⬜ [SHELL] shell: prod=não medido · design=não medido → SEM-DADO (declare window.__SB_ROLES nos DOIS lados — node scripts/design/design-diff.mjs --shell-roles imprime o mapa)
  ✗ [SAÚDE] tokens.prod: prod=--accent --pos --neg --warn --text-dim --sunken --border · design=— → NÃO MEDI (token de cor não resolve na raiz — a cascata está incompleta e toda cor cai em fallback)
  ✗ [SAÚDE] tokens.design: prod=— · design=--accent --pos --neg --warn --text-dim --sunken --border → NÃO MEDI (token de cor não resolve na raiz — a cascata está incompleta e toda cor cai em fallback)

  ✗ DIVERGE(bug): 1
  ▲ SHELL a classificar: 0

exit=1
```

## Como foi medido

- **Sonda:** a canônica (`node scripts/design/design-diff.mjs --probe`), injetada sem alteração nos dois lados, com o mesmo Chromium (Playwright), viewport 1280×900 e tema claro forçado nos dois. Papéis por lado: protótipo `title=.ph-h1`, `primary=.btn-p`, `kpi=section[aria-label="Indicadores"] div[style*="padding: 16px"]`, `tableRow=main table tbody tr`; produção `title=main h1`, `primary=main [data-slot="button"][data-variant="default"]`, `kpi=main [data-slot="kpi-card"]`, `tableRow=main table tbody tr`. Papel que não casa elemento sai SEM-DADO, nunca igual.
- **Contagens e rótulos** (botões, campos, seções, rótulos só de um lado): uma segunda função, a mesma nos dois lados, roda depois da sonda. Os rótulos "só de um lado" são os que não aparecem em nenhum ponto do texto visível do outro lado (`assinatura` do snapshot).
- **Protótipo:** `prototipo-ui/` servido por http local; aberto `cowork/Wagner/prototipos/fisio/Onboarding.dc.html` e medido depois que o DOM parou de mudar (4 leituras iguais a 500 ms). O `design-diff-lote.mjs` não foi usado: ele renderiza o protótipo pelo shell `oimpresso.com.html`, e as telas do Fisio são páginas `.dc.html` avulsas, fora dessa rota.
- **Produção:** `https://staging.oimpresso.com/fisio/onboarding` (checkout `56f45a3c52` em `main`, 2026-11-21; entre ele e o `main` de 2026-11-23 nenhum arquivo de `Modules/Fisio/Resources/js/` mudou). Login pela rota env-guarded `/_visreg-login` com o token do `.env` do staging; ninguém usou senha.
  - Para a tela abrir, foi montada uma fixture **temporária** no business 1 (CI Biz, fictício): um usuário de medida com `fisio.access`/`fisio.editar` (as duas permissões não existiam no staging), um pacote e uma assinatura com `fisio_module` (o business 1 não tinha assinatura), 3 pacientes fictícios com 3 atendimentos no dia, 1 tipo de atendimento e 1 protocolo. **Tudo apagado ao fim** e conferido: 0 usuário de medida, 0 assinatura e 0 pacote no business 1, 0 paciente, 0 contato, 0 tipo e 0 protocolo da fixture.
  - O dado da produção é mínimo e o do protótipo é mock. Contagem de botões, seções e linhas depende disso; os vereditos de estilo (tamanho, peso, alinhamento, presença do primário) não.
- **Saúde (vale para as 9 telas do lote):** a sonda acusou `tokens.prod` e `tokens.design` NÃO MEDI: `--accent --pos --neg --warn --text-dim --sunken --border` não resolvem na raiz em nenhum dos dois lados (no protótipo os tokens moram em `.oi`, na produção dentro do shell). **A dimensão cor (D6) não é conclusiva**; os valores de cor ficam registrados, não usados como veredito.
- **Não medido:** D1 (rede/partial reload: o protótipo é estático), D3 (ícones), D5 (rodapé), D7 (densidade), shell/sidebar (`__SB_ROLES` não declarado) e tema escuro.
- Nenhum screenshot: comparação por medida.
