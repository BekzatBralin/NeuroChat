import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import { Marked } from 'marked';

export function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function encodeCode(code) {
  const bytes = new TextEncoder().encode(code);
  let binary = '';
  for (let i = 0; i < bytes.length; i += 8192) {
    binary += String.fromCharCode(...bytes.subarray(i, i + 8192));
  }
  return btoa(binary);
}

const markdown = new Marked({
  gfm: true,
  breaks: true,
  renderer: {
    code({ text, lang }) {
      const code = text.replace(/\n$/, '');
      const language = lang?.trim().split(/\s+/)[0] || 'code';
      let highlighted = escapeHtml(code);
      if (lang && hljs.getLanguage(language)) {
        try {
          highlighted = hljs.highlight(code, { language }).value;
        } catch {
          // Show the original code if highlighting fails.
        }
      }
      const encoded = encodeCode(code);
      const label = escapeHtml(language);
      return `<div class="code-block">
        <div class="code-block-header">
          <span class="code-block-lang">${label}</span>
          <div class="code-block-actions" style="display:flex;gap:6px;">
            <button class="btn-preview-code" title="Просмотр кода" data-code="${encoded}" data-ext="${label}">Просмотр</button>
            <button class="btn-copy-code" title="Копировать" data-code="${encoded}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            </button>
            <button class="btn-download-code" title="Скачать код" data-code="${encoded}" data-ext="${label}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            </button>
          </div>
        </div>
        <pre><code class="hljs">${highlighted}</code></pre>
      </div>`;
    },
    image({ href, text }) {
      const url = escapeHtml(href);
      const alt = escapeHtml(text);
      return `<span class="generated-img-wrap">
        <img src="${url}" alt="${alt}" style="max-width:100%;border-radius:12px;margin-top:8px;display:block;">
        <a href="${url}" download class="btn-download-img">⬇ Скачать</a>
      </span>`;
    },
    link({ href, text, tokens }) {
      const url = escapeHtml(href);
      if (/\.mp3(?:[?#]|$)/i.test(href) || /^data:audio\//i.test(href) || text.toLowerCase() === 'audio') {
        return `<audio controls style="width:100%;margin-top:8px;border-radius:8px;"><source src="${url}" type="audio/mpeg"></audio>`;
      }
      const label = this.parser.parseInline(tokens);
      return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="md-link">${label}</a>`;
    },
  },
});

function renderToolUse(raw) {
  try {
    const data = JSON.parse(raw.trim());
    const name = data.name || 'unknown';
    let title = `🛠 Инструмент: ${name}`;
    if (name === 'web_search') title = `🔍 Поиск: ${data.args?.query || ''}`;
    else if (name === 'run_python') title = '🐍 Выполнение Python скрипта';
    else if (name === 'calculator') title = `🧮 Расчет: ${data.args?.a} ${data.args?.operator} ${data.args?.b}`;
    else if (name === 'generate_image') title = '🎨 Рисую изображение...';
    else if (name === 'generate_music') title = '🎵 Пишу музыку...';

    const args = escapeHtml(JSON.stringify(data.args ?? {}, null, 2));
    return `<details class="tool-use-block">
      <summary class="tool-use-summary">${escapeHtml(title)}</summary>
      <div class="tool-use-content"><pre><code>${args}</code></pre></div>
    </details>`;
  } catch {
    return '';
  }
}

export function formatMd(text) {
  if (!text) return '';
  const pieces = String(text).replace(/\r\n/g, '\n').split(/(<tool_use>[\s\S]*?<\/tool_use>)/g);
  return pieces.map(piece => {
    const tool = piece.match(/^<tool_use>([\s\S]*?)<\/tool_use>$/);
    if (tool) return renderToolUse(tool[1]);
    // A partial tool block can appear while streaming; wait for its closing tag.
    const safeText = piece.replace(/<tool_use>[\s\S]*$/g, '');
    return markdown.parse(safeText);
  }).join('').replace(/<table>/g, '<div class="table-wrap"><table>').replace(/<\/table>/g, '</table></div>');
}

export function renderMarkdown(input) {
  if (!input) return '';
  let text = String(input).replace(/\r\n/g, '\n');

  // Join thought sections separated only by a tool call, then hide old XML calls.
  text = text.replace(/<\/think>[\s\n]*(?:<tool_call>[\s\S]*?<\/tool_call>)?[\s\n]*<think>/g, '\n\n');
  text = text.replace(/<tool_call>[\s\S]*?<\/tool_call>/g, '');

  let html = '';
  let lastIndex = 0;
  const thinkRegex = /<think>([\s\S]*?)(?:<\/think>|$)/g;
  let match;

  while ((match = thinkRegex.exec(text)) !== null) {
    html += formatMd(text.slice(lastIndex, match.index));
    const content = formatMd(match[1].trim());
    const closed = match[0].endsWith('</think>');
    html += `<details class="think-block"${closed ? '' : ' open'}>
      <summary class="think-summary${closed ? '' : ' pulse'}">${closed ? '💭 Размышления' : '💭 Размышляю...'}</summary>
      <div class="think-content">${content}</div>
    </details>`;
    lastIndex = thinkRegex.lastIndex;
  }

  html += formatMd(text.slice(lastIndex));
  return DOMPurify.sanitize(html, {
    ADD_TAGS: ['details', 'summary', 'audio', 'source'],
    ADD_ATTR: ['target', 'download', 'controls', 'data-code', 'data-ext', 'rel'],
  });
}
