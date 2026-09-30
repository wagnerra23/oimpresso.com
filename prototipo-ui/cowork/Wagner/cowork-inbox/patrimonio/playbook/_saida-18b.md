---
sessao: "18b"
titulo: Saída da thread 18 (adendo) — smoke em produção dos drawers e da devolução de foco
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 237d99644 (origin/main, deploy "Deploy to Hostinger" 36755938951, success 18:08Z)
complementa: "_saida-18.md (2026-09-30), que declarou o smoke pendente"
---

# 18b · Smoke em produção: o drawer abre pela URL e o foco volta a quem o abriu

Adendo datado. O `_saida-18.md` fica intacto.

## Onde
`oimpresso.com`, WR2 Sistemas (biz=1, tenant de smoke), viewport 1280. Nenhum formulário foi
enviado: produção tem dado real, e o smoke é só leitura.

## 1ª passada — deploy `ffc377fee` (contém o #8262)

| Cenário | Resultado |
|---|---|
| `/asset/allocation` | carrega; header com **Devoluções** e **Alocar recurso**; coluna **Ações** |
| Recorte **Todas** | vazio: "Nenhuma alocação registrada" — a WR2 não tem alocação |
| Clique em **Alocar recurso** | URL vira `/asset/allocation/create`, drawer abre, 7 campos, foco no campo **Bem** |
| `/asset/allocation/create` **carregado direto** (o defeito original: página em branco) | Page completa com o drawer aberto |
| Props do drawer | `modo=alocar`, 0 bens atribuíveis, 8 pessoas, `d/m/Y`, 24h |
| Sem bem atribuível | aviso "Nenhum bem atribuível nesta empresa…" e **Alocar** desabilitado |
| Fundo | shell do app com `aria-hidden`; só a região de toasts fica exposta |
| `/asset/allocation/999999999/edit` · `/asset/revocation/create?id=999999999` | **404** · **404** |
| **Cancelar** | fecha e volta pra `/asset/allocation` |
| **Achado:** foco depois do Cancelar (página aberta pela URL) | **`BODY`** — descumpria o §D.5 da thread |

## 2ª passada — deploy `237d99644` (contém o #8292, conserto do foco)

Controle antes de medir: o botão só ganha `id="patrimonio-alocar-recurso"` com o #8292. Na
primeira leitura o id **não** existia (o deploy ainda rodava); depois do deploy concluído, existia.

| Cenário | Antes | Depois |
|---|---|---|
| `/create` direto pela URL → **Cancelar** | foco no `BODY` | foco em **Alocar recurso** |
| Teclado: **Enter** em "Alocar recurso" | — | drawer abre, foco no campo **Bem** |
| Teclado: **Escape** | — | drawer fecha, foco em **Alocar recurso**, com `:focus-visible` |

## O que NÃO foi exercitado em produção
**Editar**, **Devolver** e **Excluir devolução**, e a volta do foco ao botão **da linha**: os três
exigem uma alocação existente, a WR2 não tem nenhuma, e criar uma seria gravar dado real sem
autorização [W]. Cobertura que existe: Pest `AlocacoesFormContratoTest` (lane
`assetmanagement-pest`, MySQL real, tenant 98 × 99) e vitest `patrimonio-alocacoes-foco.test.tsx`.
O staging (CT 100) não serviu: checkout em `e57b78bf5` (2026-09-21), sem o #8262, com 14 arquivos
sujos de outras sessões, e login por senha.

## Nota de método
As listagens de deploy (`gh run list --workflow deploy.yml` e `actions/workflows/deploy.yml/runs`)
devolveram runs de julho e agosto como se fossem os mais recentes. O deploy foi confirmado por
`actions/runs?head_sha=<sha do merge>` e pela consequência no DOM (o id novo), nunca pela listagem.
