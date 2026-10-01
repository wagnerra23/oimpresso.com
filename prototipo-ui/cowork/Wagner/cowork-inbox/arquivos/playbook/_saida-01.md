---
sessao: "01"
titulo: Arquivos/Index — 5 bugs do design-diff (recibo)
autor: "[CL]"
base: origin/main 7201ce433b53 (2026-09-30)
---

# _saida-01 · Arquivos/Index — fechar a medida

## Errata da ficha
- O caminho é `governance/design/targets/medidas/Arquivos--Index/resultado.json`, não
  `targets/medidas/...`. Medida de **2026-09-18T12:34Z**, `staging.oimpresso.com/arquivos`.

## O que entreguei (só `resources/js/Pages/Arquivos/Index.tsx`)

| bug medido | causa no `.tsx` | conserto (fonte: `prototipo-ui/cowork/Wagner/arquivos-page.jsx`) |
|---|---|---|
| col0.mono | slug do contexto saía só no `title`; a sub-linha não tinha nada em mono | `{rótulo PT-BR} · <code className="mono">{slug}</code>`, como o protótipo (L148). Slug sem rótulo no mapa vai só no `<code>`. Sem contexto segue prosa. |
| col2.mono | visibilidade num `<span>` de texto | `<small className="mono">`, dentro de `.arq-cls` (protótipo L153) |
| col2.pílula.dot | `<Badge>` sem dot (`danger`/`secondary`) | `StatusBadge kind="sla"` com o mapa `BUCKET.k` do protótipo (sensitive→expired, active→fresh, memory→late, discard→aging). Dot sai por padrão do `StatusBadge`. Bucket fora do mapa: texto neutro, como o `BUCKET_FALLBACK`. |
| col6.cor | `Button variant="ghost"` não declara cor de texto → ícone herdava a cor da linha | `text-muted-foreground hover:text-foreground` no botão de baixar |

Contrato de tela conferido localmente: `node scripts/contrato-de-tela.mjs --contract
governance/design/contracts/arquivos-index.contract.json` → **limpo, rc=0**.

## O que NÃO entreguei, e por quê

- **col1.alcancavel ("texto morto") — NÃO é bug do `.tsx`, é DADO.** As 25 linhas medidas em
  staging são todas `NfeEmissao` (ver `prod.json`). Esse tipo **não tem rota por id** e o
  servidor manda `dono_url = null` de propósito — é o UC-INDEX-06 (*"tipo sem rota provada vira
  texto, nunca link morto"*), defendido por `ArquivosAdminControllerTest.php` (`expect($nfe['dono_url'])->toBeNull()`).
  O protótipo faz a mesma coisa (`rota ? <button> : <b>`). Fechar exigiria uma rota de tela
  para `NfeEmissao` + o mapa em `ArquivosAdminController::donoUrl` — `Http/` está no
  `nao_toca` desta thread. **Pendente [W]/thread nova**, se quiser link pra emissão de NF-e.
- **Cor exata do col6:** o `Button` do DS do protótipo é live-only (não há cópia no repo), então
  não dá pra saber a variante dele. Escolhi `muted→foreground no hover` (o que a medida pede é
  "cor própria"). Conferir na próxima medida.

## As 3 linhas "DIVERGE (dado?)" — conferidas
- **estado de linha 0/25 × 3/10:** a prod tem `estadoDaLinha()` (urgent/archived). Nas 25 linhas
  de staging nenhuma é órfã, nenhuma vence em ≤30d e nenhuma está anonimizada → **dado**.
- **col5 blocos 3 × 2:** a prod mostra a contagem "76d" (≤90 dias) e o protótipo, com prazos
  longos, não → render condicional por **dado**.
- **col6 blocos 0 × 3:** o protótipo desenha Baixar·Classificar·Excluir (papel gestor). A prod
  só tem Baixar — classificar/excluir **não têm endpoint** (threads 02 e 03). **Escopo**, não bug.
- (`table-layout`/larguras = DIVERGE (fonte): a prod declara as larguras do protótipo; fica.)

## Trilha / Retenção / Cofre
Confirmado: são **abas do mesmo `Index`** (`Acervo · Retenção · Cofre · Trilha`, presentes na
assinatura de `prod.json` e no contrato de tela) — não há rota separada. PR-2/3/4 estão
entregues como vistas.

## Prova (json da thread)
- `provas: []`; a nota pede `design-diff --compare --check` sem `DIVERGE (bug)`.
- **NÃO MEDI.** A medida precisa do staging logado com este código no ar, e o PR ainda não foi
  deployado. Além disso, **col1 continua acusando** enquanto o staging só tiver linhas
  `NfeEmissao` (é dado, ver acima) — o `--check` só fica limpo com uma linha de tipo com rota
  (OS/JobSheet) no acervo do staging, ou com a decisão de dar rota à NF-e.

## PR
Ver o PR `claude/arquivos-thread-01` (link no corpo do commit/PR).
