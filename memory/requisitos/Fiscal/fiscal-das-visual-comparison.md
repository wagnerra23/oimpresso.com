---
id: requisitos-fiscal-fiscal-das-visual-comparison
tela: Fiscal/Das
url: /fiscal/das
status: draft
prototype_source: prototipo-ui/cowork/Wagner/fiscal-das-page.jsx
implementation: resources/js/Pages/Fiscal/Das.tsx
gap: memory/requisitos/Fiscal/fiscal-das-gap.md
map: memory/requisitos/Fiscal/fiscal-das.map.json
adr: 0107
---

# Visual Comparison — Fiscal/Das

Primeiro registro desta tela (2026-11-05, auditoria do Fiscal, lote 5). A tela nasceu em
2026-10-27 (#10268) atrás da flag `useV2FiscalDas` e nunca tinha sido medida.

## Âncora

`node scripts/design/ancora.mjs Fiscal/Das` → `prototipo-ui/cowork/Wagner/fiscal-das-page.jsx`
(`related_prototype` do charter).

## Medição de runtime — 2026-11-05 · veredito NÃO MEDI

| eixo | resultado |
|---|---|
| comando | `node scripts/design/design-diff-lote.mjs --incluir-comparadas --tema light --tela Fiscal/Das --base-url http://127.0.0.1:8000 --user-id <admin do biz seedado>` |
| onde | `design-smoke-ci.yml` por `workflow_dispatch` no branch de medição `claude/fiscal-lote5-medir-nao-mergear` (run [42085947187](https://github.com/wagnerra23/oimpresso.com/actions/runs/42085947187)), app efêmero de `main` @ `7d997591d7` com `FEATURE_FLAGS_FORCED_ON=useV2FiscalDas` |
| veredito | **NÃO MEDI** (`rc=2`): `rota do shell: sem rota derivável` |
| causa 1 | O `app.jsx` não tem `route === "fiscal-das"`: a tela entra no shell pelo `prototipo-html-page.jsx`, que monta o HTML de `window.PROTO_HTML["fiscal-das"]`. A derivação do lote não cobre esse caminho. |
| causa 2 | Mesmo com a rota, a sonda não alcançaria a tela: o `prototipo-html-page.jsx` a monta numa **shadow root**, e `scripts/design/design-diff.mjs` não atravessa shadow root (`grep -c shadowRoot` = 0). |

**Para medir de verdade é preciso uma das duas:** a sonda do `design-diff` atravessar shadow root,
ou o protótipo do DAS virar um `-page.jsx` React como os irmãos do Fiscal. As duas são trabalho
em máquina compartilhada ou no protótipo, fora deste lote.

## Inventário por parte (leitura, não medida)

Lido nos dois arquivos; detalhe e linhas em [`fiscal-das-gap.md`](fiscal-das-gap.md) e
[`fiscal-das.map.json`](fiscal-das.map.json).

| Parte | Produção | Protótipo | Veredito |
|---|---|---|---|
| Cabeçalho e filtros | título, competência, anexo | idem + "Cenário (só no protótipo)" | paridade (o cenário é instrumento do protótipo) |
| Sem anexo | "Escolha o anexo que o contador usa" | idem, citando "aba Serviços" para o Fator R | **decisão [W]**: não existe aba Serviços |
| KPIs | 4 | 4, mesmos rótulos | paridade |
| A conta | fórmula com os números | idem | paridade |
| Faixas do anexo | tabela, faixa atual marcada | idem | paridade |
| Estimativa × apuração | ausente | card "Estimativa do Financeiro × apuração" | Non-Goal do charter nesta fatia |
| Perguntas ao contador | 4 | 4, mesmo texto | paridade |

## Subnav — achado, não conserto

O contrato `governance/design/contracts/fiscal-subnav.contract.json` e o
`resources/js/Pages/Fiscal/_lib/paginas-fiscais.tsx` têm **7 abas**. O `FX_TABS` do protótipo
(`prototipo-ui/cowork/Wagner/fiscal-page.jsx:20-30`) tem **9**: acrescenta "MDF-e · CT-e" e
"Tributação". Na rodada de 2026-11-05 a assinatura de texto do protótipo trouxe as duas nas 7
telas medidas; a da produção não trouxe "MDF-e · CT-e" em nenhuma. O contrato diz que a copy foi
extraída do protótipo em 2026-09-09; o protótipo mudou depois. O DAS não está em nenhuma das duas
listas (Non-Goal do charter: fora da sub-nav enquanto a flag não valer para todas as empresas).
Quem decide se o subnav ganha as abas é o [W].
