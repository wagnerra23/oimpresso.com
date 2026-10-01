---
casos: Mobile/Mais — hub da aba Mais (/m/mais)
irmaos: Mais.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Mobile/Mais

> **Fonte dos UC:** handoff design-v3 README §7 (Mais — hub) + decisão [W] 2026-10-01. **Não** o `.tsx`.
> Teste: `tests/Feature/Mobile/MobileShellContratoTest.php` (lane `mobile-pest`).

## UC-MOB-03 · Na aba Mais vejo quem está logado e em qual empresa
- **Aceite:** Dado usuário do tenant 98 · Quando abro `/m/mais` · Então a tela é `Mobile/Mais`
  com o meu nome e o nome do business 98.
- **Status: ⬜** — cita o UC; veredito vem da lane `mobile-pest`.
