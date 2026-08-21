# Sync automática Cowork → git (payload + applier)

**Problema que isso resolve:** baixar arquivo-por-arquivo via `get_file` faz o conteúdo passar pelo contexto do agente. Escrever de dentro do contexto é transcrição — a causa-raiz do STALE de 2026-08-11. Daí a conclusão (errada como teto absoluto) de que "94 dos 121 não têm rota fiel".

**A rota fiel:** o conteúdo vira dado, não texto de conversa.

```
Cowork  ──gera──▶ sync/payload.json  ──URL curta──▶  curl/fetch  ──▶ aplicar-payload.mjs ──▶ prototipo-ui/cowork/
                  (118 arquivos, hash por arquivo)                    verifica hash antes de escrever
```

Nenhum byte de conteúdo entra no contexto de agente nenhum, em nenhuma ponta.

## Aplicar

**O applier mora em `scripts/design-sync/`, NUNCA em `prototipo-ui/cowork/`.** R1 do `cowork-ssot-guard` reprova qualquer `.md` dentro de `cowork/` — o `README.md` deste pacote também fica fora (canon = `memory/` ou raiz de `prototipo-ui/`).

```bash
curl -sL "<URL do payload>" -o /tmp/payload.json
node scripts/design-sync/aplicar-payload.mjs /tmp/payload.json --dry   # relatório, não escreve
node scripts/design-sync/aplicar-payload.mjs /tmp/payload.json         # escreve
node scripts/governance/cowork-ssot-guard.mjs                          # o guard de sempre
```

Saída: `+` novo, `~` mudado, contagem de idênticos, e `?` órfão (existe no dest e o shell não carrega). Hash divergente = não escreve aquele arquivo e sai 1.

**O applier não apaga nada.** Órfão é relatado, nunca podado — poda é decisão de [W]. Isso importa: hoje o `cowork/` do git tem ~198 arquivos e o shell carrega 121. `venda-v3/`, `produto-preco-especial/`, `prototipo-ui-patch/`, `ds-v6/` sobrevivem ao apply e vão aparecer na lista `?`.

## Contrato do payload

- **Âncora = o shell.** O manifesto é `oimpresso.com.html` **mais** seus `src`/`href` locais, query `?v=` removida. Não é lista curada à mão — regenera junto com o shell, então não envelhece. O shell entra no payload: sem ele o git aplicaria os módulos e ficaria com o host stale, que é o próprio drift que isso combate.
- `fnv1a-64` (16 hex) sobre o conteúdo UTF-8. Mesma função nas duas pontas — é a verificação, não identidade criptográfica.
- **`_ds/` fica fora** (3 refs). O espelho DS é *linkado*, e o git é SSOT dele (ADR 0239) — quem espelha token é o `design-sync-push`, não isto.
- **`?v=` dupes nunca entram** — o manifesto normaliza a query, então os arquivos literais `app.jsx?v=eb2` do projeto são invisíveis pra ele. Alinhado com o `cowork-ssot-guard`.
- `missing: []` — os 3 arquivos 404 da lista anterior (`venda-v3/sells-create.jsx`, `produto-preco-especial`, `cobranca-page.jsx`) não aparecem porque **o shell não os carrega**. Origem externa (`FORA_DESTA_CONTA`), não drift.

## Limite honesto

A URL do payload é **curta (~1h, poucos fetches)** e eu preciso gerá-la a cada rodada. Então "automático" = *um comando seu, sem transcrição* — não *cron sem humano*. Pra cron de verdade, o git precisa de credencial própria pro projeto Cowork; hoje não tem.
