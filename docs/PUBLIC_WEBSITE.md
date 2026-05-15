# Public Website (GitHub Pages)

This document explains how the plugin's public website is hosted and how to add or update content. The site is served at <https://b2brouter.github.io/b2brouter-woocommerce/>.

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
- Custom domain: none — launch URL is the default `*.github.io`
- HTTPS: enforced

Pushing to `gh-pages` triggers a Pages build that publishes within about one minute.

### Branch protection

`gh-pages` has a protection rule requiring pull request review before merging. The site is public; typos should not reach production without a second pair of eyes. No status checks are required (there is no CI on this branch).

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

### 2. Make your changes

Edit HTML, CSS and images in place. Add new files as needed.

### 3. Preview locally

For a quick check, open `index.html` directly in your browser. If your pages link to each other with relative URLs, start a small local server so paths resolve correctly:

```bash
python3 -m http.server 8000
# then visit http://localhost:8000/
```

### 4. Commit and push to your fork

```bash
git add .
git commit -m "Add: short description of the change"
git push -u origin my-feature
```

This pushes `my-feature` to **your fork** (`origin`). The upstream repository's `gh-pages` is protected and only accepts changes through reviewed pull requests.

### 5. Open the PR — verify base repo AND base branch

From the command line, `gh` resolves the upstream automatically:

```bash
gh pr create --repo B2Brouter/b2brouter-woocommerce --base gh-pages
```

The explicit `--repo` flag protects against the rare case where `gh` has lost track of the fork relationship. If you open the PR from the GitHub web UI instead, verify both dropdowns at the top of the page:

- **Base repository**: `B2Brouter/b2brouter-woocommerce` (not `<your-user>/b2brouter-woocommerce`).
- **Base branch**: `gh-pages` (not `main`).

### 6. Review and merge

Once approved, a maintainer merges the PR. Pages rebuilds and the change is live at <https://b2brouter.github.io/b2brouter-woocommerce/> within about a minute.

## Common pitfalls

- **PR opened against your own fork.** The base repository must be `B2Brouter/b2brouter-woocommerce`, not `<your-user>/...`. Close the PR and reopen with the correct base repository.
- **PR opened against `main`.** Close and reopen with base `gh-pages`.
- **Branched off `main` by accident.** If your branch contains plugin code (PHP files, `composer.json`, …) alongside your HTML changes, you branched from the wrong starting point. Start over from step 1.
- **Site does not update after merge.** Check the Pages build status at Settings → Pages; a failed build surfaces there.
- **Browser shows stale content.** GitHub Pages sets `cache-control: max-age=600`. Hard-refresh with `Ctrl+Shift+R`.

## Reference

### File layout (on `gh-pages`)

```
gh-pages/
├── index.html          # Landing page (currently a placeholder)
└── …                   # Other pages, assets/, etc., added by the product team
```

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
