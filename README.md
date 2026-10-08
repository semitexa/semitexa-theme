# Semitexa Theme

`semitexa/theme`

The theme and skin resolver, together with the base theme (`theme-base`): layouts, partials and token-driven skins over one shared markup set. A theme is chosen per request from its `theme.json` manifest (`active_when`: tenant, domain, locale), and themes can extend each other.

## Install

Included in every project created by the installer (https://semitexa.com/install.sh).

## What it provides

- `theme-base`: layouts `one-column`, `two-columns-left`, `two-columns-right`, `three-columns`, `marketing`; an error page; partials for head, header, nav, breadcrumbs and footer; the default skin.
- Manifest-driven resolution (`ThemeResolverInterface`, `ThemeManifestRepositoryInterface`) with an extends chain that is also the template fallback chain.
- `SkinAlgorithmInterface` and the skin builder that `semitexa/platform-ui` uses to generate skins.
- Console commands:
  - `bin/semitexa theme:scaffold [--slug=…] [--domain=…]` creates a project theme in `src/theme/<slug>/` that extends `theme-base`;
  - `bin/semitexa theme:resolve [--tenant=…] [--domain=…] [--locale=…]` shows which theme and skin apply to a context;
  - `bin/semitexa theme:validate` checks every `theme.json` and exits 1 on errors.

## Documentation

Commands: https://semitexa.com/docs/reference/commands-theme

## License

MIT, see [LICENSE](LICENSE).
