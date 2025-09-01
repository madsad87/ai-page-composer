<?php
/**
 * Citation Manager Class - Citation Extraction and Formatting
 * 
 * This file contains the Citation_Manager class that handles extraction and
 * formatting of citations from generated content, linking them back to MVDB
 * source chunks and providing proper attribution formatting.
 *
 * @package AIPageComposer\API
 */

namespace AIPageComposer\API;

use AIPageComposer\Utils\Security_Helper;
use AIPageComposer\Utils\Validation_Helper;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Citation Manager class for citation extraction and formatting
 */
class Citation_Manager {

    /**
     * Citation patterns for extraction
     *
     * @var array
     */
    private $citation_patterns = [
        'inline' => [
            '/\[(\d+)\]/',                           // [1], [2], etc.
            '/\(([^)]+, \d{4})\)/',                 // (Author, 2023)
            '/according to ([^,]+),/',               // according to Source,
            '/research shows that ([^.]+)\./i',     // research shows that...
            '/studies indicate ([^.]+)\./i',        // studies indicate...
        ],
        'parenthetical' => [
            '/\(([^)]+)\)/',                        // (Source information)
            '/\[([^\]]+)\]/',                       // [Source information]
        ],
        'narrative' => [
            '/([A-Z][^.]+) states that ([^.]+)\./i', // Author states that...
            '/([A-Z][^.]+) found that ([^.]+)\./i',  // Author found that...
            '/([A-Z][^.]+) reports ([^.]+)\./i',     // Author reports...
        ]
    ];

    /**
     * Extract and format citations from content
     *
     * @param string $content Generated content.
     * @param array  $context_chunks MVDB context chunks.
     * @return array Formatted citations.
     */
    public function extract_and_format_citations( $content, $context_chunks ) {
        $citations = [];
        $citation_id = 1;
        
        // Extract different types of citations
        foreach ( $this->citation_patterns as $type => $patterns ) {
            foreach ( $patterns as $pattern ) {
                preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE );
                
                if ( ! empty( $matches[0] ) ) {
                    foreach ( $matches[0] as $index => $match ) {
                        $citation_text = $match[0];
                        $position = $match[1];
                        
                        // Try to match with context chunks
                        $matched_chunk = $this->find_matching_chunk( $citation_text, $context_chunks );
                        
                        $citation = [
                            'id' => 'cite-' . $citation_id,
                            'text' => trim( $citation_text, '[]()' ),
                            'type' => $type,
                            'position' => $position,
                            'source' => $matched_chunk ? $matched_chunk['source'] ?? '' : '',
                            'mvdb_chunk_id' => $matched_chunk ? $matched_chunk['id'] ?? '' : '',
                            'confidence' => $matched_chunk ? $this->calculate_match_confidence( $citation_text, $matched_chunk ) : 0
                        ];
                        
                        $citations[] = $citation;
                        $citation_id++;
                    }
                }
            }
        }
        
        // Remove duplicates and sort by position
        $citations = $this->deduplicate_citations( $citations );
        usort( $citations, function( $a, $b ) {
            return $a['position'] <=> $b['position'];
        } );
        
