import assert from 'node:assert/strict';
import test from 'node:test';
import { formatMd } from '../src/utils/markdown.js';

test('renders nested lists alongside bold and italic text', () => {
  const input = `* **Название видео:** пример.
* **В плеере:** надпись *«Пример»*.
* **Под плеером:**
  * Информационная плашка.
  * Статистика видео.`;

  const html = formatMd(input);
  assert.equal((html.match(/<ul>/g) || []).length, 2);
  assert.equal((html.match(/<li>/g) || []).length, 5);
  assert.match(html, /<strong>В плеере:<\/strong> надпись <em>«Пример»<\/em>/);
  assert.doesNotMatch(html, /<em>\s*<strong>/);
});

test('does not append an asterisk after a list followed by plain text', () => {
  const html = formatMd('* Пункт списка\n\nТаким образом, iPhone — командная работа Apple.');
  assert.match(html, /<p>Таким образом, iPhone — командная работа Apple\.<\/p>/);
  assert.doesNotMatch(html, /Apple\.\*/);
});
