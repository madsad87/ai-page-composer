<?php
/**
 * Sample Block Template - ACF Block Rendering Template
 * 
 * This template file demonstrates how to create ACF-powered Gutenberg blocks with proper data handling,
 * security practices, and responsive design. It serves as a blueprint for creating custom blocks that
 * integrate seamlessly with the Gutenberg editor while maintaining security and accessibility standards.
 *
 * Sample Block Template
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty).
 * @param bool   $is_preview True during AJAX preview.
 * @param int    $post_id    The post ID this block is saved to.
 *
 * @package ModernWPPlugin
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'sample-block-' . $block['id'];
if ( ! empty( $block['anchor'] ) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$class_name = 'sample-block';
if ( ! empty( $block['className'] ) ) {
    $class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $class_name .= ' align' . $block['align'];
}

// Load values and handle defaults.
$title = get_field( 'title' ) ?: __( 'Sample Block Title', 'modern-wp-plugin' );
$description = get_field( 'description' ) ?: __( 'This is a sample block description.', 'modern-wp-plugin' );
$image = get_field( 'image' );
$link = get_field( 'link' );

?>
<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
    
    <?php if ( $image ) : ?>
        <div class="sample-block__image">
            <?php if ( $link ) : ?>
                <a href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>">
            <?php endif; ?>
            
            <img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" />
            
            <?php if ( $link ) : ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="sample-block__content">
        <h3 class="sample-block__title">
            <?php if ( $link ) : ?>
                <a href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>">
                    <?php echo esc_html( $title ); ?>
                </a>
            <?php else : ?>
                <?php echo esc_html( $title ); ?>
            <?php endif; ?>
        </h3>
        
        <?php if ( $description ) : ?>
            <div class="sample-block__description">
                <?php echo wp_kses_post( $description ); ?>
            </div>
        <?php endif; ?>
        
        <?php if ( $link && $link['title'] ) : ?>
            <div class="sample-block__link">
                <a href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>" class="sample-block__button">
                    <?php echo esc_html( $link['title'] ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    
</div>