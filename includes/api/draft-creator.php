<?php
/**
 * Draft Creator Class - Create WordPress drafts with blocks, meta, SEO data, and taxonomies
 * 
 * This class handles the creation of WordPress draft posts with assembled block content,
 * featured images, SEO metadata, taxonomies, and complete WordPress integration.
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
 * Draft Creator class for WordPress post creation
 */
class Draft_Creator {

    /**
     * SEO plugin compatibility
     *
     * @var array
     */
    private $seo_plugins = [
        'yoast' => 'wordpress-seo/wp-seo.php',
        'rankmath' => 'seo-by-rank-math/rank-math.php',
        'aioseo' => 'all-in-one-seo-pack/all_in_one_seo_pack.php'
    ];

    /**
     * Create WordPress draft with assembled content
     *
     * @param array $draft_data Draft creation data.
     * @return array Draft creation result.
     */
    public function create_draft( $draft_data ) {
        try {
            // Validate required data
            $this->validate_draft_data( $draft_data );

            $content = $draft_data['content'];
            $meta = $draft_data['meta'];
            $seo_data = $draft_data['seo'] ?? [];
            $taxonomies = $draft_data['taxonomies'] ?? [];

            // Prepare post data
            $post_data = $this->prepare_post_data( $content, $meta );

            // Create the post
            $post_id = wp_insert_post( $post_data, true );

            if ( is_wp_error( $post_id ) ) {
                throw new \Exception( 
                    sprintf( __( 'Failed to create post: %s', 'ai-page-composer' ), $post_id->get_error_message() )
                );
            }

            // Set featured image
            if ( ! empty( $meta['featured_image_id'] ) ) {
                $this->set_featured_image( $post_id, $meta['featured_image_id'] );
            }

            // Add SEO metadata
            if ( ! empty( $seo_data ) ) {
                $this->add_seo_metadata( $post_id, $seo_data );
            }

            // Assign taxonomies
            if ( ! empty( $taxonomies ) ) {
                $this->assign_taxonomies( $post_id, $taxonomies );
            }

            // Add custom meta
            $this->add_custom_metadata( $post_id, $content, $meta );

            // Generate result data
            $result = $this->generate_draft_result( $post_id, $content, $meta, $seo_data, $taxonomies );

            // Log successful creation
            $this->log_draft_creation( $post_id, $result );

            return $result;

        } catch ( \Exception $e ) {
            error_log( '[AI Composer] Draft creation failed: ' . $e->getMessage() );
            throw new \Exception( 
                sprintf( __( 'Draft creation failed: %s', 'ai-page-composer' ), $e->getMessage() )
            );
        }
    }

    /**
     * Validate draft data
     *
     * @param array $draft_data Draft data to validate.
     * @throws \InvalidArgumentException If validation fails.
     */
    private function validate_draft_data( $draft_data ) {
        if ( empty( $draft_data['content'] ) ) {
            throw new \InvalidArgumentException( __( 'Content is required for draft creation', 'ai-page-composer' ) );
        }

        if ( empty( $draft_data['meta'] ) ) {
            throw new \InvalidArgumentException( __( 'Post meta is required for draft creation', 'ai-page-composer' ) );
        }

        $required_meta = ['title'];
        foreach ( $required_meta as $field ) {
            if ( empty( $draft_data['meta'][ $field ] ) ) {
                throw new \InvalidArgumentException( 
                    sprintf( __( 'Required meta field missing: %s', 'ai-page-composer' ), $field )
                );
            }
        }
    }

    /**
     * Prepare post data for wp_insert_post
     *
     * @param array $content Assembled content.
     * @param array $meta Post metadata.
     * @return array WordPress post data.
     */
    private function prepare_post_data( $content, $meta ) {
        // Extract blocks HTML for post content
        $post_content = $content['html'] ?? '';
        
        // Prepare post data
        $post_data = [
            'post_title' => sanitize_text_field( $meta['title'] ),
            'post_content' => wp_kses_post( $post_content ),
            'post_status' => sanitize_key( $meta['status'] ?? 'draft' ),
            'post_type' => sanitize_key( $meta['post_type'] ?? 'post' ),
            'post_author' => intval( $meta['author_id'] ?? get_current_user_id() ),
            'comment_status' => sanitize_key( $meta['comment_status'] ?? 'open' ),
            'ping_status' => sanitize_key( $meta['ping_status'] ?? 'open' ),
            'post_excerpt' => wp_kses_post( $meta['excerpt'] ?? '' ),
            'menu_order' => intval( $meta['menu_order'] ?? 0 )
        ];

        // Handle parent post
        if ( ! empty( $meta['parent_id'] ) ) {
            $post_data['post_parent'] = intval( $meta['parent_id'] );
        }

        // Handle template assignment
        if ( ! empty( $meta['template'] ) ) {
            $post_data['page_template'] = sanitize_file_name( $meta['template'] );
        }

        // Handle publish date
        if ( ! empty( $meta['publish_date'] ) ) {
            $post_data['post_date'] = sanitize_text_field( $meta['publish_date'] );
        }

        return $post_data;
    }

