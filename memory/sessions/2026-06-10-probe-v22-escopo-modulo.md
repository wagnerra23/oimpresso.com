# Sessão 2026-06-10 — Probe v2.2 + escopo por módulo + Método 9.75 na régua

## Pedido
[W]: (1) "Financeiro já entrou para o novo padrão?" (2) "na evolução está sendo considerado o método 9.75? ele faz milagres" (3) "Como garantir que o escopo do módulo e tela não vão vazar? e ficar dentro do permitido? Já existem no projeto os arquivos?" + diretrizes: "deve ter sim o css comum para não duplicar" · "com nome correto e autorizado".

## O que foi feito
1. **Financeiro vs novo padrão — medido, não inferido.** Não entrou: nota 8,3 (<9), onda W2, probe nunca rodado. Verificador #1 rodou `QAConformance.run()` nas 3 rotas fin → achou G2 🔴 (5 controles) + bug de escopo ("os-page" nas 3 rotas).
2. **Triagem do 🔴: era do TESTE, não da tela.** Os inputs fin são proxy custom (`opacity:0` + `.fin-filter-cb-box`, padrão aprovado) — falso-positivo do G2.
3. **qa-conformance.js v2.1→v2.2:**
   - G2 `nativePainted()` — só conta input pintado pelo browser (proxy custom/appearance:none = ok);
   - `rootKey()` prefere classe `*-root` → rotas fin reportam `fin-root` (não `os-page`);
   - N2 reescrito (injeta checkbox real visível — antes ficava cego em telas 100% proxy);
   - `G5_BASELINE["fin-root"]=10` (medido pelo verificador: unified 10 · pcontas 5 · fluxo 6; maioria = chrome do shell);
   - **G7 novo — escopo por módulo**: censo de prefixo dominante por stylesheet; regra com prefixo dominante de OUTRO módulo aplicando no DOM = 🔴. Camada comum (styles.css + ds-v6/ + prefixos os/sb/qa/mockup) = legítima/única. Controle-negativo N7 (insertRule de vazamento fake → gate fica 🔴 → deleteRule).
4. **Vazamento real consertado:** ~100 linhas `.fin-*` (filter-cb/density/clear/bcrumb, linhas 1476–1577) moravam em **vendas.css**, usadas só por financeiro-page.jsx/financeiro-telas-extras.jsx. Movidas (não duplicadas) → financeiro.css com lápide em vendas.css. Pós: vendas.css 0 seletores fin (medido); financeiro.css contém os blocos.
5. **Método KB-9.75 explícito na régua das ondas** (`Reestruturacao - Identidade Unica e Qualidade 9.html` §4): conformidade (probe+identidade+estados) = piso anti-regressão; tela <9 entra no ciclo completo (7 etapas Bench→…→Bench v2 + 22 features-tipo). Resposta honesta a [W]: só a Etapa 1 (bench 15-dim) estava na régua; agora o ciclo inteiro está.

## Decisões ([W] nesta sessão)
- **Modelo de escopo CSS ratificado:** camada comum EXISTE e é única ("deve ter sim o css comum para não duplicar") — styles.css + ds-v6/, nomes autorizados (`os-*`/tokens; nome novo só com OK de [W]); CSS de módulo = prefixo próprio no próprio arquivo; regra de módulo em arquivo alheio = vazamento (G7 cobra por máquina).
- Método 9.75 = perna obrigatória da onda pra tela <9 (registrado na régua §4).

## Erros + correção
- G2 v2 flagrava proxy custom (especificidade) → consertado COM controle-negativo novo (Regra 5: N2 agora exercita de verdade).
- rootKey pegava 1ª classe ("os-page") → consertado pra `*-root`.
- (meu, no turno) log de linhas com `split('\\n')` literal — sem efeito em arquivo, só log.

## Prova
- Verificador #2 (v2.1: escopo fin-root, G2, negative, drawer G4, dark G6) + #3 (v2.2: visual pills fin pós-mudança, G7 pass, N7 discrimina, Vendas intacta) → **verdict: done / passed**.

## Residual
- **Financeiro: perna conformidade ok (0🔴) · perna MÉTODO pendente = onda W2** (ciclo 9.75: pilar fiscal 5,5 + US-FIN-029 3 lentes). Compras junto na W2.
- Calibrar G5_BASELINE das demais rotas (boletos, crm, clientes, inbox, prod…) nas suas ondas.
- G7 é runtime/Cowork — versão git (gate estático nos `resources/css/*`) = proposta futura via COWORK_NOTES (estender conformance-gate, não reinventar — Regra 7).

## Refs
- `qa-conformance.js` (v2.2) · `vendas.css` (lápide L~1476) · `financeiro.css` (bloco movido no fim) · `Reestruturacao - Identidade Unica e Qualidade 9.html` §4

## Próximo passo
Onda W2 (Financeiro+Compras): ciclo Método 9.75 no Financeiro — bench honesto por pilar → fechar features-tipo → bench v2; alvo ≥9.
