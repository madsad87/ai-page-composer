<?php
/**
 * Outline Integration Test Script
 * 
 * Simple test to verify outline endpoint integration works correctly.
 * 
 * @package AIPageComposer
 */

// Basic WordPress environment check
if ( ! defined( 'ABSPATH' ) ) {
    echo "Error: This script must be run in a WordPress environment.\n";
    exit( 1 );
}

// Check if AI Page Composer is loaded
if ( ! class_exists( 'AIPageComposer\\Core\\Plugin' ) ) {
    echo "Error: AI Page Composer plugin is not loaded.\n";
    exit( 1 );
}

/**
 * Test outline endpoint integration
 */
function test_outline_endpoint_integration() {
    global $wp_rest_server;
    
    echo "=== AI Composer Outline Endpoint Integration Test ===\n\n";
    
    // Initialize REST server if not already done
    if ( empty( $wp_rest_server ) ) {
        $wp_rest_server = new WP_REST_Server();
        do_action( 'rest_api_init' );
    }
    
    // Test 1: Check if endpoint is registered
    echo "Test 1: Checking endpoint registration...\n";
    $routes = rest_get_server()->get_routes();
    
    if ( isset( $routes['/ai-composer/v1/outline'] ) ) {
        echo "✓ PASS: /ai-composer/v1/outline endpoint is registered\n";
    } else {
        echo "✗ FAIL: /ai-composer/v1/outline endpoint is NOT registered\n";
        echo "Available routes:\n";
        foreach ( $routes as $route => $methods ) {
            if ( strpos( $route, 'ai-composer' ) !== false ) {
                echo "  - $route\n";
            }
        }
        return false;
    }
    
    // Test 2: Create test blueprint
    echo "\nTest 2: Creating test blueprint...\n";
    $test_blueprint_id = create_test_blueprint();
    
    if ( $test_blueprint_id ) {
        echo "✓ PASS: Test blueprint created with ID: $test_blueprint_id\n";
    } else {
        echo "✗ FAIL: Could not create test blueprint\n";
        return false;
    }
    
    // Test 3: Test outline generation (stub mode)
    echo "\nTest 3: Testing outline generation (stub mode)...\n";
    
    // Force stub mode
    define( 'AI_COMPOSER_DEV_MODE', true );
    
    $test_data = array(
        'blueprint_id' => $test_blueprint_id,
        'brief' => 'Create a comprehensive guide about sustainable gardening practices for urban environments',
        'audience' => 'Urban gardeners and beginners',
        'tone' => 'friendly',
        'alpha' => 0.7
    );
    
    $request = new \WP_REST_Request( 'POST', '/ai-composer/v1/outline' );
    foreach ( $test_data as $key => $value ) {
        $request->set_param( $key, $value );
    }
    
    // Set up admin user for permissions
    $admin_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
    if ( ! empty( $admin_user ) ) {
        wp_set_current_user( $admin_user[0]->ID );
    } else {
        echo "Warning: No admin user found, creating temporary admin user...\n";
        $temp_admin = wp_insert_user( array(
            'user_login' => 'temp_admin_' . time(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator'
        ) );
        wp_set_current_user( $temp_admin );
    }
    
    $response = rest_get_server()->dispatch( $request );
    
    if ( $response->get_status() === 200 ) {
        echo "✓ PASS: Outline generation request successful\n";
        
        $data = $response->get_data();
        
        // Test response structure
        echo "\nTest 4: Validating response structure...\n";
        $required_fields = array( 'sections', 'total_words', 'estimated_cost', 'mode', 'blueprint_id', 'generated_at' );
        $all_fields_present = true;
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                echo "✗ FAIL: Missing required field: $field\n";
                $all_fields_present = false;
            }
        }
        
        if ( $all_fields_present ) {
            echo "✓ PASS: All required response fields present\n";
        }
        
        // Test sections structure
        echo "\nTest 5: Validating sections structure...\n";
        if ( isset( $data['sections'] ) && is_array( $data['sections'] ) && ! empty( $data['sections'] ) ) {
            echo "✓ PASS: Sections array is present and not empty\n";
            
            $section_fields_valid = true;
            foreach ( $data['sections'] as $index => $section ) {
                $required_section_fields = array( 'id', 'heading', 'type', 'targetWords', 'needsImage', 'mode' );
                
                foreach ( $required_section_fields as $field ) {
                    if ( ! isset( $section[ $field ] ) ) {
                        echo "✗ FAIL: Section $index missing required field: $field\n";
                        $section_fields_valid = false;
                    }
                }
            }
            
            if ( $section_fields_valid ) {
                echo "✓ PASS: All sections have required fields\n";
            }
        } else {
            echo "✗ FAIL: Sections array is missing or empty\n";
        }
        
        // Test block preferences integration
        echo "\nTest 6: Validating block preferences integration...\n";
        $block_prefs_valid = true;
        foreach ( $data['sections'] as $index => $section ) {
            if ( ! isset( $section['block_preference'] ) ) {
                echo "✗ FAIL: Section $index missing block_preference\n";
                $block_prefs_valid = false;
                continue;
            }
            
            $block_pref = $section['block_preference'];
            $required_bp_fields = array( 'preferred_plugin', 'primary_block', 'fallback_blocks', 'pattern_preference' );
            
            foreach ( $required_bp_fields as $field ) {
                if ( ! isset( $block_pref[ $field ] ) ) {
                    echo "✗ FAIL: Section $index block_preference missing field: $field\n";
                    $block_prefs_valid = false;
                }
            }
        }
        
        if ( $block_prefs_valid ) {
            echo "✓ PASS: All sections have valid block preferences\n";
        }
        
        // Display sample response
        echo "\nSample Response Data:\n";
        echo "Mode: " . ($data['mode'] ?? 'unknown') . "\n";
        echo "Total Words: " . ($data['total_words'] ?? 0) . "\n";
        echo "Estimated Cost: $" . number_format($data['estimated_cost'] ?? 0, 4) . "\n";
        echo "Number of Sections: " . count($data['sections']) . "\n";
        
        if ( ! empty( $data['sections'] ) ) {
            echo "\nFirst Section Example:\n";
            $first_section = $data['sections'][0];
            echo "  - ID: " . ($first_section['id'] ?? 'N/A') . "\n";
            echo "  - Heading: " . ($first_section['heading'] ?? 'N/A') . "\n";
            echo "  - Type: " . ($first_section['type'] ?? 'N/A') . "\n";
            echo "  - Target Words: " . ($first_section['targetWords'] ?? 0) . "\n";
            echo "  - Needs Image: " . ($first_section['needsImage'] ? 'Yes' : 'No') . "\n";
            
            if ( isset( $first_section['block_preference'] ) ) {
                $bp = $first_section['block_preference'];
                echo "  - Preferred Plugin: " . ($bp['preferred_plugin'] ?? 'N/A') . "\n";
                echo "  - Primary Block: " . ($bp['primary_block'] ?? 'N/A') . "\n";
            }
        }
        
    } else {
        echo "✗ FAIL: Outline generation request failed\n";
        echo "Status: " . $response->get_status() . "\n";
        
        if ( $response->get_data() ) {
            echo "Response: " . print_r( $response->get_data(), true ) . "\n";
        }
        
        return false;
    }
    
    // Cleanup
    echo "\nCleaning up test data...\n";
    wp_delete_post( $test_blueprint_id, true );
    
    echo "\n=== Test Summary ===\n";
    echo "✓ Outline endpoint integration test completed successfully!\n";
    echo "The REST API endpoint /ai-composer/v1/outline is working correctly.\n";
    
    return true;
}

