<?php
/*
Plugin Name: Post Category Prev-Next Link Fix
Plugin URI: https://wordpress.org/plugins/post-category-prev-next-fix/
Description: Solve not found page error in prev-next link of category page while using custom permalink like "/%category%/%postname%/". This is also remove category word from the url without using categorybase.
Version: 1.1
Author: Ketan Ambaliya
Author URI: https://in.linkedin.com/in/ketan-ambaliya-a7a68774
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

Post Category Prev-Next Link Fix is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Post Category Prev-Next Link Fix is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You are allowed to use, change and redistibute without any legal issues. I am not responsible for any damage caused by this program. Use at your own risk
Tested with Wordpress 5.7.1 
*/

/**
 * This plugin will fix the problem where next/previous of page number buttons are broken on list
 * of posts in a category when the custom permalink string is:
 * /%category%/%postname%/ 
 * The problem is that with a url like this:
 * /categoryname/page/2
 * the 'page' looks like a post name, not the keyword "page"
 * This is also remove category word from the url without using categorybase.
 */
if ( ! class_exists( 'post_category_prev_next' ) ) {
	class post_category_prev_next {
		
		function __construct() {
			add_action( 'init', array( $this, 'flush_rules' ), 999 );

			foreach ( array( 'created_category', 'edited_category', 'delete_category' ) as $action ) {
				add_action( $action, array( $this, 'schedule_flush' ) );
			};
			
			add_filter( 'query_vars', array( $this, 'update_query_vars' ) );
			add_filter( 'category_link', array( $this, 'remove_category_base' ) );
			add_filter( 'request', array( $this, 'redirect_old_category_url' ) );
			add_filter( 'category_rewrite_rules', array( $this, 'add_category_rewrite_rules' ) );

			register_activation_hook( __FILE__, array( $this, 'on_activation_and_deactivation' ) );
            register_deactivation_hook( __FILE__, array( $this, 'on_activation_and_deactivation' ) );
		}
		
		public function flush_rules() {
			if ( get_option( 'rcb_flush_rewrite_rules' ) ) {
				add_action( 'shutdown', 'flush_rewrite_rules' );
				delete_option( 'rcb_flush_rewrite_rules' );
			}
		}
		
		public function schedule_flush() {
			update_option( 'rcb_flush_rewrite_rules', 1 );
		}
		
		public function remove_category_base( $permalink ) {
			$category_base = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
			
			if ( '/' === substr( $category_base, 0, 1 ) ) {
				$category_base = substr( $category_base, 1 );
			}
			
			$category_base .= '/';
			
			return preg_replace( '`' . preg_quote( $category_base, '`' ) . '`u', '', $permalink, 1 );
		}
		
		public function update_query_vars( $query_vars ) {
			$query_vars[] = 'rcb_category_redirect';
			return $query_vars;
		}
		
		public function redirect_old_category_url( $query_vars ) {
			if ( isset( $query_vars['rcb_category_redirect'] ) ) {
				$category_link = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $query_vars['rcb_category_redirect'], 'category' );
				wp_redirect( $category_link, 301 );
				exit;
			}
			return $query_vars;
		}
		
		public function add_category_rewrite_rules() {
			global $wp_rewrite;
			
			$category_rewrite = array();
			
			if ( function_exists( 'is_multisite' ) && is_multisite() && ! is_subdomain_install() && is_main_site() ) {
				$blog_prefix = 'blog/';
			} else {
				$blog_prefix = '';
			}
					
			foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) {
				$category_nicename = $category->slug;
				
				if ( $category->cat_ID == $category->parent ) {
					$category->parent = 0;
				} elseif ( 0 != $category->parent ) {
					$category_nicename = get_category_parents( $category->parent, false, '/', true ) . $category_nicename;
				}
				
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/?$'] = 'index.php?category_name=$matches[1]';
			}
			
			
			$old_base = $wp_rewrite->get_category_permastruct();
			$old_base = str_replace( '%category%', '(.+)', $old_base );
			$old_base = trim( $old_base, '/' );
			
			$category_rewrite[$old_base . '$'] = 'index.php?rcb_category_redirect=$matches[1]';
			
			return $category_rewrite;
		}

		public function on_activation_and_deactivation() {
			flush_rewrite_rules();
		}
	}

	new post_category_prev_next();
}
?>
