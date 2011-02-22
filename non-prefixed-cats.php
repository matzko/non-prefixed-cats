<?php
/*
Plugin Name: Non-prefixed Categories
Plugin URI: http://austinmatzko.com/wordpress-plugins/non-prefixed-categories/
Description: Removes the "category" prefix from the URL for a category.
Version: 1.0
Author: Austin Matzko
Author URI: http://austinmatzko.com
*/

class NonPrefixedCategories {

	var $clean_category_rewrites;

	function NonPrefixedCategories()
	{
		return $this->__construct();
	}

	function __construct() 
	{
		register_activation_hook(__FILE__, array(&$this, 'event_activate'));
		register_deactivation_hook(__FILE__, array(&$this, 'event_deactivate'));
		
		add_filter('category_rewrite_rules', array(&$this, 'filter_category_rewrite_rules'));
		add_filter('category_link', array(&$this, 'filter_category_link'), 10, 2);
	/*
		add_filter('generate_rewrite_rules', array(&$this, 'filter_generate_rewrite_rules'));
		
		$this->clean_category_rewrites = array();
	*/
	}

	function cat_links()
	{
		global $wp_rewrite;
		$cat_rewrite = array();
		$cats = get_categories(array('fields' => 'ids', 'hide_empty' => false));
		$base = trailingslashit(get_option('home'));
		foreach( (array) $cats as $cat_id ) {
			$cat_base = str_replace($base, '', get_category_link($cat_id));
			$cat_rewrites = $wp_rewrite->generate_rewrite_rules($cat_base, EP_CATEGORIES, true, true, false, false, true);
			$cat_rewrites[$cat_base . '?$'] = 'index.php?';
			foreach( (array) $cat_rewrites as $match => $value ) {
				$cat_rewrites[$match] = $value .= '&cat=' . $cat_id;
			}
			
			$cat_rewrite = array_merge($cat_rewrite, $cat_rewrites);
		}

		$new_links = array();
		$links = array_keys($cat_rewrite);
		uasort($links, array(&$this, 'sort_permalinks'));
		foreach( $links as $key ) {
			$new_links[$key] = $cat_rewrite[$key];
		}

		return $new_links;
	}

	function event_activate()
	{
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	function event_deactivate()
	{
		global $wp_rewrite;

		// Remove the filters so we don't regenerate the wrong rules when we flush
		remove_filter('category_rewrite_rules', array(&$this, 'filter_category_rewrite_rules'));
		remove_filter('generate_rewrite_rules', array(&$this, 'filter_generate_rewrite_rules'));
		remove_filter('category_link', array(&$this, 'filter_category_link'));

		$wp_rewrite->flush_rules();
	}

	function filter_generate_rewrite_rules($wp_rewrite)
	{
		$wp_rewrite->rules = $wp_rewrite->rules + $this->clean_category_rewrites;
	}

	function filter_category_rewrite_rules($category_rewrite)
	{
		global $wp_rewrite;
		$wp_rewrite->use_verbose_page_rules = true;
		$links = $this->cat_links() + $category_rewrite;
		// error_log(print_r($links, true));
		return $links;
	}

	function filter_category_link($cat_link, $cat_id)
	{
		return $this->remove_cat_base($cat_link);
	}

	function remove_cat_base($link)
	{
		$category_base = get_option('category_base');
		
		// WP uses "category/" as the default
		if ($category_base == '') 
			$category_base = 'category';

		// Remove initial slash, if there is one (we remove the trailing slash in the regex replacement and don't want to end up short a slash)
		if (substr($category_base, 0, 1) == '/')
			$category_base = substr($category_base, 1);

		$category_base .= '/';

		return preg_replace('|' . $category_base . '|', '', $link, 1);
	}
	
	function sort_permalinks($a = null, $b = null)
	{
		return ( strlen($a) > strlen($b) ) ? -1 : 1;
	}
}

function init_non_prefixed_categories()
{
	global $non_prefixed_categories;
	if ( empty( $non_prefixed_categories ) ) 
		new NonPrefixedCategories;
}

function activate_non_prefixed_categories()
{
	global $non_prefixed_categories;
	if ( empty( $non_prefixed_categories ) ) 
		$non_prefixed_categories = new NonPrefixedCategories;
	$non_prefixed_categories->event_activate();

}

register_activation_hook(__FILE__, 'activate_non_prefixed_categories');
add_action('plugins_loaded', 'init_non_prefixed_categories');
