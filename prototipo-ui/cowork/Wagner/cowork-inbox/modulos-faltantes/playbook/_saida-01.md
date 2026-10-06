---
sessao: "01"
titulo: "Contratos de tela — CV, Suporte, Vestuário — NÃO entregue: a thread não fecha dentro do prefixo dela"
autor: "[CL]"
data: 2026-10-06
base: origin/main bab78f4764
thread: 01-contratos.md
veredito: "bloqueada — 0 de 3 contratos escritos. Os 3 reprovam o gate required (rc=1) porque os alvos têm 0 âncoras data-contract e a thread não pode tocar resources/js/Pages/."
---

# _saida-01 · Contratos de CV, Suporte e Vestuário

## 1 · Feito

- Nenhum arquivo em `governance/design/contracts/`. O prefixo ficou intocado.
- Este `_saida-01.md`.

## 2 · Não feito, e por quê (medido em `bab78f4764`)

**B1 — o gate é required e não tem modo advisory por contrato.** O job `Contratos de tela (fidelidade + intenção)` está na lista de contexts do `required-checks-baseline.json` (lido pelo parser, não por grep) e roda `--contract` em todo `*.contract.json` de `governance/design/contracts/`. Não existe campo que deixe um contrato só avisar. "Advisory primeiro" não tem como acontecer nesse diretório: contrato que entra ali já é required.

**B2 — os alvos não têm âncora, e a thread não pode pô-las.** Cada seção exige `data-contract="<id>"` no alvo. `nao_toca` desta thread = `resources/js/Pages/`.

| alvo | âncoras `data-contract` |
|---|---|
| `ComunicacaoVisual/Index.tsx` | 0 |
| `Suporte/Empresas.tsx` · `Suporte/Visao.tsx` | 0 |
| `Vestuario/Etiquetas/Index.tsx` | 0 |

A ficha `01-contratos.md` diz que o `data-contract` "entra no mesmo PR se couber"; o índice proíbe tocar `Pages/`. O índice manda (`_SESSAO-FRIA.md`).

**B3 — a copy também não bate com a tela viva.** Rodei o gate real sobre os 3 contratos-fonte desta pasta, com `fonte` corrigida (ver B4) e o `Log.tsx (novo)` tirado do alvo, a partir do scratchpad:

| contrato | seções | copy | sem âncora | copy ausente | rc |
|---|---|---|---|---|---|
| comunicacao-visual | 6 | 36 | 6 | 13 | 1 |
| suporte | 8 | 45 | 8 | 20 | 1 |
| vestuario-etiquetas | 5 | 34 | 5 | 14 | 1 |

Comando: `node scripts/contrato-de-tela.mjs --contract <scratch>/<k>.contract.json`. Exemplos de copy que a tela não tem: CV "Na fábrica agora", "Enviar PDF no WhatsApp"; Suporte "Você está operando como" e a seção `log` inteira; Vestuário "Prévia da etiqueta", "Baixar PDF". Parte é a tela atrás do protótipo, parte é decisão pendente (B5). Escrever um contrato só com a copy que já passa seria derivar o contrato da tela, que o passo anti-tautologia do mesmo job existe para barrar.

**B4 — os contratos-fonte apontam para `fonte` que não existe.** Os 3 dizem `prototipo-ui/cowork/modulos-faltantes/<x>-page.jsx`. Os arquivos versionados estão em `prototipo-ui/cowork/Wagner/` (e cópias em `Felipe/`): `comunicacao-visual-page.jsx`, `suporte-page.jsx`, `vestuario-page.jsx`. O `--map --check` do job reprova `fonte` inexistente. O `suporte` ainda lista `Suporte/Log.tsx (novo)` no `alvo`, que é a thread 02.

**B5 — duas seções do Vestuário dependem de decisão [W].** `previa` (com-prévia × sem-prévia) é a VEST-D2 e `aviso-permissao` (warning × hard-block) é a VEST-D1. Pinar a copy de qualquer um dos estados no contrato escolhe o lado. Não decidi; essas duas seções só entram no contrato depois das respostas.

## 3 · Pedido literal ao [CL]/Cowork (colável)

```
modulos-faltantes, thread 01: decidir antes de reabrir.
1. Corrigir a "fonte" dos 3 contratos-fonte para prototipo-ui/cowork/Wagner/<x>-page.jsx
   e tirar Suporte/Log.tsx do alvo do suporte (a seção "log" vai junto com a thread 02).
2. Escolher um caminho para as âncoras:
   a) a thread 01 passa a poder escrever data-contract nas Pages
      (tirar resources/js/Pages/ComunicacaoVisual, Suporte/{Empresas,Visao} e
      Vestuario/Etiquetas do nao_toca, 1 PR por tela), com contrato e âncoras no
      mesmo PR; ou
   b) as âncoras entram na thread que já for mexer em cada tela, e a 01 só escreve
      o contrato depois disso.
3. A copy que a tela não tem (13 CV · 20 Suporte · 14 Vestuário) é decisão de produto:
   construir na tela ou podar do contrato. O agente não escolhe.
4. Vestuário: "previa" e "aviso-permissao" esperam VEST-D2 e VEST-D1.
```

## 4 · Descobertas que mudam outra sessão

- Mesmo bloqueio da thread 01 de telas-soltas (#8809): ali `Suporte/Empresas` e `Suporte/Visao` também foram medidos com 0 âncoras. As duas threads pedem âncoras nas mesmas Pages do Suporte; quem desbloquear uma deve desbloquear as duas de uma vez.
- Thread 02 (Suporte/Log, sessão em curso): se o `Log.tsx` nascer com `data-contract="log"` e a copy da seção `log` do contrato-fonte, o contrato do Suporte ganha a primeira seção que passa.

## 5 · Prefixo tocado

Só `prototipo-ui/cowork/Wagner/cowork-inbox/modulos-faltantes/playbook/_saida-01.md`. Nada em `governance/design/contracts/`, nada em `resources/js/Pages/`. `00-INDICE.md` não editado.
