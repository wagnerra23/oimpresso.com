---
sessao: "05"
titulo: Ghosts na sidebar × ADR 0180 — o código foi na frente da lei
dono: "[W]" (despacho) → "[CL]" (emenda)
base: af09f7c3a0fd
prefixo: memory/decisions/0180-*.md (emenda datada — nunca ADR paralela, LC-19)
nao_toca: Components/cockpit/Sidebar.tsx · prototipo-ui/cowork/sidebar.jsx — NENHUMA linha de código antes do despacho
estado: BLOQUEADA em [W] (RESÍDUO-1)
---
# 05 · Ghosts × ADR 0180

## A · O fato (medido, não lembrado)
O pedido de 2026-08-28 registrou um conflito de canons e mandou **não portar sem despacho**:
- protótipo: até 5 ghosts sob o item + "⋯ mais N", promovendo a rota ativa (`sidebar.jsx:177-180`);
- ADR 0180 / AP19: ghost **não** vive na sidebar — vira tab na Zona C do PageHeader.

Treze dias depois, lendo o `main` em `af09f7c3a0fd`: `Components/cockpit/Sidebar.tsx:542-544` declara
`const GHOST_TETO = 5;` com o comentário *"Teto de ghosts exibidos sob o item ativo — espelha `GHOST_TETO` do protótipo"*, e `:569` fatia a lista (`ghostsAbertos ? ghosts : ghosts.slice(0, GHOST_TETO)`).

**O conflito não sumiu — mudou de lado.** Antes era "o protótipo contraria a ADR". Agora é **o vivo** que contraria a ADR, e a divergência está em produção.

## B · A decisão de [W] (RESÍDUO-1) — duas saídas, uma escolha
1. **Emendar a ADR 0180** — o código venceu: ghost na sidebar é canon, com teto 5 + promoção da rota ativa; a Zona C do PageHeader passa a ser complemento, não substituto. Custo: rever AP19 e as telas que o citam.
2. **Reverter o vivo** — a ADR manda: tirar `GHOST_TETO`/`GhostList` do `Sidebar.tsx` e do protótipo, e ghost só como tab. Custo: perder a navegação profunda que Vendas (18 telas) e Produtos (12) ganharam.

Não há terceira via silenciosa: manter os dois textos como estão é deixar a lei mentindo sobre o produto — exatamente o que a `memory/` existe pra impedir.

## C · Quando despachado
- A emenda vai **dentro** da 0180, datada (`## Emenda 2026-09-…`), com o motivo e o número do PR que introduziu `GHOST_TETO`. **Nunca** uma ADR nova paralela (LC-19).
- Se a saída for reverter: PR separado, com o `SidebarMenuItemContractTest` atualizado junto (o campo `ghosts[]` do contrato v2 continua existindo no backend — `app/Sidebar/SidebarGhost.php` — mesmo que a UI pare de desenhá-lo; **decidir explicitamente** se o backend também muda ou se o campo fica sem consumidor).
- Antes de escrever a emenda: **reler a ADR 0180 inteira nesta sha** — este playbook cita AP19 pela leitura de 28/08, não relida hoje.

## Prova
- `memory/decisions/0180-*.md` com seção de emenda datada `2026-09` **ou** PR de reversão mergeado com o teste de contrato atualizado.
- `_saida-05.md` com: qual saída [W] escolheu, o que mudou na ADR, e o que ficou sem consumidor (se algo ficou).
