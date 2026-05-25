{{--
    Microsoft Clarity — session replay + heatmaps (ADR 0191).

    Guards (TODOS devem passar pro snippet carregar):
      1. CLARITY_ENABLED=true em .env (default false — Wagner ativa manual)
      2. CLARITY_PROJECT_ID setado em .env
      3. usuário autenticado
      4. user_type NÃO é superadmin nem user_oimpresso (Wagner não polui dataset)
      5. cookie consent LGPD presente com analytics=true (opt-in obrigatório)

    Multi-tenant Tier 0 (ADR 0093): business_id vem de auth()->user()->business_id
    server-side via Blade. Custom tag no JS pro dashboard filtrar por cliente.

    NÃO chamar clarity('identify', user_id) — ADR 0191 §pegadinha 2 (re-identificação
    cruzando user_id + IP). Manter sessões pseudoanônimas, filtrar via custom tags.

    Carregar nos layouts root (inertia.blade.php + app.blade.php) via @include.
    NÃO incluir em layouts públicos (auth2, home) — guard sempre retornaria false
    anyway, mas economiza bytes.
--}}
@php
    $clarityEnabled = config('services.clarity.enabled')
        && config('services.clarity.project_id')
        && auth()->check()
        && ! in_array(auth()->user()->user_type ?? null, ['superadmin', 'user_oimpresso'], true);

    if ($clarityEnabled) {
        $consentRaw = request()->cookie(config('services.consent.cookie_name', 'oimpresso_consent_v1'));
        $consentDecoded = $consentRaw ? json_decode((string) $consentRaw, true) : null;
        $clarityEnabled = is_array($consentDecoded) && ! empty($consentDecoded['analytics']);
    }
@endphp

@if ($clarityEnabled)
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", @json(config('services.clarity.project_id')));

    @if (config('services.clarity.mask_strategy') === 'mask-all')
    clarity("set", "mask_strategy", "all");
    @endif

    clarity("set", "business_id", @json((string) auth()->user()->business_id));
    clarity("set", "user_type", @json((string) auth()->user()->user_type));
</script>
@endif
