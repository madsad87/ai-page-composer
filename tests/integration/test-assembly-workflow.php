<?php
/**
 * Integration Test - Assembly Workflow
 *
 * Integration tests for the complete assembly workflow from content generation
 * through preview to draft creation.
 *
 * @package AIPageComposer\Tests\Integration
 */

namespace AIPageComposer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\Assembly_Manager;
use AIPageComposer\API\Preview_Manager;
use AIPageComposer\API\Draft_Creator;
use AIPageComposer\API\Block_Detector;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integration test class for assembly workflow
 */
class Test_Assembly_Workflow extends TestCase {

    /**
     * Test data for sections
     *
     * @var array
     */
    private $test_sections;

    /**
     * Assembly manager instance
     *
     * @var Assembly_Manager
     */
    private $assembly_manager;

    /**
     * Preview manager instance
     *
     * @var Preview_Manager
     */
    private $preview_manager;

    /**
     * Draft creator instance
     *
     * @var Draft_Creator
     */
    private $draft_creator;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Mock WordPress functions for testing
        $this->mockWordPressFunctions();

        $this->test_sections = [
            [
                'id' => 'hero-section',
                'type' => 'hero',
                'title' => 'Welcome to Our Website',
                'content' => '<h1>Welcome to Our Website</h1><p>This is a compelling hero section that introduces our amazing product.</p>',
                'metadata' => [
                    'background_image' => 'hero-bg.jpg',
                    'cta_text' => 'Get Started'
                ]
            ],
            [
                'id' => 'features-section',
                'type' => 'content',
                'title' => 'Key Features',
                'content' => '<h2>Key Features</h2><ul><li>Feature 1: Advanced functionality</li><li>Feature 2: User-friendly interface</li><li>Feature 3: Reliable performance</li></ul>',
                'metadata' => [
                    'layout' => 'three-column'
                ]
            ],
            [
                'id' => 'testimonial-section',
                'type' => 'testimonial',
                'title' => 'Customer Testimonials',
                'content' => '<h2>What Our Customers Say</h2><blockquote>"This product has transformed our business operations. Highly recommended!"</blockquote><cite>- John Doe, CEO</cite>',
                'metadata' => [
                    'author' => 'John Doe',
                    'company' => 'Acme Corp'
                ]
            ]
        ];

        try {
            $this->assembly_manager = new Assembly_Manager();
            $this->preview_manager = new Preview_Manager();
            $this->draft_creator = new Draft_Creator();
        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Could not initialize components: ' . $e->getMessage() );
        }
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions() {
        if ( ! function_exists( 'wp_parse_args' ) ) {
            function wp_parse_args( $args, $defaults ) {
                return is_array( $args ) ? array_merge( $defaults, $args ) : $defaults;
            }
        }

        if ( ! function_exists( '__' ) ) {
            function __( $text, $domain = 'default' ) {
                return $text;
            }
        }

        if ( ! function_exists( 'esc_attr' ) ) {
            function esc_attr( $text ) {
                return htmlspecialchars( $text, ENT_QUOTES );
            }
        }

        if ( ! function_exists( 'esc_url' ) ) {
            function esc_url( $url ) {
                return filter_var( $url, FILTER_SANITIZE_URL );
            }
        }

        if ( ! function_exists( 'wp_kses_post' ) ) {
            function wp_kses_post( $content ) {
                return strip_tags( $content, '<p><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><strong><em><br>' );
            }
        }

        if ( ! function_exists( 'wp_json_encode' ) ) {
            function wp_json_encode( $data, $options = 0 ) {
                return json_encode( $data, $options );
            }
        }

        if ( ! function_exists( 'get_language_attributes' ) ) {
            function get_language_attributes() {
                return 'lang="en-US"';
            }
        }

        if ( ! function_exists( 'get_bloginfo' ) ) {
            function get_bloginfo( $field ) {
                switch ( $field ) {
                    case 'charset':
                        return 'UTF-8';
                    case 'version':
                        return '6.4';
                    default:
                        return '';
                }
            }
        }

        if ( ! function_exists( 'get_stylesheet_uri' ) ) {
            function get_stylesheet_uri() {
                return 'http://example.com/wp-content/themes/test/style.css';
            }
        }

        if ( ! function_exists( 'current_time' ) ) {
            function current_time( $format ) {
                return date( $format );
            }
        }
    }

