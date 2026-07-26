# Chouby Decade

This Composer 2 plugin replaces a placeholder dist URL with the licensed
Polylang Pro package URL immediately before Composer downloads the archive.
The license key is read only from `POLYLANG_PRO_LICENSE_KEY`; it is not stored
in `composer.json` or `composer.lock`.

## Consumer configuration

Add the plugin as a normal dependency and describe the required Polylang Pro
release as a package repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:szepeviktor/Chouby-decade.git"
        },
        {
            "type": "package",
            "package": {
                "name": "wpsyntex/polylang-pro",
                "version": "3.8.5",
                "type": "library",
                "dist": {
                    "type": "zip",
                    "url": "https://polylang.pro/composer-dist/polylang-pro.zip"
                }
            }
        }
    ],
    "require": {
        "szepeviktor/chouby-decade": "^1.0",
        "wpsyntex/polylang-pro": "3.8.5"
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

`polylang-pro-site-url` is optional. If it is absent, the root package's
`homepage` is used; if neither exists, the API request omits the site URL.

Export the license only for the Composer process:

```bash
POLYLANG_PRO_LICENSE_KEY='your-license-key' composer update wpsyntex/polylang-pro
```

The package version is intentionally explicit. When a new Polylang Pro release
is needed, update both occurrences of `3.8.5`. The plugin rejects a download
when the API response version differs from the Composer package version, so the
lock file cannot silently describe different code.

By default Composer installs the package under
`vendor/wpsyntex/polylang-pro`. A WordPress-specific installer can place it in
`wp-content/plugins` if the consuming project needs that layout.
