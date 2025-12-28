<?php

class Nueva_Chatbot_Updater
{

    private $slug;
    private $plugin_data;
    private $usernameParam;
    private $repoParam;
    private $plugin_file;
    private $github_auth;
    private $plugin_slug;

    public function __construct($plugin_file, $github_username, $github_repo, $access_token = '')
    {
        $this->plugin_file = $plugin_file;
        $this->usernameParam = $github_username;
        $this->repoParam = $github_repo;
        $this->github_auth = $access_token;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);

        // Populate plugin data
        $this->plugin_data = get_plugin_data($plugin_file);
        // Slug should be the folder/filename e.g. nueva-ai-chatbot/nueva-ai-chatbot.php
        $this->slug = plugin_basename($plugin_file);

        // Extract just the folder name for API checks if needed, but WP uses full path
        $this->plugin_slug = dirname(plugin_basename($plugin_file));
    }

    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();

        if (is_wp_error($release) || !$release) {
            return $transient;
        }

        // Version Compare
        // GitHub tag might be 'v1.2.0', we strip 'v'
        $remote_version = ltrim($release->tag_name, 'v');
        $local_version = $this->plugin_data['Version'];

        if (version_compare($remote_version, $local_version, '>')) {

            $res = new stdClass();
            $res->slug = $this->plugin_slug; // Just the folder name usually for WP.org, but for custom...
            // It's tricky with custom updaters. Let's use the full path key in the response.

            $res->plugin = $this->slug; // nueva-ai-chatbot/nueva-ai-chatbot.php
            $res->new_version = $remote_version;
            $res->url = $release->html_url;
            $res->package = $release->zipball_url; // GitHub zipball

            // Icons/Banners would go here if we had them

            $transient->response[$this->slug] = $res;
            $transient->no_update[$this->slug] = $res; // Prevents "update available" if strict check fails? No, actually just putting it in response is enough.
        }

        return $transient;
    }

    public function plugin_info($res, $action, $args)
    {
        if ('plugin_information' !== $action) {
            return $res;
        }

        // Check if it's our plugin
        if ($this->plugin_slug !== $args->slug) {
            return $res;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $res;
        }

        $res = new stdClass();
        $res->name = $this->plugin_data['Name'];
        $res->slug = $this->plugin_slug;
        $res->version = ltrim($release->tag_name, 'v');
        $res->author = $this->plugin_data['AuthorName'];
        $res->author_profile = $this->plugin_data['AuthorURI'];
        $res->last_updated = $release->published_at;
        $res->homepage = $this->plugin_data['PluginURI'];
        $res->download_link = $release->zipball_url;

        // Parse Body as Markdown (Simple)
        $description = $release->body;
        // Parsedown or similar would be better, but for now just nl2br
        $res->sections = array(
            'description' => nl2br($description),
            'changelog' => nl2br($description)
        );

        return $res;
    }

    private function get_github_release()
    {
        // Cache API calls for 1 hour
        $cache_key = 'nueva_chatbot_github_release';
        $release = get_transient($cache_key);

        if (false === $release) {
            $url = "https://api.github.com/repos/{$this->usernameParam}/{$this->repoParam}/releases/latest";

            $args = array('timeout' => 10);
            if (!empty($this->github_auth)) {
                $args['headers'] = array('Authorization' => "token {$this->github_auth}");
            }

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);

            if (isset($data->tag_name)) {
                $release = $data;
                set_transient($cache_key, $release, HOUR_IN_SECONDS);
            }
        }

        return $release;
    }
}
