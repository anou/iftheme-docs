<?php
/**
 * @package wpml-core
 * @subpackage wpml-core
 */
require_once WPML_TM_PATH . '/inc/translation-proxy/interfaces/TranslationProxy_Api_Interface.php';

require_once( 'translationproxy-api.class.php' );
require_once( 'translationproxy-service.class.php' );
require_once( 'translationproxy-batch.class.php' );

if ( !class_exists( 'TranslationProxy_Project' ) ) {
	class TranslationProxy_Project implements TranslationProxy_Project_Interface {

		public $id;
		public $access_key;
		public $ts_id;
		public $ts_access_key;

		/**
		 * @var TranslationProxy_Service
		 */
		public $service;
		public $errors;

		/**
		 * @param TranslationProxy_Service $service
		 * @param string                    $delivery
		 */
		public function __construct( $service, $delivery = 'xmlrpc' ) {
			$this->service = $service;
			$this->errors = array();

			$icl_translation_projects = TranslationProxy::get_translation_projects();
			$project_index            = self::generate_service_index( $service );
			if ( $project_index && $icl_translation_projects && isset( $icl_translation_projects [ $project_index ] ) ) {
				$project = $icl_translation_projects[ $project_index ];

				$this->id            = $project[ 'id' ];
				$this->access_key    = $project[ 'access_key' ];
				$this->ts_id         = $project[ 'ts_id' ];
				$this->ts_access_key = $project[ 'ts_access_key' ];

				$this->service->delivery_method = $delivery;
			}

		}

		/**
		 * Returns the index by which a translation service can be found in the array returned by
		 * \TranslationProxy::get_translation_projects
		 *
		 * @param $service object
		 *
		 * @return bool|string
		 */
		public static function generate_service_index( $service ) {
			$index = false;
			if ( $service ) {
				$service->custom_fields_data = isset( $service->custom_fields_data ) ? $service->custom_fields_data : array();
				if ( isset( $service->id ) ) {
					$index = md5( $service->id . serialize( $service->custom_fields_data ) );
				}
			}

			return $index;
		}

		/**
		 * Create and configure project (Translation Service)
		 *
		 * @param string $url
		 * @param string $name
		 * @param string $description
		 * @param int    $blog_id
		 * @param string $delivery
		 * @param string $affiliate_id
		 * @param string $affiliate_key
		 * @param bool   $dummy
		 *
		 * @return bool
		 * @throws TranslationProxy_Api_Error
		 * @throws Exception
		 */
		public function create( $url, $name, $description, $blog_id = 1, $delivery = 'xmlrpc', $affiliate_id = '', $affiliate_key = '', $dummy = false ) {
			$params = array(
				"batch_mode" => $this->service->batch_mode,
				'service'    => array( 'id' => $this->service->id ),
				'project'    => array(
					'name'            => $name,
					'description'     => $description,
					'url'             => $url,
					'delivery_method' => $delivery,
				),
				"custom_fields" => $this->service->custom_fields_data,
			);

			try {
				$response                       = TranslationProxy_Api::proxy_request( '/projects.json', $params, 'POST' );
				$this->id                       = $response->project->id;
				$this->access_key               = $response->project->accesskey;
				$this->ts_id                    = $response->project->ts_id;
				$this->ts_access_key            = $response->project->ts_accesskey;
				$this->service->delivery_method = $delivery;
			} catch (TranslationProxy_Api_Error $e) {
				$this->add_error($e);
				return false;
			} catch (Exception $e) {
				$this->add_error($e);
				return false;
			}

			return true;
		}

		/**
		 * Convert WPML language code to service language
		 *
		 * @param $language string
		 *
		 * @return bool|string
		 */
		protected function service_language( $language ) {
			return TranslationProxy_Service::get_language( $this->service, $language );
		}

		/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 * Get information about the project (Translation Service)
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		public function custom_text( $location, $locale = "en" ) {
			$response = '';
			if(!$this->ts_id || !$this->ts_access_key) return '';

			//Sending Translation Service (ts_) id and access_key, as we are talking directly to the Translation Service
			//Todo: use project->id and project->access_key once this call is moved to TP
			$params = array(
				'project_id' => $this->ts_id,
				'accesskey'  => $this->ts_access_key,
				'location'   => $location,
				'lc'         => $locale,
			);

			if ( $this->service->custom_text_url ) {
				$response = TranslationProxy_Api::service_request( $this->service->custom_text_url, $params, 'GET', false, true, true, true );
			}

			return $response;
		}

		function current_service_name(){

			return TranslationProxy::get_current_service_name();
		}

		public function test_affiliation( $key, $id ) {
			//Affiliation test not implemented on the new TP interface
			return true;
		}

		/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 * IFrames to display project info (Translation Service)
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
		public function select_translator_iframe_url( $source_language, $target_language ) {
			//Sending Translation Service (ts_) id and access_key, as we are talking directly to the Translation Service
			$params[ 'project_id' ]         = $this->ts_id;
			$params[ 'accesskey' ]          = $this->ts_access_key;
			$params[ 'source_language' ]    = $this->service_language( $source_language );
			$params[ 'target_language' ]    = $this->service_language( $target_language );
			$params[ 'compact' ]            = 1;

			return $this->_create_iframe_url( $this->service->select_translator_iframe_url, $params );
		}

		public function translator_contact_iframe_url( $translator_id ) {
			//Sending Translation Service (ts_) id and access_key, as we are talking directly to the Translation Service
			$params['project_id']       = $this->ts_id;
			$params['accesskey']        = $this->ts_access_key;
			$params['translator_id']    = $translator_id;
			$params[ 'compact' ]        = 1;
			if ( $this->service->translator_contact_iframe_url ) {
				return $this->_create_iframe_url( $this->service->translator_contact_iframe_url, $params );
			}

			return false;
		}

		protected function _create_iframe_url( $url, $params ) {
			try {
				if ( $params ) {
					$url = TranslationProxy_Api::add_parameters_to_url( $url, $params );
					$url .= '?' . http_build_query( $params );
				}

				return $url;
			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 * Jobs handling (Translation Proxy)
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		public function send_to_translation( $file, $title, $cms_id, $url, $source_language, $target_language, $word_count, $translator_id = 0, $note = '', $is_update = 0 ) {
			$params   = array(
				'project_id' => $this->id,
				'accesskey'  => $this->access_key,
				'job'        => array(
					'file'            => $file,
					'title'           => $title,
					'cms_id'          => $cms_id,
					'url'             => $url,
					'is_update'       => $is_update,
					'translator_id'   => $translator_id,
					'source_language' => $source_language,
					'target_language' => $target_language,
					'word_count'      => $word_count,
					'note'            => $note,
				)
			);
			try {
				$response = TranslationProxy_Api::proxy_request( '/jobs.json', $params, 'POST', true );

				return $response->job->id;
			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		/**
		 * @param bool $source_language
		 * @param bool $target_languages
		 *
		 * @internal param bool $name
		 * @return bool|TranslationProxy_Batch
		 */
		function get_batch_job( $source_language = false, $target_languages = false ) {
			$cache_key = md5(wp_json_encode(array($source_language, $target_languages)));
			$cache_group = 'get_batch_job';
			$cache_found = false;

			$batch_data = wp_cache_get($cache_key, $cache_group, false, $cache_found);

			if($cache_found) {
				return $batch_data;
			}

			try {
				$batch_data = TranslationProxy_Basket::get_batch_data();

				if ( !$batch_data ) {
					if ( !$source_language ) {
						$source_language = TranslationProxy_Basket::get_source_language();
					}
					if ( !$target_languages ) {
						$target_languages = TranslationProxy_Basket::get_remote_target_languages();
					}

					if ( !$source_language || !$target_languages ) {
						return false;
					}

					$batch_data = $this->create_batch_job( $source_language, $target_languages );
					if($batch_data) {
						TranslationProxy_Basket::set_batch_data( $batch_data );
					}
				}

				wp_cache_set($cache_key, $batch_data, $cache_group);
				return $batch_data;

			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		/**
		 * @param bool|string $source_language
		 * @param bool|array  $target_languages
		 *
		 * @return mixed
		 */
		function get_batch_job_id( $source_language = false, $target_languages = false ) {
			try {
				$batch_data = $this->get_batch_job();

				return $batch_data ? $batch_data->id : false;
			} catch ( Exception $ex ) {
				$this->add_error( $ex->getMessage() );

				return false;
			}
		}

		/**
		 * @param bool $source_language
		 * @param      $target_languages
		 *
		 * @internal param bool $name
		 * @return bool|TranslationProxy_Batch
		 */
		public function create_batch_job( $source_language, $target_languages ) {
			$batch_name = TranslationProxy_Basket::get_basket_name();

			$extra_fields = TranslationProxy_Basket::get_basket_extra_fields();

			if(!$source_language) {
				$source_language = TranslationProxy_Basket::get_source_language();
			}
			if(!$target_languages) {
				$target_languages = TranslationProxy_Basket::get_remote_target_languages();
			}

			if(!$source_language || !$target_languages) return false;

			if(!$batch_name) {
				$batch_name = sprintf(__('%s: WPML Translation Jobs', 'wpml-translation-management'), get_option( 'blogname' ) );
			}

			TranslationProxy_Basket::set_basket_name($batch_name);

			$params = array(
				"api_version" => TranslationProxy_Api::API_VERSION,
				"project_id"  => $this->id,
				"accesskey"   => $this->access_key,
				'batch' => array(
					'source_language' => $source_language,
					'target_languages' => $target_languages,
					'name' => $batch_name
				)
			);

			if ($extra_fields) {
				$params['extra_fields'] = $extra_fields;
			}

			try {
				$response = TranslationProxy_Api::proxy_request( '/projects/{project_id}/batches.json', $params, 'POST', false );

				$batch = false;
				if ( $response ) {
					$batch = $response->batch;
					TranslationProxy_Basket::set_batch_data( $batch );
				}

				return $batch;

			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		/**
		 *
		 * Add Files Batch Job
		 * @link http://git.icanlocalize.com/fotanus/translation_proxy/wikis/add_files_batch_job
		 *
		 * @param string $file
		 * @param string $title
		 * @param string $cms_id
		 * @param string $url
		 * @param string $source_language
		 * @param string $target_language
		 * @param int    $word_count
		 * @param int    $translator_id
		 * @param string $note
		 * @param int    $is_update
		 *
		 * @return bool
		 */
		public function send_to_translation_batch_mode( $file, $title, $cms_id, $url, $source_language, $target_language, $word_count, $translator_id = 0, $note = '', $is_update = 0 ) {

			$batch_id = $this->get_batch_job_id( $source_language, array( $target_language ) );

			if(!$batch_id) return false;

			$params   = array(
				'api_version' => TranslationProxy_Api::API_VERSION,
				'project_id'  => $this->id,
				'batch_id'    => $batch_id,
				'accesskey'   => $this->access_key,
				'job'         => array(
					'file'            => $file,
					'word_count'      => $word_count,
					'title'           => $title,
					'cms_id'          => $cms_id,
					'url'             => $url,
					'is_update'       => $is_update,
					'translator_id'   => $translator_id,
					'note'            => $note,
					'source_language' => $source_language,
					'target_language' => $target_language,
				)
			);

			try {
				$response = TranslationProxy_Api::proxy_request( '/batches/{batch_id}/jobs.json', $params, 'POST', true );

				if ( $response ) {
					return $response->job->id;
				}

				return false;

			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		/**
		 * @param bool|int $tp_batch_id
		 *
		 * @link http://git.icanlocalize.com/onthego/translation_proxy/wikis/commit_batch_job
		 *
		 * @return array|bool|mixed|null|stdClass|string
		 */
		function commit_batch_job( $tp_batch_id = false ) {
			$tp_batch_id = $tp_batch_id ? $tp_batch_id : $this->get_batch_job_id();

			if ( ! $tp_batch_id ) {
				return true;
			}

			$params = array(
				"api_version" => TranslationProxy_Api::API_VERSION,
				'project_id'  => $this->id,
				'accesskey'   => $this->access_key,
				'batch_id'    => $tp_batch_id,
			);

			try {
				$response = TranslationProxy_Api::proxy_request( '/batches/{batch_id}/commit.json', $params, 'PUT', false );
				$basket_name = TranslationProxy_Basket::get_basket_name();
				if ( $basket_name ) {
					global $wpdb;

					$batch_id_sql      = "SELECT id FROM {$wpdb->prefix}icl_translation_batches WHERE batch_name=%s";
					$batch_id_prepared = $wpdb->prepare( $batch_id_sql, array( $basket_name ) );
					$batch_id          = $wpdb->get_var( $batch_id_prepared );

					$batch_data = array(
						'batch_name'		=> $basket_name,
						'tp_id'				=> $tp_batch_id,
						'last_update'		=> date('Y-m-d H:i:s'),
					);
					if ( isset($response) && $response ) {
						$batch_data[ 'ts_url' ] = serialize( $response );
					}

					if ( !$batch_id ) {
						$wpdb->insert( $wpdb->prefix . 'icl_translation_batches', $batch_data );
					} else {
						$wpdb->update( $wpdb->prefix . 'icl_translation_batches', $batch_data, array('id' => $batch_id) );
					}
				}

				return isset($response) ? $response : false;
			} catch ( Exception $ex ) {
				$this->add_error( $ex->getMessage() );

				return false;
			}
		}

		public function jobs() {
			return $this->get_jobs( 'any' );
		}

		public function canceled_jobs() {
			return $this->get_jobs( 'canceled' );
		}

		public function pending_jobs() {
			return $this->get_jobs( 'translation_ready' );
		}

		public function cancelled_jobs() {
			return $this->get_jobs( 'cancelled' );
		}

		public function set_delivery_method( $method ) {
			$params = array(
				'project_id' => $this->id,
				'accesskey'  => $this->access_key,
				'project'    => array( 'delivery_method' => $method ),
			);
			try {
				TranslationProxy_Api::proxy_request( '/projects.json', $params, 'put' );

				return true;
			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		public static function test_xmlrpc( $url ) {
			$params   = array( 'url' => $url );
			$response = TranslationProxy_Api::proxy_request( '/projects/test_xmlrpc.json', $params );

			return $response->support;
		}

		public function fetch_translation( $job_id ) {
			$params = array(
				'project_id' => $this->id,
				'accesskey'  => $this->access_key,
				'job_id'  => $job_id,
			);
			try {
				return TranslationProxy_Api::proxy_download( "/jobs/{job_id}/xliff.json", $params);
			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		public function update_job( $job_id, $url = null, $state = 'delivered' ) {
			$params = array(
				'job_id' => $job_id,
				'project_id' => $this->id,
				'accesskey'  => $this->access_key,
				'job'        => array(
					'state' => $state
				)
			);
			if ( $url ) {
				$params[ 'job' ][ 'url' ] = $url;
			}
			try {
				TranslationProxy_Api::proxy_request( "/jobs/{job_id}.json", $params, 'PUT' );

				return true;
			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}

		protected function add_error($error) {
			$this->errors[] = $error;
		}

		protected function get_jobs( $state = 'any' ) {
			$batch =  TranslationProxy_Basket::get_batch_data();

			$params = array(
				'project_id' => $this->id,
				'accesskey'  => $this->access_key,
				'state'      => $state,
			);

			if($batch) {
				$params['batch_id'] = $batch ? $batch->id : false;
				return TranslationProxy_Api::proxy_request( '/batches/{batch_id}/jobs.json', $params );
			} else {
				//FIXME: remove this once TP will accept the TP Project ID: https://icanlocalize.basecamphq.com/projects/11113143-translation-proxy/todo_items/182251206/comments
				$params['project_id'] = $this->id;
			}
			try {
				return TranslationProxy_Api::proxy_request( '/jobs.json', $params );
			} catch ( Exception $ex ) {
				$this->add_error($ex->getMessage());

				return false;
			}
		}
	}
}
