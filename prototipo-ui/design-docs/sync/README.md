# Sync transacional Cowork → git

O conteúdo do design vira dado transportável; nenhum byte é copiado do contexto do agente.
O bundle v2 separa duas coisas que antes eram confundidas:

- **receber o design**: manifesto, delta, staging, validação integral e promoção atômica;
- **aplicar no produto**: inventário por tela/módulo com evidência e testes ligados a hashes.

## Produzir no lado que possui os arquivos do Cowork

Primeira rodada (snapshot completo):

```bash
node scripts/design-sync/gerar-payload-partes.mjs --root . --out sync
```

Rodadas seguintes (delta):

```bash
node scripts/design-sync/gerar-payload-partes.mjs \
  --root . --out sync-novo \
  --previous sync-anterior/bundle.manifest.json
```

O manifesto sempre descreve o estado-alvo inteiro. No delta, somente `added/modified` carregam
bytes; `deleted` é declarado e `unchanged` não é baixado. Arquivo grande é dividido em chunks
SHA-256. Todas as partes repetem identidade, base e total; a `part01` carrega o manifesto.

## Consumir no git

Baixe exatamente todas as `payload.partNN.json` emitidas e rode:

```bash
node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --dry --require-complete-shell
node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --require-complete-shell
node scripts/design-sync/status.mjs --check-mapping
```

O applier valida sequência 1..N, base ativa, digests, chunks, estado-alvo e grafo inteiro em
staging. Só depois troca os destinos. Falha durante a promoção restaura o estado anterior.

Destino por papel:

- fonte do Cowork → `prototipo-ui/cowork/`;
- documentação `.md` → `prototipo-ui/design-docs/`;
- `_ds/**` → snapshot de runtime do preview;
- estado/provas → `scripts/design-sync/state/`.

## Registrar aplicação no React

O relatório lista cada fonte, alvo React e módulo — inclusive mapeamentos 1:N de Superadmin e
Officeimpresso. Após aplicar e testar uma tela:

```bash
node scripts/design-sync/status.mjs \
  --mark-applied officeimpresso-page.jsx \
  --target Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Index.tsx \
  --evidence PR-1234 \
  --test "pest Officeimpresso"
```

Depois, `node scripts/design-sync/status.mjs --refresh --check-mapping`. A evidência vale apenas
enquanto os hashes atuais da fonte e do alvo forem os mesmos; qualquer nova mudança retorna a
tela para pendente. Fonte sem destino inequívoco fica bloqueada. Remoção no Design nunca apaga
automaticamente a Page React.

## Por que `_ds` continua em cache

Porque ele é saída derivada para executar o preview (tokens, CSS, fontes e bundle), não a fonte
do design nem a memória do protocolo. Rebaixá-lo do cache para “estado oficial” criaria uma
segunda fonte, misturaria histórico com artefato reconstruível e faria o delta depender de
bytes de build. O que precisa sobreviver está no manifesto, no relatório e no ledger fora de
`_ds`; o cache pode ser apagado e refeito a partir dessas fontes.