        // Enhance citations with additional metadata
        return $this->enhance_citations( $citations, $context_chunks );
    }

    /**
     * Find matching chunk for citation
     *
     * @param string $citation_text Citation text.
     * @param array  $context_chunks MVDB chunks.
     * @return array|null Matched chunk or null.
     */
    private function find_matching_chunk( $citation_text, $context_chunks ) {
        if ( empty( $context_chunks ) ) {
            return null;
        }
        
        $best_match = null;
        $best_score = 0;
        
        $cleaned_citation = $this->clean_citation_text( $citation_text );
        
        foreach ( $context_chunks as $chunk ) {
            $chunk_text = $chunk['text'] ?? '';
            $similarity = $this->calculate_text_similarity( $cleaned_citation, $chunk_text );
            
            if ( $similarity > $best_score && $similarity > 0.3 ) {
                $best_score = $similarity;
                $best_match = $chunk;
            }
        }
        
        return $best_match;
    }

    /**
     * Clean citation text for matching
     *
     * @param string $citation_text Raw citation text.
     * @return string Cleaned text.
     */
    private function clean_citation_text( $citation_text ) {
        // Remove citation markers
        $cleaned = trim( $citation_text, '[]()' );
        
        // Remove common citation prefixes
        $prefixes = [
            'according to ',
            'research shows that ',
            'studies indicate ',
            'data suggests ',
            'findings reveal '
        ];
        
        foreach ( $prefixes as $prefix ) {
            if ( stripos( $cleaned, $prefix ) === 0 ) {
                $cleaned = substr( $cleaned, strlen( $prefix ) );
                break;
            }
        }
        
        return trim( $cleaned );
    }

    /**
     * Calculate text similarity between citation and chunk
     *
     * @param string $citation_text Citation text.
     * @param string $chunk_text Chunk text.
     * @return float Similarity score (0-1).
     */
    private function calculate_text_similarity( $citation_text, $chunk_text ) {
        // Use simple word-based similarity
        $citation_words = array_unique( str_word_count( strtolower( $citation_text ), 1 ) );
        $chunk_words = array_unique( str_word_count( strtolower( $chunk_text ), 1 ) );
        
        if ( empty( $citation_words ) || empty( $chunk_words ) ) {
            return 0;
        }
        
        $intersection = array_intersect( $citation_words, $chunk_words );
        $union = array_unique( array_merge( $citation_words, $chunk_words ) );
        
        return count( $intersection ) / count( $union );
    }

    /**
     * Calculate match confidence
     *
     * @param string $citation_text Citation text.
     * @param array  $chunk Matched chunk.
     * @return float Confidence score (0-1).
     */
    private function calculate_match_confidence( $citation_text, $chunk ) {
        $base_similarity = $this->calculate_text_similarity( $citation_text, $chunk['text'] ?? '' );
        
        // Boost confidence if chunk has source metadata
        if ( ! empty( $chunk['source'] ) ) {
            $base_similarity += 0.1;
        }
        
        // Boost confidence if chunk has high relevance score
        if ( isset( $chunk['score'] ) && $chunk['score'] > 0.8 ) {
            $base_similarity += 0.1;
        }
        
        return min( $base_similarity, 1.0 );
    }

    /**
     * Remove duplicate citations
     *
     * @param array $citations Citations array.
     * @return array Deduplicated citations.
     */
    private function deduplicate_citations( $citations ) {
        $unique_citations = [];
        $seen_texts = [];
        
        foreach ( $citations as $citation ) {
            $normalized_text = strtolower( trim( $citation['text'] ) );
            
            if ( ! in_array( $normalized_text, $seen_texts ) ) {
                $unique_citations[] = $citation;
                $seen_texts[] = $normalized_text;
            }
        }
        
        return $unique_citations;
    }

    /**
     * Enhance citations with additional metadata
     *
     * @param array $citations Citations array.
     * @param array $context_chunks MVDB chunks.
     * @return array Enhanced citations.
     */
    private function enhance_citations( $citations, $context_chunks ) {
        foreach ( $citations as &$citation ) {
            // Add formatted source if missing
            if ( empty( $citation['source'] ) && ! empty( $citation['mvdb_chunk_id'] ) ) {
                $chunk = $this->find_chunk_by_id( $citation['mvdb_chunk_id'], $context_chunks );
                if ( $chunk && isset( $chunk['metadata']['source'] ) ) {
                    $citation['source'] = $chunk['metadata']['source'];
                }
            }
            
            // Add URL if available
            if ( ! empty( $citation['source'] ) && filter_var( $citation['source'], FILTER_VALIDATE_URL ) ) {
                $citation['url'] = $citation['source'];
                $citation['source'] = $this->extract_domain_from_url( $citation['source'] );
            }
            
            // Format citation according to style
            $citation['formatted'] = $this->format_citation( $citation );
            
            // Add accessibility label
            $citation['aria_label'] = $this->generate_aria_label( $citation );
        }
        
        return $citations;
    }

    /**
     * Find chunk by ID
     *
     * @param string $chunk_id Chunk ID.
     * @param array  $context_chunks MVDB chunks.
     * @return array|null Found chunk or null.
     */
    private function find_chunk_by_id( $chunk_id, $context_chunks ) {
        foreach ( $context_chunks as $chunk ) {
            if ( ( $chunk['id'] ?? '' ) === $chunk_id ) {
                return $chunk;
            }
        }
        return null;
    }

    /**
     * Extract domain from URL
     *
     * @param string $url URL string.
     * @return string Domain name.
     */
    private function extract_domain_from_url( $url ) {
        $parsed = parse_url( $url );
        $domain = $parsed['host'] ?? $url;
        
        // Remove www. prefix
        if ( strpos( $domain, 'www.' ) === 0 ) {
            $domain = substr( $domain, 4 );
        }
        
        return $domain;
    }

    /**
     * Format citation according to style
     *
     * @param array $citation Citation data.
     * @return string Formatted citation.
     */
    private function format_citation( $citation ) {
        $text = $citation['text'];
        $source = $citation['source'];
        
        if ( empty( $source ) ) {
            return $text;
        }
        
        switch ( $citation['type'] ) {
            case 'inline':
                return sprintf( '%s [%s]', $text, $source );
                
            case 'parenthetical':
                return sprintf( '%s (%s)', $text, $source );
                
            case 'narrative':
                return sprintf( 'According to %s, %s', $source, $text );
                
            default:
                return sprintf( '%s - %s', $text, $source );
        }
    }

    /**
     * Generate accessibility label for citation
     *
     * @param array $citation Citation data.
     * @return string Aria label.
     */
    private function generate_aria_label( $citation ) {
        $source = ! empty( $citation['source'] ) ? $citation['source'] : 'source';
        return sprintf( 
            __( 'Citation from %s: %s', 'ai-page-composer' ),
            $source,
            wp_trim_words( $citation['text'], 10 )
        );
    }

    /**
     * Generate citation HTML
     *
     * @param array $citations Citations array.
     * @param string $style Citation style (inline|footnote|bibliography).
     * @return string Citation HTML.
     */
    public function generate_citation_html( $citations, $style = 'inline' ) {
        if ( empty( $citations ) ) {
            return '';
        }
        
        switch ( $style ) {
            case 'footnote':
                return $this->generate_footnote_html( $citations );
                
            case 'bibliography':
                return $this->generate_bibliography_html( $citations );
                
            case 'inline':
            default:
                return $this->generate_inline_html( $citations );
        }
    }

    /**
     * Generate inline citation HTML
     *
     * @param array $citations Citations array.
     * @return string HTML string.
     */
    private function generate_inline_html( $citations ) {
        $html = '';
        
        foreach ( $citations as $citation ) {
            $url = $citation['url'] ?? '';
            $aria_label = $citation['aria_label'] ?? '';
            
            if ( $url ) {
                $html .= sprintf(
                    '<sup class="ai-citation" id="%s"><a href="%s" target="_blank" rel="noopener" aria-label="%s">%d</a></sup>',
                    esc_attr( $citation['id'] ),
                    esc_url( $url ),
                    esc_attr( $aria_label ),
                    array_search( $citation, $citations ) + 1
                );
            } else {
                $html .= sprintf(
                    '<sup class="ai-citation" id="%s" aria-label="%s">%d</sup>',
                    esc_attr( $citation['id'] ),
                    esc_attr( $aria_label ),
                    array_search( $citation, $citations ) + 1
                );
            }
        }
        
        return $html;
    }

    /**
     * Generate footnote citation HTML
     *
     * @param array $citations Citations array.
     * @return string HTML string.
     */
    private function generate_footnote_html( $citations ) {
        $html = '<div class="ai-citations-footnotes">';
        $html .= '<h4>' . __( 'References', 'ai-page-composer' ) . '</h4>';
        $html .= '<ol>';
        
        foreach ( $citations as $index => $citation ) {
            $url = $citation['url'] ?? '';
            $source = $citation['source'] ?? __( 'Source', 'ai-page-composer' );
            
            $html .= sprintf( '<li id="footnote-%s">', esc_attr( $citation['id'] ) );
            
            if ( $url ) {
                $html .= sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url( $url ),
                    esc_html( $source )
                );
            } else {
                $html .= esc_html( $source );
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ol>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate bibliography citation HTML
     *
     * @param array $citations Citations array.
     * @return string HTML string.
     */
    private function generate_bibliography_html( $citations ) {
        $html = '<div class="ai-citations-bibliography">';
        $html .= '<h4>' . __( 'Bibliography', 'ai-page-composer' ) . '</h4>';
        $html .= '<ul>';
        
        foreach ( $citations as $citation ) {
            $formatted = $citation['formatted'] ?? $citation['text'];
            $url = $citation['url'] ?? '';
            
            $html .= '<li>';
            
            if ( $url ) {
                $html .= sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url( $url ),
                    esc_html( $formatted )
                );
            } else {
                $html .= esc_html( $formatted );
            }
            
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
}