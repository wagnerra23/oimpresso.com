---
sessao: "10"
titulo: Build — REP-P do protótipo SEM selfie (ADR 0383)
dono: "[CC]"
base: e86130722de1
prefixo: prototipo-ui/cowork/ponto-mobile.jsx · ponto-data.jsx (só o bloco mobile/REP-P) · oimpresso.com.html (bump)
nao_toca: ponto-page.jsx · ponto-telas.jsx · android-frame.jsx · resources/js/Pages/**
depende: — (vaga 1). Antecede a 06 (o alvo do REP-P tem de estar limpo antes de virar pedido) e a 09 (Lei 1 em ponto-data.jsx).
---
# 10 · REP-P sem selfie — meu build viola uma ADR aceita

## O erro
`ponto-mobile.jsx:38` — `const [selfie, setSelfie] = useState(false)` em `BaterPonto`; a tela pede selfie e o doc de 04/09 ainda carregava a pergunta "copy da selfie (LGPD Art. 9º)". A **ADR 0383** (aceita 27/08, executada #6393) decidiu: **o ponto interno não coleta, não trafega e não deriva biometria**; a citação Art. 9º estava errada (base: Art. 5º II + Art. 11). Exportar este alvo era exportar violação com selo.

## O que muda (só o protótipo)
1. Remover selfie de `BaterPonto`: estado, botão/câmera, copy, qualquer `selfie`/`Art. 9` em `ponto-mobile.jsx` e no bloco mobile de `ponto-data.jsx`.
2. Substituir pelo anti-fraude que **existe** (`MobileMarcacaoService`): GPS accuracy ≤ 500 m (**recusa** com "Sinal de GPS fraco — aproxime-se de área aberta"), skew ≤ 30 s, geofence fora → grava e **sinaliza** ("Fora da área — vai para revisão"). Zero "bater mesmo assim".
3. Fila do gestor (`ValidacaoMobile`): coluna de selfie sai; entra `Sinalizado (geofence)`.
4. Copy legal literal: "Marcação registrada com NSR — Portaria MTP 671/2021" · LGPD **Art. 5º, II** onde a tela explicar o que **não** coleta.
5. A1–A12 na view · T1 (991 nós em 04/09 → remedir) · bump `?v=`.

## Não inventar
Átomos de `ponto-ui.jsx` (`Card · Tabela scope=col · Vazio · Pill · Nota`) · tokens do DS · sem emoji · sem cor crua.

## Prova (PLACAR confere)
- `prototipo-ui/cowork/ponto-mobile.jsx` **não** contém `selfie` nem `Art. 9`
- `_saida-10.md` com nós antes/depois e a lista do que saiu