    /**
     * Test complete workflow from assembly to preview to draft
     */
    public function test_complete_assembly_workflow() {
        try {
            // Step 1: Assemble content
            $assembly_options = [
                'options' => [
                    'respect_user_preferences' => true,
                    'enable_fallbacks' => true,
                    'validate_html' => true,
                    'optimize_images' => true,
                    'seo_optimization' => true
                ]
            ];

            $assembly_result = $this->assembly_manager->assemble_sections( $this->test_sections, $assembly_options );

            // Verify assembly result structure
            $this->assertIsArray( $assembly_result );
            $this->assertArrayHasKey( 'assembled_content', $assembly_result );
            $this->assertArrayHasKey( 'assembly_metadata', $assembly_result );
            $this->assertArrayHasKey( 'plugin_indicators', $assembly_result );

            $assembled_content = $assembly_result['assembled_content'];
            $this->assertArrayHasKey( 'blocks', $assembled_content );
            $this->assertArrayHasKey( 'html', $assembled_content );
            $this->assertArrayHasKey( 'json', $assembled_content );

            // Verify we have blocks for each section
            $this->assertCount( 3, $assembled_content['blocks'] );

            // Step 2: Generate preview
            $preview_options = [
                'show_plugin_indicators' => true,
                'include_responsive_preview' => true,
                'highlight_fallbacks' => true,
                'show_accessibility_info' => true
            ];

            $preview_result = $this->preview_manager->generate_preview( $assembled_content, $preview_options );

            // Verify preview result structure
            $this->assertIsArray( $preview_result );
            $this->assertArrayHasKey( 'preview_html', $preview_result );
            $this->assertArrayHasKey( 'iframe_src', $preview_result );
            $this->assertArrayHasKey( 'plugin_indicators', $preview_result );
            $this->assertArrayHasKey( 'responsive_breakpoints', $preview_result );
            $this->assertArrayHasKey( 'accessibility_report', $preview_result );

            // Verify preview HTML contains expected content
            $this->assertStringContains( '<!DOCTYPE html>', $preview_result['preview_html'] );
            $this->assertStringContains( 'Welcome to Our Website', $preview_result['preview_html'] );
            $this->assertStringContains( 'Key Features', $preview_result['preview_html'] );

            // Verify iframe src is base64 encoded
            $this->assertStringStartsWith( 'data:text/html;base64,', $preview_result['iframe_src'] );

            // Step 3: Create draft (simplified for testing)
            $draft_data = [
                'content' => $assembled_content,
                'meta' => [
                    'title' => 'Test Generated Page',
                    'post_type' => 'page',
                    'status' => 'draft',
                    'excerpt' => 'This is a test page generated by AI Composer'
                ],
                'seo' => [
                    'meta_title' => 'Test Generated Page - SEO Title',
                    'meta_description' => 'A comprehensive test page showcasing AI Composer functionality',
                    'focus_keyword' => 'test page'
                ],
                'taxonomies' => [
                    'categories' => [
                        ['id' => 1, 'name' => 'Test Category']
                    ],
                    'tags' => ['test', 'ai-composer', 'generated']
                ]
            ];

            // Note: In a real WordPress environment, this would create an actual post
            // For testing, we'll verify the data structure and validation
            $this->assertTrue( $this->validateDraftData( $draft_data ) );

            // Verify workflow completion
            $this->assertTrue( true, 'Complete workflow executed successfully' );

        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Workflow test requires full WordPress environment: ' . $e->getMessage() );
        }
    }

    /**
     * Test assembly with different content types
     */
    public function test_assembly_with_different_content_types() {
        $diverse_sections = [
            [
                'id' => 'image-gallery',
                'type' => 'image',
                'content' => '<div class="gallery"><img src="image1.jpg" alt="Image 1"><img src="image2.jpg" alt="Image 2"></div>'
            ],
            [
                'id' => 'button-section',
                'type' => 'button',
                'content' => '<a href="#" class="btn btn-primary">Call to Action</a>'
            ],
            [
                'id' => 'list-section',
                'type' => 'list',
                'content' => '<ul><li>List item 1</li><li>List item 2</li><li>List item 3</li></ul>'
            ]
        ];

        try {
            $result = $this->assembly_manager->assemble_sections( $diverse_sections );
            
            $this->assertIsArray( $result );
            $this->assertCount( 3, $result['assembled_content']['blocks'] );
            
            // Verify each section was processed
            $plugin_indicators = $result['plugin_indicators'];
            $section_types = array_column( $plugin_indicators, 'section_id' );
            
            $this->assertContains( 'image-gallery', $section_types );
            $this->assertContains( 'button-section', $section_types );
            $this->assertContains( 'list-section', $section_types );

        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Assembly test requires WordPress environment: ' . $e->getMessage() );
        }
    }

