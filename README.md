# Chouby Decade

Polylang Pro has gone a decade without official Composer installation support.
Chouby Decade fills that gap.

This Composer 2 plugin discovers the latest Polylang Pro version, offers it to
Composer when it matches the root version constraint, and replaces its internal
dist placeholder with the licensed package URL immediately before download.
The license key is read only from `POLYLANG_PRO_LICENSE_KEY`; it is not stored
in `composer.json` or `composer.lock`.

## Latest version only

Chouby Decade installs only the latest Polylang Pro version returned by the
official update API. Easy Digital Downloads does not provide older releases,
so this plugin is a latest-compatible-version bridge, not a historical package
archive.

The latest release is offered to Composer only when it satisfies the root
constraint, such as `^3.8`. If the API starts returning `4.0.0`, it will not be
offered to a project constrained to `^3.8`.

Consequently, an older version recorded in `composer.lock` may no longer be
installable after a newer Polylang Pro release becomes available. Run
`composer update wpsyntex/polylang-pro` to resolve and lock the latest compatible
version before deployment.

## Consumer configuration

Composer plugins are activated only after they have been installed. On the
first setup, Chouby Decade must therefore be installed before
`wpsyntex/polylang-pro` is added to the root requirements.

First add the plugin repository, plugin requirement, and Composer permission:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:szepeviktor/Chouby-decade.git"
        }
    ],
    "require": {
        "szepeviktor/chouby-decade": "^1.0"
    },
    "config": {
        "allow-plugins": {
            "szepeviktor/chouby-decade": true
        }
    },
    "extra": {
        "polylang-pro-site-url": "https://example.com"
    }
}
```

Install the plugin:

```bash
composer require szepeviktor/chouby-decade:^1.0
```

Then require the virtual Polylang Pro package:

```bash
POLYLANG_PRO_LICENSE_KEY='your-license-key' composer require wpsyntex/polylang-pro:^3.8
```

This two-step bootstrap is required only before the plugin exists in
`vendor`. Subsequent deployments can use the committed lock file normally.

If both requirements were added before the first `composer update`, Composer
reports that `wpsyntex/polylang-pro` could not be found. Remove that requirement
temporarily, install Chouby Decade, then add it again with the second command
above.

`polylang-pro-site-url` is optional. If it is absent, the root package's
`homepage` is used; if neither exists, the API request omits the site URL.

Export the license only for the Composer process:

```bash
POLYLANG_PRO_LICENSE_KEY='your-license-key' composer update wpsyntex/polylang-pro
```

In a Bedrock project, the license may instead be added to the project-root
`.env` file:

```dotenv
POLYLANG_PRO_LICENSE_KEY='your-license-key'
```

When `vlucas/phpdotenv` is installed, Chouby Decade loads this file before
reading the license. An environment variable already exported for the Composer
process takes precedence over the value in `.env`. Projects without
`vlucas/phpdotenv` continue to use the process environment. In Bedrock,
`WP_HOME` is also used as the site URL sent to the license API unless
`extra.polylang-pro-site-url` is configured explicitly.

The plugin adds the API's `new_version` to Composer's dependency pool only when
it satisfies `^3.8`. Composer then records the selected concrete version in
`composer.lock`. If a newer release does not satisfy the constraint, it is not
offered as an update.

By default Composer installs the package under
`vendor/wpsyntex/polylang-pro`. A WordPress-specific installer can place it in
`wp-content/plugins` if the consuming project needs that layout.
