import DOMPurify from 'dompurify';
import hljs from 'highlight.js';

export function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

export function autoCloseMarkdown(text) {
  let res = text || '';
  
  // 1. Auto-close code blocks ```
  const codeBlocks = (res.match(/```/g) || []).length;
  if (codeBlocks % 2 !== 0) {
    res += '\n```';
  }
  
  // 2. Auto-close bold **
  const boldCount = (res.match(/\*\*/g) || []).length;
  if (boldCount % 2 !== 0) {
    res += '**';
  }
  
  // 3. Auto-close italic *
  const italicCount = (res.match(/(?<!\*)\*(?!\*)/g) || []).length;
  if (italicCount % 2 !== 0) {
    res += '*';
  }
  
  // 4. Auto-close inline code `
  const inlineCodeCount = (res.match(/(?<!`)`(?!`)/g) || []).length;
  if (inlineCodeCount % 2 !== 0) {
    res += '`';
  }
  
  return res;
}

export function formatMd(text) {
  if (!text) return '';
  text = text.replace(/\r\n/g, '\n');

  // Code blocks with syntax highlighting
  text = text.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
    const trimmed = code.trim();
    const label = lang || 'code';
    let highlighted = escapeHtml(trimmed);
    if (lang && hljs.getLanguage(lang)) {
        try {
            highlighted = hljs.highlight(trimmed, { language: lang }).value;
        } catch (e) {}
    } else {
        try {
            highlighted = hljs.highlightAuto(trimmed).value;
        } catch (e) {}
    }
    const b64 = btoa(unescape(encodeURIComponent(trimmed)));
    return `<div class="code-block">
      <div class="code-block-header">
        <span class="code-block-lang">${label}</span>
        <div class="code-block-actions" style="display:flex;gap:6px;">
          <button class="btn-preview-code" title="Просмотр кода" data-code="${b64}" data-ext="${label}">Просмотр</button>
          <button class="btn-copy-code" title="Копировать" data-code="${b64}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          </button>
          <button class="btn-download-code" title="Скачать код" data-code="${b64}" data-ext="${label}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          </button>
        </div>
      </div>
      <pre><code class="hljs">${highlighted}</code></pre>
    </div>`;
  });

  // Inline code
  text = text.replace(/`([^`]+)`/g, (_, c) => `<code>${escapeHtml(c)}</code>`);

  // Headers
  text = text.replace(/^### (.+)$/gm, '<h3>$1</h3>');
  text = text.replace(/^## (.+)$/gm, '<h2>$1</h2>');
  text = text.replace(/^# (.+)$/gm, '<h1>$1</h1>');

  // HR
  text = text.replace(/^---$/gm, '<hr>');

  // Blockquotes
  text = text.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
  text = text.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>');

  // Numbered lists
  text = text.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

  // Bold
  text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

  // Images
  text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g,
    (_, alt, url) => `<div class="generated-img-wrap">
      <img src="${url}" alt="${alt}" style="max-width:100%;border-radius:12px;margin-top:8px;display:block;">
      <a href="${url}" download class="btn-download-img">⬇ Скачать</a>
    </div>`);

  // Audio - match [any text](url.mp3) or [audio](url)
  text = text.replace(/\[([^\]]*)\]\(([^)]+\.mp3)\)/gi,
    (_, text, url) => `<audio controls style="width:100%;margin-top:8px;border-radius:8px;"><source src="${url}" type="audio/mpeg"></audio>`);
    
  // Fallback for strict [audio](url) just in case
  text = text.replace(/\[audio\]\(([^)]+)\)/g,
    (_, url) => `<audio controls style="width:100%;margin-top:8px;border-radius:8px;"><source src="${url}" type="audio/mpeg"></audio>`);

  // Italic
  text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');

  // Tables
  text = text.replace(/(\|.+\|\n\|[-| :]+\|\n(?:\|.+\|\n?)+)/g, (table) => {
    const rows = table.trim().split('\n');
    const headers = rows[0].split('|').filter(c => c.trim());
    const body = rows.slice(2).map(row => {
      const cells = row.split('|').filter(c => c.trim());
      return '<tr>' + cells.map(c => `<td>${c.trim()}</td>`).join('') + '</tr>';
    }).join('');
    const thead = '<tr>' + headers.map(h => `<th>${h.trim()}</th>`).join('') + '</tr>';
    return `<div class="table-wrap"><table><thead>${thead}</thead><tbody>${body}</tbody></table></div>`;
  });

  // Unordered lists
  text = text.replace(/^[\-\*] (.+)$/gm, '<li>$1</li>');
  text = text.replace(/(<li>.*<\/li>\n?)+/g, s => `<ul>${s}</ul>`);

  // Paragraphs
  text = text.split(/\n{2,}/).map(p => {
    p = p.trim();
    if (!p) return '';
    if (p.startsWith('<')) return p;
    return `<p>${p.replace(/\n/g, '<br>')}</p>`;
  }).filter(Boolean).join('');

  return text;
}

export function renderMarkdown(text) {
  if (!text) return '';
  text = text.replace(/\r\n/g, '\n');
  
  // Merge consecutive think blocks (e.g. separated by a tool call)
  text = text.replace(/<\/think>[\s\n]*(?:<tool_call>[\s\S]*?<\/tool_call>)?[\s\n]*<think>/g, '\n\n');
  
  // Completely hide any remaining tool calls from the final output
  text = text.replace(/<tool_call>[\s\S]*?<\/tool_call>/g, '');

  let html = '';
  // Используем регулярку для поиска закрытых или открытых блоков think в любом месте текста
  const thinkRegex = /<think>([\s\S]*?)(?:<\/think>|$)/g;
  
  let lastIndex = 0;
  let match;

  while ((match = thinkRegex.exec(text)) !== null) {
    // 1. Текст до блока think
    const beforeText = text.slice(lastIndex, match.index);
    if (beforeText) {
      html += formatMd(autoCloseMarkdown(beforeText));
    }

    // 2. Содержимое think
    const content = match[1].trim();
    // Проверяем, был ли тег закрыт (оригинальный кусок текста заканчивается ли на </think>)
    const isClosed = match[0].endsWith('</think>');
    
    if (isClosed) {
      html += `<details class="think-block">
      <summary class="think-summary">💭 Размышления</summary>
      <div class="think-content">${formatMd(autoCloseMarkdown(content))}</div>
    </details>`;
    } else {
      // Если блок не закрыт, значит идет стриминг
      html += `<details class="think-block" open>
      <summary class="think-summary pulse">💭 Размышляю...</summary>
      <div class="think-content">${formatMd(autoCloseMarkdown(content))}</div>
    </details>`;
    }

    lastIndex = thinkRegex.lastIndex;
  }

  // 3. Добавляем оставшийся текст после последнего think-блока
  const remainingText = text.slice(lastIndex);
  if (remainingText) {
    html += formatMd(autoCloseMarkdown(remainingText));
  }

  return DOMPurify.sanitize(html, { ADD_TAGS: ['details', 'summary'] });
}
