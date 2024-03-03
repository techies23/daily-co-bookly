<?php

/**
 * Class for generating FAKE page and displaying accordingly into frontend.
 *
 * Class Daily_Co_Bookly_Fake_Page
 */
class Daily_Co_Bookly_Fake_Page {

	private $version = DPEN_DAILY_CO_PLUGIN_VERSION;

	public function __construct() {
		add_filter( 'the_posts', array( $this, 'fake_pages' ), 1 );
		add_filter( 'template_include', array( $this, 'page_template_manipulate' ), 1 );

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
	}

	/**
	 * Enqueue public scripts
	 */
	public function scripts() {
		wp_register_script( 'daily-api-script', 'https://unpkg.com/@daily-co/daily-js', array(), $this->version, true );
		wp_register_script( 'daily-api-network-statistics', DPEN_DAILY_CO_URI_PATH . 'assets/js/network-statistics.js', array( 'daily-api-script' ), $this->version, true );
		wp_enqueue_style( 'daily-api', DPEN_DAILY_CO_URI_PATH . 'assets/css/daily-api.css', false, $this->version );
	}

	/**
	 * Internally registers pages we want to fake. Array key is the slug under which it is being available from the frontend
	 *
	 * @return mixed
	 * @author Deepen Bajracharya
	 * @since 1.0.0
	 */
	private static function get_fake_pages() {
		//http://example.com/room/join
		$fake_pages['room/join'] = array(
			'title'   => 'Join Room',
			'content' => 'This is a content of fake page 1'
		);
		//http://example.com/room/start
		$fake_pages['room/start'] = array(
			'title'   => 'Start Room',
			'content' => 'This is a content of fake page 2'
		);

		return $fake_pages;
	}

	/**
	 * Fakes get posts result
	 *
	 * @param $posts
	 *
	 * @return array|null
	 * @author Deepen Bajracharya
	 *
	 * @since 1.0.0
	 */
	public function fake_pages( $posts ) {
		global $wp, $wp_query;
		$fake_pages       = self::get_fake_pages();
		$fake_pages_slugs = array();
		foreach ( $fake_pages as $slug => $fp ) {
			$fake_pages_slugs[] = $slug;
		}

		if ( true === in_array( strtolower( $wp->request ), $fake_pages_slugs ) || ( true === isset( $wp->query_vars['page_id'] ) && true === in_array( strtolower( $wp->query_vars['page_id'] ), $fake_pages_slugs ) ) ) {
			if ( true === in_array( strtolower( $wp->request ), $fake_pages_slugs ) ) {
				$fake_page = strtolower( $wp->request );
			} else {
				$fake_page = strtolower( $wp->query_vars['page_id'] );
			}

			$posts                   = null;
			$posts[]                 = self::create_fake_page( $fake_page, $fake_pages[ $fake_page ] );
			$wp_query->is_page       = true;
			$wp_query->is_singular   = false;
			$wp_query->is_single     = false;
			$wp_query->is_home       = false;
			$wp_query->is_archive    = false;
			$wp_query->is_category   = false;
			$wp_query->is_fake_page  = true;
			$wp_query->is_attachment = false;
			$wp_query->fake_page     = $wp->request;

			//Longer permalink structures may not match the fake post slug and cause a 404 error so we catch the error here
			unset( $wp_query->query["error"] );
			$wp_query->query_vars["error"]     = "";
			$wp_query->is_404                  = false;
			$wp_query->is_paged                = false;
			$wp_query->is_admin                = false;
			$wp_query->is_preview              = false;
			$wp_query->is_robots               = false;
			$wp_query->is_posts_page           = false;
			$wp_query->is_post_type_archive    = false;
			$wp_query->query_vars["post_type"] = "page";
		}

		return $posts;
	}

	/**
	 * Creates virtual fake page
	 *
	 * @param $pagename
	 * @param $page
	 *
	 * @return stdClass
	 * @author Deepen Bajracharya
	 *
	 * @since 1.0.0
	 */
	private static function create_fake_page( $pagename, $page ) {
		$post                 = new stdClass;
		$post->post_author    = 1;
		$post->post_name      = $pagename;
		$post->guid           = get_bloginfo( 'wpurl' ) . '/' . $pagename;
		$post->post_title     = $page['title'];
		$post->post_content   = $page['content'];
		$post->ID             = - 1;
		$post->post_status    = 'static';
		$post->comment_status = 'closed';
		$post->ping_status    = 'closed';
		$post->comment_count  = 0;
		$post->post_date      = current_time( 'mysql' );
		$post->post_date_gmt  = current_time( 'mysql', 1 );
		$post->post_type      = 'page';
		$post->filter         = 'raw';

		return $post;
	}

	/**
	 * Change template destination to custom defined inside plugin
	 *
	 * @param $single_template
	 *
	 * @return string
	 * @since 1.0.0
	 *
	 * @author Deepen Bajracharya
	 */
	public function page_template_manipulate( $single_template ) {
		global $post;

		if ( ! empty( $post ) ) {
			if ( $post->post_name == 'room/join' ) {
				wp_enqueue_script( 'daily-api-script', 'https://unpkg.com/@daily-co/daily-js', array( 'jquery' ), $this->version, true );
				$room = isset( $_GET['j'] ) ? $_GET['j'] : false;
				if ( ! empty( $room ) && ! is_user_logged_in() ) {
					wp_safe_redirect( wp_login_url( $post->guid . '?j=' . $room ) );
					exit;
				}

				$single_template = DPEN_DAILY_CO_DIR_PATH . 'templates/template-join-room.php';
			}

			if ( $post->post_name == 'room/start' ) {
				wp_enqueue_script( 'daily-api-script', 'https://unpkg.com/@daily-co/daily-js', array( 'jquery' ), $this->version, true );
				$room = isset( $_GET['s'] ) ? $_GET['s'] : false;
				if ( ! empty( $room ) && ! is_user_logged_in() ) {
					wp_safe_redirect( wp_login_url( $post->guid . '?s=' . $room ) );
					exit;
				}

				$single_template = DPEN_DAILY_CO_DIR_PATH . 'templates/template-start-room.php';
			}
		}

		return $single_template;
	}
}

new Daily_Co_Bookly_Fake_Page();