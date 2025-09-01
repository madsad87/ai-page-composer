<?php
/**
 * Governance History Page Template
 *
 * @package AIPageComposer\Templates\Admin\Governance
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div class="wrap ai-composer-governance">
    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'AI Generation Run History', 'ai-page-composer' ); ?>
    </h1>
    
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-composer-governance&action=export' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Export CSV', 'ai-page-composer' ); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Statistics Dashboard -->
    <div class="governance-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo esc_html( number_format( $statistics['total_runs'] ) ); ?></h3>
                <p><?php esc_html_e( 'Total Runs', 'ai-page-composer' ); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo esc_html( number_format( $statistics['completed_runs'] ) ); ?></h3>
                <p><?php esc_html_e( 'Completed', 'ai-page-composer' ); ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo esc_html( number_format( $statistics['failed_runs'] ) ); ?></h3>
                <p><?php esc_html_e( 'Failed', 'ai-page-composer' ); ?></p>
            </div>
            <div class="stat-card">
                <h3>$<?php echo esc_html( number_format( $statistics['total_cost'], 2 ) ); ?></h3>
                <p><?php esc_html_e( 'Total Cost', 'ai-page-composer' ); ?></p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="governance-filters">
        <form method="GET" action="">
            <input type="hidden" name="page" value="ai-composer-governance">
            
            <select name="status">
                <option value="all" <?php selected( $_GET['status'] ?? 'all', 'all' ); ?>>
                    <?php esc_html_e( 'All Statuses', 'ai-page-composer' ); ?>
                </option>
                <option value="completed" <?php selected( $_GET['status'] ?? '', 'completed' ); ?>>
                    <?php esc_html_e( 'Completed', 'ai-page-composer' ); ?>
                </option>
                <option value="failed" <?php selected( $_GET['status'] ?? '', 'failed' ); ?>>
                    <?php esc_html_e( 'Failed', 'ai-page-composer' ); ?>
                </option>
                <option value="in_progress" <?php selected( $_GET['status'] ?? '', 'in_progress' ); ?>>
                    <?php esc_html_e( 'In Progress', 'ai-page-composer' ); ?>
                </option>
            </select>
            
            <input type="date" name="date_from" value="<?php echo esc_attr( $_GET['date_from'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'ai-page-composer' ); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr( $_GET['date_to'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'ai-page-composer' ); ?>">
            
            <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'ai-page-composer' ); ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-composer-governance' ) ); ?>" class="button">
                <?php esc_html_e( 'Clear', 'ai-page-composer' ); ?>
            </a>
        </form>
    </div>

    <!-- Runs Table -->
    <div class="governance-runs">
        <?php if ( empty( $history_data['runs'] ) ) : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e( 'No AI generation runs found.', 'ai-page-composer' ); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Run ID', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Status', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Blueprint', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'User', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Start Time', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Duration', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Cost', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Plugins Used', 'ai-page-composer' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Actions', 'ai-page-composer' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $history_data['runs'] as $run ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $run['run_id'] ); ?></strong>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr( $run['status'] ); ?>">
                                    <?php echo esc_html( ucfirst( $run['status'] ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $run['blueprint_title'] ); ?></td>
                            <td><?php echo esc_html( $run['user_name'] ); ?></td>
                            <td>
                                <?php 
                                if ( $run['start_time'] ) {
                                    echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $run['start_time'] ) ) );
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ( $run['duration_seconds'] > 0 ) {
                                    echo esc_html( number_format( $run['duration_seconds'], 1 ) . 's' );
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ( $run['total_cost_usd'] > 0 ) {
                                    echo '$' . esc_html( number_format( $run['total_cost_usd'], 4 ) );
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ( !empty( $run['plugins_used'] ) ) {
                                    $plugins_display = array_slice( $run['plugins_used'], 0, 3 );
                                    echo esc_html( implode( ', ', $plugins_display ) );
                                    if ( count( $run['plugins_used'] ) > 3 ) {
                                        echo ' <em>+' . esc_html( count( $run['plugins_used'] ) - 3 ) . ' more</em>';
                                    }
                                } else {
                                    esc_html_e( 'Core blocks only', 'ai-page-composer' );
                                }
                                ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-composer-governance&action=view&run_id=' . urlencode( $run['run_id'] ) ) ); ?>">
                                            <?php esc_html_e( 'View', 'ai-page-composer' ); ?>
                                        </a>
                                    </span>
                                    
                                    <?php if ( $run['status'] === 'completed' ) : ?>
                                        | <span class="diff">
                                            <a href="#" class="diff-run" data-run-id="<?php echo esc_attr( $run['run_id'] ); ?>">
                                                <?php esc_html_e( 'Diff', 'ai-page-composer' ); ?>
                                            </a>
                                        </span>
                                        
                                        | <span class="rerun">
                                            <a href="#" class="rerun-generation" data-run-id="<?php echo esc_attr( $run['run_id'] ); ?>">
                                                <?php esc_html_e( 'Re-run', 'ai-page-composer' ); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ( $run['post_id'] ) : ?>
                                        | <span class="edit-post">
                                            <a href="<?php echo esc_url( get_edit_post_link( $run['post_id'] ) ); ?>">
                                                <?php esc_html_e( 'Edit Post', 'ai-page-composer' ); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                    
                                    | <span class="delete">
                                        <a href="#" class="delete-run" data-run-id="<?php echo esc_attr( $run['run_id'] ); ?>" style="color: #d63638;">
                                            <?php esc_html_e( 'Delete', 'ai-page-composer' ); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $history_data['pagination']['total_pages'] > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $current_page = $history_data['pagination']['current_page'];
                        $total_pages = $history_data['pagination']['total_pages'];
                        $base_url = admin_url( 'admin.php?page=ai-composer-governance' );
                        
                        // Add current filters to pagination links
                        $filter_params = [];
                        if ( !empty( $_GET['status'] ) && $_GET['status'] !== 'all' ) {
                            $filter_params['status'] = $_GET['status'];
                        }
                        if ( !empty( $_GET['date_from'] ) ) {
                            $filter_params['date_from'] = $_GET['date_from'];
                        }
                        if ( !empty( $_GET['date_to'] ) ) {
                            $filter_params['date_to'] = $_GET['date_to'];
                        }
                        
                        if ( $current_page > 1 ) {
                            $prev_url = add_query_arg( array_merge( $filter_params, ['paged' => $current_page - 1] ), $base_url );
                            echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">' . esc_html__( '‹ Previous', 'ai-page-composer' ) . '</a> ';
                        }
                        
                        printf(
                            '<span class="displaying-num">%s</span>',
                            sprintf(
                                esc_html( _n( '%s item', '%s items', $history_data['pagination']['total_items'], 'ai-page-composer' ) ),
                                number_format_i18n( $history_data['pagination']['total_items'] )
                            )
                        );
                        
                        if ( $current_page < $total_pages ) {
                            $next_url = add_query_arg( array_merge( $filter_params, ['paged' => $current_page + 1] ), $base_url );
                            echo ' <a class="next-page button" href="' . esc_url( $next_url ) . '">' . esc_html__( 'Next ›', 'ai-page-composer' ) . '</a>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Diff Modal -->
<div id="diff-modal" class="ai-composer-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php esc_html_e( 'Run Comparison', 'ai-page-composer' ); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="diff-content">
                <p><?php esc_html_e( 'Loading diff...', 'ai-page-composer' ); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Re-run Modal -->
<div id="rerun-modal" class="ai-composer-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php esc_html_e( 'Re-run Generation', 'ai-page-composer' ); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="rerun-content">
                <form id="rerun-form">
                    <h3><?php esc_html_e( 'Re-run Options', 'ai-page-composer' ); ?></h3>
                    
                    <label>
                        <input type="checkbox" name="preserve_plugin_preferences" checked>
                        <?php esc_html_e( 'Preserve plugin preferences', 'ai-page-composer' ); ?>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="fallback_on_missing_plugins" checked>
                        <?php esc_html_e( 'Fallback on missing plugins', 'ai-page-composer' ); ?>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="update_namespace_versions">
                        <?php esc_html_e( 'Update to current namespace versions', 'ai-page-composer' ); ?>
                    </label>
                    
                    <div class="modal-actions">
                        <button type="button" id="preview-rerun" class="button">
                            <?php esc_html_e( 'Preview Changes', 'ai-page-composer' ); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Execute Re-run', 'ai-page-composer' ); ?>
                        </button>
                    </div>
                </form>
                
                <div id="rerun-preview" style="display: none;">
                    <h3><?php esc_html_e( 'Preview', 'ai-page-composer' ); ?></h3>
                    <div id="preview-content"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.governance-stats {
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.stat-card h3 {
    font-size: 2em;
    margin: 0 0 10px 0;
    color: #1d2327;
}

.stat-card p {
    margin: 0;
    color: #646970;
    font-weight: 500;
}

.governance-filters {
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 15px;
    margin-bottom: 20px;
}

.governance-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-completed {
    background-color: #00a32a;
    color: white;
}

.status-failed {
    background-color: #d63638;
    color: white;
}

.status-in_progress {
    background-color: #dba617;
    color: white;
}

.ai-composer-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 4px;
    max-width: 800px;
    width: 90%;
    max-height: 90%;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #c3c4c7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 20px;
}

.modal-actions {
    margin-top: 20px;
    text-align: right;
}

.modal-actions .button {
    margin-left: 10px;
}

#rerun-form label {
    display: block;
    margin-bottom: 10px;
}

#rerun-form input[type="checkbox"] {
    margin-right: 8px;
}
</style>