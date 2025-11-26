<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gallery_Updater {
    private $slug;
    private $plugin_data;
    private $username;
    private $repo;
    private $plugin_file;
    private $github_response;

    public function __construct($plugin_file, $username, $repo) {
        $this->plugin_file = $plugin_file;
        $this->username = $username;
        $this->repo = $repo;
        $this->slug = dirname(plugin_basename($plugin_file));
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
    }

    private function get_repository_info() {
        if (!empty($this->github_response)) {
            return $this->github_response;
        }

        // Cache key
        $transient_key = 'wc_gallery_gh_update_' . $this->slug . '_test';
        $cached_response = get_transient($transient_key);

        if ($cached_response) {
            $this->github_response = $cached_response;
            return $cached_response;
        }

        // Request to GitHub API
        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
        
        $args = array(
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        
        // Cache for 12 hours
        set_transient($transient_key, $body, 12 * HOUR_IN_SECONDS);
        
        $this->github_response = $body;
        return $body;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $repo_info = $this->get_repository_info();
        
        if (!$repo_info) {
            return $transient;
        }

        $plugin_data = get_plugin_data($this->plugin_file);
        $current_version = $plugin_data['Version'];
        $new_version = $repo_info->tag_name;

        // Remove 'v' prefix if present
        $new_version = ltrim($new_version, 'v');

        if (version_compare($current_version, $new_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->plugin = plugin_basename($this->plugin_file);
            $obj->new_version = $new_version;
            $obj->url = $repo_info->html_url;
            
            // GitHub release assets usually contain the zip
            if (!empty($repo_info->assets) && isset($repo_info->assets[0]->browser_download_url)) {
                $obj->package = $repo_info->assets[0]->browser_download_url;
            } else {
                $obj->package = $repo_info->zipball_url;
            }
            
            $transient->response[$obj->plugin] = $obj;
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if ($args->slug !== $this->slug) {
            return $result;
        }

        $repo_info = $this->get_repository_info();

        if (!$repo_info) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->plugin_file);

        $plugin = new stdClass();
        $plugin->name = $plugin_data['Name'];
        $plugin->slug = $this->slug;
        $plugin->version = ltrim($repo_info->tag_name, 'v');
        $plugin->author = $plugin_data['Author'];
        $plugin->homepage = $repo_info->html_url;
        $plugin->requires = '5.0';
        $plugin->tested = get_bloginfo('version');
        $plugin->downloaded = 0;
        $plugin->last_updated = $repo_info->published_at;
        
        // Parse markdown body to HTML for sections
        require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
        
        // Simple markdown parser or just use the body as description
        // For simplicity, we just use nl2br
        $description = nl2br($repo_info->body);

        $plugin->sections = array(
            'description' => $plugin_data['Description'],
            'changelog' => $description
        );

        if (!empty($repo_info->assets) && isset($repo_info->assets[0]->browser_download_url)) {
            $plugin->download_link = $repo_info->assets[0]->browser_download_url;
        } else {
            $plugin->download_link = $repo_info->zipball_url;
        }

        return $plugin;
    }
}