    /**
     * Set featured image for post
     *
     * @param int $post_id Post ID.
     * @param int $image_id Image attachment ID.
     */
    private function set_featured_image( $post_id, $image_id ) {
        $image_id = intval( $image_id );
        
        // Verify image exists and is an attachment
        if ( get_post_type( $image_id ) === 'attachment' ) {
            set_post_thumbnail( $post_id, $image_id );
        } else {
            error_log( "[AI Composer] Invalid featured image ID: {$image_id} for post {$post_id}" );
        }
    }

    /**
     * Add SEO metadata to post
     *
     * @param int   $post_id Post ID.
     * @param array $seo_data SEO metadata.
     */
    private function add_seo_metadata( $post_id, $seo_data ) {
        $active_seo_plugin = $this->detect_active_seo_plugin();

        switch ( $active_seo_plugin ) {
            case 'yoast':
                $this->add_yoast_seo_data( $post_id, $seo_data );
                break;
            case 'rankmath':
                $this->add_rankmath_seo_data( $post_id, $seo_data );
                break;
            case 'aioseo':
                $this->add_aioseo_seo_data( $post_id, $seo_data );
                break;
            default:
                $this->add_generic_seo_data( $post_id, $seo_data );
                break;
        }
    }

    /**
     * Detect active SEO plugin
     *
     * @return string|null Active SEO plugin key.
     */
    private function detect_active_seo_plugin() {
        foreach ( $this->seo_plugins as $plugin_key => $plugin_file ) {
            if ( is_plugin_active( $plugin_file ) ) {
                return $plugin_key;
            }
        }
        return null;
    }

    /**
     * Add Yoast SEO metadata
     *
     * @param int   $post_id Post ID.
     * @param array $seo_data SEO data.
     */
    private function add_yoast_seo_data( $post_id, $seo_data ) {
        $meta_mapping = [
            'meta_title' => '_yoast_wpseo_title',
            'meta_description' => '_yoast_wpseo_metadesc',
            'focus_keyword' => '_yoast_wpseo_focuskw',
            'canonical_url' => '_yoast_wpseo_canonical',
            'og_title' => '_yoast_wpseo_opengraph-title',
            'og_description' => '_yoast_wpseo_opengraph-description',
            'og_image_id' => '_yoast_wpseo_opengraph-image-id'
        ];

        foreach ( $meta_mapping as $seo_key => $meta_key ) {
            if ( ! empty( $seo_data[ $seo_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $seo_data[ $seo_key ] ) );
            }
        }
    }

    /**
     * Add Rank Math SEO metadata
     *
     * @param int   $post_id Post ID.
     * @param array $seo_data SEO data.
     */
    private function add_rankmath_seo_data( $post_id, $seo_data ) {
        $meta_mapping = [
            'meta_title' => 'rank_math_title',
            'meta_description' => 'rank_math_description',
            'focus_keyword' => 'rank_math_focus_keyword',
            'canonical_url' => 'rank_math_canonical_url'
        ];

        foreach ( $meta_mapping as $seo_key => $meta_key ) {
            if ( ! empty( $seo_data[ $seo_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $seo_data[ $seo_key ] ) );
            }
        }
    }

    /**
     * Add All in One SEO metadata
     *
     * @param int   $post_id Post ID.
     * @param array $seo_data SEO data.
     */
    private function add_aioseo_seo_data( $post_id, $seo_data ) {
        $meta_mapping = [
            'meta_title' => '_aioseo_title',
            'meta_description' => '_aioseo_description',
            'canonical_url' => '_aioseo_canonical_url'
        ];

        foreach ( $meta_mapping as $seo_key => $meta_key ) {
            if ( ! empty( $seo_data[ $seo_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $seo_data[ $seo_key ] ) );
            }
        }
    }

    /**
     * Add generic SEO metadata
     *
     * @param int   $post_id Post ID.
     * @param array $seo_data SEO data.
     */
    private function add_generic_seo_data( $post_id, $seo_data ) {
        $meta_mapping = [
            'meta_title' => '_ai_composer_meta_title',
            'meta_description' => '_ai_composer_meta_description',
            'focus_keyword' => '_ai_composer_focus_keyword',
            'canonical_url' => '_ai_composer_canonical_url'
        ];

        foreach ( $meta_mapping as $seo_key => $meta_key ) {
            if ( ! empty( $seo_data[ $seo_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $seo_data[ $seo_key ] ) );
            }
        }
    }

