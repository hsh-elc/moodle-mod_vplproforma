<?php

use core\exception\invalid_parameter_exception;
use core\exception\invalid_state_exception;

/**
 * Fetches and extracts release information (release names and assets for a given release) from a GitHub release
 */
class proforma_release_fetcher {
    private array $releasesjson;

    public function __construct(string $repoowner, string $reponame) {
        $this->releasesjson = $this->fetch_proforma_releases_json($repoowner, $reponame);
    }

    /**
     * Gets all release names for the provided repo
     * Returns an array of strings
     */
    public function get_release_names(): array {
        $releases = [];
        foreach ($this->releasesjson as $release) {
            if (isset($release['name'])) {
                $releases[] = $release['name'];
            }
        }
        if (empty($releases)) {
            throw new invalid_state_exception('No releases found in JSON retreived from GitHub API');
        }
        return $releases;
    }

    /**
     * Gets all release assets for a specific release name.
     * Returns an array of 'name', 'url'
     */
    public function get_release_assets(string $releasename): array {
        $releasenames = $this->get_release_names();
        if (!in_array($releasename, $releasenames, true)) {
            throw new invalid_parameter_exception('Releases do not contain a release named "' . $releasename . '".');
        }

        $releaseassets = [];
        foreach ($this->releasesjson as $release) {
            if (!isset($release['name'])) {
                continue;
            }
            if ($releasename === $release['name'] && isset($release['assets'])) {
                $assets = $release['assets'];
                if (!is_array($assets)) {
                    continue;
                }
                foreach ($assets as $asset) {
                    if (!isset($asset['name']) || !isset($asset['browser_download_url'])) {
                        continue;
                    }
                    $releaseassets[] = [
                        'name' => $asset['name'],
                        'url' => $asset['browser_download_url']
                    ];
                }
            }
        }
        if (empty($releaseassets)) {
            throw new invalid_state_exception('No release assets found for given release "' . $releasename . '" in JSON retreived from GitHub API');
        }
        return $releaseassets;
    }

    /**
     * Fetches the releases info as JSON for a given GitHub Repo
     */
    private function fetch_proforma_releases_json(string $repoowner, string $reponame): array {
        $curl = new curl();
        $url = $this->build_github_releases_api_url($repoowner, $reponame);
        $response = $curl->get($url);
        $info = $curl->get_info();

        if (!isset($info['http_code']) || $info['http_code'] != 200) {
            throw new invalid_state_exception('GitHub API fetch failed: HTTP status ' . $info['http_code'] . ' for URL ' . $url);
        }
        $json = json_decode($response, true);
        
        if (is_array($json)) {
            return $json;
        } else {
            throw new invalid_state_exception('The requested data from ' . $url . ' cannot be parsed into JSON');
        }
    }

    /**
     * Builds the url for retreiving GitHub release json data from $repoowner and $reponame
     */
    private function build_github_releases_api_url(string $repoowner, string $reponame): string {
        return 'https://api.github.com/repos/' . $repoowner . '/' . $reponame . '/releases';
    }
}