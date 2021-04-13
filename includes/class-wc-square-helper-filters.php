<?php
/**
 * Handles getting around the loopback process built into Square to get syncing to complete.
 *
 * @package WC_Square_Helper
 * @since   1.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Square_Helper_Filters' ) ) {
	
	/**
	 * WC_Square_Helper_Filters class.
	 *
	 * @package WC_Square_Helper_Filters
	 * @since   1.0.6
	 * @version 1.0.6
	 */
	class WC_Square_Helper_Filters {

		/**
		 * Constructor.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function __construct() {
			add_filter( 'wc_square_helper_form_sections', [ $this, 'add_form_section' ] );
			add_action( 'init', [ $this, 'add_filters' ] );
		}

		/**
		 * Adds filters for limits.
		 *
		 * @since 1.0.3
		 * @version 1.0.6
		 */
		public function add_filters() {

			add_filter( 'wc_square_sync_max_objects_to_retrieve', function() {
				return get_option( 'wcsh_max_objects_to_retrieve', 300 );
			});

			// Commented out this and other references to this filter due to https://github.com/woocommerce/woocommerce-square/issues/566
			// add_filter( 'wc_square_sync_max_objects_per_batch', function() {
			// 	return get_option( 'wcsh_max_objects_per_batch', 1000 );
			// });

			add_filter( 'wc_square_sync_max_objects_per_upsert', function() {
				return get_option( 'wcsh_max_objects_per_upsert', 5000 );
			});

			add_filter( 'wc_square_sync_max_objects_total', function() {
				return get_option( 'wcsh_max_objects_total', 600 );
			});

			add_filter( 'wc_square_import_api_limit', function() {
				return get_option( 'wcsh_import_api_limit', 100 );
			});

			$default_time_limit = $this->get_default_time_limit();

			add_filter( 'wc_square_background_sync_default_time_limit', function () use ( $default_time_limit ) {
				return get_option( 'wcsh_default_time_limit', $default_time_limit );
			}, 99 );

			add_filter( 'wc_square_background_sync_queue_lock_time', function () use ( $default_time_limit ) {
				return get_option( 'wcsh_queue_lock_time', $default_time_limit + 10 );
			}, 99 );
		}

		/**
		 * Add the form section to the tools page.
         * 
         * @param array Current form sections.
         * @return array Updated form sections.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function add_form_section( $form_sections ) {

			$wcsh_max_objects_to_retrieve = get_option( 'wcsh_max_objects_to_retrieve', 300 );
			// $wcsh_max_objects_per_batch   = get_option( 'wcsh_max_objects_per_batch', 1000 );
			$wcsh_max_objects_per_upsert  = get_option( 'wcsh_max_objects_per_upsert', 5000 );
			$wcsh_max_objects_total       = get_option( 'wcsh_max_objects_total', 600 );
			$wcsh_import_api_limit        = get_option( 'wcsh_import_api_limit', 100 );

			$default_time_limit = $this->get_default_time_limit();

			$wcsh_default_time_limit = get_option( 'wcsh_default_time_limit', $default_time_limit );
			$wcsh_queue_lock_time    = get_option( 'wcsh_queue_lock_time', $default_time_limit + 10 );

			$section = [
				'name'    => 'sync_limits',
				'heading' => 'Adjust Sync Limits',
				'button'  => 'Adjust Sync Limits',
			];

			$section['content'] = '
				<tr>
					<td>
						<label>Max single sync: <input type="number" name="wcsh_max_objects_to_retrieve" id="wcsh_max_objects_to_retrieve" min="1" value="'. $wcsh_max_objects_to_retrieve .'" /></label>
						Default 300. The maximum number of objects to retrieve in a single sync job.
					</td>
				</tr>
				<!--<tr>-->
				<!--    <td>-->
				<!--        <label>Max per batch: <input type="number" name="wcsh_max_objects_per_batch" id="wcsh_max_objects_per_batch" min="1" value="$wcsh_max_objects_per_batch" /></label>-->
				<!--        Default 1000. The maximum number of objects per batch in a single sync job.-->
				<!--    </td>-->
				<!--</tr>-->
				<tr>
					<td>
						<label>Max per upsert: <input type="number" name="wcsh_max_objects_per_upsert" id="wcsh_max_objects_per_upsert" min="1" value="'. $wcsh_max_objects_per_upsert .'" /></label>
						Default 5000. The maximum number of objects per upsert in a single request.
					</td>
				</tr>
				<tr>
					<td>
						<label>Max objects total: <input type="number" name="wcsh_max_objects_total" id="wcsh_max_objects_total" min="1" value="'. $wcsh_max_objects_total .'" /></label>
						Default 600. The maximum number of objects allowed in a single sync job.
					</td>
				</tr>
				<tr>
					<td>
						<label>Max products per batch: <input type="number" name="wcsh_import_api_limit" id="wcsh_import_api_limit" min="1" value="'. $wcsh_import_api_limit .'" /></label>
						Default 100. The maximum number of products per batch.
					</td>
				</tr>
				<tr>
					<td>
						Default time limit: <input type="number" name="wcsh_default_time_limit" id="wcsh_default_time_limit" min="10" value="'. $wcsh_default_time_limit .'" /></label>
						Defaults to 10 seconds less than the max_execution_time set for the server, or 20 if max_execution_time is not set. The maximum amount of seconds allowed in a single sync job.
					</td>
				</tr>
				<tr>
					<td>
						<label>Queue lock time: <input type="number" name="wcsh_queue_lock_time" id="wcsh_queue_lock_time" min="20" value="'. $wcsh_queue_lock_time .'" /></label>
						Defaults to 10 seconds more than the Default time limit.
					</td>
				</tr>';
			
			$form_sections[] = $section;
			return $form_sections;
		}

		/**
		 * Gets the default time limit available for a Square sync batch.
		 *
		 * @return int The number of seconds to use.
		 *
		 * @since 1.0.5
		 * @version 1.0.6
		 */
		public function get_default_time_limit() {
			/**
			 * This chunk of code is from here:
			 * https://github.com/woocommerce/woocommerce-square/blob/2.3.4/includes/Handlers/Background_Job.php#L304-L315
			 * The default time limit of 20 is set in the SV framework, which is brought in on build, so linking to it isn't as easy.
			 * plugins/woocommerce-square/vendor/skyverge/wc-plugin-framework/woocommerce/utilities/class-sv-wp-background-job-handler.php
			 * in the time_exceeded method.
			 */
			$default_time_limit = 20;
			$server_time_limit  = (int) ini_get( 'max_execution_time' );
			$time_limit_buffer  = 10;

			if ( isset( $server_time_limit ) && $time_limit_buffer < $server_time_limit ) {
				$default_time_limit = $server_time_limit - $time_limit_buffer;
			}

			return $default_time_limit;
		}

		/**
		 * Updates the sync limits submitted on the form.
		 *
		 * @since 1.0.3
		 * @version 1.0.6
		 */
		public function update_sync_limits() {
			WC_Square_Helper::log( 'Begin updating sync limits.' );
			$default_time_limit = $this->get_default_time_limit();

			// Get the current settings from the database.
			$current['max_objects_to_retrieve'] = get_option( 'wcsh_max_objects_to_retrieve', false );
			// $current['max_objects_per_batch']   = get_option( 'wcsh_max_objects_per_batch', false );
			$current['max_objects_per_upsert']  = get_option( 'wcsh_max_objects_per_upsert', false );
			$current['max_objects_total']       = get_option( 'wcsh_max_objects_total', false );
			$current['import_api_limit']        = get_option( 'wcsh_import_api_limit', false );
			$current['default_time_limit']      = get_option( 'wcsh_default_time_limit' );
			$current['queue_lock_time']         = get_option( 'wcsh_queue_lock_time' );

			// Get the new settings from $_POST, with defaults.
			$new['max_objects_to_retrieve'] = ( isset( $_POST['wcsh_max_objects_to_retrieve'] ) ) ? (int) $_POST['wcsh_max_objects_to_retrieve'] : 300;
			// $new['max_objects_per_batch']   = ( isset( $_POST['wcsh_max_objects_per_batch'] ) ) ? (int) $_POST['wcsh_max_objects_per_batch'] : 1000;
			$new['max_objects_per_upsert']  = ( isset( $_POST['wcsh_max_objects_per_upsert'] ) ) ? (int) $_POST['wcsh_max_objects_per_upsert'] : 5000;
			$new['max_objects_total']       = ( isset( $_POST['wcsh_max_objects_total'] ) ) ? (int) $_POST['wcsh_max_objects_total'] : 600;
			$new['import_api_limit']        = ( isset( $_POST['wcsh_import_api_limit'] ) ) ? (int) $_POST['wcsh_import_api_limit'] : 600;
			$new['default_time_limit']      = ( isset( $_POST['wcsh_default_time_limit'] ) ) ? (int) $_POST['wcsh_default_time_limit'] : $default_time_limit;
			$new['queue_lock_time']         = ( isset( $_POST['wcsh_queue_lock_time'] ) ) ? (int) $_POST['wcsh_queue_lock_time'] : $default_time_limit + 10;

			// We need to make sure these will work.
			// $new['default_time_limit'] should not be greater than the actual default time limit, due to that's just going to fail.
			// $new['queue_lock_time'] needs to be greater than $new['default_time_limit'], or that will fail.
			$new['default_time_limit'] = ( $new['default_time_limit'] < $default_time_limit ) ? $new['default_time_limit'] : $default_time_limit;
			$new['queue_lock_time'] = ( $new['queue_lock_time'] > $new['default_time_limit'] ) ? $new['queue_lock_time'] : $new['default_time_limit'] + 10;

			// Go through each and log.
			foreach ( $current as $key => $value ) {
				WC_Square_Helper::log( 'Option: '. $key .' | Current value: '. $value .' | New value: '. $new[ $key ] );
			}

			// Now update them all.
			foreach ( $new as $key => $value ) {
				update_option( 'wcsh_'. $key, $value );
			}

			WC_Square_Helper::log( 'Done updating sync limits.' );
			//$this->print_notice( 'Sync limits updated.' );
			return 'Sync limits updated.';
		}
	}
	// new WC_Square_Helper_Filters();
}
