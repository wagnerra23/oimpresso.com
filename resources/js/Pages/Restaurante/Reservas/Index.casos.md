---
id: resources-js-pages-restaurante-reservas-index-casos
casos: Reservas · /bookings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a reserva de outro negócio na tela é incidente Tier 0; ampliar em silêncio quem vê as reservas muda a regra de acesso do legado.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Reservas

> Thread `restaurante/playbook/03` (Reservas). Derivados do `BookingController`, do `RestaurantUtil::getBookingsForCalendar`
> e da Blade `restaurant.booking.index` (RUNBOOK-reservas) — não do `Index.tsx`.
> Teste: [`tests/Feature/Restaurante/ReservasContratoTest.php`](../../../../../tests/Feature/Restaurante/ReservasContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `ReservasBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-RESV-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem recebe ligação de cliente e confere as reservas do salão.
- **Aceite:** Dado `crud_all_bookings` e a flag `useV2RestauranteReservas` ligada · Quando faço `GET /bookings` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Restaurante/Reservas/Index`**.
- **Status: 🧪**

## UC-RESV-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /bookings` · Então a resposta é a Blade
  `restaurant.booking.index` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-RESV-03 · Só as reservas do meu negócio, com a situação · `[T0]`
- **Aceite:** Dado uma reserva minha `waiting` amanhã com mesa e atendente, uma de outro negócio e uma minha do mês
  passado · Quando a lista carrega (prop deferida `reservas`) · Então vejo a minha com cliente, mesa, atendente e
  situação `waiting` · E não vejo a alheia nem a do mês passado.
- **Status: 🧪**

## UC-RESV-04 · Com só `crud_own_bookings`, só as que eu criei · `[T0]`
- **Aceite:** Dado uma reserva que eu criei e outra que um colega criou comigo de atendente · Quando a lista carrega ·
  Então vejo a minha e não a do colega — a mesma regra do calendário da Blade (`ReservasBaselineTest`).
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Criar, ver, mudar situação e excluir no drawer do protótipo.
- [BACKLOG] Unificar a regra de quem vê (calendário × reservas de hoje) — decisão [W].
- [BACKLOG] Calendário, se [W] quiser de volta.

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `restaurante/playbook/03`.
