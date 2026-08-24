---
sessao: "06"
titulo: Correção medida — o diagnóstico se inverte
autor: "[CC]" (a partir da medição [CL] 2026-08-23)
criado: 2026-08-23
substitui: 05-DIAGNOSTICO-PRODUCAO.md (refutado)
---

# O diagnóstico se inverte

## O que eu errei, nominalmente

| Minha afirmação | Medição | Veredito |
|---|---|---|
| "nenhuma tela chegou em produção" | **54 `live` · 48 com sinal de prod · 0 sem** | **falso** |
| H1: todo caminho termina em [W] e nunca foi atravessado | atravessado **48 vezes**, com prova | **refutada** |
| H4: nada trava tecnicamente, tudo advisory | **46 contexts required**, com `enforce_admins` | **refutada** |
| "não existe oráculo de tela-em-produção" | `scripts/governance/charter-live-signal.mjs` — **required** | **falso** |
| proposta: criar `prototipo-ui/PRODUCAO.md` | seria **segundo dono** do mesmo tema | **retirada** |

**Causa do meu erro, para não repetir:** eu leí as *notas dos contratos* ("roda advisory", "flip pendente") e tratei nota datada como estado atual. Nota é fato do dia em que foi escrita. Estado é medição. Eu tinha essa regra no `CLAUDE.md` e a apliquei ao `main` — e não a apliquei às **notas dentro** dos arquivos do `main`. A regra vale um nível abaixo também.

## O que sobrevive — e agora é o caminho crítico

**H3 confirmada:** `prototipo-readiness.mjs` mede **29 prontas de 54** com protótipo real. As 25 restantes travam em **`casos.md` com UC** e **scorecard** — artefato de contrato, **não aprovação humana**.

Isso muda o dono do gargalo: **é meu, não seu.** As 25 se fecham escrevendo casos+UC e scorecard. Nenhuma depende de decisão sua.

## O único defeito real medido

**`governance/route-hits.json` está expirado.** Janela de 30 dias, `ultima_data` máxima **2026-07-25**, último commit que o tocou **2026-08-07**. Hoje é 23/08 — a janela inteira venceu. O export é manual (`php artisan route-hits:export --write` no host de prod + commit à mão).

É a peça que faz o sistema **parecer** parado por dentro do repo — e foi exatamente o que me enganou. Dono: [W]/[CL], não eu.

> Limite que a própria medição declara: o ledger cobrir 11 páginas **não** prova que só 11 são usadas; prova que está velho.

---

## Plano corrigido

### Sai
- ❌ `PRODUCAO.md` (9.x / §4 do arquivo 05) — dono já existe e é required
- ❌ Tratar as decisões [W] como o gargalo — 3.25, 5.17, 5.04 seguem abertas mas **não travam produção**; travam *escopo*, não *entrega*
- ❌ A leitura "advisory eterno" — 46 checks trancam merge hoje

### Entra — prioridade 1: fechar as 25
| # | Processo | Dono |
|---|---|---|
| C.01 | Obter a **lista nominal das 25** (`prototipo-readiness.mjs`, saída completa) | [CL] |
| C.02 | Ler o critério de **scorecard** — eu nunca o vi; não escrevo contra critério que não li | [CC] |
| C.03 | Escrever `casos.md` com UC para as 25, em ondas por módulo (Lei 1 de prefixo) | [CC] |
| C.04 | Scorecard para as 25 | [CC] |
| C.05 | Reconferir readiness após cada onda — a métrica é 29→54 | máquina |

### Entra — prioridade 2
| # | Processo | Dono |
|---|---|---|
| C.06 | **Religar o export do `route-hits`** — automatizar ou agendar; é o que faz o sistema parecer parado | [W]/[CL] |
| C.07 | Fila dos **186 `draft`** — real, mas é dívida de lei, não de entrega. Priorizar só os que têm tela viva | [W] |

### Continua válido (a medição não tocou)
S1 (build) · S2 (13 órfãos do Ponto) · S4 (diff do resíduo) · S3 (curadoria) · as 4 leis de paralelismo · o pedido do teste prático do Ponto.

**E ganha reforço:** S2/S5/S9 eram "cobertura"; agora são **o gargalo medido**. Escrever casos.md deixou de ser higiene e passou a ser a única coisa entre 29 e 54.

---

## Onde a ponte estava certa e onde estava errada

| Peça | Veredito |
|---|---|
| Inventário de contratos (11 + schema + intent) | ✅ correto |
| Trio quebrado: Ponto/Dashboard e CaixaUnificada sem casos | ✅ correto, e agora **explica** parte das 25 |
| 103 telas sem casos · 206 sem contrato | ✅ correto — e é o gargalo, não a periferia |
| 13 órfãos do Ponto (S2) | ✅ correto |
| Diagnóstico de produção (05) | ❌ refutado |
| "o gargalo é [W]" | ❌ refutado — o gargalo é artefato, e é meu |

## A frase honesta

Eu disse que o caminho crítico não era volume, era decisão. **Era o contrário.** 5.811 PRs merged e deploy contínuo dizem que a máquina anda; 29 de 54 dizem que o que falta é artefato escrito — e artefato escrito é exatamente o meu trabalho, sem depender de você.

**Próximo passo concreto:** peça ao [CL] a saída completa do `prototipo-readiness.mjs` (a lista nominal das 25) e o critério do scorecard. Com esses dois, eu abro as ondas e a métrica 29→54 passa a se mover.