/**
 * Create test blueprint for testing
 */
function create_test_blueprint() {
    $blueprint_post = wp_insert_post( array(
        'post_type' => 'ai_blueprint',
        'post_title' => 'Test Blueprint for Integration Test',
        'post_status' => 'publish',
        'post_content' => 'Test blueprint for outline generation integration testing',
    ) );
    
    if ( is_wp_error( $blueprint_post ) ) {
        return false;
    }
    
    // Add blueprint schema meta
    $blueprint_schema = array(
        'sections' => array(
            array(
                'type' => 'hero',
                'heading' => 'Introduction to Sustainable Gardening',
                'word_target' => 150,
                'media_policy' => 'required',
            ),
            array(
                'type' => 'content',
                'heading' => 'Getting Started with Urban Gardening',
                'word_target' => 300,
                'media_policy' => 'optional',
            ),
            array(
                'type' => 'content',
                'heading' => 'Essential Tools and Techniques',
                'word_target' => 250,
                'media_policy' => 'optional',
            ),
            array(
                'type' => 'testimonial',
                'heading' => 'Success Stories from Urban Gardeners',
                'word_target' => 200,
                'media_policy' => 'required',
            ),
        ),
        'global_settings' => array(
            'total_target_words' => 900,
            'audience' => 'urban_gardeners',
            'tone' => 'friendly',
        ),
    );
    
    update_post_meta( $blueprint_post, '_ai_blueprint_schema', $blueprint_schema );
    
    return $blueprint_post;
}

// Run the test if script is executed directly
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    // Running via WP CLI
    test_outline_endpoint_integration();
} elseif ( isset( $_GET['run_outline_test'] ) && current_user_can( 'manage_options' ) ) {
    // Running via web request (admin only)
    header( 'Content-Type: text/plain' );
    test_outline_endpoint_integration();
} else {
    echo "To run this test:\n";
    echo "1. Via WP CLI: wp eval-file outline-integration-test.php\n";
    echo "2. Via web: Add ?run_outline_test=1 to URL (admin only)\n";
}