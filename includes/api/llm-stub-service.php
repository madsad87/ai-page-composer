<?php
/**
 * LLM Stub Service Class - Development Mode Outline Generation
 * 
 * This file contains the LLM_Stub_Service class that provides realistic outline
 * generation for local development without API costs. It simulates AI responses
 * using blueprint structures and contextual content generation.
 *
 * @package AIPageComposer
 */

namespace AIPageComposer\API;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LLM Stub Service class for development mode outline generation
 */
class LLM_Stub_Service {

    /**
     * Heading templates by section type
     *
     * @var array
     */
    private $heading_templates = array(
        'hero' => array(
            'Transform Your %s Today',
            'The Ultimate Guide to %s',
            'Master %s: Expert Solutions',
            'Discover the Power of %s',
            'Revolutionize Your %s Experience',
            'The Complete %s Handbook',
        ),
        'content' => array(
            'Understanding %s: Key Insights',
            'Essential %s Strategies',
            'How %s Can Benefit You',
            'The Science Behind %s',
            'Advanced %s Techniques',
            'Getting Started with %s',
        ),
        'testimonial' => array(
            'What Our Clients Say About %s',
            'Success Stories: %s in Action',
            'Real Results from %s Users',
            'Customer Experiences with %s',
            'Testimonials: %s Success',
        ),
        'pricing' => array(
            'Choose Your %s Plan',
            'Affordable %s Solutions',
            '%s Pricing That Works',
            'Investment Options for %s',
            'Find Your Perfect %s Package',
        ),
        'team' => array(
            'Meet the %s Experts',
            'Our %s Team',
            'The People Behind %s',
            'Expert %s Professionals',
            'Your %s Support Team',
        ),
        'faq' => array(
            'Frequently Asked Questions About %s',
            'Common %s Questions Answered',
            '%s FAQ: Everything You Need to Know',
            'Your %s Questions, Answered',
        ),
        'cta' => array(
            'Ready to Start Your %s Journey?',
            'Take Action with %s Today',
            'Get Started with %s Now',
            'Transform Your Business with %s',
            'Begin Your %s Success Story',
        ),
    );

    /**
     * Subheading templates by section type
     *
     * @var array
     */
    private $subheading_templates = array(
        'hero' => array(
            'Why Choose %s?',
            'Key Benefits',
            'Get Started Today',
        ),
        'content' => array(
            'Core Concepts',
            'Best Practices',
            'Implementation Guide',
            'Common Challenges',
            'Expert Tips',
        ),
        'testimonial' => array(
            'Customer Feedback',
            'Success Metrics',
            'Case Studies',
        ),
        'pricing' => array(
            'Plan Comparison',
            'Value Proposition',
            'Money-Back Guarantee',
        ),
        'team' => array(
            'Leadership Team',
            'Industry Experts',
            'Support Staff',
        ),
        'faq' => array(
            'Getting Started',
            'Technical Questions',
            'Billing & Support',
        ),
        'cta' => array(
            'Next Steps',
            'Contact Information',
            'Free Consultation',
        ),
    );

    /**
     * Generate outline using stub service
     *
     * @param array $params Generation parameters.
     * @param array $blueprint Blueprint data.
     * @return array Generated outline data.
     */
    public function generate_outline( $params, $blueprint ) {
        $blueprint_sections = $blueprint['schema']['sections'] ?? array();
        $sections = array();

        // Extract key terms from brief for contextual headings
        $key_terms = $this->extract_key_terms( $params['brief'] );
        $main_topic = ! empty( $key_terms ) ? $key_terms[0] : 'Your Topic';

        foreach ( $blueprint_sections as $index => $template_section ) {
            $section = array(
                'id' => 'section-' . ( $index + 1 ),
                'heading' => $this->generate_contextual_heading( $template_section, $main_topic ),
                'type' => $template_section['type'] ?? 'content',
                'targetWords' => intval( $template_section['word_target'] ?? 150 ),
                'needsImage' => $this->determine_image_need( $template_section ),
                'mode' => 'stub',
                'subheadings' => $this->generate_subheadings( $template_section['type'] ?? 'content', $main_topic ),
            );

            $sections[] = $section;
        }

        // If no blueprint sections, generate default structure
        if ( empty( $sections ) ) {
            $sections = $this->generate_default_outline( $main_topic, $params );
        }

        $total_words = array_sum( array_column( $sections, 'targetWords' ) );

        return array(
            'sections' => $sections,
            'total_words' => $total_words,
            'estimated_time' => $this->estimate_writing_time( $total_words ),
        );
    }

    /**
     * Extract key terms from brief
     *
     * @param string $brief Content brief.
     * @return array Array of key terms.
     */
    private function extract_key_terms( $brief ) {
        // Simple keyword extraction
        $words = preg_split( '/\s+/', strtolower( $brief ) );
        
        // Remove common stop words
        $stop_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
            'by', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
            'up', 'down', 'out', 'off', 'over', 'under', 'again', 'further', 'then', 'once',
            'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each',
            'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only',
            'own', 'same', 'so', 'than', 'too', 'very', 'can', 'will', 'just', 'should',
            'now', 'create', 'content', 'write', 'article', 'blog', 'post', 'page',
        );

