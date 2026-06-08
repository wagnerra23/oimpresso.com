# Relatório — CSS morto em `resources/css/sells-cowork.css`

> Gerado por `node scripts/sells-cowork-dead-css.mjs --report <este-arquivo>`. NÃO editar à mão.
> Determinístico (sem browser/dependência). Reproduzível — re-rode pra atualizar.

## Resumo

- Regras top-level analisadas: **2025**
- Regras **mortas** (toda classe do seletor ausente no JS/TSX): **933** (~**4090** linhas)
- Critério: token-inteiro no `resources/js`; pula @media/@keyframes/@container + famílias de impressão (vd-trans/vd-pres) + classes de estado.

## Famílias mortas (prefixo → nº de regras)

| Família | Regras mortas |
|---|---|
| `vd-drawer` | 97 |
| `os-new` | 73 |
| `os-drawer` | 23 |
| `rec-a4` | 20 |
| `os-art` | 19 |
| `os-decision` | 16 |
| `os-bulk` | 13 |
| `prod-col` | 13 |
| `vd-step` | 13 |
| `rec-tx` | 13 |
| `prod-card` | 12 |
| `cli-os` | 12 |
| `prod-drawer` | 11 |
| `os-cell` | 10 |
| `os-version` | 10 |
| `cli-kpi` | 10 |
| `vd-art` | 10 |
| `os-step` | 9 |
| `os-modal` | 9 |
| `vc-move` | 9 |
| `os-tl` | 8 |
| `vd-ai` | 8 |
| `vw-pnt` | 7 |
| `os-approve` | 7 |
| `vc-counter` | 7 |
| `vw-card` | 6 |
| `vw-art` | 6 |
| `vw-contact` | 6 |
| `vw-btn` | 6 |
| `os-stage` | 6 |
| `prod-kpi` | 6 |
| `prod-equip` | 6 |
| `cli-avatar` | 6 |
| `vd-client` | 6 |
| `vd-foot` | 6 |
| `vd-pay` | 6 |
| `topnav-pill` | 6 |
| `os-empty` | 5 |
| `cli-empty` | 5 |
| `cli-contact` | 5 |
| `vd-prod` | 5 |
| `vd-confirm` | 5 |
| `vrep-cli` | 5 |
| `vrep-donut` | 5 |
| `nfe-cfop` | 5 |
| `nfe-review` | 5 |
| `sb-routine` | 4 |
| `vw-dl` | 4 |
| `vw-hist` | 4 |
| `vw-radio` | 4 |
| `cli-section` | 4 |
| `prod-header` | 4 |
| `prod-view` | 4 |
| `prod-act` | 4 |
| `cli-fin` | 4 |
| `vd-items` | 4 |
| `vco-card` | 4 |
| `vco-numbers` | 4 |
| `vrep-summary` | 4 |
| `vrep-bar` | 4 |
| `pdv-items` | 4 |
| `pdv-pay` | 4 |
| `pdv-total` | 4 |
| `nfe-tax` | 4 |
| `sb-tab` | 3 |
| `vw` | 3 |
| `vw-chip` | 3 |
| `vw-money` | 3 |
| `linked-body` | 3 |
| `lpunch-row` | 3 |
| `os-cat` | 3 |
| `cli-name` | 3 |
| `cli-status` | 3 |
| `cli-row` | 3 |
| `orc-prob` | 3 |
| `prod-bom` | 3 |
| `prod-exp` | 3 |
| `cli-head` | 3 |
| `cli-info` | 3 |
| `vd-section` | 3 |
| `vd-meta` | 3 |
| `vd-total` | 3 |
| `vd-os` | 3 |
| `vd-search` | 3 |
| `vd-fields` | 3 |
| `vd-totals` | 3 |
| `vm-subtab` | 3 |
| `vm-pdv` | 3 |
| `vdv-tl` | 3 |
| `vco-period` | 3 |
| `vco-rules` | 3 |
| `vrep-tabs` | 3 |
| `vrep-origin` | 3 |
| `pdv-head` | 3 |
| `pdv-scan` | 3 |
| `pdv-suggest` | 3 |
| `pdv-qty` | 3 |
| `pdv-finalize` | 3 |
| `rec-layout` | 3 |
| `embed-btn` | 3 |
| `vd-src` | 3 |
| `mini-input` | 2 |
| `vw-textarea` | 2 |
| `vw-due` | 2 |
| `composer-inner` | 2 |
| `linked-h` | 2 |
| `linked-empty` | 2 |
| `os-select` | 2 |
| `os-urgent` | 2 |
| `os-resp` | 2 |
| `os-foot` | 2 |
| `os-timeline` | 2 |
| `os-chip` | 2 |
| `cli-kpis` | 2 |
| `prod-toggle` | 2 |
| `prod-price` | 2 |
| `prod-meta` | 2 |
| `prod-pop` | 2 |
| `prod-progress` | 2 |
| `prod-pieces` | 2 |
| `vd-stepper` | 2 |
| `vd-walkin` | 2 |
| `vd-empty` | 2 |
| `vd-callout` | 2 |
| `vendas-module` | 2 |
| `vm-subnav` | 2 |
| `vdv-timeline` | 2 |
| `vdv-match` | 2 |
| `vdv-reason` | 2 |
| `vco-bar` | 2 |
| `vco-chip` | 2 |
| `vco-table` | 2 |
| `vrep-card` | 2 |
| `vrep-clients` | 2 |
| `pdv-shortcut` | 2 |
| `pdv-empty` | 2 |
| `embed-view` | 2 |
| `embed-toolbar` | 2 |
| `sb-divider` | 2 |
| `sb-group` | 2 |
| `topnav-area` | 2 |
| `sb-reopen` | 2 |
| `main-body` | 1 |
| `bc-tenant` | 1 |
| `bc-up` | 1 |
| `sb-tabs` | 1 |
| `sb-chat` | 1 |
| `sb-pin` | 1 |
| `sb-menu` | 1 |
| `tk-empty` | 1 |
| `vw-grid` | 1 |
| `vw-p` | 1 |
| `vw-stage` | 1 |
| `vw-thread` | 1 |
| `mini-msg` | 1 |
| `mini-av` | 1 |
| `mini-bub` | 1 |
| `vw-notes` | 1 |
| `vw-chips` | 1 |
| `vw-check` | 1 |
| `vw-actions` | 1 |
| `vw-spacer` | 1 |
| `lpunch` | 1 |
| `os-stages` | 1 |
| `os-kbd` | 1 |
| `cli-sub` | 1 |
| `cli-seg` | 1 |
| `cli-city` | 1 |
| `cli-num` | 1 |
| `cli-last` | 1 |
| `cli-tabs` | 1 |
| `cli-tags` | 1 |
| `cli-tag` | 1 |
| `orc-items` | 1 |
| `orc-status` | 1 |
| `orc-os` | 1 |
| `prod-grid` | 1 |
| `prod-cat` | 1 |
| `prod-inactive` | 1 |
| `prod-name` | 1 |
| `prod-id` | 1 |
| `prod-foot` | 1 |
| `prod-page` | 1 |
| `prod-kpis` | 1 |
| `prod-kanban` | 1 |
| `prod-empty` | 1 |
| `prod-seq` | 1 |
| `prod-os` | 1 |
| `prod-urgent` | 1 |
| `prod-eta` | 1 |
| `prod-deadline` | 1 |
| `prod-actions` | 1 |
| `prod-op` | 1 |
| `prod-route` | 1 |
| `prod-pct` | 1 |
| `prod-list` | 1 |
| `row-urgent` | 1 |
| `stage-pill` | 1 |
| `stage-producao` | 1 |
| `stage-acabamento` | 1 |
| `stage-expedicao` | 1 |
| `t-truncate` | 1 |
| `t-urgent` | 1 |
| `cli-doc` | 1 |
| `cli-phone` | 1 |
| `cli-drawer` | 1 |
| `cli-history` | 1 |
| `vd-docs` | 1 |
| `vd-create` | 1 |
| `vd-strong` | 1 |
| `vd-toggle` | 1 |
| `vm-body` | 1 |
| `vc-moves` | 1 |
| `vd-dev` | 1 |
| `vdv-input` | 1 |
| `vdv-matches` | 1 |
| `vdv-selected` | 1 |
| `vdv-reasons` | 1 |
| `vdv-textarea` | 1 |
| `vco-grid` | 1 |
| `vco-avatar` | 1 |
| `vco-rank` | 1 |
| `vco-com` | 1 |
| `vco-mix` | 1 |
| `vrep-bars` | 1 |
| `vrep-rank` | 1 |
| `vrep-dot` | 1 |
| `pdv-overlay` | 1 |
| `pdv-brand` | 1 |
| `pdv-sep` | 1 |
| `pdv-close` | 1 |
| `pdv-grid` | 1 |
| `pdv-left` | 1 |
| `pdv-prod` | 1 |
| `pdv-num` | 1 |
| `pdv-rm` | 1 |
| `pdv-right` | 1 |
| `pdv-r` | 1 |
| `pdv-client` | 1 |
| `pdv-cancel` | 1 |
| `pdv-recibo` | 1 |
| `rec-stage` | 1 |
| `rec-paper` | 1 |
| `rec-termica` | 1 |
| `nfe-transp` | 1 |
| `nfe-callout` | 1 |
| `os-kpi` | 1 |
| `os-tabs` | 1 |
| `embed-label` | 1 |
| `embed-src` | 1 |
| `embed-iframe` | 1 |
| `topnav-pills` | 1 |
| `sb-rail` | 1 |
| `sb-dd` | 1 |

## Como remover (seguro)

1. **Incremental, 1 família/PR** — começar pela família não-portada maior (OS detail-drawer: `os-drawer`/`os-new`/`os-art`/`os-decision`).
2. **Visual-regression como rede** — className 100% dinâmico (`'os-'+x`) e sub-view rara não são detectáveis estaticamente; o gate visual pega regressão. `/sells` é a tela do cliente-piloto.
3. **Re-baseline do conformance-gate** após cada remoção (a cor-crua cai → teto desce).

