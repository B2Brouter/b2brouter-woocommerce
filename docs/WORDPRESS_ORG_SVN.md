# WordPress.org SVN Release Process

This document describes how to publish a new version of B2Brouter for WooCommerce to the official [WordPress.org plugin directory](https://wordpress.org/plugins/b2brouter-for-woocommerce/) via Subversion.

It is the second half of the release workflow. The first half — bumping versions, opening the release PR, tagging, and producing the canonical release ZIP via GitHub Actions — is documented in [DISTRIBUTION.md](DISTRIBUTION.md). This document picks up after that ZIP exists on the GitHub Releases page.

## Background

WordPress.org hosts every plugin in a single shared Subversion repository at `https://plugins.svn.wordpress.org`. Each plugin is a top-level directory in that repo — for us, `https://plugins.svn.wordpress.org/b2brouter-for-woocommerce`. Inside it:

```
/trunk          current stable plugin code
/tags/X.Y.Z     immutable snapshot of trunk for each released version
/assets         marketing assets shown on the public listing page (banners,
                screenshots, icon). Not shipped to users.
/branches       rarely used
```

The `Stable tag:` line in `trunk/readme.txt` is what WordPress.org reads to decide which version is offered for download. As long as `Stable tag` matches an existing `/tags/X.Y.Z`, that tag is served. Otherwise WordPress.org falls back to whatever is in trunk.

The plugin slug is `b2brouter-for-woocommerce` and is **frozen** — it cannot be renamed.

## One-time setup

Run these once per developer machine.

### 1. Install Subversion

```bash
sudo apt install -y subversion
```

### 2. Generate the SVN password

The WordPress.org SVN password is **separate** from your WordPress.org login password. The SVN username is `b2brouter` (case-sensitive).

### 3. Check out the working copy

Put the working copy **outside** the git repo to keep `.svn/` and `.git/` cleanly separated:

```bash
mkdir -p ~/svn
svn checkout https://plugins.svn.wordpress.org/b2brouter-for-woocommerce ~/svn/b2brouter-for-woocommerce
```

The initial checkout is tiny (an empty `trunk/`, `tags/`, `assets/` skeleton) until you `svn update` after a release.

## Releasing a new version

These steps assume the GitHub release for version `X.Y.Z` is already published (see [DISTRIBUTION.md](DISTRIBUTION.md)).

### 1. Sync the working copy

```bash
cd ~/svn/b2brouter-for-woocommerce
svn update
```

### 2. Download the canonical release ZIP from GitHub

**Always use the GitHub-built ZIP, not a local `dist/b2brouter-for-woocommerce-X.Y.Z.zip`.** The GitHub Actions build is reproducible and is what was reviewed by the WordPress.org team; a local rebuild may differ by a few hundred bytes (file ordering, timestamps).

```bash
mkdir -p /tmp/wporg-stage
cd /tmp/wporg-stage
gh release download vX.Y.Z -R B2Brouter/b2brouter-woocommerce --clobber

# Verify SHA-256 against the published checksum
sha256sum b2brouter-for-woocommerce-X.Y.Z.zip
cat b2brouter-for-woocommerce-X.Y.Z.zip.sha256

unzip -q b2brouter-for-woocommerce-X.Y.Z.zip
```

### 3. Sync the extracted tree into `trunk/`

```bash
rsync -a --delete \
  /tmp/wporg-stage/b2brouter-for-woocommerce/ \
  ~/svn/b2brouter-for-woocommerce/trunk/
```

The trailing slash on the source path is important — it means "copy the contents of, not the directory itself". `--delete` removes files that existed in the previous release but not in the new one.

### 4. Stage the changes for commit

```bash
cd ~/svn/b2brouter-for-woocommerce
svn status

# Add new files / directories
svn add --force trunk

# Remove deleted files explicitly (Subversion does not auto-detect deletions)
# For each file shown as "!" in svn status:
svn rm trunk/path/to/removed-file.php
```

Re-run `svn status` until every entry is `A`, `M`, or `D` and there are no `!` (missing) or `?` (untracked) entries.

Verify the staged tree matches the extracted release ZIP byte-for-byte:

```bash
diff -r /tmp/wporg-stage/b2brouter-for-woocommerce/ ~/svn/b2brouter-for-woocommerce/trunk/
# Silent output = identical
```

### 5. Commit trunk

```bash
svn commit -m "Release X.Y.Z" --username b2brouter
```

You will be prompted for the SVN password the first time; afterwards it is cached in `~/.subversion/auth/`.

Expected output ends with `Committed revision NNNN.`

### 6. Tag the release

```bash
svn cp \
  ^/b2brouter-for-woocommerce/trunk \
  ^/b2brouter-for-woocommerce/tags/X.Y.Z \
  -m "Tag X.Y.Z" \
  --username b2brouter
```

This is a server-side copy — no upload, near-instant.

**Important:** include the full plugin slug in the path. See [The `^/` gotcha](#the--gotcha) below.

### 7. Verify

```bash
# Confirm the tag exists with the expected contents
svn ls https://plugins.svn.wordpress.org/b2brouter-for-woocommerce/tags
svn ls https://plugins.svn.wordpress.org/b2brouter-for-woocommerce/tags/X.Y.Z

# Inspect recent commits
svn log -l 5 https://plugins.svn.wordpress.org/b2brouter-for-woocommerce
```

Then load the public listing in a browser:

<https://wordpress.org/plugins/b2brouter-for-woocommerce/>

The page itself updates within a few minutes; search indexing can lag up to 72 hours.

## Updating `/assets` (banners, screenshots, icon)

The `/assets` directory in SVN is independent of `/trunk` and `/tags`. It holds files shown on the public listing page and is **not** shipped to end users.

Asset changes do **not** require a `Stable tag` bump or a new release tag. You can update banners or add an icon any time.

### Naming conventions

WordPress.org expects exact filenames. Both PNG and JPG are accepted.

| Purpose      | Filename(s) |
|--------------|-------------|
| Small banner | `banner-772x250.png` |
| Large banner | `banner-1544x500.png` |
| Small icon   | `icon-128x128.png` |
| Large icon   | `icon-256x256.png` |
| Screenshots  | `screenshot-1.png`, `screenshot-2.png`, ... |

Screenshot filenames must align with the numbered captions in `readme.txt` under `== Screenshots ==`. If you change screenshots, also update the captions in trunk's `readme.txt` and commit the trunk change.

### Workflow

```bash
cd ~/svn/b2brouter-for-woocommerce
svn update

# Copy the new asset into place (rename to wp.org spec if needed)
cp ~/some/path/new-banner.png assets/banner-1544x500.png

svn status
svn add assets/banner-1544x500.png   # if new
# (existing files modified in place are picked up automatically)

svn commit -m "Update large banner" --username b2brouter
```

## The `^/` gotcha

Subversion's `^/` shorthand expands to the **repository root** reported by `svn info`. Because WordPress.org runs every plugin in one shared SVN repo, that root is `https://plugins.svn.wordpress.org` — **not** the plugin's URL.

This means the tag command you find in most generic SVN tutorials:

```bash
svn cp ^/trunk ^/tags/X.Y.Z -m "Tag X.Y.Z"
```

…fails on WordPress.org with:

```
svn: E160013: '/!svn/rvr/NNNN/trunk' path not found
```

…because it's looking for a `trunk` directory directly under `plugins.svn.wordpress.org/`, which does not exist.

Always include the plugin slug:

```bash
svn cp ^/b2brouter-for-woocommerce/trunk ^/b2brouter-for-woocommerce/tags/X.Y.Z ...
```

Or use absolute URLs:

```bash
svn cp \
  https://plugins.svn.wordpress.org/b2brouter-for-woocommerce/trunk \
  https://plugins.svn.wordpress.org/b2brouter-for-woocommerce/tags/X.Y.Z \
  ...
```

E160013 failures don't consume a revision — just re-run with the corrected path.

## Troubleshooting

### `svn: E155004: Working copy is locked`

```bash
cd ~/svn/b2brouter-for-woocommerce
svn cleanup
```

### Stable tag temporarily points to a non-existent tag

There is a brief window between the trunk commit (step 5) and the tag commit (step 6) where `trunk/readme.txt`'s `Stable tag: X.Y.Z` references a tag that doesn't exist yet. This is harmless — WordPress.org falls back to serving trunk in that case. Just run step 6 promptly afterwards.

### Plugin Check complains about files only in the dev tree

WordPress.org gates on what's in `/trunk` (or in `/tags/X.Y.Z` when `Stable tag` matches it), not what's in the git repository. The dev tree contains many files (`tests/`, `vendor/.../tests/`, `phpunit.xml`, etc.) that are stripped from the release ZIP by `build-release.sh` and therefore never reach SVN. Run Plugin Check against the build output, not the dev tree.

## Reference

- WordPress.org SVN handbook: <https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/>
- Plugin assets: <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>
- `readme.txt` standard: <https://wordpress.org/plugins/developers/#readme>
- `readme.txt` validator: <https://wordpress.org/plugins/developers/readme-validator/>
- Plugin guidelines: <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>
