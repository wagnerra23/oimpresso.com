{{-- Design system compartilhado do modulo Officeimpresso --}}
<style>
    /* ======== OFFICEIMPRESSO DESIGN SYSTEM ======== */
    .oi-page { padding: 0 20px 40px; }
    .oi-page-header { margin: 16px 0 20px; }
    .oi-page-header h1 { font-size: 22px; font-weight: 600; color: #111827; margin: 0; }
    .oi-page-header .subtitle { color: #6b7280; font-size: 13px; margin-top: 4px; }

    /* ======== CARD BASE ======== */
    .oi-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); margin-bottom: 16px; }
    .oi-card > .hdr { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
    .oi-card > .hdr h3 { margin: 0; font-size: 15px; font-weight: 600; color: #111827; }
    .oi-card > .body { padding: 16px; }
    .oi-card > .body.no-pad { padding: 0; }

    /* ======== KPI ======== */
    .oi-kpi { display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.04); height: 100%; }
    .oi-kpi .icon { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 8px; color: #fff; font-size: 20px; flex-shrink: 0; }
    .oi-kpi .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; }
    .oi-kpi .value { font-size: 24px; font-weight: 600; color: #111827; line-height: 1.1; }
    .oi-kpi .delta { font-size: 11px; color: #9ca3af; margin-top: 2px; }
    .oi-kpi .bg-green  { background: #10b981; }
    .oi-kpi .bg-red    { background: #ef4444; }
    .oi-kpi .bg-blue   { background: #3b82f6; }
    .oi-kpi .bg-amber  { background: #f59e0b; }
    .oi-kpi .bg-purple { background: #8b5cf6; }
    .oi-kpi .bg-gray   { background: #6b7280; }

    /* ======== EVENT BADGE (licenca_log) ======== */
    .event-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
    .event-login_success, .event-token_refresh, .event-unblock { background: #d1fae5; color: #065f46; }
    .event-login_error { background: #fee2e2; color: #991b1b; }
    .event-login_attempt, .event-api_call, .event-heartbeat { background: #dbeafe; color: #1e40af; }
    .event-block { background: #fef3c7; color: #92400e; }
    .event-create_licenca, .event-update_licenca, .event-businessupdate { background: #ede9fe; color: #5b21b6; }
    .event-desktop_audit { background: #f3f4f6; color: #374151; }

    /* ======== STATUS PILL ======== */
    .oi-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .oi-pill-ok { background: #d1fae5; color: #065f46; }
    .oi-pill-blocked { background: #fee2e2; color: #991b1b; }
    .oi-pill-warn { background: #fef3c7; color: #92400e; }
    .oi-pill-neutral { background: #f3f4f6; color: #374151; }

    /* ======== TABELA ======== */
    .oi-table { width: 100%; }
    .oi-table th { background: #f9fafb; color: #374151; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
    .oi-table td { padding: 10px 12px; vertical-align: middle; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
    .oi-table tr:hover td { background: #f9fafb; }
    .oi-table .text-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }

    /* ======== FILTER BAR ======== */
    .oi-filter-bar { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .oi-filter-bar label { font-weight: 500; font-size: 13px; color: #374151; margin-bottom: 4px; display: block; }

    /* ======== SOURCE TAG ======== */
    .source-tag { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.3px; }

    /* ======== COMPANY CARD (computadores) ======== */
    .oi-company { text-align: center; padding: 20px; }
    .oi-company h2 { font-size: 22px; font-weight: 600; color: #111827; margin: 0 0 16px; }
    .oi-company p { margin: 6px 0; color: #374151; font-size: 14px; }
    .oi-company p i { color: #6b7280; margin-right: 6px; width: 18px; }
    .oi-company .actions { margin-top: 18px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

    .oi-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; border: 1px solid transparent; cursor: pointer; text-decoration: none; transition: all 0.15s; }
    .oi-btn-primary { background: #3b82f6; color: #fff; }
    .oi-btn-primary:hover { background: #2563eb; color: #fff; }
    .oi-btn-success { background: #10b981; color: #fff; }
    .oi-btn-success:hover { background: #059669; color: #fff; }
    .oi-btn-danger { background: #ef4444; color: #fff; }
    .oi-btn-danger:hover { background: #dc2626; color: #fff; }
    .oi-btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
    .oi-btn-ghost:hover { background: #f9fafb; color: #111827; }
    .oi-btn-xs { padding: 3px 10px; font-size: 11px; }
</style>
