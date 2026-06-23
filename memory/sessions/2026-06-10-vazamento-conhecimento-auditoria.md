# Sessão 2026-06-10 (b) — Vazamento de conhecimento: auditoria + fix do add-row

**Papel:** [CC] · **Pedido [W]:** screenshot do drawer estourado + "onde vaza o conhecimento? Protocolo? memória? eslint? lista e critérios meticulosos, nota + explicação da evolução. As regras parecem ultrapassadas, algo não encaixa."

## O que foi feito
1. **Fix do bug do screenshot:** `.ofc-dvi-add` (estado "adicionando" do DVI) estourava o drawer 480px — grid `1fr auto 1.4fr 80px auto auto` com min-content do select > largura. → `minmax(0,1fr) auto minmax(0,1.3fr) 56px auto auto` + `width:100%; min-width:0` nos inputs (idem `.ofc-items-add`). **Provado:** `scrollWidth 464 == clientWidth 464`, screenshot do estado aberto dentro do drawer.
2. **Auditoria entregue:** `Auditoria - Vazamento de Conhecimento 2026-06-10.html` — 13 critérios em 4 camadas (mora/executa/envelhece/protocolo), nota 0-10 + evolução + furo, média **5.4**.

## Diagnóstico central (3 desencaixes)
1. **Gates protegem o repo; eu erro no protótipo** — ESLint ds/* pegaria o checkbox cru no F3; no Cowork nada roda.
2. **Regras fossilizam a última guerra** — `qa-conformance.js` (probe que JÁ existe no shell, botão "▶ Conformância DS") só conhece as 4 classes do drift roxo×verde de 06-03, hardcoded `.vendas-aplus`. Os erros novos (accent-color, fg-as-fill, overflow de estado) passam nos 4 checks.
3. **Conhecimento-exemplo não transfere + verificação só vê happy path** — accent-color existia 6× no styles.css como exemplo; estado "adicionando" nunca entrou em roteiro de verificação.

**Furo central (C2, nota 2):** não existe o loop "erro novo → check novo". Lição vira prosa, nunca asserção — toda regra nasce com data de validade.

## Propostas F0 (decide [W] — nada virou lei)
- **P1** generalizar `qa-conformance.js` p/ classes genéricas + qualquer rota (estende, não cria — Regra 7).
- **P2** probe no ritual pré-done do [CC] (DS-GUARD §8 vira mecânico).
- **P3** matriz de estados obrigatória no verificador (vazio/cheio/editando/dark/overflow).
- **P4** `licao_sem_assercao` no memory-health — toda lição nova aponta a asserção que passa a pegá-la.
- **P5** pro git: papel de token no conformance-gate (`--*-fg` em background = 🔴).

## Erros + correção
- Nenhum novo nesta sessão; o quase-erro evitado foi propor probe novo sem ver que `qa-conformance.js` existia (Regra 7 — grep antes de propor pegou).

## Residual
- [W] decide quais P1–P5 disparam. P1+P2 são [CC]-only (sem ponte); P5 vai pro Code via COWORK_NOTES se aprovado.
- F2 do drawer (acabamentos + add-row) segue pendente de olhada do [W].

## Refs
`Auditoria - Vazamento de Conhecimento 2026-06-10.html` · `qa-conformance.js` · `METODO_TELA_ANTI-REGRESSAO.md` · sessão (a) de hoje · auditoria 06-08 (Lei de Uma Tela)
