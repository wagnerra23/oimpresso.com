---
slug: 0397-prototipo-minimo-por-dono-e-ds-direto
number: 397
title: "Protótipo mínimo por dono, máquinas fora do artefato e Design System direto"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-11"
module: governance
tags: [design, cowork, prototipo, ssot, organizacao, scripts, documentacao, handoff]
supersedes:
  - 0396-prototipo-fonte-unica-build-sem-canon-sombra
superseded_by: []
related:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0379-bundle-design-transacao-manifesto-delta-staging
pii: false
---

# ADR 0397 — protótipo mínimo por dono e DS direto

## Contexto

Em 2026-09-11, [W] autorizou eliminar todas as cópias e reorganizar também máquinas,
documentação e arquivos paralelos. A ADR 0396 removeu a principal árvore-sombra, mas ainda
deixava contratos, alvos, máquinas e documentação dentro de `prototipo-ui/`, além de admitir
um cache `_ds/` fisicamente duplicado. Também não separava a procedência das duas contas de
design. Isso mantinha caminhos concorrentes e permitia que uma importação parecesse deslocar
arquivos entre origens.

## Decisão

**D1 — árvore mínima.** `prototipo-ui/` contém somente:

```text
prototipo-ui/
├── cowork/
│   ├── Wagner/
│   └── Felipe/
└── design-system/
```

Não se admitem arquivos soltos nem outras pastas nessa raiz. `cowork/` também não admite
terceiro dono ou arquivo solto.

**D2 — procedência é parte do endereço.** Cada fonte de tela permanece sob o dono da conta
que a originou. Os materiais Wagner vivem em `cowork/Wagner/`; Venda e Produto recebidos da
outra conta vivem em `cowork/Felipe/`. Cada dono possui `handoffs/`. Importadores da conta
Wagner não escrevem na árvore Felipe e não achatam o segmento do dono.

**D3 — protótipo contém artefato, não operação.** Máquinas de inspeção, importação e comparação
vivem em `scripts/design/`; transporte e sincronização vivem em `scripts/design-sync/`;
contratos, alvos e mapas máquina-legíveis vivem em `governance/design/`; fixtures e avaliações
vivem em `tests/Design/`; política, runbooks e referências vivem em
`memory/reference/prototipo-ui/`. O endereço de cada classe tem um único dono.

**D4 — DS sem cópia intermediária.** `prototipo-ui/design-system/` é a única cópia física do
Design System no repositório. O shell Wagner referencia esse diretório diretamente.
`scripts/design-sync/mirror-snapshot/` e `cowork/Wagner/_ds/` foram removidos; a antiga ação
`--preview-ds` não materializa cache. Payloads `_ds/**` são normalizados e gravados diretamente
no DS canônico.

**D5 — histórico somente no Git.** `_arquivo/`, `design-docs/`, protótipos repetidos e snapshots
paralelos não são cemitérios válidos. Conteúdo histórico recuperável permanece no Git. Uma
trava percorre todos os arquivos físicos de `prototipo-ui/`, inclusive ignorados, e falha quando
encontra bytes idênticos em dois caminhos.

**D6 — caminho literal e owner-aware.** Mapas, charters, contratos, workflows e máquinas usam
os novos endereços completos. Heurística de basename não pode trocar dono, subdiretório ou
âncora. Quando charter e alias discordam, o charter é a âncora e o alias concorrente é removido.

**D7 — prova antiga não atravessa mudança de identidade.** A migração de namespace invalida,
sem substituir, a última prova dos arquivos cujo conteúdo precisou ser adaptado à nova topologia.
Eles voltam ao estado explícito `NUNCA VERIFICADO` até uma nova comparação com o Cowork vivo;
autorização de organização não é registrada como prova de fidelidade visual.

## Consequências

- abrir ou importar um protótipo não recria DS paralelo nem muda sua procedência;
- Wagner e Felipe têm canais de handoff independentes e endereços inequívocos;
- documentação e máquinas chegam aos diretórios próprios sem poluir o artefato visual;
- qualquer nova pasta na raiz, novo dono informal, documentação fora de `handoffs/` ou conteúdo
  duplicado faz o guard falhar;
- a ADR 0396 foi supersedida porque sua topologia ainda admitia classes que esta decisão retirou
  de `prototipo-ui/`.
