---
casos: Mobile/Inicio — raiz do app das lojas (/m)
irmaos: Inicio.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Mobile/Inicio

> **Fonte dos UC:** decisão [W] 2026-10-01 (app abre o protótipo Mobile) + handoff design-v3
> README §"Interações" e §15 Login + ADR 0093. **Não** o `.tsx`.
> Teste: `tests/Feature/Mobile/MobileShellContratoTest.php` (lane `mobile-pest`).

## UC-MOB-01 · Abro o app sem sessão e caio no login, que me devolve ao app
- **Persona:** vendedor/operador abrindo o app pela primeira vez.
- **Aceite:** Dado que não estou logado · Quando abro `/m` · Então sou levado ao `/login` e o
  destino guardado é `/m`, para voltar ao app depois de entrar.
- **Regressão que defende:** app abrindo direto no dashboard desktop depois do login.
- **Status: ⬜** — cita o UC; veredito vem da lane `mobile-pest`.

## UC-MOB-02 · Logado, vejo o Início com o meu nome e a MINHA empresa
- **Aceite:** Dado usuário do tenant 98 · Quando abro `/m` · Então a tela é `Mobile/Inicio` com
  o meu nome e o nome do business 98 — nunca o de outro business.
- **Regressão que defende:** vazamento de identidade entre tenants (Tier 0).
- **Status: ⬜** — cita o UC; veredito vem da lane `mobile-pest`.
