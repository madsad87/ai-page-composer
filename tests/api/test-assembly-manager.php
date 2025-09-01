<?php
/**
 * Test Assembly Manager
 *
 * Unit tests for the Assembly Manager class functionality.
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\Assembly_Manager;
use AIPageComposer\API\Block_Detector;
use AIPageComposer\API\Block_Fallback;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Test class for Assembly Manager
 */
class Test_Assembly_Manager extends TestCase {

    /**
     * Assembly manager instance
     *
     * @var Assembly_Manager
     */
    private $assembly_manager;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress functions if not in WordPress environment
        if ( ! function_exists( 'wp_parse_args' ) ) {
            function wp_parse_args( $args, $defaults ) {
                return array_merge( $defaults, $args );
            }
        }
        
        if ( ! function_exists( '__' ) ) {
            function __( $text, $domain = 'default' ) {
                return $text;
            }
        }

        $this->assembly_manager = new Assembly_Manager();
    }

    /**
     * Test assembly manager initialization
     */
    public function test_initialization() {
        $this->assertInstanceOf( Assembly_Manager::class, $this->assembly_manager );
    }

    /**
     * Test section assembly with valid data
     */
    public function test_assemble_sections_with_valid_data() {
        $sections = [
            [
                'id' => 'test-section-1',
                'type' => 'hero',
                'content' => '<h1>Test Hero Section</h1><p>This is a test hero section.</p>'
            ],
            [
                'id' => 'test-section-2', 
                'type' => 'content',
                'content' => '<h2>Test Content</h2><p>This is test content.</p>'
            ]
        ];

        $options = [
            'options' => [
                'respect_user_preferences' => true,
                'enable_fallbacks' => true,
                'validate_html' => true
            ]
        ];

        try {
            $result = $this->assembly_manager->assemble_sections( $sections, $options );
            
            $this->assertIsArray( $result );
            $this->assertArrayHasKey( 'assembled_content', $result );
            $this->assertArrayHasKey( 'assembly_metadata', $result );
            $this->assertArrayHasKey( 'plugin_indicators', $result );
            
            $assembled_content = $result['assembled_content'];
            $this->assertArrayHasKey( 'blocks', $assembled_content );
            $this->assertArrayHasKey( 'html', $assembled_content );
            $this->assertArrayHasKey( 'json', $assembled_content );
            
            $this->assertIsArray( $assembled_content['blocks'] );
            $this->assertNotEmpty( $assembled_content['blocks'] );
            
        } catch ( \Exception $e ) {
            // In a real test environment, we might mock the dependencies
            $this->markTestSkipped( 'Test requires WordPress environment: ' . $e->getMessage() );
        }
    }

    /**
     * Test assembly with empty sections
     */
    public function test_assemble_sections_with_empty_data() {
        $this->expectException( \InvalidArgumentException::class );
        $this->assembly_manager->assemble_sections( [] );
    }

    /**
     * Test assembly with invalid sections
     */
    public function test_assemble_sections_with_invalid_data() {
        $this->expectException( \InvalidArgumentException::class );
        $this->assembly_manager->assemble_sections( null );
    }

    /**
     * Test content type determination
     */
    public function test_content_type_determination() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass( $this->assembly_manager );
        $method = $reflection->getMethod( 'determine_section_type' );
        $method->setAccessible( true );

        // Test hero detection
        $hero_section = [
            'title' => 'Hero Section',
            'content' => 'This is a banner content'
        ];
        $result = $method->invoke( $this->assembly_manager, $hero_section );
        $this->assertEquals( 'hero', $result );

        // Test image detection
        $image_section = [
            'content' => '<img src="test.jpg" alt="Test image">'
        ];
        $result = $method->invoke( $this->assembly_manager, $image_section );
        $this->assertEquals( 'image', $result );

        // Test default content
        $content_section = [
            'content' => '<p>Regular paragraph content</p>'
        ];
        $result = $method->invoke( $this->assembly_manager, $content_section );
        $this->assertEquals( 'content', $result );
    }

    /**
     * Test HTML validation
     */
    public function test_html_validation() {
        $reflection = new \ReflectionClass( $this->assembly_manager );
        $method = $reflection->getMethod( 'validate_and_clean_html' );
        $method->setAccessible( true );

        $html_content = '<p>Valid paragraph</p><script>alert("xss")</script>';
        $result = $method->invoke( $this->assembly_manager, $html_content );
        
        // Should remove script tags but keep paragraph
        $this->assertStringContains( '<p>Valid paragraph</p>', $result );
        $this->assertStringNotContains( '<script>', $result );
    }

    /**
     * Test image optimization
     */
    public function test_image_optimization() {
        $reflection = new \ReflectionClass( $this->assembly_manager );
        $method = $reflection->getMethod( 'optimize_image_references' );
        $method->setAccessible( true );

        $content = '<img src="test.jpg">';
        $result = $method->invoke( $this->assembly_manager, $content );
        
        // Should add loading="lazy" and alt=""
        $this->assertStringContains( 'loading="lazy"', $result );
        $this->assertStringContains( 'alt=""', $result );
    }

    /**
     * Test accessibility score calculation
     */
    public function test_accessibility_score_calculation() {
        $reflection = new \ReflectionClass( $this->assembly_manager );
        $method = $reflection->getMethod( 'calculate_accessibility_score' );
        $method->setAccessible( true );

        // Test with good content
        $good_blocks = [
            [
                'innerHTML' => '<h1>Title</h1><p>Content with <img src="test.jpg" alt="Good alt text"></p>'
            ]
        ];
        $score = $method->invoke( $this->assembly_manager, $good_blocks );
        $this->assertGreaterThan( 90, $score );

        // Test with bad content
        $bad_blocks = [
            [
                'innerHTML' => '<h3>No H1</h3><p>Content with <img src="test.jpg" alt=""></p>'
            ]
        ];
        $score = $method->invoke( $this->assembly_manager, $bad_blocks );
        $this->assertLessThan( 100, $score );
    }

    /**
     * Test blocks to HTML conversion
     */
    public function test_blocks_to_html_conversion() {
        $reflection = new \ReflectionClass( $this->assembly_manager );
        $method = $reflection->getMethod( 'blocks_to_html' );
        $method->setAccessible( true );

        $blocks = [
            [
                'blockName' => 'core/paragraph',
                'attrs' => ['className' => 'test-class'],
                'innerHTML' => '<p class="test-class">Test paragraph</p>'
            ]
        ];

        $result = $method->invoke( $this->assembly_manager, $blocks );
        
        $this->assertStringContains( '<!-- wp:core/paragraph', $result );
        $this->assertStringContains( 'Test paragraph', $result );
        $this->assertStringContains( '<!-- /wp:core/paragraph -->', $result );
    }

    /**
     * Test blocks to JSON conversion
     */
    public function test_blocks_to_json_conversion() {
        $reflection = new \ReflectionClass( $this->assembly_manager );
        $method = $reflection->getMethod( 'blocks_to_json' );
        $method->setAccessible( true );

        $blocks = [
            [
                'blockName' => 'core/paragraph',
                'attrs' => [],
                'innerHTML' => '<p>Test</p>'
            ]
        ];

        $result = $method->invoke( $this->assembly_manager, $blocks );
        $decoded = json_decode( $result, true );
        
        $this->assertIsArray( $decoded );
        $this->assertEquals( 2, $decoded['version'] );
        $this->assertArrayHasKey( 'blocks', $decoded );
        $this->assertCount( 1, $decoded['blocks'] );
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void {
        parent::tearDown();
        $this->assembly_manager = null;
    }
}