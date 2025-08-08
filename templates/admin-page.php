<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap import-rpps-admin">
    <h1><?php _e('Import RPPS', 'import-rpps'); ?></h1>

    <div class="section-grid">
        <div class="section section-stat">
            <div class="section-header">
                <h3><?php _e('Enregistrements', 'import-rpps'); ?></h3>
            </div>
            <div class="section-body">
                <div class="stat-value"><?php echo number_format($stats['total_records']); ?></div>
                <div class="stat-label"><?php _e('numéros RPPS en base', 'import-rpps'); ?></div>
            </div>
        </div>

        <div class="section section-stat">
            <div class="section-header">
                <h3><?php _e('Dernière mise à jour', 'import-rpps'); ?></h3>
            </div>
            <div class="section-body">
                <div class="stat-value">
                    <?php
                    if ($stats['last_import']) {
                        echo date_i18n('d/m/Y H:i', strtotime($stats['last_import']));
                    } else {
                        _e('Jamais', 'import-rpps');
                    }
                    ?>
                </div>
                <div class="stat-label"><?php _e('date du dernier import', 'import-rpps'); ?></div>
            </div>
        </div>

        <div class="section section-stat">
            <div class="section-header">
                <h3><?php _e('Taille de la base', 'import-rpps'); ?></h3>
            </div>
            <div class="section-body">
                <div class="stat-value"><?php echo $stats['table_size']; ?></div>
                <div class="stat-label"><?php _e('espace disque utilisé', 'import-rpps'); ?></div>
            </div>
        </div>
        <div class="section">
            <div class="section-header">
                <h3><?php _e('Validation de numéro RPPS', 'import-rpps'); ?></h3>
            </div>
            <div class="section-body">
                <p><?php _e('Vérifiez si un numéro RPPS existe dans la base de données.', 'import-rpps'); ?></p>

                <form id="import-rpps-validate-form" class="form-inline">
                    <div class="form-group">
                        <label for="import-rpps-validate-number"><?php _e('Numéro RPPS :', 'import-rpps'); ?></label>
                        <input type="text" id="import-rpps-validate-number" name="number" placeholder="<?php _e('Saisissez un numéro RPPS', 'import-rpps'); ?>" />
                    </div>
                    <button type="submit" class="button button-primary">
					    <?php _e('Valider', 'import-rpps'); ?>
                    </button>
                </form>

                <div class="import-rpps-validator-result"></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3><?php _e('Actions', 'import-rpps'); ?></h3>
        </div>
        <div class="section-body">
            <p><?php _e('Lancez manuellement l\'import des données RPPS ou gérez les logs du plugin.', 'import-rpps'); ?></p>

            <form method="post" id="import-rpps-manual-form">
                <?php wp_nonce_field('import_rpps_manual_nonce'); ?>
                <button type="submit" name="import_rpps_manual_submit" id="import-rpps-manual-import" class="button button-primary">
                    <?php _e('Lancer l\'import', 'import-rpps'); ?>
                </button>
                <button id="import-rpps-clear-logs" class="button button-secondary">
                    <?php _e('Effacer les logs', 'import-rpps'); ?>
                </button>
            </form>

            <div id="import-rpps-progress" class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">0%</div>
            </div>
        </div>
    </div>


    <div class="section">
        <div class="section-header">
            <h3><?php _e('Configuration', 'import-rpps'); ?></h3>
        </div>
        <div class="section-body">
            <form method="post" action="options.php">
                <?php
                settings_fields('import_rpps_settings');
                settings_errors('import_rpps_settings');
                do_settings_sections('import_rpps_settings');
                submit_button();
                ?>
            </form>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <h3><?php _e('Journal des événements', 'import-rpps'); ?></h3>
        </div>
        <div class="section-body">
            <?php if (!empty($logs)): ?>
                <p><?php _e('Les 20 derniers événements du plugin (maximum 100 conservés).', 'import-rpps'); ?></p>

                <div class="log-container">
                    <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <div class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></div>
                        <div class="log-level log-level-<?php echo esc_attr($log['level']); ?>">
                            <?php echo strtoupper(esc_html($log['level'])); ?>
                        </div>
                        <div class="log-message"><?php echo esc_html($log['message']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('Aucun événement enregistré pour le moment.', 'import-rpps'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div id="markdown-content"><?php echo file_get_contents(IMPORT_RPPS_PLUGIN_DIR . '/README.md'); ?></div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('markdown-content').innerHTML = marked.parse(document.getElementById('markdown-content').innerHTML);
                });
            </script>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/15.0.7/marked.min.js" integrity="sha512-rPuOZPx/WHMHNx2RoALKwiCDiDrCo4ekUctyTYKzBo8NGA79NcTW2gfrbcCL2RYL7RdjX2v9zR0fKyI4U4kPew==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    </div>

</div>