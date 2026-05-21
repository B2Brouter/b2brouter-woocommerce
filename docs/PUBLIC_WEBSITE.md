# Public Website (GitHub Pages)

This document explains how the plugin's public website is hosted and how to add or update content. The site is served at <https://woocommerce.b2brouter.net/>.

The original infrastructure discussion is in [issue #85](https://github.com/B2Brouter/b2brouter-woocommerce/issues/85).

## How it is set up

### Branch model

The website lives on a dedicated orphan branch `gh-pages` whose root **is** the site. The branch shares no history with `main`:

- `main` contains the plugin code; nothing in `main` is published.
- `gh-pages` contains only the public site (HTML, CSS, images).
- Plugin contributors do not need to touch `gh-pages`; website contributors do not need to touch `main`.

Audience separation is enforced by branch, not by folder convention.

### GitHub Pages configuration

In repo **Settings → Pages**:

- Source: *Deploy from a branch*
- Branch: `gh-pages` / `/` (root)
- Custom domain: `woocommerce.b2brouter.net` (set via the `CNAME` file at the branch root)
- HTTPS: enforced

Pushing to `gh-pages` triggers a Pages build that publishes within about one minute.

### Branch protection

`gh-pages` matches `main`'s policy: changes must go through a pull request, but approval is not required to merge. No status checks are required (there is no CI on this branch).

## Adding or updating content

This guide assumes you have a personal fork of `B2Brouter/b2brouter-woocommerce` on your GitHub account, cloned locally, with `origin` pointing at your fork. All content changes go through a pull request from a branch on your fork into `gh-pages` on the upstream repository.

**Two things to double-check before clicking Create on the PR:**

- The PR's **base repository** must be `B2Brouter/b2brouter-woocommerce` — not your fork.
- The PR's **base branch** must be `gh-pages` — not `main`.

GitHub's PR form may default either of these wrong; verify both.

### 0. One-time setup — add the `upstream` remote

If you have not already done so, point an `upstream` remote at the canonical repository:

```bash
git remote add upstream git@github.com:B2Brouter/b2brouter-woocommerce.git
git remote -v
# expected:
#   origin    git@github.com:<your-user>/b2brouter-woocommerce.git (fetch/push)
#   upstream  git@github.com:B2Brouter/b2brouter-woocommerce.git  (fetch/push)
```

### 1. Branch off the latest `gh-pages` from upstream

```bash
git fetch upstream
git checkout -b my-feature upstream/gh-pages
```

Branching from `upstream/gh-pages` (rather than from your fork's `gh-pages`, which may be stale) ensures you start from the current state of the published site. You will see only the website files; `main`'s plugin code is not present on this branch. This is expected.

### 2. Install build dependencies (one time per clone)

The site is authored in `src/` and built into a single inlined+minified `index.html` at the branch root. The build runs locally before each commit; there is no CI build on `gh-pages`.

```bash
npm install
```

Requires Node.js 18 or newer. See the `README.md` on `gh-pages` for what the build does.

### 3. Make your changes

Edit files in `src/`:

- `src/index.html` — markup
- `src/assets/css/*.css` — styles (use `@import url('…')` for local CSS deps; remote `@import`s like Google Fonts are left external by the build)
- `src/assets/js/*.js` — scripts
- `src/assets/img/*` — images (referenced as `assets/img/…` in the HTML — the path resolves the same way before and after build)

Add new files as needed.

### 4. Preview locally

```bash
npm run dev
# serves src/ at http://localhost:8000/ — sources are live, no rebuild needed
```

For a production-like preview, run `npm run build` first and serve the branch root instead.

### 5. Build, commit, and push to your fork

The built artifacts are tracked alongside the sources — Pages serves the built `index.html` directly.

```bash
npm run build
git add src/ index.html assets/img/
git commit -m "Add: short description of the change"
git push -u origin my-feature
```

This pushes `my-feature` to **your fork** (`origin`). The upstream repository's `gh-pages` is protected and only accepts changes through pull requests.

### 6. Open the PR — verify base repo AND base branch

From the command line, `gh` resolves the upstream automatically:

```bash
gh pr create --repo B2Brouter/b2brouter-woocommerce --base gh-pages
```

The explicit `--repo` flag protects against the rare case where `gh` has lost track of the fork relationship. If you open the PR from the GitHub web UI instead, verify both dropdowns at the top of the page:

- **Base repository**: `B2Brouter/b2brouter-woocommerce` (not `<your-user>/b2brouter-woocommerce`).
- **Base branch**: `gh-pages` (not `main`).

### 7. Review and merge

A maintainer reviews and merges the PR. Pages rebuilds and the change is live at <https://woocommerce.b2brouter.net/> within about a minute.

## Common pitfalls

- **PR opened against your own fork.** The base repository must be `B2Brouter/b2brouter-woocommerce`, not `<your-user>/...`. Close the PR and reopen with the correct base repository.
- **PR opened against `main`.** Close and reopen with base `gh-pages`.
- **Branched off `main` by accident.** If your branch contains plugin code (PHP files, `composer.json`, …) alongside your HTML changes, you branched from the wrong starting point. Start over from step 1.
- **Forgot to run `npm run build` before committing.** The PR will contain edits in `src/` but a stale built `index.html`. Run the build and amend or add a second commit.
- **Edited the built `index.html` at the root by hand.** Changes get overwritten on the next `npm run build`. Always edit `src/index.html` instead.
- **Site does not update after merge.** Check the Pages build status at Settings → Pages; a failed build surfaces there.
- **Browser shows stale content.** GitHub Pages sets `cache-control: max-age=600`. Hard-refresh with `Ctrl+Shift+R`.

## Reference

### File layout (on `gh-pages`)

```
gh-pages/
├── src/                    # editable sources
│   ├── index.html
│   └── assets/{css,js,img}/
├── build.mjs               # build script (inline + minify)
├── package.json            # dev deps: html-minifier-terser
├── README.md               # contributor-facing build flow
├── index.html              # generated — what Pages serves
├── assets/img/             # generated — copied from src/assets/img/
├── CNAME                   # custom domain
├── robots.txt
└── sitemap.xml
```

`index.html` and `assets/img/` at the root are build artifacts but are tracked in git — Pages serves them directly. Do not edit them by hand.

### How `gh-pages` was originally created

For maintainers who need to understand or recreate the branch from scratch:

```bash
git checkout --orphan gh-pages
git rm -rf .
# write a minimal index.html
git add index.html
git commit -m "Initial gh-pages branch"
git push -u origin gh-pages
```

The `--orphan` flag is what makes `gh-pages` share no history with `main`.
