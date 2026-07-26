<?php

declare(strict_types=1);

namespace SzepeViktor\ChoubyDecade;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use RuntimeException;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const API_URL = 'https://polylang.pro/';
    private const DIST_URL = 'https://polylang.pro/composer-dist/polylang-pro.zip';
    private const LICENSE_ENV = 'POLYLANG_PRO_LICENSE_KEY';
    private const PACKAGE_NAME = 'wpsyntex/polylang-pro';

    /** @var Composer */
    private $composer;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PRE_FILE_DOWNLOAD => 'resolvePolylangProDownload',
        ];
    }

    public function resolvePolylangProDownload(PreFileDownloadEvent $event): void
    {
        if ('package' !== $event->getType() || self::DIST_URL !== $event->getProcessedUrl()) {
            return;
        }

        $package = $event->getContext();

        if (!$package instanceof PackageInterface || self::PACKAGE_NAME !== $package->getName()) {
            return;
        }

        $license = getenv(self::LICENSE_ENV);

        if (!is_string($license) || '' === trim($license)) {
            throw new RuntimeException(
                sprintf('The %s environment variable must contain the Polylang Pro license key.', self::LICENSE_ENV)
            );
        }

        $version = ltrim($package->getPrettyVersion(), 'v');
        $body = [
            'edd_action' => 'get_version',
            'license' => trim($license),
            'item_name' => 'Polylang Pro',
            'version' => $version,
            'slug' => 'polylang-pro',
            'author' => 'WP SYNTEX',
            'beta' => '0',
            'php_version' => PHP_VERSION,
        ];

        $siteUrl = $this->getSiteUrl();

        if (null !== $siteUrl) {
            $body['url'] = $siteUrl;
        }

        $response = $event->getHttpDownloader()->get(
            self::API_URL,
            [
                'http' => [
                    'method' => 'POST',
                    'header' => ['Content-Type: application/x-www-form-urlencoded'],
                    'content' => http_build_query($body, '', '&', PHP_QUERY_RFC3986),
                    'timeout' => 15,
                ],
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data)) {
            throw new RuntimeException('The Polylang Pro update API returned invalid JSON.');
        }

        $remoteVersion = isset($data['new_version']) && is_string($data['new_version'])
            ? ltrim($data['new_version'], 'v')
            : '';

        if ('' === $remoteVersion || !version_compare($remoteVersion, $version, '==')) {
            throw new RuntimeException(
                sprintf(
                    'The Polylang Pro API returned version "%s", but Composer requested "%s". Update the package repository version.',
                    '' !== $remoteVersion ? $remoteVersion : 'unknown',
                    $version
                )
            );
        }

        $downloadUrl = isset($data['package']) && is_string($data['package'])
            ? $data['package']
            : '';

        if ('https' !== parse_url($downloadUrl, PHP_URL_SCHEME)) {
            throw new RuntimeException(
                'The Polylang Pro API did not return a secure package download URL. Check the license and its activation.'
            );
        }

        $event->setProcessedUrl($downloadUrl);
        $event->setCustomCacheKey('polylang-pro-' . $version);
    }

    private function getSiteUrl(): ?string
    {
        $extra = $this->composer->getPackage()->getExtra();
        $siteUrl = $extra['polylang-pro-site-url'] ?? $this->composer->getPackage()->getHomepage();

        if (!is_string($siteUrl) || '' === trim($siteUrl)) {
            return null;
        }

        return $siteUrl;
    }
}