    /**
     * Assign taxonomies to post
     *
     * @param int   $post_id Post ID.
     * @param array $taxonomies Taxonomy assignments.
     */
    private function assign_taxonomies( $post_id, $taxonomies ) {
        // Handle categories
        if ( ! empty( $taxonomies['categories'] ) ) {
            $category_ids = $this->process_taxonomy_terms( $taxonomies['categories'], 'category' );
            if ( ! empty( $category_ids ) ) {
                wp_set_post_categories( $post_id, $category_ids );
            }
        }

        // Handle tags
        if ( ! empty( $taxonomies['tags'] ) ) {
            $tag_ids = $this->process_taxonomy_terms( $taxonomies['tags'], 'post_tag' );
            if ( ! empty( $tag_ids ) ) {
                wp_set_post_tags( $post_id, $tag_ids );
            }
        }

        // Handle custom taxonomies
        foreach ( $taxonomies as $taxonomy => $terms ) {
            if ( in_array( $taxonomy, ['categories', 'tags'] ) ) {
                continue;
            }

            if ( taxonomy_exists( $taxonomy ) ) {
                $term_ids = $this->process_taxonomy_terms( $terms, $taxonomy );
                if ( ! empty( $term_ids ) ) {
                    wp_set_post_terms( $post_id, $term_ids, $taxonomy );
                }
            }
        }
    }

    /**
     * Process taxonomy terms for assignment
     *
     * @param array  $terms Term data.
     * @param string $taxonomy Taxonomy name.
     * @return array Term IDs.
     */
    private function process_taxonomy_terms( $terms, $taxonomy ) {
        $term_ids = [];

        foreach ( $terms as $term ) {
            if ( is_array( $term ) ) {
                // Term data with ID and name
                $term_id = intval( $term['id'] ?? 0 );
                $term_name = sanitize_text_field( $term['name'] ?? '' );

                if ( $term_id > 0 && term_exists( $term_id, $taxonomy ) ) {
                    $term_ids[] = $term_id;
                } elseif ( ! empty( $term_name ) ) {
                    // Create term if it doesn't exist
                    $new_term = wp_insert_term( $term_name, $taxonomy );
                    if ( ! is_wp_error( $new_term ) ) {
                        $term_ids[] = $new_term['term_id'];
                    }
                }
            } elseif ( is_numeric( $term ) ) {
                // Term ID
                $term_id = intval( $term );
                if ( term_exists( $term_id, $taxonomy ) ) {
                    $term_ids[] = $term_id;
                }
            } else {
                // Term name
                $term_name = sanitize_text_field( $term );
                $term_obj = get_term_by( 'name', $term_name, $taxonomy );
                if ( $term_obj ) {
                    $term_ids[] = $term_obj->term_id;
                } else {
                    // Create term if it doesn't exist
                    $new_term = wp_insert_term( $term_name, $taxonomy );
                    if ( ! is_wp_error( $new_term ) ) {
                        $term_ids[] = $new_term['term_id'];
                    }
                }
            }
        }

        return array_unique( $term_ids );
    }

    /**
     * Add custom metadata
     *
     * @param int   $post_id Post ID.
     * @param array $content Assembled content.
     * @param array $meta Post metadata.
     */
    private function add_custom_metadata( $post_id, $content, $meta ) {
        // Store AI Composer metadata
        update_post_meta( $post_id, '_ai_composer_generated', true );
        update_post_meta( $post_id, '_ai_composer_version', '1.0' );
        update_post_meta( $post_id, '_ai_composer_created_at', current_time( 'mysql' ) );

        // Store block data
        if ( ! empty( $content['json'] ) ) {
            update_post_meta( $post_id, '_ai_composer_blocks_json', $content['json'] );
        }

        // Store assembly metadata
        if ( ! empty( $content['assembly_metadata'] ) ) {
            update_post_meta( $post_id, '_ai_composer_assembly_metadata', $content['assembly_metadata'] );
        }

        // Store plugin indicators
        if ( ! empty( $content['plugin_indicators'] ) ) {
            update_post_meta( $post_id, '_ai_composer_plugin_indicators', $content['plugin_indicators'] );
        }

        // Store custom fields from meta
        $custom_fields = $meta['custom_fields'] ?? [];
        foreach ( $custom_fields as $field_key => $field_value ) {
            $sanitized_key = sanitize_key( $field_key );
            update_post_meta( $post_id, $sanitized_key, $field_value );
        }
    }

