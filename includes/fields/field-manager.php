<?php
/**
 * Field Manager - ACF Integration and Custom Field Management
 * 
 * This file handles all custom field functionality, including ACF field group registration, meta box creation
 * for non-ACF environments, and provides a unified API for field operations. It follows ACF patterns while
 * providing fallbacks for environments where ACF is not available, ensuring plugin compatibility.
 *
 * Field Manager class following ACF patterns
 *
 * @package ModernWPPlugin
 */

namespace ModernWPPlugin\Fields;

/**
 * Field Manager class
 */
class Field_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'acf/init', array( $this, 'register_field_groups' ) );
        add_action( 'init', array( $this, 'register_custom_fields' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
    }

    /**
     * Register ACF field groups
     */
    public function register_field_groups() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $this->register_sample_field_group();
    }

    /**
     * Register sample field group
     */
    private function register_sample_field_group() {
        acf_add_local_field_group( array(
            'key'      => 'group_modern_wp_plugin_sample',
            'title'    => __( 'Sample Field Group', 'modern-wp-plugin' ),
            'fields'   => array(
                array(
                    'key'   => 'field_sample_text',
                    'label' => __( 'Sample Text', 'modern-wp-plugin' ),
                    'name'  => 'sample_text',
                    'type'  => 'text',
                ),
                array(
                    'key'   => 'field_sample_textarea',
                    'label' => __( 'Sample Textarea', 'modern-wp-plugin' ),
                    'name'  => 'sample_textarea',
                    'type'  => 'textarea',
                ),
                array(
                    'key'   => 'field_sample_image',
                    'label' => __( 'Sample Image', 'modern-wp-plugin' ),
                    'name'  => 'sample_image',
                    'type'  => 'image',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'post',
                    ),
                ),
            ),
        ) );
    }

    /**
     * Register custom fields (fallback for non-ACF environments)
     */
    public function register_custom_fields() {
        // Custom field registration logic for environments without ACF
    }

    /**
     * Add meta boxes for custom fields
     */
    public function add_meta_boxes() {
        add_meta_box(
            'modern-wp-plugin-fields',
            __( 'Plugin Custom Fields', 'modern-wp-plugin' ),
            array( $this, 'render_meta_box' ),
            'post',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post Current post object.
     */
    public function render_meta_box( $post ) {
        // Only show if ACF is not available
        if ( function_exists( 'get_field' ) ) {
            return;
        }

        wp_nonce_field( 'modern_wp_plugin_meta_box', 'modern_wp_plugin_meta_box_nonce' );

        $sample_text = get_post_meta( $post->ID, 'sample_text', true );
        $sample_textarea = get_post_meta( $post->ID, 'sample_textarea', true );

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sample_text"><?php esc_html_e( 'Sample Text', 'modern-wp-plugin' ); ?></label>
                </th>
                <td>
                    <input type="text" id="sample_text" name="sample_text" value="<?php echo esc_attr( $sample_text ); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sample_textarea"><?php esc_html_e( 'Sample Textarea', 'modern-wp-plugin' ); ?></label>
                </th>
                <td>
                    <textarea id="sample_textarea" name="sample_textarea" rows="5" class="large-text"><?php echo esc_textarea( $sample_textarea ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save meta box data
     *
     * @param int $post_id Post ID.
     */
    public function save_meta_boxes( $post_id ) {
        // Don't save if ACF is handling it
        if ( function_exists( 'get_field' ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['modern_wp_plugin_meta_box_nonce'] ) || 
             ! wp_verify_nonce( $_POST['modern_wp_plugin_meta_box_nonce'], 'modern_wp_plugin_meta_box' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save fields
        if ( isset( $_POST['sample_text'] ) ) {
            update_post_meta( $post_id, 'sample_text', sanitize_text_field( $_POST['sample_text'] ) );
        }

        if ( isset( $_POST['sample_textarea'] ) ) {
            update_post_meta( $post_id, 'sample_textarea', sanitize_textarea_field( $_POST['sample_textarea'] ) );
        }
    }

    /**
     * Get field value with fallback
     *
     * @param string $field_name Field name.
     * @param int    $post_id    Post ID.
     * @return mixed Field value.
     */
    public static function get_field( $field_name, $post_id = null ) {
        if ( function_exists( 'get_field' ) ) {
            return get_field( $field_name, $post_id );
        }
        
        if ( null === $post_id ) {
            $post_id = get_the_ID();
        }
        
        return get_post_meta( $post_id, $field_name, true );
    }

    /**
     * Display field value with fallback
     *
     * @param string $field_name Field name.
     * @param int    $post_id    Post ID.
     */
    public static function the_field( $field_name, $post_id = null ) {
        echo self::get_field( $field_name, $post_id );
    }
}