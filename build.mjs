import { readFile, writeFile, mkdir, cp, stat, rm, readdir } from 'node:fs/promises';
import { minify } from 'html-minifier-terser';
import path from 'node:path';

const SRC = 'src';
const OUT = '.';
const isRemote = (url) => /^(https?:)?\/\//.test(url);

async function inlineCssImports(filePath) {
  const dir = path.dirname(filePath);
  let css = await readFile(filePath, 'utf8');
  const importRe = /@import\s+url\(\s*['"]?([^'")]+)['"]?\s*\)\s*;?/g;

  const locals = [];
  css.replace(importRe, (full, url) => {
    if (!isRemote(url)) locals.push({ full, url });
    return full;
  });

  for (const { full, url } of locals) {
    const resolved = await inlineCssImports(path.join(dir, url));
    css = css.replace(full, resolved);
  }
  return css;
}

async function build() {
  const srcHtml = path.join(SRC, 'index.html');
  let html = await readFile(srcHtml, 'utf8');

  const linkRe = /<link\s+[^>]*rel="stylesheet"[^>]*href="([^"]+)"[^>]*>/g;
  for (const m of [...html.matchAll(linkRe)]) {
    const href = m[1];
    if (isRemote(href)) continue;
    const css = await inlineCssImports(path.join(SRC, href));
    html = html.replace(m[0], `<style>\n${css}\n</style>`);
  }

  const scriptRe = /<script\s+[^>]*\bsrc="([^"]+)"[^>]*><\/script>/g;
  for (const m of [...html.matchAll(scriptRe)]) {
    const src = m[1];
    if (isRemote(src)) continue;
    const js = await readFile(path.join(SRC, src), 'utf8');
    html = html.replace(m[0], `<script>\n${js}\n</script>`);
  }

  const minified = await minify(html, {
    collapseWhitespace: true,
    conservativeCollapse: false,
    removeComments: true,
    minifyCSS: true,
    minifyJS: true,
    removeRedundantAttributes: true,
    useShortDoctype: true,
    sortAttributes: true,
    sortClassName: true,
  });

  const outHtml = path.join(OUT, 'index.html');
  await writeFile(outHtml, minified);

  const outImg = path.join(OUT, 'assets', 'img');
  await rm(outImg, { recursive: true, force: true });
  await mkdir(path.dirname(outImg), { recursive: true });
  await cp(path.join(SRC, 'assets', 'img'), outImg, { recursive: true });

  const walk = async (dir) => {
    let bytes = 0, count = 0;
    for (const e of await readdir(dir, { withFileTypes: true })) {
      const p = path.join(dir, e.name);
      if (e.isDirectory()) {
        const sub = await walk(p);
        bytes += sub.bytes; count += sub.count;
      } else {
        bytes += (await stat(p)).size; count += 1;
      }
    }
    return { bytes, count };
  };
  const htmlSrc = (await stat(srcHtml)).size;
  const css = await walk(path.join(SRC, 'assets', 'css'));
  const js = await walk(path.join(SRC, 'assets', 'js'));
  const srcTotal = htmlSrc + css.bytes + js.bytes;
  const outSize = (await stat(outHtml)).size;
  const pct = ((1 - outSize / srcTotal) * 100).toFixed(1);
  const kb = (n) => `${(n / 1024).toFixed(1)} KB`;
  console.log(`source  ${kb(srcTotal)} across ${1 + css.count + js.count} files (HTML ${kb(htmlSrc)} + CSS ${kb(css.bytes)} + JS ${kb(js.bytes)})`);
  console.log(`output  ${kb(outSize)} in 1 file  (-${pct}%, plus the saved HTTP round-trips)`);
}

build().catch((e) => { console.error(e); process.exit(1); });
