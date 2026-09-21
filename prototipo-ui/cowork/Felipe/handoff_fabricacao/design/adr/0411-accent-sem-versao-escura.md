# ADR 0411 — `--accent` não é reescrito no tema escuro e reprova como cor de texto

**Data:** 2026-09-01 · **Status:** proposto (aguarda decisão) · **Camada:** 1 · Fundações
**Origem:** conferência da família de telas Fabricação (Manufacturing)

## Contexto

O bloco `.cockpit[data-theme="dark"]` de `_ds/…/colors_and_type.css` (L328-341) reescreve
`--bg`, `--bg-2`, `--surface`, `--border`, `--border-2`, `--text`, `--text-dim`, `--text-mute`,
`--accent-soft`, `--pos`, `--neg` e `--warn`.

**Não reescreve `--accent` nem `--accent-2`.** O roxo do tema claro — `oklch(0.55 0.15 295)` —
continua valendo sobre superfícies escuras.

Medição (WCAG 2.1, sRGB):

| Par | Claro | Escuro | Mínimo | Veredito |
|---|---|---|---|---|
| `--accent` como **texto** sobre `--surface` | 5,15:1 | **2,64:1** | 4,5 | reprova no escuro |
| `--accent` como **texto** sobre `--bg-2` | 4,66:1 | **3,27:1** | 4,5 | reprova no escuro |
| `--accent-fg` (#fff) sobre `--accent` (**fundo**) | 5,15:1 | 5,15:1 | 4,5 | aprova |

Comparação que mostra que é defeito de origem e não de tela: `--pos`, `--neg` e `--warn` **são**
reescritos no escuro (0,74 / 0,72 / 0,80 de luminosidade contra 0,50 / 0,55 / 0,58 no claro), e por
isso passam nos dois temas. O acento é o único tom saturado sem versão escura.

Onde dói na família Fabricação, medido `[TELA]`:

| Elemento | Uso do acento |
|---|---|
| `.mfg-link` (7 ocorrências: pontes para Compras, Produtos, Fila, Financeiro, receita) | cor de **texto** |
| `.mfg-tot .big dd` / valor "Custo por unidade" | cor de **texto**, 15px 600 |
| `.mfg-tab.act` | cor de **texto** + borda inferior |
| `.mfg-chip.act` | cor de **texto** + borda + fundo 8% |
| `.mfg-crumb button` (trilha de volta) | cor de **texto** |
| `.mfg-kpi.act`, `.mfg-inp:focus`, `.mfg-bar-mini i`, `.os-btn.primary` | **fundo / borda / anel** — aprovam |

Agravante de runtime, declarado: `--accent` é reescrito **inline em `<html>`** pelo seletor de
matiz do shell (`app.jsx`, tweak `accentHue`, default 295) `[RUNTIME]`. Qualquer matiz escolhido
pelo usuário herda o mesmo problema no escuro, e o valor observado num navegador **não é** a
cor da empresa: o contrato é o token do DS.

## Decisão

**No DS (proposto):** acrescentar ao bloco `.cockpit[data-theme="dark"]` uma versão clara do
acento — pelo mesmo critério já usado em `--pos`/`--neg`/`--warn` (subir a luminosidade OKLCH
mantendo matiz e croma). O nome do token e o valor exato são decisão do DS, não desta tela;
**não inventar aqui**.

**Na tela (proposto, não aplicado):** enquanto não houver decisão, `--accent` **só como fundo,
borda e anel** no tema escuro; texto que precisa de destaque usa `--text` com peso 600.

**Não aplicado sem decisão.** O valor do DS foi aplicado como está.

## Consequências

- **Se aprovado no DS:** link, aba ativa e valor destacado passam a aprovar no escuro em todas as
  telas do cockpit de uma vez — o defeito não é desta família.
- **Se aprovado só na tela:** perde-se o destaque roxo do número principal no escuro, que é a
  única marca de hierarquia no quadro de custo.
- **Se não aprovado:** o tema escuro entrega 2,64:1 em texto de link — o pior número medido nesta
  família.

## Referências

- `_ds/office-impresso-design-system-019dd02f-…/colors_and_type.css` L274-341
- `design/LAUDO-conferencia-fabricacao.md` §4
- `contexto/pauta-design-system.md`
