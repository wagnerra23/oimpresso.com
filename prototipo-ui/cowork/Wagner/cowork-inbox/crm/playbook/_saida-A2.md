---
sessao: "A2"
titulo: "ALVO lote 2 do Crm — 7 de 7 telas medidas — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: origin/main 8fe939db25
thread: A2-alvos-lote2.md
veredito: "entregue — 7 alvos medidos (36 seções, 0 ausentes), cada um 2× byte-idêntico; secao-check conforme nos sete."
---

# _saida-A2 · Alvos do Crm, lote 2

## O que saiu

| tela | slug | rota no protótipo | seções | sha256 (16) |
|---|---|---|---|---|
| Campanhas | `crm--campanhas--index` | `crm-campanhas` → `CrmBladePage view=campanhas` | 5 · header · nav · filtros · toolbar · grade | `660a00fc7022cdee` |
| Registro de chamadas | `crm--chamadas--index` | `crm-chamadas` → `view=chamadas` | 6 · header · nav · filtros · toolbar · grade · rodape | `2f5a3d336a0849db` |
| Relatórios | `crm--relatorios--index` | `crm-relatorios` → `view=relatorios` | 6 · header · nav · filtros · por_usuario · por_contato · conversao | `9ca51ed2b7374102` |
| Propostas | `crm--propostas--index` | `crm-propostas` → `view=propostas` | 4 · header · nav · toolbar · grade | `c74f6261734cabc1` |
| Configurações | `crm--config--index` | `crm-config` → `view=config` | 4 · header · nav · campos · acoes | `dd46e7aaf853ca5c` |
| Login de contatos | `crm--logins--index` | `crm-logins` → `view=logins` | 5 · header · nav · filtros · toolbar · grade | `d2c25d0565ae1693` |
| Pedido de ordem | `crm--pedidos--index` | `crm-pedidos` → `view=pedidos` | 6 · header · nav · filtros · toolbar · grade · rodape | `76b72f8416eed76d` |

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json` para os 7 slugs. Os `.alvo.json` são saída do `alvo:medir`, não editados à mão. Os 7 hashes são distintos.

## Como foi medido

- Espelho `prototipo-ui/cowork/Wagner/` servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR`) na porta 5571.
- Seletores colhidos com `alvo:mapa --rota crm-<x>` nas raízes `.cb-root`, `.cb-root > .pb-body` e `… > section:nth-child(N) > .pb-widget-b`; o resultado está no `_mapa` de cada `.secoes.json`. Onde o protótipo tem `data-contract` (todo `Widget` grava o seu, inclusive `crm-filtros`; toolbar = `crm-toolbar`, rodapé = `crm-rodape`), o seletor usa o contrato. Só header e nav vão por classe, como na A1.
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5571/ --tela <slug> --rota crm-<x> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`, duas vezes por tela (a 2ª com `--saida` fora do repo); `cmp` idêntico nas sete.
- Viewport 1280×900, tema dark (default do `alvo.mjs`). `nos_totais`: 576 · 678 · 709 · 559 · 474 · 577 · 594. `ausentes: []` nas sete.
- `node scripts/design-sync/alvo.mjs --selftest --browser`: 27/27 ok, rc=0.
- `node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho --porta 5572`: **conforme**, rc=0 nas sete. A porta padrão (5550) estava ocupada por outra sessão (`EADDRINUSE`), por isso o `--porta`.

## Diferença em relação à A1

A A1 apontou os filtros por posição (`section:nth-child(2)`) e escreveu que eles não tinham contrato. O `Widget` de filtros grava `data-contract="crm-filtros"` (`crm-blade.jsx:226`), e aqui o seletor usa esse contrato. Os alvos da A1 não foram tocados (fora do prefixo).

## Ressalva medida: o relógio do header

Igual à A1: o header mostra "Atualizado HH:MM" e isso só entra em `base.assinatura`, que o `secao-check` trata como informativo (`~ (página) → base.assinatura`). Nenhuma seção carrega o horário; os 7 pares saíram idênticos no mesmo minuto.

## Provas do json conferidas

`governance/design/targets/crm--{campanhas,chamadas,relatorios,propostas,config,logins,pedidos}--index.alvo.json` existem e têm a chave `secoes`.

## Pendente (não inventado)

1. **Modais fora do alvo.** Campanhas, Logins e Propostas têm modais ("Criar campanha", "Adicionar login", "Enviar proposta"); só a vista padrão foi medida. Medi-los é outro alvo, com `--clicar`.
2. **Rodapé ausente em Campanhas, Logins e Propostas** porque o protótipo não renderiza `Rodape` nessas telas — não é omissão da medida.
3. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: fora do `prefixo`.
4. Fidelidade do protótipo ao Blade legado segue não provada; o alvo mede o protótipo.

## Placar

entregue 7 de 7 alvos · 36 de 36 seções medidas · ausentes 0.

## PR

O PR que adiciona este arquivo — branch `claude/crm-a2-alvos`.
