<?php
/**
 * Test Citation Manager Class
 *
 * @package AIPageComposer\Tests
 */

namespace AIPageComposer\Tests;

use PHPUnit\Framework\TestCase;
use AIPageComposer\API\Citation_Manager;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test Citation Manager
 */
class Test_Citation_Manager extends TestCase {

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('wp_trim_words')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('__')->returnArg();
    }

    /**
     * Tear down test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor
     */
    public function test_constructor() {
        $manager = new Citation_Manager();
        $this->assertInstanceOf(Citation_Manager::class, $manager);
    }

    /**
     * Test extract and format citations
     */
    public function test_extract_and_format_citations() {
        $manager = new Citation_Manager();
        
        $content = "According to research findings, AI technology is advancing rapidly [1]. Studies indicate that automation will transform industries (Source, 2024).";
        $context_chunks = [
            [
                'id' => 'chunk-1',
                'text' => 'Research findings show that AI technology is advancing at an unprecedented rate.',
                'source' => 'https://example.com/ai-research'
            ],
            [
                'id' => 'chunk-2', 
                'text' => 'Studies indicate significant transformation in various industries.',
                'source' => 'https://example.com/automation-study'
            ]
        ];

        $citations = $manager->extract_and_format_citations($content, $context_chunks);
        
        $this->assertIsArray($citations);
        $this->assertNotEmpty($citations);
        
        foreach ($citations as $citation) {
            $this->assertArrayHasKey('id', $citation);
            $this->assertArrayHasKey('text', $citation);
            $this->assertArrayHasKey('type', $citation);
            $this->assertArrayHasKey('position', $citation);
            $this->assertStringStartsWith('cite-', $citation['id']);
        }
    }

    /**
     * Test clean citation text
     */
    public function test_clean_citation_text() {
        $manager = new Citation_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('clean_citation_text');
        $method->setAccessible(true);
        
        $this->assertEquals('research shows AI advancement', $method->invoke($manager, '[research shows AI advancement]'));
        $this->assertEquals('technology is growing', $method->invoke($manager, '(technology is growing)'));
        $this->assertEquals('AI advancement', $method->invoke($manager, 'according to AI advancement'));
        $this->assertEquals('significant changes', $method->invoke($manager, 'research shows that significant changes'));
    }

    /**
     * Test calculate text similarity
     */
    public function test_calculate_text_similarity() {
        $manager = new Citation_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('calculate_text_similarity');
        $method->setAccessible(true);
        
        // Perfect match
        $similarity = $method->invoke($manager, 'hello world', 'hello world');
        $this->assertEquals(1.0, $similarity);
        
        // Partial match
        $similarity = $method->invoke($manager, 'hello world test', 'hello world example');
        $this->assertGreaterThan(0, $similarity);
        $this->assertLessThan(1.0, $similarity);
        
        // No match
        $similarity = $method->invoke($manager, 'hello world', 'foo bar');
        $this->assertEquals(0, $similarity);
    }

    /**
     * Test extract domain from URL
     */
    public function test_extract_domain_from_url() {
        $manager = new Citation_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('extract_domain_from_url');
        $method->setAccessible(true);
        
        $this->assertEquals('example.com', $method->invoke($manager, 'https://www.example.com/page'));
        $this->assertEquals('test.org', $method->invoke($manager, 'http://test.org'));
        $this->assertEquals('site.net', $method->invoke($manager, 'https://site.net/path/to/page'));
    }

    /**
     * Test format citation
     */
    public function test_format_citation() {
        $manager = new Citation_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('format_citation');
        $method->setAccessible(true);
        
        // Inline citation
        $citation = [
            'text' => 'AI is advancing',
            'source' => 'example.com',
            'type' => 'inline'
        ];
        $result = $method->invoke($manager, $citation);
        $this->assertEquals('AI is advancing [example.com]', $result);
        
        // Parenthetical citation
        $citation['type'] = 'parenthetical';
        $result = $method->invoke($manager, $citation);
        $this->assertEquals('AI is advancing (example.com)', $result);
        
        // Narrative citation
        $citation['type'] = 'narrative';
        $result = $method->invoke($manager, $citation);
        $this->assertEquals('According to example.com, AI is advancing', $result);
    }

    /**
     * Test generate aria label
     */
    public function test_generate_aria_label() {
        $manager = new Citation_Manager();
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('generate_aria_label');
        $method->setAccessible(true);
        
        $citation = [
            'source' => 'example.com',
            'text' => 'This is a long citation text that should be trimmed'
        ];
        
        $result = $method->invoke($manager, $citation);
        $this->assertIsString($result);
        $this->assertStringContainsString('example.com', $result);
    }

    /**
     * Test deduplicate citations
     */
    public function test_deduplicate_citations() {
        $manager = new Citation_Manager();
        
        $citations = [
            ['text' => 'AI is advancing', 'id' => 'cite-1'],
            ['text' => 'AI IS ADVANCING', 'id' => 'cite-2'], // Duplicate (case insensitive)
            ['text' => 'Technology is growing', 'id' => 'cite-3'],
            ['text' => 'ai is advancing', 'id' => 'cite-4'] // Another duplicate
        ];

        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('deduplicate_citations');
        $method->setAccessible(true);
        
        $result = $method->invoke($manager, $citations);
        
        $this->assertCount(2, $result); // Should remove duplicates
        $this->assertEquals('AI is advancing', $result[0]['text']);
        $this->assertEquals('Technology is growing', $result[1]['text']);
    }

    /**
     * Test generate citation HTML
     */
    public function test_generate_citation_html() {
        $manager = new Citation_Manager();
        
        $citations = [
            [
                'id' => 'cite-1',
                'text' => 'AI research',
                'source' => 'example.com',
                'url' => 'https://example.com',
                'aria_label' => 'Citation from example.com'
            ]
        ];

        // Test inline HTML generation
        $html = $manager->generate_citation_html($citations, 'inline');
        $this->assertIsString($html);
        $this->assertStringContainsString('ai-citation', $html);
        $this->assertStringContainsString('cite-1', $html);

        // Test footnote HTML generation
        $html = $manager->generate_citation_html($citations, 'footnote');
        $this->assertIsString($html);
        $this->assertStringContainsString('ai-citations-footnotes', $html);
        $this->assertStringContainsString('References', $html);

        // Test bibliography HTML generation
        $html = $manager->generate_citation_html($citations, 'bibliography');
        $this->assertIsString($html);
        $this->assertStringContainsString('ai-citations-bibliography', $html);
        $this->assertStringContainsString('Bibliography', $html);
    }
}