        $filtered_words = array_filter( $words, function( $word ) use ( $stop_words ) {
            return strlen( $word ) > 3 && ! in_array( $word, $stop_words, true );
        });

        // Get most frequent words (simple frequency analysis)
        $word_freq = array_count_values( $filtered_words );
        arsort( $word_freq );
        
        return array_slice( array_keys( $word_freq ), 0, 3 );
    }

    /**
     * Generate contextual heading for section
     *
     * @param array  $template_section Blueprint section template.
     * @param string $main_topic Main topic from brief.
     * @return string Generated heading.
     */
    private function generate_contextual_heading( $template_section, $main_topic ) {
        $section_type = $template_section['type'] ?? 'content';
        
        // Use custom heading if provided
        if ( ! empty( $template_section['heading'] ) ) {
            return $template_section['heading'];
        }

        // Get templates for section type
        $templates = $this->heading_templates[ $section_type ] ?? $this->heading_templates['content'];
        $selected_template = $templates[ array_rand( $templates ) ];

        // Apply topic to template
        return sprintf( $selected_template, ucfirst( $main_topic ) );
    }

    /**
     * Generate subheadings for section
     *
     * @param string $section_type Section type.
     * @param string $main_topic Main topic.
     * @return array Array of subheadings.
     */
    private function generate_subheadings( $section_type, $main_topic ) {
        $templates = $this->subheading_templates[ $section_type ] ?? $this->subheading_templates['content'];
        
        // Randomly select 2-3 subheadings
        $count = rand( 2, 3 );
        $selected = array_rand( $templates, min( $count, count( $templates ) ) );
        
        if ( ! is_array( $selected ) ) {
            $selected = array( $selected );
        }

        $subheadings = array();
        foreach ( $selected as $index ) {
            $template = $templates[ $index ];
            $subheadings[] = strpos( $template, '%s' ) !== false 
                ? sprintf( $template, $main_topic )
                : $template;
        }

        return $subheadings;
    }

    /**
     * Determine if section needs image
     *
     * @param array $template_section Blueprint section template.
     * @return bool True if image is needed.
     */
    private function determine_image_need( $template_section ) {
        // Check blueprint media policy
        if ( isset( $template_section['media_policy'] ) ) {
            return $template_section['media_policy'] === 'required';
        }

        // Default based on section type
        $image_sections = array( 'hero', 'testimonial', 'team', 'pricing' );
        $section_type = $template_section['type'] ?? 'content';
        
        return in_array( $section_type, $image_sections, true );
    }

    /**
     * Generate default outline when no blueprint is available
     *
     * @param string $main_topic Main topic.
     * @param array  $params Generation parameters.
     * @return array Default sections.
     */
    private function generate_default_outline( $main_topic, $params ) {
        $default_sections = array(
            array(
                'type' => 'hero',
                'word_target' => 100,
                'media_policy' => 'required',
            ),
            array(
                'type' => 'content',
                'word_target' => 300,
                'media_policy' => 'optional',
            ),
            array(
                'type' => 'content',
                'word_target' => 250,
                'media_policy' => 'optional',
            ),
        );

        // Add additional sections based on tone
        if ( $params['tone'] === 'professional' || $params['tone'] === 'authoritative' ) {
            $default_sections[] = array(
                'type' => 'testimonial',
                'word_target' => 150,
                'media_policy' => 'required',
            );
        }

        if ( ! empty( $params['audience'] ) ) {
            $default_sections[] = array(
                'type' => 'cta',
                'word_target' => 100,
                'media_policy' => 'optional',
            );
        }

        $sections = array();
        foreach ( $default_sections as $index => $template_section ) {
            $section = array(
                'id' => 'section-' . ( $index + 1 ),
                'heading' => $this->generate_contextual_heading( $template_section, $main_topic ),
                'type' => $template_section['type'],
                'targetWords' => $template_section['word_target'],
                'needsImage' => $this->determine_image_need( $template_section ),
                'mode' => 'stub',
                'subheadings' => $this->generate_subheadings( $template_section['type'], $main_topic ),
            );

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * Estimate writing time based on word count
     *
     * @param int $word_count Total word count.
     * @return int Estimated time in minutes.
     */
    private function estimate_writing_time( $word_count ) {
        // Assumption: ~50 words per minute for quality content writing
        return max( 5, intval( $word_count / 50 ) );
    }

    /**
     * Generate realistic variation in responses
     *
     * This method adds some randomness to make stub responses more realistic
     * and varied, simulating the non-deterministic nature of AI responses.
     */
    private function add_variation() {
        // Add small random delays to simulate API response time
        if ( defined( 'AI_COMPOSER_SIMULATE_DELAY' ) && AI_COMPOSER_SIMULATE_DELAY ) {
            usleep( rand( 100000, 500000 ) ); // 0.1 to 0.5 seconds
        }
    }
}