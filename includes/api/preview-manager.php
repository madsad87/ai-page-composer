<?php
/**
 * Preview Manager Class - Generate preview HTML with visual indicators and responsive breakpoints
 * 
 * This class handles the generation of preview content with visual indicators showing
 * which plugin blocks are used, responsive breakpoint testing, and accessibility reporting.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Preview Manager class for content preview generation
 */
class Preview_Manager {

    /**
     * Default responsive breakpoints
     *
     * @var array
     */
    private $default_breakpoints = [
        'mobile' => ['width' => 320, 'name' => 'Mobile Portrait'],
        'tablet' => ['width' => 768, 'name' => 'Tablet'],
        'desktop' => ['width' => 1200, 'name' => 'Desktop']
    ];

    /**
     * Plugin color mappings for indicators
     *
     * @var array
     */
    private $plugin_colors = [
        'core' => '#0073aa',
        'kadence_blocks' => '#e74c3c',
        'genesis_blocks' => '#27ae60',
        'stackable' => '#9b59b6',
        'ultimate_addons' => '#f39c12',
        'generateblocks' => '#34495e'
    ];

    /**
     * Generate preview HTML with indicators
     *
     * @param array $assembled_content Assembled content from assembly manager.
     * @param array $preview_options Preview configuration options.
     * @return array Preview result with HTML and metadata.
     */
    public function generate_preview( $assembled_content, $preview_options = [] ) {
        try {
            // Parse preview options
            $options = wp_parse_args( $preview_options, [
                'show_plugin_indicators' => true,
                'include_responsive_preview' => true,
                'highlight_fallbacks' => true,
                'show_accessibility_info' => true
            ]);

            // Get assembled blocks and metadata
            $blocks = $assembled_content['blocks'] ?? [];
            $html_content = $assembled_content['html'] ?? '';
            $plugin_indicators = $assembled_content['plugin_indicators'] ?? [];

            if ( empty( $blocks ) && empty( $html_content ) ) {
                throw new \InvalidArgumentException( __( 'No content provided for preview', 'ai-page-composer' ) );
            }

            // Generate base preview HTML
            $preview_html = $this->generate_base_preview_html( $html_content, $blocks, $options );

            // Add plugin indicators
            $indicators_data = [];
            if ( $options['show_plugin_indicators'] ) {
                $indicators_data = $this->generate_plugin_indicators( $blocks, $plugin_indicators, $options );
                $preview_html = $this->inject_plugin_indicators( $preview_html, $indicators_data );
            }

            // Generate iframe-safe HTML
            $iframe_src = 'data:text/html;base64,' . base64_encode( $preview_html );

            // Generate responsive breakpoints
            $responsive_breakpoints = $options['include_responsive_preview'] ? 
                array_values( $this->default_breakpoints ) : [];

            // Generate accessibility report
            $accessibility_report = $options['show_accessibility_info'] ? 
                $this->generate_accessibility_report( $blocks, $html_content ) : [];

            return [
                'preview_html' => $preview_html,
                'iframe_src' => $iframe_src,
                'plugin_indicators' => $indicators_data,
                'responsive_breakpoints' => $responsive_breakpoints,
                'accessibility_report' => $accessibility_report,
                'preview_metadata' => [
                    'block_count' => count( $blocks ),
                    'content_length' => strlen( $html_content ),
                    'generation_time' => microtime( true )
                ]
            ];

        } catch ( \Exception $e ) {
            error_log( '[AI Composer] Preview generation failed: ' . $e->getMessage() );
            throw new \Exception( 
                sprintf( __( 'Preview generation failed: %s', 'ai-page-composer' ), $e->getMessage() )
            );
        }
    }