## Regras mortas (seletor · linhas)

<details><summary>Listar as 933 regras</summary>

- L108-112 · `*/ .sells-cowork .main-body-noop`
- L205-206 · `.sells-cowork .bc-tenant`
- L207-208 · `.sells-cowork .bc-up`
- L290-297 · `/* Tabs Chat/Menu */ .sells-cowork .sb-tabs`
- L297-314 · `.sells-cowork .sb-tab`
- L314-315 · `.sells-cowork .sb-tab:hover`
- L315-319 · `.sells-cowork .sb-tab.active`
- L333-335 · `/* ── Aba CHAT ── */ .sells-cowork .sb-chat`
- L380-389 · `.sells-cowork .sb-pin-empty`
- L412-421 · `.sells-cowork .sb-routine`
- L421-422 · `.sells-cowork .sb-routine:hover`
- L422-426 · `.sells-cowork .sb-routine-t`
- L426-431 · `.sells-cowork .sb-routine-f`
- L431-433 · `/* ── Aba MENU ── */ .sells-cowork .sb-menu`
- L856-862 · `.sells-cowork .tk-empty-old`
- L928-935 · `/* ────────────────── Viewers ────────────────── */ .sells-cowork .vw`
- L935-936 · `.sells-cowork .vw::-webkit-scrollbar`
- L936-940 · `.sells-cowork .vw::-webkit-scrollbar-thumb`
- L940-946 · `.sells-cowork .vw-grid`
- L946-953 · `.sells-cowork .vw-card`
- L953-957 · `.sells-cowork .vw-card.hi`
- L957-960 · `.sells-cowork [data-theme="dark"] .vw-card.hi`
- L960-968 · `.sells-cowork .vw-card h3`
- L968-969 · `.sells-cowork .vw-card h4`
- L969-974 · `.sells-cowork .vw-card .vw-sub`
- L974-975 · `.sells-cowork .vw-p`
- L975-976 · `.sells-cowork .vw-dl`
- L976-977 · `.sells-cowork .vw-dl dt`
- L977-978 · `.sells-cowork .vw-dl dd`
- L979-980 · `.sells-cowork .vw-dl dd.urgent`
- L980-989 · `.sells-cowork .vw-stage`
- L989-995 · `.sells-cowork .vw-art`
- L995-1004 · `.sells-cowork .vw-art-thumb`
- L1004-1005 · `.sells-cowork .vw-art-meta`
- L1005-1006 · `.sells-cowork .vw-art-meta b`
- L1006-1007 · `.sells-cowork .vw-art-meta small`
- L1007-1008 · `.sells-cowork .vw-art-actions`
- L1008-1009 · `.sells-cowork .vw-hist`
- L1009-1014 · `.sells-cowork .vw-hist li`
- L1014-1015 · `.sells-cowork .vw-hist li:last-child`
- L1015-1016 · `.sells-cowork .vw-hist .t`
- L1016-1017 · `.sells-cowork .vw-thread-mini`
- L1017-1018 · `.sells-cowork .mini-msg`
- L1019-1026 · `.sells-cowork .mini-av`
- L1026-1034 · `.sells-cowork .mini-bub`
- L1040-1054 · `.sells-cowork .mini-input`
- L1054-1055 · `.sells-cowork .mini-input:focus`
- L1055-1057 · `/* CRM */ .sells-cowork .vw-contact`
- L1057-1064 · `.sells-cowork .vw-contact-av`
- L1064-1065 · `.sells-cowork .vw-contact b`
- L1065-1066 · `.sells-cowork .vw-contact small`
- L1066-1074 · `.sells-cowork .vw-contact-row`
- L1075-1076 · `.sells-cowork .vw-contact-row a`
- L1076-1077 · `.sells-cowork .vw-notes`
- L1077-1079 · `/* Radio/Chips/Textarea reutilizáveis */ .sells-cowork .vw-radio-group`
- L1079-1087 · `.sells-cowork .vw-radio`
- L1087-1088 · `.sells-cowork .vw-radio:hover`
- L1088-1089 · `.sells-cowork .vw-radio input`
- L1089-1090 · `.sells-cowork .vw-chips`
- L1090-1101 · `.sells-cowork .vw-chip`
- L1101-1102 · `.sells-cowork .vw-chip:hover`
- L1102-1107 · `.sells-cowork .vw-chip.on`
- L1107-1120 · `.sells-cowork .vw-textarea`
- L1120-1121 · `.sells-cowork .vw-textarea:focus`
- L1121-1122 · `.sells-cowork .vw-check`
- L1122-1128 · `/* PNT */ .sells-cowork .vw-pnt-grid`
- L1128-1135 · `.sells-cowork .vw-pnt-cell`
- L1135-1136 · `.sells-cowork .vw-pnt-cell small`
- L1136-1137 · `.sells-cowork .vw-pnt-cell b`
- L1137-1142 · `.sells-cowork .vw-pnt-cell.miss`
- L1142-1143 · `.sells-cowork .vw-pnt-cell.miss b`
- L1143-1147 · `.sells-cowork [data-theme="dark"] .vw-pnt-cell.miss`
- L1147-1149 · `/* FIN — money */ .sells-cowork .vw-money`
- L1149-1157 · `.sells-cowork .vw-money small`
- L1157-1165 · `.sells-cowork .vw-money b`
- L1165-1166 · `.sells-cowork .vw-due`
- L1166-1167 · `.sells-cowork .vw-due.urgent`
- L1167-1176 · `/* Botões viewer */ .sells-cowork .vw-actions`
- L1176-1177 · `.sells-cowork .vw-spacer`
- L1177-1193 · `.sells-cowork .vw-btn`
- L1193-1194 · `.sells-cowork .vw-btn:hover`
- L1194-1199 · `.sells-cowork .vw-btn.primary`
- L1199-1200 · `.sells-cowork .vw-btn.primary:hover`
- L1200-1205 · `.sells-cowork .vw-btn.danger`
- L1205-1206 · `.sells-cowork .vw-btn.danger:hover`
- L1610-1620 · `.sells-cowork .composer-inner`
- L1620-1624 · `.sells-cowork .composer-inner:focus-within`
- L1810-1818 · `.sells-cowork .linked-h`
- L1818-1825 · `.sells-cowork .linked-h b`
- L1839-1848 · `.sells-cowork .linked-body`
- L1848-1849 · `.sells-cowork .linked-body::-webkit-scrollbar`
- L1849-1853 · `.sells-cowork .linked-body::-webkit-scrollbar-thumb`
- L1853-1862 · `.sells-cowork .linked-empty`
- L1862-1863 · `.sells-cowork .linked-empty p`
- L1977-1983 · `.sells-cowork .lpunch`
- L1983-1992 · `.sells-cowork .lpunch-row`
- L1992-1993 · `.sells-cowork .lpunch-row span`
- L1993-1994 · `.sells-cowork .lpunch-row b`
- L2152-2165 · `.sells-cowork .os-select`
- L2165-2166 · `.sells-cowork .os-select:focus`
- L2183-2192 · `/* Bulk bar */ .sells-cowork .os-bulk`
- L2192-2193 · `.sells-cowork .os-bulk b`
- L2193-2194 · `.sells-cowork .os-bulk-spacer`
- L2259-2260 · `.sells-cowork .os-cell-check`
- L2260-2261 · `.sells-cowork .os-cell-check input`
- L2261-2265 · `.sells-cowork .os-cell-num`
- L2270-2276 · `.sells-cowork .os-urgent-dot`
- L2276-2283 · `.sells-cowork .os-cell-client b`
- L2283-2288 · `.sells-cowork .os-cell-client small`
- L2288-2293 · `.sells-cowork .os-cell-product`
- L2293-2297 · `.sells-cowork .os-cell-resp`
- L2297-2306 · `.sells-cowork .os-resp-av`
- L2307-2308 · `.sells-cowork .os-resp-name`
- L2308-2314 · `.sells-cowork .os-cell-deadline`
- L2314-2315 · `.sells-cowork .os-cell-deadline.urgent`
- L2315-2322 · `.sells-cowork .os-cell-value`
- L2329-2330 · `.sells-cowork .os-foot-l`
- L2330-2331 · `.sells-cowork .os-foot-v`
- L2331-2333 · `/* Empty in table */ .sells-cowork .os-empty-row`
- L2333-2337 · `.sells-cowork .os-empty-state`
- L2337-2338 · `.sells-cowork .os-empty-state b`
- L2338-2339 · `.sells-cowork .os-empty-state small`
- L2372-2384 · `.sells-cowork .os-drawer`
- L2384-2385 · `.sells-cowork [data-theme="dark"] .os-drawer`
- L2386-2392 · `.sells-cowork .os-drawer-h`
- L2392-2393 · `.sells-cowork .os-drawer-h-l`
- L2393-2398 · `.sells-cowork .os-drawer-num`
- L2398-2408 · `.sells-cowork .os-urgent-tag`
- L2408-2411 · `.sells-cowork .os-drawer-title`
- L2411-2418 · `.sells-cowork .os-drawer-title h2`
- L2418-2423 · `.sells-cowork .os-drawer-title p`
- L2423-2424 · `.sells-cowork .os-drawer-title b`
- L2424-2430 · `.sells-cowork .os-drawer-meta`
- L2430-2436 · `.sells-cowork .os-drawer-meta > div`
- L2436-2445 · `.sells-cowork .os-drawer-meta small`
- L2445-2451 · `.sells-cowork .os-drawer-meta b`
- L2451-2452 · `.sells-cowork .os-drawer-meta .urgent`
- L2453-2457 · `.sells-cowork .os-drawer-section`
- L2457-2465 · `.sells-cowork .os-drawer-section h3`
- L2465-2468 · `.sells-cowork .os-drawer-resp`
- L2468-2469 · `.sells-cowork .os-drawer-resp b`
- L2469-2470 · `.sells-cowork .os-drawer-resp small`
- L2470-2471 · `.sells-cowork .os-drawer-resp button`
- L2471-2476 · `/* Stages flow */ .sells-cowork .os-stages-flow`
- L2476-2482 · `.sells-cowork .os-stage-step`
- L2482-2488 · `.sells-cowork .os-stage-step .step-dot`
- L2488-2489 · `.sells-cowork .os-stage-step.done .step-dot`
- L2489-2490 · `.sells-cowork .os-stage-step.done`
- L2490-2495 · `.sells-cowork .os-stage-step.current .step-dot`
- L2495-2496 · `.sells-cowork .os-stage-step.current`
- L2496-2502 · `/* Timeline */ .sells-cowork .os-timeline`
- L2502-2509 · `.sells-cowork .os-timeline::before`
- L2509-2513 · `.sells-cowork .os-tl-item`
- L2513-2521 · `.sells-cowork .os-tl-dot`
- L2526-2530 · `.sells-cowork .os-tl-h`
- L2530-2531 · `.sells-cowork .os-tl-h b`
- L2531-2532 · `.sells-cowork .os-tl-h small`
- L2532-2538 · `.sells-cowork .os-tl-when`
- L2538-2544 · `.sells-cowork .os-tl-content p`
- L2544-2554 · `.sells-cowork .os-tl-file`
- L2554-2562 · `.sells-cowork .os-drawer-actions`
- L2564-2572 · `.sells-cowork .os-new-num`
- L2572-2580 · `.sells-cowork .os-new-stepper`
- L2580-2589 · `.sells-cowork .os-step`
- L2589-2590 · `.sells-cowork .os-step:last-child`
- L2590-2591 · `.sells-cowork .os-step:hover`
- L2591-2595 · `.sells-cowork .os-step.active`
- L2595-2604 · `.sells-cowork .os-step-bullet`
- L2604-2609 · `.sells-cowork .os-step.done .os-step-bullet`
- L2609-2610 · `.sells-cowork .os-step-text`
- L2610-2611 · `.sells-cowork .os-step-text b`
- L2611-2616 · `.sells-cowork .os-step-text small`
- L2616-2621 · `.sells-cowork .os-new-body`
- L2621-2622 · `.sells-cowork .os-new-sec`
- L2622-2628 · `.sells-cowork .os-new-sec h3`
- L2628-2633 · `.sells-cowork .os-new-help`
- L2633-2641 · `.sells-cowork .os-new-search`
- L2641-2645 · `.sells-cowork .os-new-search input`
- L2645-2646 · `.sells-cowork .os-new-search input::placeholder`
- L2646-2653 · `.sells-cowork .os-new-results`
- L2653-2660 · `.sells-cowork .os-new-results li`
- L2660-2661 · `.sells-cowork .os-new-results li:last-child`
- L2661-2662 · `.sells-cowork .os-new-results li:hover`
- L2662-2663 · `.sells-cowork .os-new-results li b`
- L2663-2664 · `.sells-cowork .os-new-results li small`
- L2664-2665 · `.sells-cowork .os-new-results li.empty`
- L2665-2666 · `.sells-cowork .os-new-results li.empty a`
- L2666-2670 · `.sells-cowork .os-new-last`
- L2670-2671 · `.sells-cowork .os-new-price`
- L2671-2677 · `.sells-cowork .os-new-client-card`
- L2677-2681 · `.sells-cowork .os-new-client-h`
- L2681-2682 · `.sells-cowork .os-new-client-h b`
- L2682-2683 · `.sells-cowork .os-new-client-h small`
- L2683-2686 · `.sells-cowork .os-new-row2`
- L2686-2687 · `.sells-cowork .os-new-row2 label`
- L2687-2688 · `.sells-cowork .os-new-row2 small`
- L2688-2697 · `.sells-cowork .os-new-row2 input`
- L2698-2699 · `.sells-cowork .os-new-row2 input:focus`
- L2699-2702 · `.sells-cowork .os-new-prod-pickers`
- L2702-2705 · `.sells-cowork .os-new-cats`
- L2705-2716 · `.sells-cowork .os-cat`
- L2716-2717 · `.sells-cowork .os-cat:hover`
- L2717-2722 · `.sells-cowork .os-cat.active`
- L2722-2729 · `.sells-cowork .os-new-items`
- L2729-2734 · `.sells-cowork .os-new-itable`
- L2734-2745 · `.sells-cowork .os-new-itable th`
- L2745-2746 · `.sells-cowork .os-new-itable th.r, .sells-cowork .os-new-itable td.r`
- L2746-2751 · `.sells-cowork .os-new-itable td`
- L2751-2752 · `.sells-cowork .os-new-itable tbody tr:last-child td`
- L2752-2753 · `.sells-cowork .os-new-itable td b`
- L2753-2759 · `.sells-cowork .os-new-itable tfoot td`
- L2759-2760 · `.sells-cowork .os-new-itable tfoot b`
- L2760-2769 · `.sells-cowork .os-new-itemnote`
- L2769-2770 · `.sells-cowork .os-new-itemnote:focus`
- L2770-2771 · `.sells-cowork .os-new-itemnote::placeholder`
- L2771-2782 · `.sells-cowork .os-new-qty, .sells-cowork .os-new-price-i`
- L2782-2783 · `.sells-cowork .os-new-price-i`
- L2783-2784 · `.sells-cowork .os-new-qty:focus, .sells-cowork .os-new-price-i:focus`
- L2784-2790 · `.sells-cowork .os-new-unit`
- L2790-2797 · `.sells-cowork .os-new-empty`
- L2797-2798 · `.sells-cowork .os-new-empty b`
- L2798-2799 · `.sells-cowork .os-new-empty small`
- L2799-2809 · `.sells-cowork .os-new-toggle`
- L2809-2810 · `.sells-cowork .os-new-toggle input`
- L2810-2811 · `.sells-cowork .os-new-toggle span`
- L2811-2815 · `.sells-cowork .os-new-textarea`
- L2815-2816 · `.sells-cowork .os-new-textarea small`
- L2816-2828 · `.sells-cowork .os-new-textarea textarea`
- L2828-2829 · `.sells-cowork .os-new-textarea textarea:focus`
- L2829-2833 · `.sells-cowork .os-new-shortcuts`
- L2833-2834 · `.sells-cowork .os-new-shortcuts small`
- L2834-2844 · `.sells-cowork .os-chip`
- L2844-2845 · `.sells-cowork .os-chip:hover`
- L2845-2850 · `.sells-cowork .os-new-resps`
- L2850-2860 · `.sells-cowork .os-new-resp`
- L2860-2861 · `.sells-cowork .os-new-resp:hover`
- L2861-2865 · `.sells-cowork .os-new-resp.active`
- L2865-2866 · `.sells-cowork .os-new-resp b`
- L2866-2867 · `.sells-cowork .os-new-resp small`
- L2867-2874 · `.sells-cowork .os-new-attach`
- L2874-2880 · `.sells-cowork .os-new-files`
- L2880-2887 · `.sells-cowork .os-new-files li`
- L2887-2888 · `.sells-cowork .os-new-files li:last-child`
- L2888-2889 · `.sells-cowork .os-new-files li span`
- L2889-2897 · `.sells-cowork .os-new-fkind`
- L2897-2901 · `.sells-cowork .os-new-foot`
- L2901-2905 · `.sells-cowork .os-new-summary`
- L2905-2909 · `.sells-cowork .os-new-total`
- L2912-2920 · `/* ════════════════════════ APROVAR ARTE — MODAL ════════════════════════ */ .sells-cowork .os-modal-back`
- L2920-2921 · `.sells-cowork [data-theme="dark"] .os-modal-back`
- L2921-2936 · `.sells-cowork .os-modal`
- L2936-2937 · `.sells-cowork [data-theme="dark"] .os-modal`
- L2941-2946 · `.sells-cowork .os-modal-h`
- L2946-2950 · `.sells-cowork .os-modal-h h2`
- L2950-2954 · `.sells-cowork .os-modal-h p`
- L2954-2955 · `.sells-cowork .os-modal-h > div`
- L2956-2962 · `.sells-cowork .os-modal-foot`
- L2962-2970 · `/* Body do modal de aprovação: 240px coluna versões + canvas */ .sells-cowork .os-approve-body`
- L2970-2976 · `.sells-cowork .os-approve-versions`
- L2976-2981 · `.sells-cowork .os-approve-modes`
- L2981-2986 · `.sells-cowork .os-version-list`
- L2986-2994 · `.sells-cowork .os-version-list li`
- L2994-2995 · `.sells-cowork .os-version-list li:hover`
- L2995-2999 · `.sells-cowork .os-version-list li.selected`
- L3003-3007 · `.sells-cowork .os-version-h`
- L3007-3008 · `.sells-cowork .os-version-h b`
- L3008-3018 · `.sells-cowork .os-version-tag`
- L3018-3022 · `.sells-cowork [data-theme="dark"] .os-version-tag`
- L3022-3027 · `.sells-cowork .os-version-list small`
- L3027-3032 · `.sells-cowork .os-version-list p`
- L3032-3040 · `.sells-cowork .os-approve-canvas`
- L3041-3050 · `.sells-cowork .os-approve-vs`
- L3050-3056 · `.sells-cowork .os-approve-vs span`
- L3056-3060 · `/* Thumbs (placeholder gráfico colorido por versão) */ .sells-cowork .os-art-thumb`
- L3060-3064 · `.sells-cowork .os-art-thumb small`
- L3064-3072 · `.sells-cowork .os-art-frame`
- L3072-3076 · `.sells-cowork [data-theme="dark"] .os-art-frame`
- L3076-3082 · `.sells-cowork .os-art-corner`
- L3086-3090 · `.sells-cowork .os-art-content`
- L3090-3096 · `.sells-cowork .os-art-blob`
- L3096-3097 · `.sells-cowork .os-art-thumb.art-v1 .os-art-blob.a`
- L3097-3098 · `.sells-cowork .os-art-thumb.art-v1 .os-art-blob.b`
- L3098-3099 · `.sells-cowork .os-art-thumb.art-v1 .os-art-blob.c`
- L3099-3100 · `.sells-cowork .os-art-thumb.art-v2 .os-art-blob.a`
- L3100-3101 · `.sells-cowork .os-art-thumb.art-v2 .os-art-blob.b`
- L3101-3102 · `.sells-cowork .os-art-thumb.art-v2 .os-art-blob.c`
- L3102-3103 · `.sells-cowork .os-art-thumb.art-v3 .os-art-blob.a`
- L3103-3104 · `.sells-cowork .os-art-thumb.art-v3 .os-art-blob.b`
- L3104-3105 · `.sells-cowork .os-art-thumb.art-v3 .os-art-blob.c`
- L3105-3109 · `.sells-cowork .os-art-text`
- L3109-3116 · `.sells-cowork .os-art-text b`
- L3116-3122 · `.sells-cowork .os-art-text span`
- L3122-3128 · `/* Decisão (aprovar/ajustar/rejeitar) */ .sells-cowork .os-approve-decision`
- L3128-3133 · `.sells-cowork .os-decision-options`
- L3133-3143 · `.sells-cowork .os-decision`
- L3143-3144 · `.sells-cowork .os-decision:hover`
- L3144-3145 · `.sells-cowork .os-decision b`
- L3145-3146 · `.sells-cowork .os-decision small`
- L3146-3147 · `.sells-cowork .os-decision svg`
- L3155-3159 · `.sells-cowork .os-decision.adjust.active`
- L3159-3162 · `.sells-cowork [data-theme="dark"] .os-decision.adjust.active`
- L3162-3163 · `.sells-cowork .os-decision.adjust.active svg`
- L3171-3186 · `.sells-cowork .os-decision-comment`
- L3186-3187 · `.sells-cowork .os-decision-comment:focus`
- L3187-3197 · `.sells-cowork .os-decision-confirm`
- L3197-3202 · `.sells-cowork [data-theme="dark"] .os-decision-confirm`
- L3202-3203 · `.sells-cowork .os-decision-confirm svg`
- L3203-3204 · `.sells-cowork .os-decision-confirm b`
- L3204-3216 · `.sells-cowork .os-kbd`
- L3216-3217 · `.sells-cowork .os-decision.active .os-kbd`
- L3231-3233 · `/* ════════════════════════ BULK MODAL ════════════════════════ */ .sells-cowork .os-bulk-modal`
- L3233-3237 · `.sells-cowork .os-bulk-ids`
- L3237-3243 · `.sells-cowork .os-bulk-body`
- L3243-3247 · `.sells-cowork .os-bulk-stages`
- L3247-3257 · `.sells-cowork .os-bulk-stage`
- L3257-3258 · `.sells-cowork .os-bulk-stage:hover`
- L3258-3262 · `.sells-cowork .os-bulk-stage.active`
- L3262-3263 · `.sells-cowork .os-bulk-stage svg`
- L3263-3274 · `.sells-cowork .os-bulk-toggle`
- L3274-3275 · `.sells-cowork .os-bulk-toggle input`
- L3275-3277 · `/* ════════════════════════ CLIENTES (Fase 3) ════════════════════════ */ .sells-cowork .cli-name`
- L3277-3278 · `.sells-cowork .cli-sub`
- L3278-3287 · `.sells-cowork .cli-seg`
- L3287-3288 · `.sells-cowork .cli-city`
- L3288-3289 · `.sells-cowork .cli-num`
- L3289-3290 · `.sells-cowork .cli-last`
- L3290-3298 · `.sells-cowork .cli-status`
- L3300-3306 · `.sells-cowork .cli-kpis`
- L3306-3313 · `.sells-cowork .cli-kpi`
- L3313-3314 · `.sells-cowork .cli-kpi b`
- L3314-3315 · `.sells-cowork .cli-kpi b.ok`
- L3315-3316 · `.sells-cowork .cli-kpi b.muted`
- L3316-3317 · `.sells-cowork .cli-kpi small`
- L3317-3318 · `.sells-cowork .cli-tabs`
- L3318-3321 · `.sells-cowork .cli-section`
- L3321-3329 · `.sells-cowork .cli-row`
- L3329-3330 · `.sells-cowork .cli-row span`
- L3330-3331 · `.sells-cowork .cli-row b`
- L3331-3332 · `.sells-cowork .cli-tags`
- L3332-3340 · `.sells-cowork .cli-tag`
- L3340-3349 · `.sells-cowork .cli-empty`
- L3349-3350 · `.sells-cowork .cli-empty b`
- L3350-3351 · `.sells-cowork .cli-empty small`
- L3351-3352 · `.sells-cowork .cli-empty svg`
- L3352-3359 · `.sells-cowork .cli-contact`
- L3359-3368 · `.sells-cowork .cli-contact-av`
- L3368-3369 · `.sells-cowork .cli-contact b`
- L3369-3370 · `.sells-cowork .cli-contact small`
- L3370-3379 · `/* ════════════════════════ ORÇAMENTOS ════════════════════════ */ .sells-cowork .orc-items`
- L3379-3388 · `.sells-cowork .orc-status`
- L3388-3393 · `.sells-cowork .orc-os`
- L3393-3404 · `.sells-cowork .orc-prob`
- L3404-3409 · `.sells-cowork .orc-prob-fill`
- L3409-3415 · `.sells-cowork .orc-prob span`
- L3415-3425 · `/* ════════════════════════ PRODUTOS ════════════════════════ */ .sells-cowork .prod-toggle`
- L3425-3426 · `.sells-cowork .prod-toggle input`
- L3426-3432 · `.sells-cowork .prod-grid`
- L3432-3444 · `.sells-cowork .prod-card`
- L3444-3445 · `.sells-cowork .prod-card:hover`
- L3445-3446 · `.sells-cowork .prod-card.selected`
- L3447-3452 · `.sells-cowork .prod-card-h`
- L3452-3459 · `.sells-cowork .prod-cat`
- L3459-3466 · `.sells-cowork .prod-inactive-badge`
- L3466-3473 · `.sells-cowork .prod-name`
- L3473-3477 · `.sells-cowork .prod-id`
- L3477-3483 · `.sells-cowork .prod-foot`
- L3483-3487 · `.sells-cowork .prod-price b`
- L3487-3492 · `.sells-cowork .prod-price small`
- L3492-3499 · `.sells-cowork .prod-meta`
- L3499-3500 · `.sells-cowork .prod-meta span`
- L3500-3508 · `.sells-cowork .prod-pop`
- L3508-3512 · `.sells-cowork .prod-pop-fill`
- L3512-3520 · `.sells-cowork .prod-bom-h`
- L3520-3528 · `.sells-cowork .prod-bom-row`
- L3528-3529 · `.sells-cowork .prod-bom-row svg`
- L3529-3536 · `/* ─── Produção: Fila / Acabamento / Expedição ─── */ .sells-cowork .prod-page`
- L3536-3542 · `.sells-cowork .prod-header`
- L3542-3548 · `.sells-cowork .prod-header-l h1`
- L3548-3553 · `.sells-cowork .prod-header-l p`
- L3553-3556 · `.sells-cowork .prod-header-r`
- L3556-3563 · `.sells-cowork .prod-view-toggle`
- L3563-3572 · `.sells-cowork .prod-view-toggle button`
- L3572-3573 · `.sells-cowork .prod-view-toggle button:last-child`
- L3573-3577 · `.sells-cowork .prod-view-toggle button.active`
- L3577-3582 · `.sells-cowork .prod-kpis`
- L3582-3589 · `.sells-cowork .prod-kpi`
- L3589-3593 · `.sells-cowork .prod-kpi-label`
- L3593-3597 · `.sells-cowork .prod-kpi-value`
- L3597-3600 · `.sells-cowork .prod-kpi-sub`
- L3600-3601 · `.sells-cowork .prod-kpi-urgent .prod-kpi-value`
- L3601-3602 · `.sells-cowork .prod-kpi-urgent`
- L3602-3606 · `.sells-cowork .prod-equip-filters`
- L3606-3616 · `.sells-cowork .prod-equip-tab`
- L3623-3628 · `.sells-cowork .prod-equip-tab.active`
- L3629-3633 · `.sells-cowork .prod-equip-dot`
- L3636-3644 · `.sells-cowork .prod-kanban`
- L3644-3651 · `.sells-cowork .prod-col`
- L3651-3657 · `.sells-cowork .prod-col-head`
- L3657-3660 · `.sells-cowork .prod-col-head-l`
- L3660-3663 · `.sells-cowork .prod-col-head h3`
- L3663-3671 · `.sells-cowork .prod-col-count`
- L3671-3675 · `.sells-cowork .prod-col-cap`
- L3675-3678 · `.sells-cowork .prod-col-dot`
- L3681-3682 · `.sells-cowork .prod-col-violet`
- L3682-3683 · `.sells-cowork .prod-col-amber`
- L3683-3684 · `.sells-cowork .prod-col-cyan`
- L3684-3687 · `.sells-cowork .prod-col-ops`
- L3687-3695 · `.sells-cowork .prod-col-op-tag`
- L3695-3700 · `.sells-cowork .prod-col-body`
- L3700-3708 · `.sells-cowork .prod-empty`
- L3708-3718 · `/* Cards */ .sells-cowork .prod-card`
- L3718-3722 · `.sells-cowork .prod-card:hover`
- L3722-3726 · `.sells-cowork .prod-card.urgent`
- L3726-3730 · `.sells-cowork .prod-card-top`
- L3730-3735 · `.sells-cowork .prod-seq`
- L3735-3741 · `.sells-cowork .prod-os`
- L3741-3752 · `.sells-cowork .prod-urgent`
- L3752-3758 · `.sells-cowork .prod-card-title`
- L3758-3762 · `.sells-cowork .prod-card-client`
- L3762-3766 · `.sells-cowork .prod-equip-row`
- L3766-3772 · `.sells-cowork .prod-equip-pill`
- L3775-3780 · `.sells-cowork .prod-eta`
- L3787-3793 · `.sells-cowork .prod-progress-bar`
- L3793-3800 · `.sells-cowork .prod-progress-label`
- L3800-3805 · `.sells-cowork .prod-card-foot`
- L3805-3809 · `.sells-cowork .prod-deadline`
- L3809-3810 · `.sells-cowork .prod-card.urgent .prod-deadline`
- L3810-3813 · `.sells-cowork .prod-actions`
- L3813-3824 · `.sells-cowork .prod-act`
- L3824-3825 · `.sells-cowork .prod-act:hover`
- L3825-3830 · `.sells-cowork .prod-act.primary`
- L3830-3831 · `.sells-cowork .prod-act.primary:hover`
- L3831-3839 · `.sells-cowork .prod-op-pill`
- L3839-3848 · `.sells-cowork .prod-route-pill`
- L3848-3852 · `.sells-cowork .prod-pieces-row`
- L3852-3853 · `.sells-cowork .prod-pieces`
- L3853-3854 · `.sells-cowork .prod-pct`
- L3854-3860 · `.sells-cowork .prod-exp-meta`
- L3860-3861 · `.sells-cowork .prod-exp-meta dt`
- L3861-3862 · `.sells-cowork .prod-exp-meta dd`
- L3862-3868 · `/* Lista plana */ .sells-cowork .prod-list`
- L3870-3873 · `.sells-cowork .row-urgent td:first-child`
- L3873-3879 · `.sells-cowork .stage-pill`
- L3879-3880 · `.sells-cowork .stage-producao`
- L3880-3881 · `.sells-cowork .stage-acabamento`
- L3881-3882 · `.sells-cowork .stage-expedicao`
- L3882-3883 · `.sells-cowork .t-truncate`
- L3883-3884 · `.sells-cowork .t-urgent`
- L3884-3891 · `/* Drawer */ .sells-cowork .prod-drawer-backdrop`
- L3891-3898 · `.sells-cowork .prod-drawer`
- L3898-3904 · `.sells-cowork .prod-drawer-head`
- L3904-3910 · `.sells-cowork .prod-drawer-eyebrow`
- L3910-3915 · `.sells-cowork .prod-drawer-head h2`
- L3915-3920 · `.sells-cowork .prod-drawer-head p`
- L3920-3925 · `.sells-cowork .prod-drawer-body`
- L3925-3931 · `.sells-cowork .prod-drawer-meta`
- L3931-3932 · `.sells-cowork .prod-drawer-meta dt`
- L3932-3933 · `.sells-cowork .prod-drawer-meta dd`
- L3933-3936 · `.sells-cowork .prod-drawer-actions`
- L3938-3939 · `.sells-cowork .cli-name`
- L3939-3945 · `.sells-cowork .cli-avatar`
- L3946-3947 · `.sells-cowork .cli-avatar.grad-1`
- L3947-3948 · `.sells-cowork .cli-avatar.grad-2`
- L3948-3949 · `.sells-cowork .cli-avatar.grad-3`
- L3949-3950 · `.sells-cowork .cli-avatar.grad-4`
- L3950-3951 · `.sells-cowork .cli-avatar.grad-5`
- L3951-3952 · `.sells-cowork .cli-name-text`
- L3952-3953 · `.sells-cowork .cli-doc`
- L3953-3954 · `.sells-cowork .cli-contact-line`
- L3954-3955 · `.sells-cowork .cli-phone`
- L3957-3962 · `.sells-cowork .cli-status`
- L3963-3964 · `.sells-cowork .cli-status.active`
- L3965-3967 · `/* Drawer */ .sells-cowork .cli-drawer`
- L3967-3968 · `.sells-cowork .cli-head`
- L3968-3969 · `.sells-cowork .cli-head-name`
- L3969-3970 · `.sells-cowork .cli-head-doc`
- L3970-3975 · `.sells-cowork .cli-kpis`
- L3975-3981 · `.sells-cowork .cli-kpi`
- L3981-3982 · `.sells-cowork .cli-kpi.danger`
- L3982-3983 · `.sells-cowork .cli-kpi-v`
- L3983-3984 · `.sells-cowork .cli-kpi.danger .cli-kpi-v`
- L3984-3985 · `.sells-cowork .cli-kpi-l`
- L3985-3986 · `.sells-cowork .cli-section`
- L3986-3987 · `.sells-cowork .cli-section:last-of-type`
- L3987-3992 · `.sells-cowork .cli-section-title`
- L3992-3993 · `.sells-cowork .cli-info-grid`
- L3993-3994 · `.sells-cowork .cli-info-l`
- L3994-3995 · `.sells-cowork .cli-info-v`
- L3995-3996 · `.sells-cowork .cli-history`
- L3996-4007 · `.sells-cowork .cli-os`
- L4007-4008 · `.sells-cowork .cli-os-id`
- L4008-4009 · `.sells-cowork .cli-os-prod`
- L4009-4014 · `.sells-cowork .cli-os-stage`
- L4014-4015 · `.sells-cowork .cli-os-stage.stage-aprovacao`
- L4015-4016 · `.sells-cowork .cli-os-stage.stage-producao, .sells-cowork .cli-os-stage.stage-acabamento`
- L4016-4017 · `.sells-cowork .cli-os-stage.stage-expedicao`
- L4017-4018 · `.sells-cowork .cli-os-stage.stage-entregue`
- L4018-4019 · `.sells-cowork .cli-os-stage.stage-orcado`
- L4019-4020 · `.sells-cowork .cli-os-stage.stage-cancelado`
- L4020-4021 · `.sells-cowork .cli-os-deadline`
- L4021-4022 · `.sells-cowork .cli-os-value`
- L4022-4023 · `.sells-cowork .cli-fin`
- L4023-4030 · `.sells-cowork .cli-fin-row`
- L4030-4031 · `.sells-cowork .cli-fin-row b`
- L4031-4032 · `.sells-cowork .cli-fin-row b.danger`
- L4032-4037 · `.sells-cowork .cli-empty`
- L4067-4069 · `/* Drawer venda */ .sells-cowork .vd-section`
- L4069-4070 · `.sells-cowork .vd-section:last-child`
- L4070-4071 · `.sells-cowork .vd-section h3`
- L4071-4072 · `.sells-cowork .vd-meta`
- L4072-4073 · `.sells-cowork .vd-meta dt`
- L4073-4074 · `.sells-cowork .vd-meta dd`
- L4074-4075 · `.sells-cowork .vd-total-strong`
- L4075-4076 · `.sells-cowork .vd-os-list`
- L4076-4077 · `.sells-cowork .vd-os-link`
- L4077-4078 · `.sells-cowork .vd-os-link:hover`
- L4078-4079 · `.sells-cowork .vd-docs`
- L4079-4081 · `/* Stepper */ .sells-cowork .vd-stepper`
- L4081-4082 · `.sells-cowork .vd-step`
- L4082-4083 · `.sells-cowork .vd-step.active`
- L4083-4084 · `.sells-cowork .vd-step.done`
- L4084-4085 · `.sells-cowork .vd-step-num`
- L4085-4086 · `.sells-cowork .vd-step.active .vd-step-num`
- L4086-4087 · `.sells-cowork .vd-step.done .vd-step-num`
- L4087-4088 · `.sells-cowork .vd-step-sep`
- L4088-4090 · `/* Create body */ .sells-cowork .vd-create-body`
- L4090-4091 · `.sells-cowork .vd-search-wrap`
- L4091-4092 · `.sells-cowork .vd-search-wrap input`
- L4092-4093 · `.sells-cowork .vd-search-wrap input:focus`
- L4093-4094 · `.sells-cowork .vd-walkin`
- L4094-4095 · `.sells-cowork .vd-walkin:hover`
- L4095-4096 · `.sells-cowork .vd-client-list`
- L4096-4097 · `.sells-cowork .vd-client-card`
- L4097-4098 · `.sells-cowork .vd-client-card:hover`
- L4098-4099 · `.sells-cowork .vd-client-card-name`
- L4099-4100 · `.sells-cowork .vd-client-card-meta`
- L4100-4101 · `.sells-cowork .vd-client-selected`
- L4101-4102 · `.sells-cowork .vd-fields`
- L4102-4103 · `.sells-cowork .vd-fields label`
- L4103-4104 · `.sells-cowork .vd-fields input, .sells-cowork .vd-fields select`
- L4104-4106 · `/* Itens */ .sells-cowork .vd-prod-suggest`
- L4106-4107 · `.sells-cowork .vd-prod-row`
- L4107-4108 · `.sells-cowork .vd-prod-row:hover`
- L4108-4109 · `.sells-cowork .vd-prod-row:last-child`
- L4109-4110 · `.sells-cowork .vd-prod-price`
- L4110-4111 · `.sells-cowork .vd-empty-mini`
- L4111-4112 · `.sells-cowork .vd-empty-state`
- L4112-4113 · `.sells-cowork .vd-items-table`
- L4113-4114 · `.sells-cowork .vd-items-table th`
- L4114-4115 · `.sells-cowork .vd-items-table td`
- L4115-4116 · `.sells-cowork .vd-items-table input`
- L4116-4117 · `.sells-cowork .vd-strong`
- L4117-4118 · `.sells-cowork .vd-toggle`
- L4118-4119 · `.sells-cowork .vd-foot-l`
- L4119-4120 · `.sells-cowork .vd-foot-r`
- L4120-4122 · `/* Pagamento */ .sells-cowork .vd-pay-grid`
- L4122-4123 · `.sells-cowork .vd-pay-card`
- L4123-4124 · `.sells-cowork .vd-pay-card.active`
- L4124-4125 · `.sells-cowork .vd-pay-icon`
- L4125-4126 · `.sells-cowork .vd-pay-label`
- L4126-4127 · `.sells-cowork .vd-pay-clear`
- L4127-4128 · `.sells-cowork .vd-totals`
- L4128-4129 · `.sells-cowork .vd-totals dt`
- L4129-4130 · `.sells-cowork .vd-totals dd`
- L4130-4131 · `.sells-cowork .vd-total-row`
- L4131-4133 · `/* Confirmar */ .sells-cowork .vd-confirm-grid`
- L4133-4134 · `.sells-cowork .vd-confirm-block`
- L4134-4135 · `.sells-cowork .vd-confirm-label`
- L4135-4136 · `.sells-cowork .vd-confirm-block strong`
- L4136-4137 · `.sells-cowork .vd-confirm-total`
- L4137-4138 · `.sells-cowork .vd-total-big`
- L4138-4139 · `.sells-cowork .vd-callout`
- L4139-4140 · `.sells-cowork .vd-callout-ok`
- L4140-4142 · `/* Foot */ .sells-cowork .vd-foot`
- L4142-4143 · `.sells-cowork .vd-foot-summary`
- L4143-4144 · `.sells-cowork .vd-foot-total`
- L4144-4145 · `.sells-cowork .vd-foot-actions`
- L4145-4147 · `/* ════════════ VENDAS MODULE — sub-nav + extras ════════════ */ .sells-cowork .vendas-module`
- L4147-4152 · `.sells-cowork .vm-subnav`
- L4152-4153 · `.sells-cowork .vm-subnav-tabs`
- L4153-4159 · `.sells-cowork .vm-subtab`
- L4159-4160 · `.sells-cowork .vm-subtab:hover`
- L4160-4161 · `.sells-cowork .vm-subtab.active`
- L4161-4167 · `.sells-cowork .vm-pdv-btn`
- L4167-4168 · `.sells-cowork .vm-pdv-btn:hover`
- L4168-4173 · `.sells-cowork .vm-pdv-btn kbd`
- L4173-4174 · `.sells-cowork .vm-body`
- L4212-4213 · `.sells-cowork .vc-moves`
- L4213-4218 · `.sells-cowork .vc-move`
- L4218-4219 · `.sells-cowork .vc-move-time`
- L4219-4222 · `.sells-cowork .vc-move-type`
- L4222-4223 · `.sells-cowork .vc-move-abertura .vc-move-type`
- L4223-4224 · `.sells-cowork .vc-move-suprimento .vc-move-type`
- L4224-4225 · `.sells-cowork .vc-move-sangria .vc-move-type`
- L4225-4226 · `.sells-cowork .vc-move-amount`
- L4228-4233 · `.sells-cowork .vc-move-add`
- L4233-4237 · `.sells-cowork .vc-move-add input, .sells-cowork .vc-move-add select`
- L4237-4241 · `.sells-cowork .vc-counter`
- L4241-4242 · `.sells-cowork .vc-counter label`
- L4242-4246 · `.sells-cowork .vc-counter input`
- L4246-4247 · `.sells-cowork .vc-counter-big`
- L4247-4248 · `.sells-cowork .vc-counter-summary`
- L4248-4249 · `.sells-cowork .vc-counter-summary dt`
- L4249-4250 · `.sells-cowork .vc-counter-summary dd`
- L4303-4305 · `/* ──── DEVOLUÇÕES ──── */ .sells-cowork .vd-dev-page .vdv-reason`
- L4305-4306 · `.sells-cowork .vdv-timeline`
- L4306-4307 · `.sells-cowork .vdv-timeline li`
- L4307-4308 · `.sells-cowork .vdv-tl-dot`
- L4308-4309 · `.sells-cowork .vdv-tl-dot.ok`
- L4309-4310 · `.sells-cowork .vdv-tl-dot.active`
- L4310-4311 · `.sells-cowork .vdv-input`
- L4311-4312 · `.sells-cowork .vdv-matches`
- L4312-4313 · `.sells-cowork .vdv-match`
- L4313-4314 · `.sells-cowork .vdv-match:hover`
- L4314-4315 · `.sells-cowork .vdv-selected`
- L4315-4316 · `.sells-cowork .vdv-reasons`
- L4316-4317 · `.sells-cowork .vdv-reason-btn`
- L4317-4318 · `.sells-cowork .vdv-reason-btn.active`
- L4318-4319 · `.sells-cowork .vdv-textarea`
- L4319-4321 · `/* ──── COMISSÕES ──── */ .sells-cowork .vco-period`
- L4321-4322 · `.sells-cowork .vco-period button`
- L4322-4323 · `.sells-cowork .vco-period button.active`
- L4323-4324 · `.sells-cowork .vco-grid`
- L4324-4325 · `.sells-cowork .vco-card`
- L4325-4326 · `.sells-cowork .vco-card header`
- L4326-4327 · `.sells-cowork .vco-card header h3`
- L4327-4328 · `.sells-cowork .vco-card header span`
- L4328-4334 · `.sells-cowork .vco-avatar`
- L4334-4338 · `.sells-cowork .vco-rank`
- L4338-4339 · `.sells-cowork .vco-bar`
- L4339-4340 · `.sells-cowork .vco-bar-fill`
- L4340-4341 · `.sells-cowork .vco-numbers`
- L4341-4342 · `.sells-cowork .vco-numbers > div`
- L4342-4343 · `.sells-cowork .vco-numbers span`
- L4343-4344 · `.sells-cowork .vco-numbers strong`
- L4344-4345 · `.sells-cowork .vco-com strong`
- L4345-4346 · `.sells-cowork .vco-mix`
- L4346-4347 · `.sells-cowork .vco-chip`
- L4347-4348 · `.sells-cowork .vco-chip em`
- L4348-4349 · `.sells-cowork .vco-table-card`
- L4349-4350 · `.sells-cowork .vco-table-card h3`
- L4350-4351 · `.sells-cowork .vco-rules`
- L4351-4352 · `.sells-cowork .vco-rules th, .sells-cowork .vco-rules td`
- L4352-4353 · `.sells-cowork .vco-rules th`
- L4353-4355 · `/* ──── RELATÓRIOS ──── */ .sells-cowork .vrep-tabs`
- L4355-4356 · `.sells-cowork .vrep-tabs button`
- L4356-4357 · `.sells-cowork .vrep-tabs button.active`
- L4357-4361 · `.sells-cowork .vrep-summary`
- L4361-4365 · `.sells-cowork .vrep-summary > div`
- L4365-4366 · `.sells-cowork .vrep-summary span`
- L4366-4367 · `.sells-cowork .vrep-summary strong`
- L4367-4368 · `.sells-cowork .vrep-card`
- L4368-4369 · `.sells-cowork .vrep-card h3`
- L4369-4373 · `.sells-cowork .vrep-bars`
- L4373-4377 · `.sells-cowork .vrep-bar-col`
- L4377-4382 · `.sells-cowork .vrep-bar`
- L4382-4386 · `.sells-cowork .vrep-bar-val`
- L4386-4387 · `.sells-cowork .vrep-bar-lbl`
- L4387-4388 · `.sells-cowork .vrep-clients`
- L4388-4392 · `.sells-cowork .vrep-clients li`
- L4392-4393 · `.sells-cowork .vrep-rank`
- L4393-4394 · `.sells-cowork .vrep-cli-name`
- L4394-4395 · `.sells-cowork .vrep-cli-n`
- L4395-4396 · `.sells-cowork .vrep-cli-bar`
- L4396-4397 · `.sells-cowork .vrep-cli-bar > div`
- L4397-4398 · `.sells-cowork .vrep-cli-total`
- L4398-4399 · `.sells-cowork .vrep-origin`
- L4399-4404 · `.sells-cowork .vrep-donut`
- L4404-4407 · `.sells-cowork .vrep-donut::after`
- L4407-4408 · `.sells-cowork .vrep-donut > *`
- L4408-4409 · `.sells-cowork .vrep-donut span`
- L4409-4410 · `.sells-cowork .vrep-donut small`
- L4410-4411 · `.sells-cowork .vrep-origin-legend`
- L4411-4412 · `.sells-cowork .vrep-origin-legend dt`
- L4412-4413 · `.sells-cowork .vrep-dot`
- L4413-4418 · `/* ──── PDV BALCÃO (overlay) ──── */ .sells-cowork .pdv-overlay`
- L4418-4423 · `.sells-cowork .pdv-head`
- L4423-4424 · `.sells-cowork .pdv-head-l`
- L4424-4425 · `.sells-cowork .pdv-brand`
- L4425-4426 · `.sells-cowork .pdv-sep`
- L4426-4427 · `.sells-cowork .pdv-head-r`
- L4427-4428 · `.sells-cowork .pdv-shortcut`
- L4428-4434 · `.sells-cowork .pdv-shortcut kbd`
- L4434-4439 · `.sells-cowork .pdv-close`
- L4439-4440 · `.sells-cowork .pdv-grid`
- L4440-4441 · `.sells-cowork .pdv-left`
- L4441-4442 · `.sells-cowork .pdv-scan`
- L4442-4443 · `.sells-cowork .pdv-scan-label`
- L4443-4449 · `.sells-cowork .pdv-scan-input`
- L4449-4455 · `.sells-cowork .pdv-suggest`
- L4455-4460 · `.sells-cowork .pdv-suggest li`
- L4460-4461 · `.sells-cowork .pdv-suggest li:hover`
- L4461-4462 · `.sells-cowork .pdv-items-wrap`
- L4462-4463 · `.sells-cowork .pdv-empty`
- L4463-4464 · `.sells-cowork .pdv-empty-big`
- L4464-4465 · `.sells-cowork .pdv-items`
- L4465-4466 · `.sells-cowork .pdv-items th`
- L4466-4467 · `.sells-cowork .pdv-items td`
- L4467-4468 · `.sells-cowork .pdv-qty`
- L4468-4469 · `.sells-cowork .pdv-qty button`
- L4469-4470 · `.sells-cowork .pdv-qty span`
- L4470-4471 · `.sells-cowork .pdv-prod`
- L4471-4472 · `.sells-cowork .pdv-num`
- L4473-4474 · `.sells-cowork .pdv-rm`
- L4474-4479 · `.sells-cowork .pdv-right`
- L4479-4480 · `.sells-cowork .pdv-r-label`
- L4480-4485 · `.sells-cowork .pdv-client input`
- L4485-4486 · `.sells-cowork .pdv-pay-grid`
- L4486-4492 · `.sells-cowork .pdv-pay-btn`
- L4492-4493 · `.sells-cowork .pdv-pay-btn.active`
- L4493-4494 · `.sells-cowork .pdv-pay-icon`
- L4494-4499 · `.sells-cowork .pdv-total-block`
- L4499-4500 · `.sells-cowork .pdv-total-label`
- L4500-4501 · `.sells-cowork .pdv-total-value`
- L4501-4502 · `.sells-cowork .pdv-total-meta`
- L4502-4507 · `.sells-cowork .pdv-finalize`
- L4507-4508 · `.sells-cowork .pdv-finalize:disabled`
- L4508-4513 · `.sells-cowork .pdv-finalize kbd`
- L4513-4514 · `.sells-cowork .pdv-cancel`
- L4514-4516 · `/* ──── RECIBO (térmica + A4) ──── */ .sells-cowork .pdv-recibo-overlay`
- L4516-4517 · `.sells-cowork .rec-stage`
- L4517-4518 · `.sells-cowork .rec-layout`
- L4518-4519 · `.sells-cowork .rec-layout button`
- L4519-4520 · `.sells-cowork .rec-layout button.active`
- L4520-4521 · `.sells-cowork .rec-paper`
- L4521-4525 · `.sells-cowork .rec-termica`
- L4525-4526 · `.sells-cowork .rec-tx-header`
- L4526-4527 · `.sells-cowork .rec-tx-title`
- L4527-4528 · `.sells-cowork .rec-tx-meta`
- L4528-4529 · `.sells-cowork .rec-tx-divider`
- L4529-4530 · `.sells-cowork .rec-tx-items`
- L4530-4531 · `.sells-cowork .rec-tx-prod`
- L4531-4532 · `.sells-cowork .rec-tx-r`
- L4532-4533 · `.sells-cowork .rec-tx-totals`
- L4533-4534 · `.sells-cowork .rec-tx-totals > div`
- L4534-4535 · `.sells-cowork .rec-tx-total`
- L4535-4536 · `.sells-cowork .rec-tx-pay`
- L4536-4537 · `.sells-cowork .rec-tx-foot`
- L4537-4538 · `.sells-cowork .rec-tx-barcode`
- L4538-4539 · `.sells-cowork .rec-a4`
- L4539-4540 · `.sells-cowork .rec-a4-h`
- L4540-4541 · `.sells-cowork .rec-a4-logo`
- L4541-4542 · `.sells-cowork .rec-a4-tag`
- L4542-4543 · `.sells-cowork .rec-a4-meta`
- L4543-4544 · `.sells-cowork .rec-a4-title`
- L4544-4545 · `.sells-cowork .rec-a4-title h1`
- L4545-4546 · `.sells-cowork .rec-a4-title span`
- L4546-4547 · `.sells-cowork .rec-a4-block`
- L4547-4548 · `.sells-cowork .rec-a4-block h3`
- L4548-4549 · `.sells-cowork .rec-a4-items`
- L4549-4550 · `.sells-cowork .rec-a4-items th`
- L4550-4551 · `.sells-cowork .rec-a4-items td`
- L4551-4552 · `.sells-cowork .rec-a4-items th:nth-child(n+2), .sells-cowork .rec-a4-items td:nth-child(n+2)`
- L4552-4553 · `.sells-cowork .rec-a4-totals`
- L4553-4554 · `.sells-cowork .rec-a4-totals dl`
- L4554-4555 · `.sells-cowork .rec-a4-totals dt`
- L4555-4556 · `.sells-cowork .rec-a4-totals dd`
- L4556-4557 · `.sells-cowork .rec-a4-total`
- L4557-4558 · `.sells-cowork .rec-a4-foot`
- L4558-4560 · `/* ──── NF-e drawer extras ──── */ .sells-cowork .nfe-cfop`
- L4560-4564 · `.sells-cowork .nfe-cfop-btn, .sells-cowork .nfe-transp-btn`
- L4564-4565 · `.sells-cowork .nfe-cfop-btn.active, .sells-cowork .nfe-transp-btn.active`
- L4565-4566 · `.sells-cowork .nfe-cfop-btn strong`
- L4566-4567 · `.sells-cowork .nfe-cfop-btn span, .sells-cowork .nfe-transp-btn span`
- L4567-4568 · `.sells-cowork .nfe-tax-grid`
- L4568-4569 · `.sells-cowork .nfe-tax-grid > div`
- L4569-4570 · `.sells-cowork .nfe-tax-grid span`
- L4570-4571 · `.sells-cowork .nfe-tax-grid strong`
- L4571-4572 · `.sells-cowork .nfe-transp`
- L4572-4573 · `.sells-cowork .nfe-review-grid`
- L4573-4574 · `.sells-cowork .nfe-review-card`
- L4575-4576 · `.sells-cowork .nfe-review-card span`
- L4576-4577 · `.sells-cowork .nfe-review-card strong`
- L4578-4579 · `.sells-cowork .nfe-review-card .small`
- L4579-4584 · `.sells-cowork .nfe-callout`
- L4611-4612 · `.sells-cowork .os-kpi-alert`
- L4631-4632 · `.sells-cowork .os-tabs-r`
- L4656-4657 · `.sells-cowork .os-empty`
- L4667-4668 · `.sells-cowork .os-drawer-id`
- L4670-4671 · `.sells-cowork .os-drawer-head-r`
- L4679-4684 · `/* Stepper for create/nfe drawers */ .sells-cowork .vd-stepper`
- L4684-4689 · `.sells-cowork .vd-step`
- L4689-4695 · `.sells-cowork .vd-step-num`
- L4695-4696 · `.sells-cowork .vd-step.active .vd-step-num`
- L4696-4697 · `.sells-cowork .vd-step.active`
- L4697-4698 · `.sells-cowork .vd-step.done .vd-step-num`
- L4698-4699 · `.sells-cowork .vd-step-sep`
- L4699-4707 · `/* ────────────────── Embed (iframe consolidator) ────────────────── */ .sells-cowork .embed-view`
- L4707-4713 · `.sells-cowork .embed-view.embed-fs`
- L4713-4725 · `.sells-cowork .embed-toolbar`
- L4725-4730 · `.sells-cowork .embed-toolbar-l, .sells-cowork .embed-toolbar-r`
- L4730-4736 · `.sells-cowork .embed-label`
- L4736-4745 · `.sells-cowork .embed-src`
- L4745-4759 · `.sells-cowork .embed-btn`
- L4759-4763 · `.sells-cowork .embed-btn:hover`
- L4763-4768 · `.sells-cowork .embed-btn.on`
- L4768-4775 · `.sells-cowork .embed-iframe`
- L4779-4785 · `.sells-cowork .sb-divider`
- L4785-4788 · `.sells-cowork .sb-group-flat`
- L4814-4822 · `.sells-cowork .topnav-area`
- L4822-4829 · `.sells-cowork .topnav-area-label`
- L4829-4836 · `.sells-cowork .topnav-pills`
- L4836-4852 · `.sells-cowork .topnav-pill`
- L4852-4855 · `.sells-cowork .topnav-pill svg`
- L4855-4859 · `.sells-cowork .topnav-pill:hover`
- L4859-4860 · `.sells-cowork .topnav-pill:hover svg`
- L4860-4865 · `.sells-cowork .topnav-pill.active`
- L4865-4866 · `.sells-cowork .topnav-pill.active svg`
- L4918-4920 · `/* esconde estilo lean antigo */ .sells-cowork .sb-divider, .sells-cowork .sb-menu-lean .sb-divider`
- L4920-4921 · `.sells-cowork .sb-group-flat`
- L4950-4970 · `/* Alça flutuante para reabrir quando oculta */ .sells-cowork .sb-reopen-handle`
- L4970-4975 · `.sells-cowork .sb-reopen-handle:hover`
- L5045-5057 · `.sells-cowork .sb-rail-group-pill`
- L5081-5089 · `/* Dropdown de empresa quando em rail (alinhado à direita do rail) */ .sells-cowork .sb-dd-rail`
- L5562-5565 · `.sells-cowork .vd-drawer-aplus .os-drawer-head-r`
- L5565-5570 · `.sells-cowork .vd-drawer-aplus .vd-drawer-total`
- L5570-5577 · `/* drawer tabs */ .sells-cowork .vd-drawer-aplus .vd-drawer-tabs`
- L5577-5585 · `.sells-cowork .vd-drawer-aplus .vd-drawer-tab`
- L5585-5586 · `.sells-cowork .vd-drawer-aplus .vd-drawer-tab:hover`
- L5586-5591 · `.sells-cowork .vd-drawer-aplus .vd-drawer-tab.on`
- L5591-5596 · `.sells-cowork .vd-drawer-aplus .vd-drawer-tab-ct`
- L5596-5599 · `.sells-cowork .vd-drawer-aplus .vd-drawer-tab.on .vd-drawer-tab-ct`
- L5599-5602 · `.sells-cowork .vd-drawer-aplus .vd-drawer-body`
- L5602-5606 · `.sells-cowork .vd-drawer-aplus .vd-drawer-body .vd-section h3`
- L5606-5611 · `/* Itens cards */ .sells-cowork .vd-drawer-aplus .vd-items-cards`
- L5611-5617 · `.sells-cowork .vd-drawer-aplus .vd-item-card`
- L5617-5618 · `.sells-cowork .vd-drawer-aplus .vd-item-card:last-child`
- L5618-5619 · `.sells-cowork .vd-drawer-aplus .vd-item-c-l b`
- L5619-5620 · `.sells-cowork .vd-drawer-aplus .vd-item-c-l small`
- L5620-5623 · `.sells-cowork .vd-drawer-aplus .vd-item-c-qty, .sells-cowork .vd-drawer-aplus .vd-item-c-unit, .sells-cowork .vd-drawer-`
- L5623-5624 · `.sells-cowork .vd-drawer-aplus .vd-item-c-sub`
- L5624-5629 · `.sells-cowork .vd-drawer-aplus .vd-items-foot`
- L5629-5630 · `.sells-cowork .vd-drawer-aplus .vd-items-foot span`
- L5630-5631 · `.sells-cowork .vd-drawer-aplus .vd-items-foot b`
- L5631-5639 · `/* Emit panel */ .sells-cowork .vd-drawer-aplus .vd-emit`
- L5639-5640 · `.sells-cowork .vd-drawer-aplus .vd-emit > div b`
- L5640-5641 · `.sells-cowork .vd-drawer-aplus .vd-emit > div small`
- L5642-5648 · `/* Sub-tabs fiscal */ .sells-cowork .vd-drawer-aplus .vd-fsub`
- L5648-5655 · `.sells-cowork .vd-drawer-aplus .vd-fsub button`
- L5655-5656 · `.sells-cowork .vd-drawer-aplus .vd-fsub button.on`
- L5656-5660 · `.sells-cowork .vd-drawer-aplus .vd-fsub button span`
- L5660-5664 · `/* Fiscal card grid */ .sells-cowork .vd-drawer-aplus .vd-fcard-grid`
- L5667-5672 · `/* Fiscal card */ .sells-cowork .vd-drawer-aplus .vd-fcard`
- L5672-5676 · `.sells-cowork .vd-drawer-aplus .vd-fcard-h`
- L5676-5679 · `.sells-cowork .vd-drawer-aplus .vd-fcard-h h4`
- L5679-5684 · `.sells-cowork .vd-drawer-aplus .vd-fcard-date`
- L5684-5685 · `.sells-cowork .vd-drawer-aplus .vd-fcard-date span`
- L5685-5691 · `.sells-cowork .vd-drawer-aplus .vd-fcard-fail`
- L5696-5697 · `.sells-cowork .vd-drawer-aplus .vd-fcard-fail b`
- L5697-5701 · `.sells-cowork .vd-drawer-aplus .vd-fcard-meta`
- L5701-5702 · `.sells-cowork .vd-drawer-aplus .vd-fcard-meta dt`
- L5702-5703 · `.sells-cowork .vd-drawer-aplus .vd-fcard-meta dd`
- L5703-5710 · `.sells-cowork .vd-drawer-aplus .vd-fcard-chave`
- L5710-5714 · `.sells-cowork .vd-drawer-aplus .vd-fcard-chave-num`
- L5714-5720 · `.sells-cowork .vd-drawer-aplus .vd-fcard-copy`
- L5724-5730 · `/* Timeline SEFAZ horizontal */ .sells-cowork .vd-drawer-aplus .vd-fcard-tl`
- L5730-5734 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step`
- L5734-5738 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step::before`
- L5738-5739 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step:first-child::before`
- L5739-5746 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step-d`
- L5746-5749 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step.done .vd-fcard-step-d`
- L5749-5750 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step.done::before`
- L5753-5759 · `.sells-cowork .vd-drawer-aplus .vd-fcard-step-l`
- L5761-5765 · `.sells-cowork .vd-drawer-aplus .vd-fcard-cce`
- L5765-5770 · `.sells-cowork .vd-drawer-aplus .vd-fcard-cce summary`
- L5770-5771 · `.sells-cowork .vd-drawer-aplus .vd-fcard-cce summary:hover`
- L5771-5774 · `.sells-cowork .vd-drawer-aplus .vd-fcard-cce p`
- L5774-5778 · `.sells-cowork .vd-drawer-aplus .vd-fcard-ctas`
- L5778-5785 · `.sells-cowork .vd-drawer-aplus .vd-fcard-cta`
- L5785-5786 · `.sells-cowork .vd-drawer-aplus .vd-fcard-cta:hover`
- L5786-5792 · `/* Breakdown box */ .sells-cowork .vd-drawer-aplus .vd-fbreak`
- L5792-5797 · `.sells-cowork .vd-drawer-aplus .vd-fbreak h4`
- L5797-5801 · `.sells-cowork .vd-drawer-aplus .vd-fbreak dl`
- L5801-5802 · `.sells-cowork .vd-drawer-aplus .vd-fbreak dt`
- L5802-5803 · `.sells-cowork .vd-drawer-aplus .vd-fbreak dd`
- L5803-5807 · `.sells-cowork .vd-drawer-aplus .vd-fbreak dt.tot, .sells-cowork .vd-drawer-aplus .vd-fbreak dd.tot`
- L5807-5808 · `.sells-cowork .vd-drawer-aplus .vd-fbreak dd.tot`
- L5808-5813 · `/* Payment tab */ .sells-cowork .vd-drawer-aplus .vd-pay-meta`
- L5813-5817 · `.sells-cowork .vd-drawer-aplus .vd-pay-meta dl`
- L5817-5818 · `.sells-cowork .vd-drawer-aplus .vd-pay-meta dt`
- L5818-5819 · `.sells-cowork .vd-drawer-aplus .vd-pay-meta dd`
- L5819-5822 · `.sells-cowork .vd-drawer-aplus .vd-pay-meta .vd-comm`
- L5822-5823 · `.sells-cowork .vd-drawer-aplus .vd-pay-meta .vd-comm small`
- L5823-5829 · `/* Timeline tab */ .sells-cowork .vd-drawer-aplus .vd-tline`
- L5829-5833 · `.sells-cowork .vd-drawer-aplus .vd-tline::before`
- L5833-5836 · `.sells-cowork .vd-drawer-aplus .vd-tline-it`
- L5836-5842 · `.sells-cowork .vd-drawer-aplus .vd-tline-it::before`
- L5842-5846 · `.sells-cowork .vd-drawer-aplus .vd-tline-it small`
- L5846-5849 · `.sells-cowork .vd-drawer-aplus .vd-tline-it b`
- L6138-6142 · `/* ── Tab IA no drawer ── */ .sells-cowork .vd-drawer-aplus .vd-drawer-tab.vd-tab-ai`
- L6142-6147 · `.sells-cowork .vd-drawer-aplus .vd-drawer-tab.vd-tab-ai.on`
- L6259-6261 · `/* ── História: stats grid ── */ .sells-cowork .vd-ai-history`
- L6291-6298 · `.sells-cowork .vd-ai-prods`
- L6298-6303 · `.sells-cowork .vd-ai-prods > small`
- L6303-6304 · `.sells-cowork .vd-ai-prods ul`
- L6304-6310 · `.sells-cowork .vd-ai-prods li`
- L6310-6311 · `.sells-cowork .vd-ai-prods li:first-child`
- L6345-6353 · `.sells-cowork .vd-ai-suggest-cta`
- L6353-6354 · `.sells-cowork .vd-ai-suggest-cta:hover`
- L6443-6453 · `/* ═══════════════════════════════════════════════════════════════════ VENDAS · REFINO #3 KB-9.75 · CURADORIA + GUIA (20`
- L6453-6460 · `/* ── Tab counter comentários ── */ .sells-cowork .vd-drawer-aplus .vd-drawer-tab-cmt`
- L6629-6640 · `/* ── Troubleshooter button ── */ .sells-cowork .vd-drawer-aplus .vd-trouble-btn`
- L6640-6644 · `.sells-cowork .vd-drawer-aplus .vd-trouble-btn:hover`
- L6644-6651 · `.sells-cowork .vd-drawer-aplus .vd-trouble-ic`
- L6651-6652 · `.sells-cowork .vd-drawer-aplus .vd-trouble-lbl`
- L6652-6653 · `.sells-cowork .vd-drawer-aplus .vd-trouble-lbl b`
- L6653-6658 · `.sells-cowork .vd-drawer-aplus .vd-trouble-ct`
- L6689-6693 · `/* ── Botão Apresentar no drawer footer ── */ .sells-cowork .vd-drawer-aplus .vd-btn-present`
- L6693-6697 · `.sells-cowork .vd-drawer-aplus .vd-btn-present:hover`
- L7013-7017 · `.sells-cowork .vd-drawer-aplus .vd-msg-h`
- L7017-7018 · `.sells-cowork .vd-drawer-aplus .vd-msg-h h4`
- L7018-7022 · `.sells-cowork .vd-drawer-aplus .vd-msg-tpls`
- L7022-7028 · `.sells-cowork .vd-drawer-aplus .vd-msg-tpl`
- L7028-7029 · `.sells-cowork .vd-drawer-aplus .vd-msg-tpl:hover`
- L7029-7033 · `.sells-cowork .vd-drawer-aplus .vd-msg-tpl.on`
- L7044-7050 · `.sells-cowork .vd-drawer-aplus .vd-msg-var`
- L7050-7055 · `.sells-cowork .vd-drawer-aplus .vd-msg-var .k`
- L7056-7060 · `.sells-cowork .vd-drawer-aplus .vd-msg-var .v`
- L7060-7065 · `.sells-cowork .vd-drawer-aplus .vd-msg-preview`
- L7077-7088 · `/* ══════════════════════════════════════════ ART-SLOT (preview da arte por item) ══════════════════════════════════════`
- L7088-7091 · `.sells-cowork .vd-art.empty`
- L7097-7101 · `.sells-cowork .vd-art img`
- L7101-7108 · `.sells-cowork .vd-art-empty`
- L7108-7109 · `.sells-cowork .vd-art-empty:hover`
- L7109-7110 · `.sells-cowork .vd-art-ic`
- L7110-7115 · `.sells-cowork .vd-art-empty small`
- L7115-7116 · `.sells-cowork .vd-art-empty input`
- L7116-7124 · `.sells-cowork .vd-art-rm`
- L7124-7125 · `.sells-cowork .vd-art:hover .vd-art-rm`
- L7184-7186 · `/* Esconder a antiga topnav (vendas-extras) — agora vive em "Visões ▾" */ .sells-cowork .vendas-module-no-subnav .vm-sub`
- L7450-7451 · `.sells-cowork .vd-src-balcao`
- L7452-7453 · `.sells-cowork .vd-src-oficina`
- L7454-7455 · `.sells-cowork .vd-src-online`

</details>
