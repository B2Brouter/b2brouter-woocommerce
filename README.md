# B2Brouter for WooCommerce — public website

This branch (`gh-pages`) is the source of <https://woocommerce.b2brouter.net/>.

For the full contribution flow (forking, branching, opening PRs against the right base), see `docs/PUBLIC_WEBSITE.md` on the `main` branch.

## Build

The site is authored in `src/` and built into a single inlined+minified `index.html` at the repo root. The built file is what GitHub Pages serves; the source files are kept for editing.

```
src/
├── index.html            ← edit this
├── assets/
│   ├── css/*.css         ← edit these
│   ├── js/*.js           ← edit these
│   └── img/*             ← edit these
build.mjs                 ← build script
package.json
index.html                ← generated (do not edit by hand)
assets/img/               ← generated (copied from src/assets/img/)
```

### Prerequisites

- Node.js 18 or newer

### Workflow

```bash
npm install                 # once
# edit files in src/
npm run dev                 # optional: serves src/ at http://localhost:8000 with live source files
npm run build               # produces ./index.html (inlined + minified) and ./assets/img/
git add src/ index.html assets/img/
git commit -m "…"
```

Commit both the sources (`src/`) and the built artifacts (`index.html`, `assets/img/`). Pages serves the built artifacts directly — there is no CI build on this branch.

### What the build does

- Inlines `<link rel="stylesheet">` blocks (resolving local `@import url(...)` chains).
- Inlines `<script src="...">` blocks.
- Minifies HTML, CSS and JS in one pass (`html-minifier-terser`).
- Leaves remote URLs (Google Fonts, CDN scripts) external.
- Copies `src/assets/img/` to `assets/img/` (images stay external — base64-inlining bloats raster).

Typical reduction for this site: ~5 HTTP requests → 1, ~80 KB → ~64 KB.