    /**
     * Generate base preview HTML structure
     *
     * @param string $html_content Block HTML content.
     * @param array  $blocks Block data array.
     * @param array  $options Preview options.
     * @return string Complete preview HTML.
     */
    private function generate_base_preview_html( $html_content, $blocks, $options ) {
        $theme_stylesheet = get_stylesheet_uri();
        $preview_css = $this->generate_preview_css( $options );

        $html = '<!DOCTYPE html>';
        $html .= '<html ' . get_language_attributes() . '>';
        $html .= '<head>';
        $html .= '<meta charset="' . get_bloginfo( 'charset' ) . '">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<title>' . __( 'AI Composer Preview', 'ai-page-composer' ) . '</title>';
        $html .= '<link rel="stylesheet" href="' . esc_url( $theme_stylesheet ) . '">';
        $html .= '<style>' . $preview_css . '</style>';
        $html .= '</head>';
        $html .= '<body class="ai-composer-preview">';

        if ( $options['show_plugin_indicators'] ) {
            $html .= $this->generate_preview_header();
        }

        $html .= '<div class="ai-composer-content-wrapper">';
        $html .= $this->process_html_content( $html_content, $options );
        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }

    /**
     * Generate preview-specific CSS
     *
     * @param array $options Preview options.
     * @return string CSS content.
     */
    private function generate_preview_css( $options ) {
        $css = '
        .ai-composer-preview { margin: 0; padding: 0; }
        .ai-composer-content-wrapper { background: white; margin: 0 auto; max-width: 1200px; }';

        if ( $options['show_plugin_indicators'] ) {
            $css .= '
            .ai-composer-block-indicator { position: relative; }
            .ai-composer-block-indicator::before {
                content: attr(data-plugin);
                position: absolute; top: -2px; right: -2px;
                background: var(--plugin-color, #666); color: white;
                padding: 2px 6px; font-size: 10px; border-radius: 3px;
                z-index: 1000; font-weight: bold; text-transform: uppercase;
            }';

            foreach ( $this->plugin_colors as $plugin => $color ) {
                $css .= ".ai-composer-block-indicator[data-plugin-key=\"{$plugin}\"] { --plugin-color: {$color}; }";
            }
        }

        if ( $options['highlight_fallbacks'] ) {
            $css .= '
            .ai-composer-fallback-block { border: 2px dashed #e74c3c !important; }
            .ai-composer-fallback-block::after {
                content: "FALLBACK"; position: absolute; top: 5px; left: 5px;
                background: #e74c3c; color: white; padding: 2px 6px;
                font-size: 10px; border-radius: 3px; z-index: 1000;
            }';
        }

        return $css;
    }

    /**
     * Process HTML content for preview
     *
     * @param string $html_content Raw HTML content.
     * @param array  $options Preview options.
     * @return string Processed HTML content.
     */
    private function process_html_content( $html_content, $options ) {
        // Remove WordPress comment syntax
        $content = preg_replace( '/<!-- wp:.*? -->/s', '', $html_content );
        $content = preg_replace( '/<!-- \/wp:.*? -->/s', '', $content );
        $content = trim( $content );

        return $content;
    }

    /**
     * Generate plugin indicators data
     *
     * @param array $blocks Block data.
     * @param array $plugin_indicators Plugin indicator information.
     * @param array $options Preview options.
     * @return array Indicator data.
     */
    private function generate_plugin_indicators( $blocks, $plugin_indicators, $options ) {
        $indicators = [];

        foreach ( $plugin_indicators as $indicator ) {
            $plugin_key = $indicator['plugin_used'] ?? 'unknown';
            $block_name = $indicator['block_name'] ?? '';
            $section_id = $indicator['section_id'] ?? '';
            $fallback_used = $indicator['fallback_used'] ?? false;

            $indicators[] = [
                'selector' => "#{$section_id}",
                'plugin' => $this->get_plugin_display_name( $plugin_key ),
                'plugin_key' => $plugin_key,
                'block_title' => $this->get_block_display_name( $block_name ),
                'block_name' => $block_name,
                'is_fallback' => $fallback_used
            ];
        }

        return $indicators;
    }

    /**
     * Inject plugin indicators into HTML
     *
     * @param string $html Preview HTML.
     * @param array  $indicators Indicator data.
     * @return string HTML with indicators.
     */
    private function inject_plugin_indicators( $html, $indicators ) {
        foreach ( $indicators as $indicator ) {
            $selector = $indicator['selector'];
            $plugin_key = $indicator['plugin_key'];
            $plugin_name = $indicator['plugin'];
            $block_name = $indicator['block_name'];
            $is_fallback = $indicator['is_fallback'];

            $pattern = '/(<[^>]*id=["\']' . preg_quote( ltrim( $selector, '#' ), '/' ) . '["\'][^>]*>)/';
            
            $html = preg_replace_callback( $pattern, function( $matches ) use ( $plugin_key, $plugin_name, $block_name, $is_fallback ) {
                $element = $matches[1];
                
                $classes = 'ai-composer-block-indicator';
                if ( $is_fallback ) {
                    $classes .= ' ai-composer-fallback-block';
                }
                
                $element = str_replace( 'class="', 'class="' . $classes . ' ', $element );
                $element = str_replace( '>', ' data-plugin="' . esc_attr( $plugin_name ) . '" data-plugin-key="' . esc_attr( $plugin_key ) . '" data-block-name="' . esc_attr( $block_name ) . '">', $element );
                
                return $element;
            }, $html );
        }

        return $html;
    }

    /**
     * Generate preview header with legend
     *
     * @return string Header HTML.
     */
    private function generate_preview_header() {
        $html = '<div style="background: #23282d; color: white; padding: 10px; text-align: center;">';
        $html .= __( 'AI Composer Preview - Block Indicators', 'ai-page-composer' );
        $html .= '</div>';
        return $html;
    }

    /**
     * Generate accessibility report
     *
     * @param array  $blocks Block data.
     * @param string $html_content HTML content.
     * @return array Accessibility report.
     */
    private function generate_accessibility_report( $blocks, $html_content ) {
        $score = 100;
        $issues = [];

        // Check for images without alt text
        if ( preg_match_all( '/<img[^>]*>/i', $html_content, $matches ) ) {
            foreach ( $matches[0] as $img ) {
                if ( strpos( $img, 'alt=' ) === false || strpos( $img, 'alt=""' ) !== false ) {
                    $issues[] = __( 'Image missing descriptive alt text', 'ai-page-composer' );
                    $score -= 10;
                }
            }
        }

        // Check heading structure
        if ( preg_match_all( '/<h([1-6])[^>]*>/i', $html_content, $matches ) ) {
            $heading_levels = array_map( 'intval', $matches[1] );
            if ( ! empty( $heading_levels ) && min( $heading_levels ) > 1 ) {
                $issues[] = __( 'Heading structure should start with H1 or H2', 'ai-page-composer' );
                $score -= 5;
            }
        }

        return [
            'score' => max( 0, $score ),
            'issues' => $issues,
            'recommendations' => []
        ];
    }

    /**
     * Get plugin display name
     *
     * @param string $plugin_key Plugin key.
     * @return string Display name.
     */
    private function get_plugin_display_name( $plugin_key ) {
        $names = [
            'core' => __( 'Core', 'ai-page-composer' ),
            'kadence_blocks' => __( 'Kadence', 'ai-page-composer' ),
            'genesis_blocks' => __( 'Genesis', 'ai-page-composer' ),
            'stackable' => __( 'Stackable', 'ai-page-composer' ),
            'ultimate_addons' => __( 'UAGB', 'ai-page-composer' ),
            'generateblocks' => __( 'Generate', 'ai-page-composer' )
        ];

        return $names[ $plugin_key ] ?? ucwords( str_replace( '_', ' ', $plugin_key ) );
    }

    /**
     * Get block display name
     *
     * @param string $block_name Block name.
     * @return string Display name.
     */
    private function get_block_display_name( $block_name ) {
        $parts = explode( '/', $block_name );
        $name = end( $parts );
        
        return ucwords( str_replace( ['-', '_'], ' ', $name ) );
    }
}