<?php
/**
 * GitHub Updater
 *
 * Клас для автоматичного оновлення плагіну через GitHub релізи.
 *
 * @package Test
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Test_GitHub_Updater {

	private $slug;
	private $plugin_data;
	private $username;
	private $repo;
	private $plugin_file;
	private $github_api_result;
	private $access_token;

	/**
	 * Class constructor.
	 *
	 * @param string $plugin_file Path to the plugin file.
	 * @param string $github_username GitHub username.
	 * @param string $github_repo GitHub repo name.
	 * @param string $access_token GitHub access token (optional).
	 */
	public function __construct( $plugin_file, $github_username, $github_repo, $access_token = '' ) {
		$this->plugin_file = $plugin_file;
		$this->username    = $github_username;
		$this->repo        = $github_repo;
		$this->access_token = $access_token;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	/**
	 * Get repository info from GitHub.
	 *
	 * @return array|bool
	 */
	private function get_repository_info() {
		if ( ! empty( $this->github_api_result ) ) {
			return $this->github_api_result;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
		
		$headers = array(
			'Accept' => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
		);
		
		// Використовуємо Authorization header замість параметра URL
		if ( ! empty( $this->access_token ) ) {
			$headers['Authorization'] = 'token ' . $this->access_token;
		}

		$response = wp_remote_get( $url, array(
			'headers' => $headers,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $result ) ) {
			return false;
		}

		$this->github_api_result = $result;
		return $result;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient object.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get plugin data
		if ( ! isset( $this->plugin_data ) ) {
			$this->plugin_data = get_plugin_data( $this->plugin_file, false, false );
			$this->slug = plugin_basename( $this->plugin_file );
		}

		$repository_info = $this->get_repository_info();
		if ( false === $repository_info ) {
			return $transient;
		}

		$current_version = $this->plugin_data['Version'];
		// Remove 'v' prefix from GitHub version
		$github_version = ltrim( $repository_info['tag_name'], 'v' );

		if ( version_compare( $github_version, $current_version, '>' ) ) {
			$download_link = $this->get_zip_download_link( $repository_info );

			if ( $download_link ) {
				$transient->response[ $this->slug ] = (object) array(
					'slug'        => dirname( $this->slug ),
					'new_version' => $github_version,
					'url'         => $this->plugin_data['PluginURI'],
					'package'     => $download_link,
				);
			}
		}

		return $transient;
	}

	/**
	 * Get zip download link.
	 *
	 * @param array $repository_info Repository info.
	 * @return string
	 */
	private function get_zip_download_link( $repository_info ) {
		if ( ! empty( $repository_info['assets'] ) && is_array( $repository_info['assets'] ) ) {
			foreach ( $repository_info['assets'] as $asset ) {
				if ( isset( $asset['content_type'] ) && 'application/zip' === $asset['content_type'] ) {
					return $asset['browser_download_url'];
				}
			}
		}

		// Якщо немає ZIP архіву в assets, використовуємо стандартний ZIP з GitHub
		return "https://github.com/{$this->username}/{$this->repo}/archive/refs/tags/{$repository_info['tag_name']}.zip";
	}

	/**
	 * Display plugin information in dialog box.
	 *
	 * @param bool|object $result Result object.
	 * @param string $action Action name.
	 * @param object $args Arguments.
	 * @return object
	 */
	public function plugin_popup( $result, $action, $args ) {
		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->slug ) ) {
			return $result;
		}

		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$repository_info = $this->get_repository_info();
		if ( false === $repository_info ) {
			return $result;
		}

		$plugin_data = $this->plugin_data;
		$github_version = ltrim( $repository_info['tag_name'], 'v' );

		$result                = new stdClass();
		$result->name          = $plugin_data['Name'];
		$result->slug          = dirname( $this->slug );
		$result->version       = $github_version;
		$result->author        = $plugin_data['Author'];
		$result->homepage      = $plugin_data['PluginURI'];
		$result->requires      = $plugin_data['RequiresWP'] ?? '';
		$result->tested        = $plugin_data['TestedUpTo'] ?? '';
		$result->downloaded    = 0;
		$result->last_updated  = $repository_info['published_at'];
		$result->sections      = array(
			'description' => $plugin_data['Description'],
			'changelog'   => nl2br( $repository_info['body'] ),
		);
		$result->download_link = $this->get_zip_download_link( $repository_info );

		return $result;
	}

	/**
	 * Rename the plugin folder after update.
	 *
	 * @param bool $response Update response.
	 * @param array $hook_extra Extra arguments.
	 * @param array $result Result of the update.
	 * @return array
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Перевірка чи ініціалізовано $wp_filesystem
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$install_directory = plugin_dir_path( $this->plugin_file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		return $result;
	}
} 