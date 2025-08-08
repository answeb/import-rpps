<?php

namespace ImportRpps;

if (!defined('ABSPATH')) {
    exit;
}

class Database
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rpps_data';
    }

    public function getTableName()
    {
        return $this->table_name;
    }

    public function createTable()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type_identifiant_pp varchar(10) NOT NULL,
            identifiant_pp varchar(20) NOT NULL,
            identification_nationale_pp varchar(20) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY identification_nationale_pp (identification_nationale_pp),
            KEY identifiant_pp (identifiant_pp),
            KEY type_identifiant_pp (type_identifiant_pp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $this->logMessage("Table {$this->table_name} créée avec succès");
    }

    public function dropTable()
    {
        global $wpdb;

        $sql = "DROP TABLE IF EXISTS {$this->table_name}";
        $wpdb->query($sql);

        $this->logMessage("Table {$this->table_name} supprimée");
    }

    public function truncateTable()
    {
        global $wpdb;

        $sql = "TRUNCATE TABLE {$this->table_name}";
        $result = $wpdb->query($sql);

        if ($result !== false) {
            $this->logMessage("Table {$this->table_name} vidée avec succès");
            return true;
        } else {
            $this->logMessage("Erreur lors du vidage de la table: " . $wpdb->last_error, 'error');
            return false;
        }
    }

    public function insertBatch($data)
    {
        global $wpdb;

        if (empty($data)) {
            return 0;
        }

        $values = array();
        $placeholders = array();

        foreach ($data as $row) {
            $values[] = $row['type_identifiant_pp'];
            $values[] = $row['identifiant_pp'];
            $values[] = $row['identification_nationale_pp'];
            $placeholders[] = "(%s, %s, %s)";
        }

        $sql = "INSERT IGNORE INTO {$this->table_name} 
                (type_identifiant_pp, identifiant_pp, identification_nationale_pp) 
                VALUES " . implode(', ', $placeholders);

        $result = $wpdb->query($wpdb->prepare($sql, $values));

        if ($result === false) {
            $this->logMessage("Erreur lors de l'insertion: " . $wpdb->last_error, 'error');
            return false;
        }

        return $result;
    }

    public function getCount()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        return (int) $count;
    }

    public function validateRppsNumber($rpps_number)
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE identification_nationale_pp = %s OR identifiant_pp = %s",
            $rpps_number,
            $rpps_number
        );

        $count = $wpdb->get_var($sql);
        return (int) $count > 0;
    }

    public function getLastImportDate()
    {
	    global $wpdb;

	    $date = $wpdb->get_row($wpdb->prepare(
		    'SELECT update_time FROM information_schema.TABLES 
             WHERE table_schema = %s AND table_name = %s',
		    DB_NAME,
		    $this->table_name
	    ));
		return $date->update_time ? date_i18n('Y-m-d H:i:s', strtotime($date->update_time)) : __('Jamais', 'import-rpps');
    }

    public function getImportStats()
    {
        return array(
            'total_records' => $this->getCount(),
            'last_import' => $this->getLastImportDate(),
            'table_size' => $this->getTableSize()
        );
    }

    private function getTableSize()
    {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
             FROM information_schema.TABLES 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $this->table_name
        ));

        return $result ? $result->size_mb . ' Mo' : 'N/A';
    }

    private function logMessage($message, $level = 'info')
    {
        $logs = get_option('import_rpps_logs', array());

        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message
        );

        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option('import_rpps_logs', $logs);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Import RPPS] {$level}: {$message}");
        }
    }
}