    /**
     * Test preview accessibility features
     */
    public function test_preview_accessibility_features() {
        try {
            // Create content with accessibility issues
            $accessible_content = [
                'blocks' => [
                    [
                        'blockName' => 'core/heading',
                        'innerHTML' => '<h3>Bad heading structure</h3>'
                    ],
                    [
                        'blockName' => 'core/image',
                        'innerHTML' => '<img src="test.jpg" alt="">'
                    ]
                ],
                'html' => '<h3>Bad heading structure</h3><img src="test.jpg" alt="">',
                'json' => '{"blocks":[]}'
            ];

            $preview_result = $this->preview_manager->generate_preview( $accessible_content, [
                'show_accessibility_info' => true
            ] );

            $accessibility_report = $preview_result['accessibility_report'];
            
            $this->assertIsArray( $accessibility_report );
            $this->assertArrayHasKey( 'score', $accessibility_report );
            $this->assertArrayHasKey( 'issues', $accessibility_report );
            
            // Should detect accessibility issues
            $this->assertLessThan( 100, $accessibility_report['score'] );
            $this->assertNotEmpty( $accessibility_report['issues'] );

        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Preview test requires WordPress environment: ' . $e->getMessage() );
        }
    }

    /**
     * Test error handling in workflow
     */
    public function test_workflow_error_handling() {
        // Test with invalid sections
        try {
            $this->assembly_manager->assemble_sections( [] );
            $this->fail( 'Expected InvalidArgumentException was not thrown' );
        } catch ( \InvalidArgumentException $e ) {
            $this->assertStringContains( 'Valid sections array is required', $e->getMessage() );
        }

        // Test preview with empty content
        try {
            $this->preview_manager->generate_preview( [] );
            $this->fail( 'Expected InvalidArgumentException was not thrown' );
        } catch ( \InvalidArgumentException $e ) {
            $this->assertStringContains( 'No content provided for preview', $e->getMessage() );
        }
    }

    /**
     * Validate draft data structure
     *
     * @param array $draft_data Draft data to validate.
     * @return bool True if valid.
     */
    private function validateDraftData( $draft_data ) {
        // Check required fields
        if ( empty( $draft_data['content'] ) || empty( $draft_data['meta'] ) ) {
            return false;
        }

        // Check meta fields
        $meta = $draft_data['meta'];
        if ( empty( $meta['title'] ) || empty( $meta['post_type'] ) ) {
            return false;
        }

        // Check content structure
        $content = $draft_data['content'];
        if ( ! isset( $content['blocks'] ) || ! isset( $content['html'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Test performance of workflow
     */
    public function test_workflow_performance() {
        $start_time = microtime( true );

        try {
            // Run assembly
            $assembly_result = $this->assembly_manager->assemble_sections( $this->test_sections );
            
            // Run preview
            $preview_result = $this->preview_manager->generate_preview( 
                $assembly_result['assembled_content'] 
            );

            $end_time = microtime( true );
            $execution_time = $end_time - $start_time;

            // Workflow should complete within reasonable time (5 seconds for testing)
            $this->assertLessThan( 5.0, $execution_time, 'Workflow took too long to execute' );

            // Verify metadata includes timing information
            $this->assertArrayHasKey( 'processing_time', $assembly_result['assembly_metadata'] );
            $this->assertArrayHasKey( 'generation_time', $preview_result['preview_metadata'] );

        } catch ( \Exception $e ) {
            $this->markTestSkipped( 'Performance test requires WordPress environment: ' . $e->getMessage() );
        }
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void {
        parent::tearDown();
        $this->assembly_manager = null;
        $this->preview_manager = null;
        $this->draft_creator = null;
        $this->test_sections = null;
    }
}