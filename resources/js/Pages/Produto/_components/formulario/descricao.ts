// Descrição do produto no Editar (UC-PEDIT-33).
//
// A tela antiga (edit.blade.php / create.blade.php) grava a descrição pelo editor rico (TinyMCE),
// então o texto vem com HTML: `<p>teste obeservacao</p>`. A tela React edita em caixa de texto
// simples. Para não mostrar as tags nem estragar o texto:
// - ao abrir, a caixa mostra o texto (sem tags, com as quebras de parágrafo);
// - ao salvar sem mexer, vai a descrição gravada, igual (a formatação fica);
// - ao salvar mexida, vai o texto novo. Se a gravada era HTML, o texto vai em parágrafos HTML com os
//   caracteres especiais escapados, para a tela antiga continuar mostrando as linhas e um "<" digitado
//   não virar tag. A formatação que a caixa simples não mostra (negrito, listas) se perde só nesse caso.

const TEM_TAG = /<\/?[a-z][\s\S]*?>/i;

/** A descrição gravada tem HTML (veio do editor rico da tela antiga)? */
export function descricaoEhHtml(gravada: string | null | undefined): boolean {
  return TEM_TAG.test(gravada ?? '');
}

function decodificarEntidades(texto: string): string {
  if (typeof document === 'undefined') return texto;
  const caixa = document.createElement('textarea');
  caixa.innerHTML = texto;
  return caixa.value;
}

/** O texto para a caixa: sem tags, com uma linha por parágrafo ou quebra. */
export function textoDaDescricao(gravada: string | null | undefined): string {
  const valor = gravada ?? '';
  if (!descricaoEhHtml(valor)) return valor;
  const semTags = valor
    .replace(/\r\n?/g, '\n')
    .replace(/\n/g, ' ')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|li|h[1-6]|tr)\s*>/gi, '\n')
    .replace(/<[^>]*>/g, '');
  return decodificarEntidades(semTags)
    .split('\n')
    .map((l) => l.replace(/[ \t\u00a0]+/g, ' ').trim())
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

function escapar(texto: string): string {
  return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/** O texto da caixa em parágrafos HTML: linha em branco separa parágrafos, linha simples vira <br>. */
function textoParaHtml(texto: string): string {
  const paragrafos = texto
    .replace(/\r\n?/g, '\n')
    .split(/\n{2,}/)
    .map((p) => p.trim())
    .filter((p) => p !== '');
  return paragrafos.map((p) => `<p>${p.split('\n').map(escapar).join('<br>')}</p>`).join('');
}

/** O que vai no envio: a gravada quando o texto não mudou; senão o texto novo (em HTML se a gravada era). */
export function descricaoParaEnvio(gravada: string | null | undefined, textoInicial: string, textoAtual: string): string {
  if (textoAtual === textoInicial) return gravada ?? '';
  return descricaoEhHtml(gravada) ? textoParaHtml(textoAtual) : textoAtual;
}
