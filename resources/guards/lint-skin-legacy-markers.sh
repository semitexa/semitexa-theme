#!/bin/sh
# ─────────────────────────────────────────────────────────────────────────────
# Regression guard for the unified frontend skin-mode contract.
#
# The marketing site packages (semitexa-theme, semitexa-site, semitexa-os-site,
# semitexa-platform-site) were unified in `ep-remove-legacy-theme-rendering` /
# `tk-frontend-skin-mode-unification` to use ONE frontend skin-mode contract:
#
#     data-skin-mode  /  data-skin-toggle  /  data-skin-text
#     localStorage key:  semitexa_skin_mode
#
# The legacy per-site `theme_prefix` parameter, `data-{prefix}-theme-toggle/text`
# attributes, and `semitexa_{site}_site_theme` localStorage keys are gone from
# these packages. This script fails (exit 1) if any of them re-appear.
#
# Out-of-scope (deferred to `tk-demo-hello-skin-migration-followup`):
#   - packages/semitexa-demo
#   - packages/semitexa-ultimate (Hello scaffold)
# Both still own a separate legacy theme system. They are deliberately skipped.
#
# Also asserts that the three sites' Static/js/site.js are byte-identical.
# Drift may indicate per-site logic creeping back in. If you intentionally
# need them to diverge, document the reason and remove this assertion in a
# follow-up task — do not edit this script to make divergence silent.
#
# Usage:   sh scripts/lint-skin-legacy-markers.sh
#          bin/semitexa lint:skin-legacy
# Exit:    0 = clean,  1 = forbidden marker or site.js drift detected.
# ─────────────────────────────────────────────────────────────────────────────
set -eu

# The project root. bin/semitexa passes PROJECT_ROOT; running this script by
# hand has to work too.
#
# `dirname "$0"/..` was correct while this lived in <project>/scripts/. It is
# wrong now: the guard ships inside semitexa/theme, so the same expression lands
# on <package>/resources and every guarded path resolves under it. Depth to the
# project differs between a vendor install, a monorepo package and a
# project-local copy, so walk up to the marker instead of counting directories.
if [ -z "${PROJECT_ROOT:-}" ]; then
    _dir="$(cd "$(dirname "$0")" && pwd -P)"
    while [ ! -f "$_dir/vendor/autoload.php" ]; do
        _parent="$(dirname "$_dir")"
        if [ "$_parent" = "$_dir" ]; then
            printf "Cannot locate the project root from %s.\n" "$(dirname "$0")" >&2
            printf "Set PROJECT_ROOT, or run: bin/semitexa lint:skin-legacy\n" >&2
            exit 1
        fi
        _dir="$_parent"
    done
    PROJECT_ROOT="$_dir"
fi

# The two guard scripts live inside semitexa-theme, which this guard scans, and
# they carry the forbidden-marker list as literals. Without this the guard flags
# its own definitions — every marker matched, in the file that declares them.
#
# `pwd -P` is load-bearing, not decoration. bin/semitexa reaches this script
# through vendor/semitexa/theme, a symlink into packages/ in a monorepo, while
# the scan walks the packages/ path directly. Logical pwd returns the vendor
# spelling, the two paths never compare equal, and the exclusion silently does
# nothing — which is exactly how this was first written.
GUARD_DIR="$(cd "$(dirname "$0")" && pwd -P)"

# Colour helpers (safe for non-tty).
if [ -t 1 ] && command -v tput >/dev/null 2>&1; then
    C_RESET="$(tput sgr0 2>/dev/null || true)"
    C_GREEN="$(tput setaf 2 2>/dev/null || true)"
    C_YELLOW="$(tput setaf 3 2>/dev/null || true)"
    C_RED="$(tput setaf 1 2>/dev/null || true)"
    C_CYAN="$(tput setaf 6 2>/dev/null || true)"
else
    C_RESET="" C_GREEN="" C_YELLOW="" C_RED="" C_CYAN=""
fi

ok()    { printf "%s[OK]%s    %s\n"    "$C_GREEN"  "$C_RESET" "$*"; }
info()  { printf "%s[INFO]%s  %s\n"    "$C_CYAN"   "$C_RESET" "$*"; }
warn()  { printf "%s[WARN]%s  %s\n"    "$C_YELLOW" "$C_RESET" "$*" >&2; }
fail()  { printf "%s[FAIL]%s  %s\n"    "$C_RED"    "$C_RESET" "$*" >&2; }

