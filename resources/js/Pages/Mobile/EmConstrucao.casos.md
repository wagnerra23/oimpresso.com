---
casos: Mobile/EmConstrucao — abas ainda sem tela (/m/tarefas · /m/pedidos · /m/producao)
irmaos: EmConstrucao.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Mobile/EmConstrucao

> **Fonte dos UC:** handoff design-v3 README §"Navegação" (tab bar de 5 abas; nunca deixar o
> usuário preso). **Não** o `.tsx`.
> Teste: `tests/Feature/Mobile/MobileShellContratoTest.php` (lane `mobile-pest`).

## UC-MOB-04 · Toco numa aba que ainda não existe e não caio em erro
- **Aceite:** Dado usuário logado · Quando abro `/m/tarefas`, `/m/pedidos` ou `/m/producao` ·
  Então recebo 200 com `Mobile/EmConstrucao` e o slug da aba; e um slug fora da lista dá 404.
- **Regressão que defende:** tab bar levando a página de erro.
- **Status: ⬜** — cita o UC; veredito vem da lane `mobile-pest`.
