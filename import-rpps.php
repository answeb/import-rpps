<?php
/**
 * Plugin Name: Import RPPS
 * Plugin URI: https://www.answeb.net
 * Description: Plugin WordPress pour maintenir à jour une liste de numéros RPPS (Répertoire Partagé des Professionnels de Santé)
 * Version: 1.0.0
 * Author: Answeb
 * Author URI: https://www.answeb.net
 * Text Domain: import-rpps
 * Domain Path: /languages
 * Requires at least: 6.8.1
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IMPORT_RPPS_VERSION', '1.0.0' );
define( 'IMPORT_RPPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMPORT_RPPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IMPORT_RPPS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( IMPORT_RPPS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once IMPORT_RPPS_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	wp_die( __( 'Les dépendances Composer ne sont pas installées. Veuillez exécuter "composer install" dans le répertoire du plugin.',
		'import-rpps' ) );
}

use ImportRpps\Admin;
use ImportRpps\Database;
use ImportRpps\Scheduler;

class ImportRppsPlugin {
	private static $instance = null;
	private $database;
	private $admin;
	private $scheduler;

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	public function init() {
		if ( ! $this->checkRequirements() ) {
			return;
		}

		$this->database  = new Database();
		$this->admin     = new Admin();
		$this->scheduler = new Scheduler();

		load_plugin_textdomain( 'import-rpps', false, dirname( IMPORT_RPPS_PLUGIN_BASENAME ) . '/languages' );
	}

	public function activate() {
		if ( ! $this->checkRequirements() ) {
			deactivate_plugins( IMPORT_RPPS_PLUGIN_BASENAME );
			wp_die( __( 'Ce plugin nécessite WordPress 6.8.1 ou supérieur et PHP 8.0 ou supérieur.', 'import-rpps' ) );
		}

		$database = new Database();
		$database->createTable();

		if ( ! wp_next_scheduled( 'import_rpps_cron_hook' ) ) {
			wp_schedule_event( time(), 'weekly', 'import_rpps_cron_hook' );
		}
	}

	public function deactivate() {
		wp_clear_scheduled_hook( 'import_rpps_cron_hook' );
	}

	private function checkRequirements() {
		global $wp_version;
		$compliant = true;
		if ( version_compare( $wp_version, '6.8.1', '<' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="error"><p>' . __( 'Le plugin Import RPPS nécessite WordPress 6.8.1 ou supérieur.',
						'import-rpps' ) . '</p></div>';
			} );
			$compliant = false;
		}

		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="error"><p>' . __( 'Le plugin Import RPPS nécessite PHP 8.0 ou supérieur.',
						'import-rpps' ) . '</p></div>';
			} );
			$compliant = false;
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="error"><p>' . __( 'Le plugin Import RPPS nécessite l\'extension PHP ZipArchive.',
						'import-rpps' ) . '</p></div>';
			} );
			$compliant = false;
		}
		return $compliant;
	}

	public function getDatabase() {
		return $this->database;
	}

	public function getAdmin() {
		return $this->admin;
	}

	public function getScheduler() {
		return $this->scheduler;
	}
}

function importRpps() {
	return ImportRppsPlugin::getInstance();
}

/**
 * Fonction globale pour valider un numéro RPPS
 * 
 * @param string $rpps_number Le numéro RPPS à valider
 * @return bool True si le numéro est valide, false sinon
 */
function import_rpps_validate_number($rpps_number) {
	$plugin = importRpps();
	if (!$plugin || !$plugin->getDatabase()) {
		return false;
	}
	
	return $plugin->getDatabase()->validateRppsNumber($rpps_number);
}

importRpps();