<?php
/**
 * Core Plugin Class - Central Orchestrator and Singleton Manager
 *
 * This file contains the main plugin class for AI Page Composer that serves as the central
 * orchestrator for all plugin functionality. It implements the singleton pattern, manages
 * component initialization, handles WordPress hooks, and coordinates between different
 * managers (Admin, Settings, Security, API). This is the heart of the plugin architecture.
 *
 * Main plugin class
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\Core;

use AIPageComposer\Admin\Admin_Manager;
use AIPageComposer\Admin\Settings_Manager;
use AIPageComposer\Admin\Block_Preferences;
use AIPageComposer\API\API_Manager;
use AIPageComposer\API\Outline_Controller;
use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Blueprints\Blueprint_Manager;
use AIPageComposer\Blueprints\Blueprint_REST_Controller;
use AIPageComposer\API\Governance_Controller;

/**
 * Main AI Page Composer plugin class
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Admin manager instance
	 *
	 * @var Admin_Manager
	 */
	public $admin;

	/**
	 * Settings manager instance
	 *
	 * @var Settings_Manager
	 */
	public $settings;

	/**
	 * Block preferences instance
	 *
	 * @var Block_Preferences
	 */
	public $block_preferences;

	/**
	 * API manager instance
	 *
	 * @var API_Manager
	 */
	public $api;

	/**
	 * Outline controller instance
	 *
	 * @var Outline_Controller
	 */
	public $outline_controller;

	/**
	 * Security helper instance
	 *
	 * @var Security_Helper
	 */
	public $security;

	/**
	 * Blueprint manager instance
	 *
	 * @var Blueprint_Manager
	 */
	public $blueprints;

	/**
	 * Blueprint REST controller instance
	 *
	 * @var Blueprint_REST_Controller
	 */
	public $blueprint_rest;

	/**
	 * Governance controller instance
	 *
	 * @var Governance_Controller
	 */
	public $governance;

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Load plugin textdomain
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			AI_PAGE_COMPOSER_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( AI_PAGE_COMPOSER_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init_plugin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'add_meta_tags' ) );

		// Register scheduled event hooks
		add_action( 'ai_composer_daily_reset', array( $this, 'reset_daily_costs' ) );
		add_action( 'ai_composer_weekly_cleanup', array( $this, 'cleanup_old_runs' ) );
		add_action( 'ai_composer_monthly_reset', array( $this, 'reset_monthly_costs' ) );
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Initialize security helper first
		$this->security = new Security_Helper();

		// Initialize settings manager
		$this->settings = new Settings_Manager();

		// Initialize block preferences
		$this->block_preferences = new Block_Preferences();

		// Initialize blueprint system
		$this->blueprints = new Blueprint_Manager( $this->block_preferences );

		// Initialize blueprint REST controller
		$this->blueprint_rest = new Blueprint_REST_Controller();
		$this->blueprint_rest->set_blueprint_manager( $this->blueprints );

		// Initialize admin manager
		$this->admin = new Admin_Manager( $this->settings, $this->block_preferences );

		// Initialize API manager
		$this->api = new API_Manager( $this->settings );

		// Initialize outline controller
		$this->outline_controller = new Outline_Controller( $this->blueprints, $this->block_preferences );
		$this->api->set_outline_controller( $this->outline_controller );

		// Initialize governance system
		$this->governance = new Governance_Controller();
		$this->governance->init();

		// Register REST routes after init
		add_action( 'rest_api_init', array( $this->blueprint_rest, 'register_routes' ) );
	}

	/**
	 * Initialize plugin on WordPress init
	 */
	public function init_plugin() {
		// Plugin initialization logic
		do_action( 'ai_composer_init' );

		// Check if we need to run any upgrades
		$this->maybe_upgrade();

		// Initialize cost tracking
		$this->init_cost_tracking();
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'ai-page-composer-style',
			AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/css/style.css',
			array(),
			AI_PAGE_COMPOSER_VERSION
		);

		wp_enqueue_script(
			'ai-page-composer-script',
			AI_PAGE_COMPOSER_PLUGIN_URL . 'assets/js/script.js',
			array( 'jquery' ),
			AI_PAGE_COMPOSER_VERSION,
			true
		);

		// Localize script with necessary data
		wp_localize_script(
			'ai-page-composer-script',
			'aiComposer',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => Security_Helper::generate_ajax_nonce(),
				'textDomain'    => AI_PAGE_COMPOSER_TEXT_DOMAIN,
				'isAdmin'       => is_admin(),
				'currentUserId' => get_current_user_id(),
			)
		);
	}

	/**
	 * Add meta tags for AI Page Composer
	 */
	public function add_meta_tags() {
		// Add generator meta tag
		echo '<meta name="generator" content="AI Page Composer ' . esc_attr( AI_PAGE_COMPOSER_VERSION ) . '" />' . "\n";
	}

	/**
	 * Reset daily cost tracking
	 */
	public function reset_daily_costs() {
		update_option( 'ai_composer_daily_costs', 0.0 );
		update_option( 'ai_composer_daily_runs', 0 );

		// Log reset event
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[AI Page Composer] Daily costs reset at ' . current_time( 'mysql' ) );
		}
	}

	/**
	 * Cleanup old generation runs
	 */
	public function cleanup_old_runs() {
		global $wpdb;

		$runs_table  = $wpdb->prefix . 'ai_composer_runs';
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// Delete runs older than 30 days (but keep successful ones for 90 days)
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $runs_table WHERE started_at < %s AND (status = 'failed' OR status = 'cancelled')",
				$cutoff_date
			)
		);

		$long_cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $runs_table WHERE started_at < %s",
				$long_cutoff_date
			)
		);
	}

	/**
	 * Reset monthly cost tracking
	 */
	public function reset_monthly_costs() {
		update_option( 'ai_composer_monthly_costs', 0.0 );
		update_option( 'ai_composer_monthly_runs', 0 );

		// Log reset event
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[AI Page Composer] Monthly costs reset at ' . current_time( 'mysql' ) );
		}
	}

	/**
	 * Maybe run plugin upgrades
	 */
	private function maybe_upgrade() {
		$plugin_data     = get_option( 'ai_composer_plugin_data', array() );
		$current_version = $plugin_data['version'] ?? '0.0.0';

		if ( version_compare( $current_version, AI_PAGE_COMPOSER_VERSION, '<' ) ) {
			$this->run_upgrade( $current_version );
		}
	}

	/**
	 * Run plugin upgrade
	 *
	 * @param string $from_version The version upgrading from.
	 */
	private function run_upgrade( $from_version ) {
		// Update version in database
		$plugin_data            = get_option( 'ai_composer_plugin_data', array() );
		$plugin_data['version'] = AI_PAGE_COMPOSER_VERSION;
		update_option( 'ai_composer_plugin_data', $plugin_data );

		// Run version-specific upgrades if needed
		do_action( 'ai_composer_upgrade', $from_version, AI_PAGE_COMPOSER_VERSION );
	}

	/**
	 * Initialize cost tracking
	 */
	private function init_cost_tracking() {
		// Ensure cost tracking options exist
		if ( false === get_option( 'ai_composer_daily_costs' ) ) {
			add_option( 'ai_composer_daily_costs', 0.0 );
		}

		if ( false === get_option( 'ai_composer_monthly_costs' ) ) {
			add_option( 'ai_composer_monthly_costs', 0.0 );
		}

		if ( false === get_option( 'ai_composer_last_reset' ) ) {
			add_option( 'ai_composer_last_reset', current_time( 'mysql' ) );
		}
	}

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		return self::$instance;
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version() {
		return AI_PAGE_COMPOSER_VERSION;
	}

	/**
	 * Get plugin settings
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings ? $this->settings->get_all_settings() : array();
	}

	/**
	 * Check if plugin requirements are met
	 *
	 * @return bool
	 */
	public function requirements_met() {
		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			return false;
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			return false;
		}

		// Check if required functions exist
		if ( ! function_exists( 'wp_remote_post' ) || ! function_exists( 'wp_create_nonce' ) ) {
			return false;
		}

		return true;
	}
}
