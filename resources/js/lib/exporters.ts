import TurndownService from 'turndown';

const turndown = new TurndownService({ headingStyle: 'atx', bulletListMarker: '-', codeBlockStyle: 'fenced', emDelimiter: '*' });
// Markdown has no underline; keep it as inline HTML so it survives a re-import as visible text.
turndown.addRule('underline', { filter: ['u'], replacement: (content) => `<u>${content}</u>` });
turndown.addRule('strike', { filter: ['s', 'del'], replacement: (content) => `~~${content}~~` });

function safeName(title: string): string {
  return (title.trim() || 'document').replace(/[\\/:*?"<>|]+/g, '-').slice(0, 120);
}

function download(filename: string, body: string, type: string): void {
  const url = URL.createObjectURL(new Blob([body], { type }));
  const a = Object.assign(document.createElement('a'), { href: url, download: filename });
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function exportMarkdown(title: string, html: string): void {
  download(`${safeName(title)}.md`, `# ${title}\n\n${turndown.turndown(html)}\n`, 'text/markdown;charset=utf-8');
}

export function exportHtml(title: string, html: string): void {
  const escaped = title.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c] ?? c);
  const page = `<!DOCTYPE html>\n<html lang="en"><head><meta charset="utf-8"><title>${escaped}</title>
<style>body{max-width:720px;margin:48px auto;padding:0 16px;font:17px/1.65 system-ui,sans-serif;color:#1d1f20}h1,h2,h3{font-family:Georgia,serif;line-height:1.15}</style>
</head><body>\n${html}\n</body></html>\n`;
  download(`${safeName(title)}.html`, page, 'text/html;charset=utf-8');
}
