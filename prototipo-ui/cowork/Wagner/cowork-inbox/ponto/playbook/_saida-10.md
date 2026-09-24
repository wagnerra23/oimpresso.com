---
thread: "10 · REP-P do protótipo sem selfie (ADR 0383)"
dono: "[CC]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: ponto-mobile.jsx (2 comentários) · oimpresso.com.html (?v=pt20btn-b)
---
# _saida-10 · REP-P sem selfie

## Estado encontrado
A biometria **já tinha saído do código** em ciclo anterior (estado, botão, coluna "Selfie (hash)" da fila e `LIMITES.selfie_min_kb`) — o `github.md` registra, e a bateria `31-bateria-comportamento.md` B1/B2 ✅. Mas as **duas provas desta thread falhavam por texto em comentário**:
- `ponto-mobile.jsx:6` — comentário citava "Art. 9º" (para dizer que a citação era errada).
- `ponto-mobile.jsx:21` — comentário `selfie_min_kb REMOVIDO — era o SELFIE_MIN_BYTES…`.

## O que mudou
Os 2 comentários foram reescritos sem os termos. Nenhuma linha de código mudou.
- `nao_contem "selfie"` em `ponto-mobile.jsx` → **ausente** (grep case-insensitive: 0)
- `nao_contem "Art. 9"` em `ponto-mobile.jsx` → **ausente**
- `ponto-data.jsx`: 0 ocorrências de `selfie`/`Art. 9`/`foto` — não tocado.

## Conferido no fonte (itens 2–4 da thread)
- GPS: `LIMITES.accuracy_max: 500`, `drift_max: 30`; geofence **não bloqueia** — marca "fora da área — vai para revisão do RH" e entra na fila.
- Fila do gestor: sem coluna de biometria; NSR + device + Validar/Recusar (recusar = anulação append-only).
- Copy legal: "Marcação imutável (Portaria MTP 671/2021)"; base LGPD **Art. 5º II + Art. 11** no cabeçalho.

## Não medido
T1 (nós antes/depois) não rodado nesta sessão — mudança só em comentário, render idêntico por construção. A1–A12 fica para quando a 06 virar pedido.

## Fora do meu prefixo — achado para o Code
`resources/js/Pages/Ponto/RepP.charter.md` (:29, :35, :37, :54) e `RepP.casos.md` (UC-REPP-01, UC-REPP-04) **no `main` ainda pedem selfie obrigatória** e "selfie abaixo de 100KB". Contradizem a ADR 0383. Não é desta thread (`nao_toca: resources/js/Pages/**`); é insumo da **06** ou de um pedido de 2 arquivos.
