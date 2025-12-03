<?php

if (!defined('ABSPATH')) {
    exit;
}

class Adyen_Apple_Pay_Log_Viewer {

    public function render() {
        // Handle log clearing
        if (isset($_POST['clear_logs']) && check_admin_referer('adyen_clear_logs', 'adyen_logs_nonce')) {
            $this->clear_logs();
            echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared successfully.', 'adyen-apple-pay') . '</p></div>';
        }

        // Get log files
        $log_files = $this->get_log_files();
        $selected_log = isset($_GET['log_file']) ? sanitize_text_field($_GET['log_file']) : '';

        ?>
        <h2><?php echo esc_html__('Logs', 'adyen-apple-pay'); ?></h2>

        <div class="adyen-logs-container">
            <?php if (!empty($log_files)): ?>
                <div class="adyen-logs-controls">
                    <label for="log-file-select">
                        <?php esc_html_e('Select Log File:', 'adyen-apple-pay'); ?>
                    </label>
                    <select id="log-file-select">
                        <option value=""><?php esc_html_e('-- Select a log file --', 'adyen-apple-pay'); ?></option>
                        <?php foreach ($log_files as $file): ?>
                            <option value="<?php echo esc_attr($file['name']); ?>" <?php selected($selected_log, $file['name']); ?>>
                                <?php echo esc_html($file['name']); ?>
                                (<?php echo esc_html($file['size']); ?> - <?php echo esc_html($file['modified']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="load-log-btn">
                        <?php esc_html_e('Load Log', 'adyen-apple-pay'); ?>
                    </button>
                </div>

                <div class="adyen-logs-actions">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('adyen_clear_logs', 'adyen_logs_nonce'); ?>
                        <button type="submit" name="clear_logs" class="button button-secondary"
                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'adyen-apple-pay'); ?>');">
                            <?php esc_html_e('Clear All Logs', 'adyen-apple-pay'); ?>
                        </button>
                    </form>
                    <button type="button" class="button" id="refresh-log-btn">
                        <?php esc_html_e('Refresh', 'adyen-apple-pay'); ?>
                    </button>
                    <button type="button" class="button" id="download-log-btn">
                        <?php esc_html_e('Download Log', 'adyen-apple-pay'); ?>
                    </button>
                </div>

                <div id="log-content" class="adyen-log-content">
                    <?php
                    if ($selected_log) {
                        echo esc_html($this->get_log_content($selected_log));
                    } else {
                        echo esc_html__('Select a log file to view its contents.', 'adyen-apple-pay');
                    }
                    ?>
                </div>

            <?php else: ?>
                <div class="adyen-notice adyen-notice-warning">
                    <p><strong><?php esc_html_e('No log files found.', 'adyen-apple-pay'); ?></strong></p>
                    <p><?php esc_html_e('Logs will appear here when debug mode is enabled and events are logged.', 'adyen-apple-pay'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=adyen-apple-pay-config')); ?>" class="button button-primary">
                            <?php esc_html_e('Enable Debug Logging', 'adyen-apple-pay'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#load-log-btn').on('click', function() {
                var logFile = $('#log-file-select').val();
                if (logFile) {
                    window.location.href = '<?php echo admin_url('admin.php?page=adyen-apple-pay-logs'); ?>&log_file=' + encodeURIComponent(logFile);
                } else {
                    alert('<?php esc_html_e('Please select a log file.', 'adyen-apple-pay'); ?>');
                }
            });

            $('#refresh-log-btn').on('click', function() {
                location.reload();
            });

            $('#download-log-btn').on('click', function() {
                var logFile = $('#log-file-select').val();
                if (logFile) {
                    var content = $('#log-content').text();
                    var blob = new Blob([content], { type: 'text/plain' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = logFile;
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('<?php esc_html_e('Please select a log file.', 'adyen-apple-pay'); ?>');
                }
            });
        });
        </script>
        <?php
    }

    private function get_log_files() {
        $log_files = array();

        if (!class_exists('WC_Log_Handler_File')) {
            return $log_files;
        }

        $upload_dir = wp_upload_dir(null, false);
        $log_dir = $upload_dir['basedir'] . '/wc-logs/';

        if (method_exists('WC_Log_Handler_File', 'get_log_file_path')) {
            $log_path = WC_Log_Handler_File::get_log_file_path('adyen-apple-pay');
            $log_dir = dirname($log_path) . '/';
        }

        if (!is_dir($log_dir)) {
            return $log_files;
        }

        $files = glob($log_dir . 'adyen-apple-pay-*.log');

        if ($files && is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file) && is_readable($file)) {
                    $log_files[] = array(
                        'name' => basename($file),
                        'path' => $file,
                        'size' => size_format(filesize($file)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file))
                    );
                }
            }

            if (!empty($log_files)) {
                usort($log_files, function($a, $b) {
                    return filemtime($b['path']) - filemtime($a['path']);
                });
            }
        }

        return $log_files;
    }

    private function get_log_content($log_file) {
        $upload_dir = wp_upload_dir(null, false);
        $log_dir = $upload_dir['basedir'] . '/wc-logs/';

        if (class_exists('WC_Log_Handler_File') && method_exists('WC_Log_Handler_File', 'get_log_file_path')) {
            $log_path = WC_Log_Handler_File::get_log_file_path('adyen-apple-pay');
            $log_dir = dirname($log_path) . '/';
        }

        $file_path = $log_dir . $log_file;

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return __('Log file not found or not readable.', 'adyen-apple-pay');
        }

        try {
            $file = new SplFileObject($file_path, 'r');
            $file->seek(PHP_INT_MAX);
            $total_lines = $file->key();

            $start_line = max(0, $total_lines - 5000);
            $file->seek($start_line);

            $lines = array();
            while (!$file->eof()) {
                $lines[] = $file->current();
                $file->next();
            }

            $content = implode('', $lines);

            if ($total_lines > 5000) {
                $content = sprintf(
                    "=== Showing last 5000 lines of %d total lines ===\n\n",
                    $total_lines
                ) . $content;
            }

            return $content;
        } catch (Exception $e) {
            return sprintf(__('Error reading log file: %s', 'adyen-apple-pay'), $e->getMessage());
        }
    }

    private function clear_logs() {
        $upload_dir = wp_upload_dir(null, false);
        $log_dir = $upload_dir['basedir'] . '/wc-logs/';

        if (class_exists('WC_Log_Handler_File') && method_exists('WC_Log_Handler_File', 'get_log_file_path')) {
            $log_path = WC_Log_Handler_File::get_log_file_path('adyen-apple-pay');
            $log_dir = dirname($log_path) . '/';
        }

        $files = glob($log_dir . 'adyen-apple-pay-*.log');

        if ($files && is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file) && is_writable($file)) {
                    unlink($file);
                }
            }
        }
    }
}
