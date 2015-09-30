<?php

abstract class WPML_TM_AJAX_Factory {
	protected $ajax_actions;

	protected function init() {
		$this->add_ajax_actions();
	}

	protected final function add_ajax_action( $handle, $callback ) {
		$this->ajax_actions[ $handle ] = $callback;
	}

	private final function add_ajax_actions() {
		if ( ! $this->is_cronjob() ) {
			foreach ( $this->ajax_actions as $handle => $callback ) {
				if ( stripos( $handle, 'wp_ajax_' ) !== 0 ) {
					$handle = 'wp_ajax_' . $handle;
				}
				add_action( $handle, $callback );
				if ( $this->is_back_end() && $this->is_jobs_tab() && $this->ajax_actions ) {
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_resources' ) );
				}
			}
		}
	}

	private final function is_back_end() {
		return is_admin() && ! $this->is_ajax() && ! $this->is_cronjob();
	}

	private final function is_jobs_tab() {
		return $this->is_tm_page( 'jobs' );
	}

	public abstract function enqueue_resources( $hook_suffix );

	private final function is_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	protected final function is_cronjob() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}

	private final function is_tm_page( $tab = null ) {
		$result = is_admin()
		          && isset( $_GET[ 'page' ] )
		          && $_GET[ 'page' ] == WPML_TM_FOLDER . '/menu/main.php';

		if ( $tab ) {
			$result = $result && isset( $_GET[ 'sm' ] ) && $_GET[ 'sm' ] == $tab;
		}

		return $result;
	}

}