<?php

declare(strict_types=1);

namespace SzepeViktor\ChoubyDecade;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Plugin\PrePoolCreateEvent;
use Composer\Semver\Constraint\Constraint;
use Dotenv\Dotenv;
use RuntimeException;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const API_URL = 'https://polylang.pro/';
    private const DIST_URL = 'https://polylang-pro.invalid/polylang-pro.zip';
    private const LICENSE_ENV = 'POLYLANG_PRO_LICENSE_KEY';
    private const PACKAGE_NAME = 'wpsyntex/polylang-pro';
    private const SITE_URL_ENV = 'WP_HOME';

    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    /** @var array<string, mixed>|null */
    private $versionInfo;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
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
            PluginEvents::PRE_POOL_CREATE => 'providePolylangProPackage',
            PluginEvents::PRE_FILE_DOWNLOAD => 'resolvePolylangProDownload',
        ];
    }

    public function providePolylangProPackage(PrePoolCreateEvent $event): void
    {
        $requires = $event->getRequest()->getRequires();

        if (!isset($requires[self::PACKAGE_NAME])) {
            return;
        }

        $versionInfo = $this->getVersionInfo();
        $remoteVersion = $this->getRemoteVersion($versionInfo);
        $versionParser = new VersionParser();
        $normalizedVersion = $versionParser->normalize($remoteVersion);
        $providedVersion = new Constraint('==', $normalizedVersion);

        if (!$requires[self::PACKAGE_NAME]->matches($providedVersion)) {
            $this->io->writeError(
                sprintf(
                    '<warning>Polylang Pro %s is available, but it does not match the root constraint %s.</warning>',
                    $remoteVersion,
                    $requires[self::PACKAGE_NAME]->getPrettyString()
                )
            );

            return;
        }

        $packages = $event->getPackages();

        foreach ($packages as $package) {
            if (self::PACKAGE_NAME === $package->getName() && $normalizedVersion === $package->getVersion()) {
                return;
            }
        }

        $package = new CompletePackage(self::PACKAGE_NAME, $normalizedVersion, $remoteVersion);
        $package->setType('library');
        $package->setDistType('zip');
        $package->setDistUrl(self::DIST_URL);
        $package->setDistReference($remoteVersion);
        $package->setDescription('Adds multilingual capability to WordPress');
        $package->setHomepage('https://polylang.pro/');
        $package->setLicense(['GPL-3.0-or-later']);

        $packages[] = $package;
        $event->setPackages($packages);
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

        $version = ltrim($package->getPrettyVersion(), 'v');
        $versionInfo = $this->getVersionInfo($version);
        $remoteVersion = $this->getRemoteVersion($versionInfo);

        if (!version_compare($remoteVersion, $version, '==')) {
            throw new RuntimeException(
                sprintf(
                    'The Polylang Pro API returned version "%s", but composer.lock requests "%s". Run Composer update.',
                    $remoteVersion,
                    $version
                )
            );
        }

        $event->setProcessedUrl($this->getDownloadUrl($versionInfo));
        $event->setCustomCacheKey('polylang-pro-' . $version);
    }

    /**
     * @return array<string, mixed>
     */
    private function getVersionInfo(string $currentVersion = '0.0.0'): array
    {
        if (null !== $this->versionInfo) {
            return $this->versionInfo;
        }

        $this->loadDotenv();

        $license = $this->getEnvironmentVariable(self::LICENSE_ENV);

        if (!is_string($license) || '' === trim($license)) {
            throw new RuntimeException(
                sprintf('The %s environment variable must contain the Polylang Pro license key.', self::LICENSE_ENV)
            );
        }

        $body = [
            'edd_action' => 'get_version',
            'license' => trim($license),
            'item_name' => 'Polylang Pro',
            'version' => $currentVersion,
            'slug' => 'polylang-pro',
            'author' => 'WP SYNTEX',
            'beta' => '0',
            'php_version' => PHP_VERSION,
        ];

        $siteUrl = $this->getSiteUrl();

        if (null !== $siteUrl) {
            $body['url'] = $siteUrl;
        }

        $response = $this->composer->getLoop()->getHttpDownloader()->get(
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

        $this->versionInfo = $data;

        return $this->versionInfo;
    }

    private function loadDotenv(): void
    {
        if (!class_exists(Dotenv::class)) {
            return;
        }

        $workingDirectory = getcwd();

        if (!is_string($workingDirectory) || !is_file($workingDirectory . DIRECTORY_SEPARATOR . '.env')) {
            return;
        }

        Dotenv::createUnsafeImmutable($workingDirectory)->safeLoad();
    }

    private function getEnvironmentVariable(string $name): ?string
    {
        $value = getenv($name);

        if (is_string($value)) {
            return $value;
        }

        if (isset($_ENV[$name]) && is_string($_ENV[$name])) {
            return $_ENV[$name];
        }

        return isset($_SERVER[$name]) && is_string($_SERVER[$name]) ? $_SERVER[$name] : null;
    }

    /**
     * @param array<string, mixed> $versionInfo
     */
    private function getRemoteVersion(array $versionInfo): string
    {
        $remoteVersion = isset($versionInfo['new_version']) && is_string($versionInfo['new_version'])
            ? ltrim($versionInfo['new_version'], 'v')
            : '';

        if ('' === $remoteVersion) {
            throw new RuntimeException('The Polylang Pro API did not return a version.');
        }

        return $remoteVersion;
    }

    /**
     * @param array<string, mixed> $versionInfo
     */
    private function getDownloadUrl(array $versionInfo): string
    {
        $downloadUrl = isset($versionInfo['package']) && is_string($versionInfo['package'])
            ? $versionInfo['package']
            : '';

        if ('https' !== parse_url($downloadUrl, PHP_URL_SCHEME)) {
            throw new RuntimeException(
                'The Polylang Pro API did not return a secure package download URL. Check the license and its activation.'
            );
        }

        return $downloadUrl;
    }

    private function getSiteUrl(): ?string
    {
        $extra = $this->composer->getPackage()->getExtra();
        $siteUrl = $extra['polylang-pro-site-url'] ?? $this->getEnvironmentVariable(self::SITE_URL_ENV);

        if (!is_string($siteUrl) || '' === trim($siteUrl)) {
            $siteUrl = $this->composer->getPackage()->getHomepage();
        }

        if (!is_string($siteUrl) || '' === trim($siteUrl)) {
            return null;
        }

        return $siteUrl;
    }
}
