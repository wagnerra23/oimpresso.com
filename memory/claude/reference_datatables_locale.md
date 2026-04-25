---
name: DataTables locale pt-BR
description: Locale pt-BR do DataTables vive em public/locale/datatables/pt-BR.json. Incluir via language: { url } em toda init de DataTable nova.
type: reference
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Locale pt-BR compartilhado para todas as tabelas DataTables do sistema está em `public/locale/datatables/pt-BR.json` (criado em 2026-04-24). Contém traduções de `info`, `lengthMenu`, `paginate`, `buttons` (CSV/Excel/PDF/Imprimir/Visibilidade), `zeroRecords`, `search`, etc.

Padrão de uso em qualquer blade que inicialize DataTable:
```js
$('#meu_table').DataTable({
    language: { url: '{{ asset('locale/datatables/pt-BR.json') }}' },
    // ...
});
```

Sem esse arquivo, a tabela exibe strings em inglês: `Showing X to Y of Z entries`, `Search:`, `Previous/Next`, etc.

Referência: primeira aplicação em `resources/views/sell/index.blade.php` (commit `dcefd087` em `6.7-bootstrap`). Várias outras telas ainda usam DataTable sem esse URL — candidato a sweep global quando o tempo permitir.
