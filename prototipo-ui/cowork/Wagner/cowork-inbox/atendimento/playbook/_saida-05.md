---
sessao: "05"
titulo: "Recibo — contrato atendimento-caixa-unificada"
autor: "[CL]"
data: 2026-10-06
base: origin/main 451a35dc9d
thread: 05-alvo-contrato.md
veredito: "ENTREGUE — contrato com as 8 seções do alvo A1, âncoras data-contract na Page no mesmo PR, gate verde"
---

# _saída 05 · Contrato da Caixa Unificada

## Cruzei o `nao_toca`, e por quê

O índice diz `nao_toca: ${MPAGES}/`. O job required `Contratos de tela (fidelidade + intenção)` roda
**todos** os `*.contract.json` e exige uma âncora `data-contract` no alvo para cada seção. A Page não
tinha nenhuma das 8. Medido com o contrato sem âncoras:

```
X seção "atend-header" sem âncora data-contract no alvo
X seção "atend-lista-cabecalho" sem âncora data-contract no alvo
❌ 2 falha(s).   rc=1
```

Mergear assim deixaria o required vermelho em todo PR. É o mesmo impasse do Repair, resolvido no
#8533 com contrato e âncoras no mesmo PR (`repair/playbook/_saida-05.md`). Segui o mesmo caminho: as
âncoras são **só atributos `data-contract`**, sem mudança de comportamento nem de estilo.

## O que mudou

| arquivo | mudança |
|---|---|
| `governance/design/contracts/atendimento-caixa-unificada.contract.json` | novo, 8 seções do alvo A1 |
| `Index.tsx` | `atend-header` no wrapper do header |
| `ConversationListV4.tsx` | `atend-lista-cabecalho`, `atend-busca`, `atend-lista` |
| `ChannelHealthBanner.tsx` | `atend-saude` no `Stack` raiz |
| `ConversationThreadV4.tsx` | `atend-thread-cabecalho`, `atend-mensagens` |
| `ComposerV4.tsx` | `atend-composer` no `div` raiz |
| `Index.charter.md` (v23) | declara o segundo contrato |

## Decisões de derivação

- **Copy:** o alvo não carrega texto. Entrou só `"Conversas"`, que está no protótipo (`inbox-page.jsx`)
  e na tela. O placeholder da busca diverge (protótipo "Buscar nome, empresa, texto…" × tela "Buscar
  nome, número, texto…") e ficou fora: escolher é do dono.
- **Ordem:** o gate ordena as âncoras por arquivo e depois por posição. A `ordem` cobre só lista →
  thread (ConversationListV4 antes de ConversationThreadV4, que é também a ordem visual). Header,
  saúde e composer ficam fora.
- **Saúde (ressalva 4 da A1):** entrou. A âncora é estática; o banner só renderiza com canal degradado.
- **Contrato antigo** `caixa-unificada.contract.json` (reconexão) segue intacto e verde.

## Provas

- `node scripts/contrato-de-tela.mjs --contract …/atendimento-caixa-unificada.contract.json` → 8 OK + ordem OK, rc 0
- mesmo comando no `caixa-unificada.contract.json` → rc 0
- `--map --check` → rc 0 · `--anti-tautologia` → 77 contratos, 0 reprovados
- `module-surface.mjs Whatsapp --check` → sem drift