    /**
     * Generate draft result data
     *
     * @param int   $post_id Created post ID.
     * @param array $content Assembled content.
     * @param array $meta Post metadata.
     * @param array $seo_data SEO data.
     * @param array $taxonomies Taxonomy data.
     * @return array Draft result.
     */
    private function generate_draft_result( $post_id, $content, $meta, $seo_data, $taxonomies ) {
        $post = get_post( $post_id );
        
        // Generate URLs
        $edit_url = admin_url( "post.php?post={$post_id}&action=edit" );
        $preview_url = get_preview_post_link( $post_id );
        $permalink = get_permalink( $post_id );

        // Get featured image data
        $featured_image = null;
        if ( has_post_thumbnail( $post_id ) ) {
            $thumbnail_id = get_post_thumbnail_id( $post_id );
            $featured_image = [
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_image_url( $thumbnail_id, 'full' ),
                'thumbnail' => wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' )
            ];
        }

        // Calculate content statistics
        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        $block_count = count( $content['blocks'] ?? [] );

        // Get assigned taxonomies
        $assigned_taxonomies = [];
        $assigned_taxonomies['categories'] = wp_get_post_categories( $post_id, ['fields' => 'all'] );
        $assigned_taxonomies['tags'] = wp_get_post_tags( $post_id, ['fields' => 'all'] );

        // Generate SEO meta information
        $seo_meta = $this->generate_seo_meta_info( $post_id, $seo_data );

        return [
            'post_id' => $post_id,
            'edit_url' => $edit_url,
            'preview_url' => $preview_url,
            'permalink' => $permalink,
            'post_data' => [
                'title' => $post->post_title,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'word_count' => $word_count,
                'block_count' => $block_count,
                'featured_image' => $featured_image,
                'excerpt' => $post->post_excerpt,
                'author_id' => $post->post_author,
                'created_at' => $post->post_date
            ],
            'seo_meta' => $seo_meta,
            'taxonomies' => $assigned_taxonomies,
            'assembly_metadata' => $content['assembly_metadata'] ?? [],
            'creation_timestamp' => current_time( 'mysql' )
        ];
    }

    /**
     * Generate SEO meta information
     *
     * @param int   $post_id Post ID.
     * @param array $seo_data SEO data.
     * @return array SEO meta info.
     */
    private function generate_seo_meta_info( $post_id, $seo_data ) {
        $seo_meta = [
            'plugin_used' => $this->detect_active_seo_plugin() ?? 'none',
            'meta_title' => $seo_data['meta_title'] ?? '',
            'meta_description' => $seo_data['meta_description'] ?? '',
            'focus_keyword' => $seo_data['focus_keyword'] ?? ''
        ];

        // Calculate basic SEO scores (simplified)
        $content = get_post_field( 'post_content', $post_id );
        $title = get_post_field( 'post_title', $post_id );
        
        $seo_meta['title_length'] = strlen( $title );
        $seo_meta['content_length'] = strlen( wp_strip_all_tags( $content ) );
        $seo_meta['readability_score'] = $this->calculate_basic_readability( $content );

        return $seo_meta;
    }

    /**
     * Calculate basic readability score
     *
     * @param string $content Post content.
     * @return int Basic readability score.
     */
    private function calculate_basic_readability( $content ) {
        $text = wp_strip_all_tags( $content );
        $sentence_count = preg_match_all( '/[.!?]+/', $text );
        $word_count = str_word_count( $text );
        
        if ( $sentence_count === 0 || $word_count === 0 ) {
            return 50;
        }

        $avg_sentence_length = $word_count / $sentence_count;
        
        // Simple scoring based on sentence length
        if ( $avg_sentence_length <= 15 ) {
            return 90;
        } elseif ( $avg_sentence_length <= 20 ) {
            return 80;
        } elseif ( $avg_sentence_length <= 25 ) {
            return 70;
        } else {
            return 60;
        }
    }

    /**
     * Log draft creation
     *
     * @param int   $post_id Created post ID.
     * @param array $result Draft result.
     */
    private function log_draft_creation( $post_id, $result ) {
        $log_data = [
            'post_id' => $post_id,
            'title' => $result['post_data']['title'],
            'word_count' => $result['post_data']['word_count'],
            'block_count' => $result['post_data']['block_count'],
            'timestamp' => current_time( 'mysql' )
        ];

        error_log( sprintf(
            '[AI Composer] Draft created successfully: Post ID %d, Title: "%s", Words: %d, Blocks: %d',
            $log_data['post_id'],
            $log_data['title'],
            $log_data['word_count'],
            $log_data['block_count']
        ) );

        // Store creation log for analytics
        $creation_logs = get_option( 'ai_composer_creation_logs', [] );
        $creation_logs[] = $log_data;
        
        // Keep only last 100 entries
        if ( count( $creation_logs ) > 100 ) {
            $creation_logs = array_slice( $creation_logs, -100 );
        }
        
        update_option( 'ai_composer_creation_logs', $creation_logs );
    }
}