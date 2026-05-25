{{-- Consent banner LGPD (ADR 0191) — versão Blade legacy. Equivalente vanilla
     do ConsentBanner.tsx pra layouts sem React. Sem dialog "Personalizar"
     (fluxo curto: aceitar / só essenciais). --}}
@php
    $consentCookie = config('services.consent.cookie_name', 'oimpresso_consent_v1');
    $needsConsentBanner = ! request()->cookie($consentCookie);
@endphp

@if ($needsConsentBanner)
<div id="oi-consent-banner" role="region" aria-label="Aviso de cookies"
    style="position:fixed;bottom:0;left:0;right:0;z-index:9998;background:#fff;border-top:1px solid #e4e4e7;box-shadow:0 -2px 8px rgba(0,0,0,0.04);padding:12px 16px;padding-bottom:calc(12px + env(safe-area-inset-bottom, 0px));font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
    <div style="max-width:1280px;margin:0 auto;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
        <p style="margin:0;font-size:14px;color:#3f3f46;line-height:1.5;max-width:768px;flex:1 1 280px">
            Usamos cookies pra entender como você usa o oimpresso e melhorar a experiência.
            Dados sensíveis (CPF, email, telefone) <strong>não são capturados</strong>.
        </p>
        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
            <button type="button" id="oi-consent-essential"
                style="background:transparent;border:none;color:#52525b;font-size:14px;padding:6px 12px;cursor:pointer;border-radius:6px">Só essenciais</button>
            <button type="button" id="oi-consent-accept-all"
                style="background:oklch(0.55 0.15 295);border:1px solid oklch(0.45 0.15 295);color:#fff;font-size:14px;font-weight:500;padding:6px 16px;cursor:pointer;border-radius:6px">Aceitar tudo</button>
        </div>
    </div>
</div>
<script>
(function () {
    var banner = document.getElementById('oi-consent-banner');
    if (!banner) return;
    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : '';
    function send(analytics, marketing) {
        return fetch('/api/consent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ analytics: analytics, marketing: marketing })
        }).then(function (res) { if (res.ok) banner.style.display = 'none'; })
          .catch(function () { /* silencioso — user pode tentar de novo */ });
    }
    document.getElementById('oi-consent-accept-all').addEventListener('click', function () { send(true, true); });
    document.getElementById('oi-consent-essential').addEventListener('click', function () { send(false, false); });
})();
</script>
@endif
