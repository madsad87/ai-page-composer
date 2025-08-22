<?php
/**
 * Testimonial Block Template - Customer Testimonial Display Component
 * 
 * This template creates a professional testimonial block for displaying customer reviews and feedback.
 * It includes support for author information, company details, star ratings, and avatar images while
 * maintaining responsive design and accessibility standards for optimal user experience.
 *
 * Testimonial Block Template
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty).
 * @param bool   $is_preview True during AJAX preview.
 * @param int    $post_id    The post ID this block is saved to.
 *
 * @package ModernWPPlugin
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'testimonial-' . $block['id'];
if ( ! empty( $block['anchor'] ) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$class_name = 'testimonial-block';
if ( ! empty( $block['className'] ) ) {
    $class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $class_name .= ' align' . $block['align'];
}

// Load values and handle defaults.
$quote = get_field( 'quote' ) ?: __( 'This is a sample testimonial quote.', 'modern-wp-plugin' );
$author = get_field( 'author' ) ?: __( 'John Doe', 'modern-wp-plugin' );
$position = get_field( 'position' );
$company = get_field( 'company' );
$avatar = get_field( 'avatar' );
$rating = get_field( 'rating' );

?>
<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
    
    <blockquote class="testimonial-block__quote">
        <?php echo wp_kses_post( $quote ); ?>
    </blockquote>
    
    <div class="testimonial-block__author">
        
        <?php if ( $avatar ) : ?>
            <div class="testimonial-block__avatar">
                <img src="<?php echo esc_url( $avatar['url'] ); ?>" alt="<?php echo esc_attr( $avatar['alt'] ?: $author ); ?>" />
            </div>
        <?php endif; ?>
        
        <div class="testimonial-block__details">
            <cite class="testimonial-block__name"><?php echo esc_html( $author ); ?></cite>
            
            <?php if ( $position || $company ) : ?>
                <div class="testimonial-block__meta">
                    <?php if ( $position ) : ?>
                        <span class="testimonial-block__position"><?php echo esc_html( $position ); ?></span>
                    <?php endif; ?>
                    
                    <?php if ( $position && $company ) : ?>
                        <span class="testimonial-block__separator">, </span>
                    <?php endif; ?>
                    
                    <?php if ( $company ) : ?>
                        <span class="testimonial-block__company"><?php echo esc_html( $company ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ( $rating && $rating > 0 ) : ?>
                <div class="testimonial-block__rating">
                    <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                        <span class="testimonial-block__star <?php echo $i <= $rating ? 'filled' : 'empty'; ?>">★</span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
</div>