<?php
/*
Plugin Name: Simple URLs（短链接）
Plugin URI: http://www.studiopress.com/plugins/simple-urls

Description: Simple URLs is a complete URL management system that allows you create, manage, and track outbound links from your site by using custom post types and 301 redirects.（短链接是一个完整的链接管理器，它使用独立的类别来进行301跳转）汉化版本更新：https://ixh.me/

Author: Nathan Rice
Author URI: http://www.nathanrice.net/

Version: 0.9.6

Text Domain: simple-urls
Domain Path: /languages

License: GNU General Public License v2.0 (or later)
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

class SimpleURLs {

	// Constructor
	function __construct() {

		//register_activation_hook( __FILE__, 'flush_rewrite_rules' );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'columns_data' ) );
		add_filter( 'manage_edit-surl_columns', array( $this, 'columns_filter' ) );

		add_filter( 'post_updated_messages', array( $this, 'updated_message' ) );

		add_action( 'admin_menu', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'meta_box_save' ), 1, 2 );
		add_action( 'template_redirect', array( $this, 'count_and_redirect' ) );

	}

	function load_textdomain() {
		load_plugin_textdomain( 'simple-urls', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	function register_post_type() {

		$slug = 'surl';

		$labels = array(
			'name'               => __( '短链接', 'simple-urls' ),
			'singular_name'      => __( '链接', 'simple-urls' ),
			'add_new'            => __( '添加', 'simple-urls' ),
			'add_new_item'       => __( '添加新的短链接', 'simple-urls' ),
			'edit'               => __( '编辑', 'simple-urls' ),
			'edit_item'          => __( '编辑短链接', 'simple-urls' ),
			'new_item'           => __( '新的短链接', 'simple-urls' ),
			'view'               => __( '打开这个链接', 'simple-urls' ),
			'view_item'          => __( '打开这个链接', 'simple-urls' ),
			'search_items'       => __( '搜索短链接', 'simple-urls' ),
			'not_found'          => __( '还没有短链接哦', 'simple-urls' ),
			'not_found_in_trash' => __( '别找啦，垃圾桶里面没有东西', 'simple-urls' ),
			'messages'           => array(
				 0 => '', // 未使用，消息文本从1开始
				 1 => __( '链接更新成功。 <a href="%s">点击访问</a>', 'simple-urls' ),
				 2 => __( '已更新。', 'simple-urls' ),
				 3 => __( '已删除。', 'simple-urls' ),
				 4 => __( '链接已更新', 'simple-urls' ),
				/* translators: %s: date and time of the revision */
				 5 => isset( $_GET['revision'] ) ? sprintf( __( '设定从 %s 恢复', 'simple-urls' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				 6 => __( '链接已更改。 <a href="%s">访问链接</a>', 'simple-urls' ),
				 7 => __( '链接已保存。', 'simple-urls' ),
				 8 => __( '链接已提交。', 'simple-urls' ),
				 9 => __( '链接已计划发布。', 'simple-urls' ),
				10 => __( '链接草稿已保存。', 'simple-urls' ),
			),
		);

		$labels = apply_filters( 'simple_urls_cpt_labels', $labels );

		$rewrite_slug = apply_filters( 'simple_urls_slug', 'go' );

		register_post_type( $slug,
			array(
				'labels'        => $labels,
				'public'        => true,
				'query_var'     => true,
				'menu_position' => 20,
				'supports'      => array( 'title' ),
				'rewrite'       => array( 'slug' => $rewrite_slug, 'with_front' => false ),
			)
		);

	}

	function columns_filter( $columns ) {

		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'title'     => __( '标题', 'simple-urls' ),
			'url'       => __( '重定向到', 'simple-urls' ),
			'permalink' => __( '短链接', 'simple-urls' ),
			'clicks'    => __( '点击数', 'simple-urls' ),
		);

		return $columns;

	}

	function columns_data( $column ) {

		global $post;

		$url   = get_post_meta( $post->ID, '_surl_redirect', true );
		$count = get_post_meta( $post->ID, '_surl_count', true );

		if ( 'url' == $column ) {
			echo make_clickable( esc_url( $url ? $url : '' ) );
		}
		elseif ( 'permalink' == $column ) {
			echo make_clickable( get_permalink() );
		}
		elseif ( 'clicks' == $column ) {
			echo esc_html( $count ? $count : 0 );
		}

	}

	function updated_message( $messages ) {

		$surl_object = get_post_type_object( 'surl' );
		$messages['surl'] = $surl_object->labels->messages;

		if ( $permalink = get_permalink() ) {
			foreach ( $messages['surl'] as $id => $message ) {
				$messages['surl'][ $id ] = sprintf( $message, $permalink );
			}
		}

		return $messages;

	}

	function add_meta_box() {
		add_meta_box( 'surl', __( '链接信息', 'simple-urls' ), array( $this, 'meta_box' ), 'surl', 'normal', 'high' );
	}

	function meta_box() {

		global $post;

		printf( '<input type="hidden" name="_surl_nonce" value="%s" />', wp_create_nonce( plugin_basename(__FILE__) ) );

		printf( '<p><label for="%s">%s</label></p>', '_surl_redirect', __( '重定向到', 'simple-urls' ) );
		printf( '<p><input style="%s" type="text" name="%s" id="%s" value="%s" /></p>', 'width: 99%;', '_surl_redirect', '_surl_redirect', esc_attr( get_post_meta( $post->ID, '_surl_redirect', true ) ) );
		printf( '<p><span class="description">%s</span></p>', __( '当访问短链接时，插件会将请求重定向到这个链接。', 'simple-urls' ) );

		$count = isset( $post->ID ) ? get_post_meta($post->ID, '_surl_count', true) : 0;
		echo '<p>' . sprintf( __( '这个链接被访问了 %d 次', 'simple-urls' ), esc_attr( $count ) ) . '</p>';

	}

	function meta_box_save( $post_id, $post ) {

		$key = '_surl_redirect';

		//	verify the nonce
		if ( ! isset( $_POST['_surl_nonce'] ) || ! wp_verify_nonce( $_POST['_surl_nonce'], plugin_basename( __FILE__ ) ) ) {
			return;
		}

		//	don't try to save the data under autosave, ajax, or future post.
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( defined('DOING_AJAX') && DOING_AJAX ) return;
		if ( defined('DOING_CRON') && DOING_CRON ) return;

		//	is the user allowed to edit the URL?
		if ( ! current_user_can( 'edit_posts' ) || 'surl' != $post->post_type )
			return;

		$value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';

		if ( $value ) {
			//	save/update
			update_post_meta( $post->ID, $key, $value );
		} else {
			//	delete if blank
			delete_post_meta( $post->ID, $key );
		}

	}


	function count_and_redirect() {

		if ( ! is_singular( 'surl' ) ) {
			return;
		}

		global $wp_query;

		// Update the count
		$count = isset( $wp_query->post->ID ) ? get_post_meta( $wp_query->post->ID, '_surl_count', true ) : 0;
		update_post_meta( $wp_query->post->ID, '_surl_count', $count + 1 );

		// Handle the redirect
		$redirect = isset( $wp_query->post->ID ) ? get_post_meta( $wp_query->post->ID, '_surl_redirect', true ) : '';

		/**
		 * Filter the redirect URL.
		 *
		 * @since 0.9.5
		 *
		 * @param string  $redirect The URL to redirect to.
		 * @param int  $var The current click count.
		 */
		$redirect = apply_filters( 'simple_urls_redirect_url', $redirect, $count );

		/**
		 * Action hook that fires before the redirect.
		 *
		 * @since 0.9.5
		 *
		 * @param string  $redirect The URL to redirect to.
		 * @param int  $var The current click count.
		 */
		do_action( 'simple_urls_redirect', $redirect, $count );

		if ( ! empty( $redirect ) ) {
			wp_redirect( esc_url_raw( $redirect ), 301);
			exit;
		}
		else {
			wp_redirect( home_url(), 302 );
			exit;
		}

	}

}

new SimpleURLs;