# Marketing site packages covered by the guard. Any new marketing-site package
# should be added here — adding it makes the guard apply automatically.
#
# semitexa-site, semitexa-os-site and semitexa-platform-site used to be listed
# here. They are gone: absent from packages/, from composer.json, from
# composer.lock, and from GitHub. Only three dangling vendor/ symlinks were
# left behind, and they have been removed too. Naming a package that does not
# exist is not a stricter guard — see the emptiness check below for what it
# actually bought.
GUARDED_PACKAGES="
packages/semitexa-theme
"

# Forbidden markers. Each is the literal string that must not appear in any
# guarded package. Boundary semantics applied via the regex below: the marker
# must be followed by end-of-line OR a character outside `[-a-zA-Z0-9_]`. That
# stops `data-os-theme` from double-matching `data-os-theme-toggle`, and stops
# `semitexa_demo_theme` from matching a hypothetical `semitexa_demo_themes`.
FORBIDDEN_PATTERNS='theme_prefix
data-demo-theme
data-demo-theme-toggle
data-demo-theme-text
data-os-theme
data-os-theme-toggle
data-os-theme-text
data-platform-theme
data-platform-theme-toggle
data-platform-theme-text
semitexa_demo_theme
semitexa_os_site_theme
semitexa_platform_site_theme'

violations=0

printf "\n%sSemitexa — Skin-mode legacy regression guard%s\n\n" "$C_CYAN" "$C_RESET"

# ── Forbidden-marker scan ──────────────────────────────────────────────────
info "Scanning for forbidden frontend theme markers in marketing packages..."

# A guard that scans nothing must never report success. Three of this guard's
# four packages were deleted and every one of them was skipped by a silent
# `continue`, so it went on printing OK while covering a quarter of its scope.
_scanned=0
_absent=""
for _pkg in $GUARDED_PACKAGES; do
    if [ -d "$PROJECT_ROOT/$_pkg" ]; then
        _scanned=$((_scanned + 1))
    else
        _absent="$_absent $_pkg"
    fi
done

if [ -n "$_absent" ]; then
    warn "Guarded package(s) not present, nothing scanned in them:$_absent"
    warn "Delete them from GUARDED_PACKAGES if they are gone for good."
fi

if [ "$_scanned" -eq 0 ]; then
    fail "No guarded package exists — this guard verified NOTHING."
    fail "A green run here would be a lie. Fix GUARDED_PACKAGES."
    printf "\n"
    exit 1
fi

for _pattern in $FORBIDDEN_PATTERNS; do
    # Boundary regex — see FORBIDDEN_PATTERNS comment above.
    _pattern_re="${_pattern}(\$|[^-a-zA-Z0-9_])"
    _hits_file="$(mktemp)"
    for _pkg in $GUARDED_PACKAGES; do
        _abs="$PROJECT_ROOT/$_pkg"
        [ -d "$_abs" ] || continue
        # Excludes generated artefacts that may transiently contain matches.
        grep -RnE "$_pattern_re" "$_abs" \
            --include='*.php' \
            --include='*.twig' \
            --include='*.html' \
            --include='*.js' \
            --include='*.css' \
            --include='*.json' \
            --include='*.md' \
            2>/dev/null \
            | grep -vE '/(var/cache|var/log|node_modules|vendor)/' \
            | grep -vF "$GUARD_DIR/" \
            >> "$_hits_file" || true
    done
    if [ -s "$_hits_file" ]; then
        fail "Forbidden marker '$_pattern' found in guarded package(s):"
        sed 's/^/      /' "$_hits_file" >&2
        violations=$((violations + 1))
    fi
    rm -f "$_hits_file"
done

if [ "$violations" -eq 0 ]; then
    ok "No forbidden markers in $_scanned guarded package(s)."
fi

# The site.js drift detector lived here. It asserted that the three marketing
# sites' Static/js/site.js were byte-identical, and the script told whoever
# removed it to document the rationale rather than make divergence silent, so:
# the three packages it compared no longer exist in any form. There is nothing
# left to diverge. This is a subject that disappeared, not a divergence that
# was waved through.

# ── Summary ────────────────────────────────────────────────────────────────
printf "\n"
if [ "$violations" -eq 0 ]; then
    ok "Skin-mode legacy guard: clean."
    printf "\n"
    exit 0
fi

fail "Skin-mode legacy guard: $violations issue(s)."
info "Deferred legacy lives in semitexa-demo and semitexa-ultimate/Hello"
info "(tracked under tk-demo-hello-skin-migration-followup) — those packages"
info "are intentionally NOT scanned by this guard."
printf "\n"
exit 1
