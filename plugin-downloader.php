<?php
/*
Plugin Name: Plugin Downloader
Description: Download installed plugins as ZIP files
Version: 1.0
Author: Ricardo Christovão da Silva
*/

if (!defined('ABSPATH')) exit;

class PluginDownloader {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_post_download_plugins', array($this, 'download_selected_plugins'));
    }

    public function add_plugin_page() {
        add_submenu_page(
            'plugins.php',
            'Download Plugins',
            'Download Plugins',
            'install_plugins',
            'plugin-downloader',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        if (!current_user_can('install_plugins')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Obtém todos os plugins instalados
        $all_plugins = get_plugins();
        ?>
        <div class="wrap">
            <h1>Download Plugins</h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="download_plugins">
                <?php wp_nonce_field('download_plugins_nonce'); ?>
                
                <table class="wp-list-table widefat plugins">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="select-all-plugins"></th>
                            <th>Plugin</th>
                            <th>Description</th>
                            <th>Version</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_plugins as $plugin_path => $plugin_data): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="selected_plugins[]" 
                                       value="<?php echo esc_attr($plugin_path); ?>">
                            </td>
                            <td>
                                <strong><?php echo esc_html($plugin_data['Name']); ?></strong>
                            </td>
                            <td><?php echo esc_html($plugin_data['Description']); ?></td>
                            <td><?php echo esc_html($plugin_data['Version']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" 
                           value="Download Selected Plugins">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#select-all-plugins').change(function() {
                $('input[name="selected_plugins[]"]').prop('checked', this.checked);
            });
        });
        </script>
        <?php
    }

    public function download_selected_plugins() {
        if (!current_user_can('install_plugins') || 
            !check_admin_referer('download_plugins_nonce')) {
            wp_die('Unauthorized');
        }

        if (!isset($_POST['selected_plugins']) || empty($_POST['selected_plugins'])) {
            wp_redirect(admin_url('admin.php?page=plugin-downloader&error=no-selection'));
            exit;
        }

        require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');

        $zip_file = wp_tempnam('plugins_backup.zip');
        $zip = new PclZip($zip_file);

        $plugins_dir = WP_PLUGIN_DIR;
        $selected_plugins = $_POST['selected_plugins'];

        foreach ($selected_plugins as $plugin_path) {
            $plugin_folder = dirname($plugins_dir . '/' . $plugin_path);
            $plugin_name = basename($plugin_folder);
            
            $zip->add(
                $plugin_folder,
                PCLZIP_OPT_REMOVE_PATH, $plugins_dir,
                PCLZIP_OPT_ADD_PATH, $plugin_name
            );
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="wordpress-plugins-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zip_file));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zip_file);
        unlink($zip_file);
        exit;
    }
}

new PluginDownloader();