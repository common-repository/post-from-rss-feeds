<?php

/*
Plugin Name: RSS to Post 
Description: This plugin fetches RSS feed items and creates WordPress posts automatically.
Version: 1.0.1
Author: PL
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

//--------------------------------Input meta field for rss url-------------------------------------//

// Callback for the URL field
function cpfr_url_callback() {
    $rss_url = get_option('custom_rss_feed_url', '');
    echo '<input type="text" id="custom_rss_feed_url" name="custom_rss_feed_url" value="' . esc_attr($rss_url) . '" size="50" />';
}

// Settings section callback
function cpfr_rss_url_section_callback() {
    echo '<p>Enter the RSS feed URL from which you want to fetch posts:</p>';
}

// Add settings field and section
function cpfr_rss_url_settings() {
    add_settings_section(
        'custom_rss_feed_section',
        'Custom RSS Feed',
        'cpfr_rss_url_section_callback',
        'general'
    );

    add_settings_field(
        'custom_rss_feed_url',
        'RSS Feed URL',
        'cpfr_url_callback',
        'general',
        'custom_rss_feed_section'
    );

    register_setting('general', 'custom_rss_feed_url');
}
add_action('admin_init', 'cpfr_rss_url_settings');

//---------------------------------End input meta field-------------------------------------------------//


//---------------------------------Rss feed fetch & working---------------------------------------------//

// Fetch RSS feed items
function fpfr_rss_feed($rss_url) {
    $rss = simplexml_load_file($rss_url);

    if ($rss === false) {
        return [];
    }

    $items = [];
    foreach ($rss->channel->item as $item) {
        $items[] = [
            'title'       => (string) $item->title,
            'link'        => (string) $item->link,
            'description' => (string) $item->description,
            'pubDate'     => (string) $item->pubDate,
        ];
    }

    return $items;
}

// Create posts from RSS items
function cpfr_posts_from_rss($rss_items) {
    foreach ($rss_items as $item) {
        // Set up the query arguments
        $args = [
            'title'         => wp_strip_all_tags($item['title']),
            'post_type'     => 'post',
            'post_status'   => 'any',
            'posts_per_page'=> 1,
        ];
        
        // Query to check if a post with the same title exists
        $query = new WP_Query($args);
        
        // If a post with the same title exists, skip to the next item
        if ($query->have_posts()) {
            continue;
        }

        // Create the post data array
        $post_data = [
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => $item['description'],
            'post_status'   => 'publish',
            'post_author'   => 1, // Adjust as needed
            'post_date'     => gmdate('Y-m-d H:i:s', strtotime($item['pubDate'])),
            'post_category' => [1], // Adjust as needed
        ];  

        // Insert the post into the database
        wp_insert_post($post_data);
    }
}


// Schedule event on plugin activation
function pfr_rss_to_posts_activation() {
    if (!wp_next_scheduled('fetch_rss_to_posts_event')) {
        wp_schedule_event(time(), 'hourly', 'fetch_rss_to_posts_event');
    }
}
register_activation_hook(__FILE__, 'pfr_rss_to_posts_activation');

// Unschedule event on plugin deactivation
function pfr_rss_to_posts_deactivation() {
    $timestamp = wp_next_scheduled('fetch_rss_to_posts_event');
    wp_unschedule_event($timestamp, 'fetch_rss_to_posts_event');
}
register_deactivation_hook(__FILE__, 'pfr_rss_to_posts_deactivation');

// Fetch and create posts from the RSS feed
function fcpfr_posts() {
    $rss_url = get_option('custom_rss_feed_url', '');
    
    if ($rss_url) {
        $rss_items = fpfr_rss_feed($rss_url);
        cpfr_posts_from_rss($rss_items);
    }
}
add_action('fetch_rss_to_posts_event', 'fcpfr_posts');

//---------------------------------------End rss feed---------------------------------------------//