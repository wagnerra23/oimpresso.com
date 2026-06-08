// Onda 13 v9,75 — Print extrato A4 styled.

const STYLE_ID = 'oimpresso-rec-print-styles';

export function installPrintStyles(): void {
  if (typeof document === 'undefined') return;
  if (document.getElementById(STYLE_ID)) return;
  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = `
    @media print {
      body:not(.rec-printing) > * { display: block; }
      body.rec-printing > *:not(#app) { display: none !important; }
      body.rec-printing #app { display: block !important; }
      body.rec-printing #app > *:not([data-print-target]) { display: none !important; }
      body.rec-printing [data-print-target] { display: block !important; padding: 20px; }
    }
  `;
  document.head.appendChild(style);
}

export function printSubDetail(subId: number): void {
  if (typeof document === 'undefined') return;
  document.body.classList.add('rec-printing');
  document.body.setAttribute('data-print-sub-id', String(subId));
  setTimeout(() => {
    window.print();
    setTimeout(() => {
      document.body.classList.remove('rec-printing');
      document.body.removeAttribute('data-print-sub-id');
    }, 200);
  }, 80);
}
