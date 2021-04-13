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

if ( ! class_exists( 'WC_Square_Sync_Kicker' ) ) {
	/**
	 * WC_Square_Sync_Kicker class.
	 *
	 * @package WC_Square_Sync_Kicker
	 * @since   1.0.6
	 * @version 1.0.6
	 */
	class WC_Square_Sync_Kicker {

		/**
		 * Is the kicker enabled?
		 * 
		 * @since 1.0.6
		 */
		public $is_enabled = false;

		/**
		 * Constructor.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function __construct() {
			add_action( 'init', [ $this, 'init' ], 50 );
			add_action( 'wc-square-sync-kicker', [ $this, 'maybe_kick_sync' ] );
			add_filter( 'wc_square_helper_form_sections', [ $this, 'add_form_section' ] );
		}

		/**
		 * Init.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function init() {
			$this->is_enabled = 'on' == get_option( 'wcsh_sync_kicker', 'off' ) ? true : false;
			$this->maybe_add_scheduled_action();

			if ( class_exists( 'WooCommerce_Square_Loader' ) ) {
				$this->includes();
			}
		}

		/**
		 * File includes.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function includes() {
			require_once( 'class-wc-square-sync-kicker-job.php' );
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

			$wcsh_sync_kicker = get_option( 'wcsh_sync_kicker', 'off' );

			$section = [
				'name'    => 'sync_kicker',
				'heading' => 'Sync Kicker',
				'button'  => 'Update Sync Kicker',
			];

			$section['content'] = '
				<tr>
					<td>
						<label>Sync Kicker: <input type="checkbox" name="wcsh_sync_kicker" id="wcsh_sync_kicker" min="1" '. checked( $wcsh_sync_kicker, 'on', false ) .'" /></label>
						The sync kicker gets around the background processing limitations, enable this if the sync gets stuck and does not process.
					</td>
				</tr>';
			
			$form_sections[] = $section;
			return $form_sections;
		}

		/**
		 * Turns the kicker on or off.
		 *
		 * @since 1.0.6
		 * @version 1.0.6
		 */
		public function enable_disable_kicker() {
			// Get the current settings from the database.
			$current['sync_kicker'] = get_option( 'wcsh_sync_kicker', 'off' );

			// Get the new settings from $_POST, with defaults.
			$new['sync_kicker'] = ( isset( $_POST['wcsh_sync_kicker'] ) ) ? $_POST['wcsh_sync_kicker'] : 'off';

			// Go through each and log.
			foreach ( $current as $key => $value ) {
				WC_Square_Helper::log( 'Option: '. $key .' | Current value: '. $value .' | New value: '. $new[ $key ] );
			}

			// Now update them all.
			foreach ( $new as $key => $value ) {
				update_option( 'wcsh_'. $key, $value );
			}

			return 'Kicker settings updated.';
		}

		/**
		 * Will add the scheduled action, if needed.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function maybe_add_scheduled_action() {
			// Get the option from the database, set our hook and group.
			$enabled = get_option( 'square_helper_fix_stalled_syncs', false );
			$hook    = 'wc-square-sync-kicker';
			$group   = 'wc-square-sync-kicker';

			//Check for current pending scheduled actions.
			$actions = as_get_scheduled_actions( [
				'hook'   => $hook,
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'group'  => $group,
			]);

			// If Square is not active, or if this is not enabled, we need to remove scheduled actions.
			if ( ! class_exists( 'WooCommerce_Square_Loader' ) || ! $this->is_enabled ) {
				if ( count( $actions ) >= 1 ) {
					as_unschedule_all_actions( $hook );
					WC_Square_Helper::log( 'Square not active, or kicker disabled, all scheduled actions removed.' );
				}
				return;
			}

			// If we somehow have more than 1 remove them all.
			if ( count( $actions ) > 1  ) {
				as_unschedule_all_actions( $hook );
				WC_Square_Helper::log( 'More than one scheduled action found. all scheduled actions removed.' );
			}

			// If there are none set, add one.
			if ( ! as_next_scheduled_action( $hook ) ) {
				as_unschedule_all_actions( $hook );
				as_schedule_recurring_action( time(), 120, $hook, [], $group );
				WC_Square_Helper::log( 'Scheduled action added.' );
			}
		}

		/**
		 * Checks current jobs against past ones, and if they match, it kicks of the next batch.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function maybe_kick_sync() {

			// Get the global db var and get the job from the db.
			global $wpdb;
			$current_jobs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}options WHERE option_name LIKE 'wc_square_background_sync_job_%'" );

			// Exit if there are no current jobs.
			if ( 0 == count( $current_jobs ) ) {
				return;
			}

			// Go through and set the job id as the key in a new array.
			foreach ( $current_jobs as $job ) {
				$new_jobs[ $job->option_id ] = $job; 
			}
			
			// Get our saved jobs from the db.
			$saved_jobs = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}options WHERE option_name LIKE 'wc_square_sync_kicker_jobs'" );
			$saved_jobs = unserialize( $saved_jobs->option_value );

			// Go through each saved job to see if we need to kick the jobs.
			$needs_kick = false;
			foreach ( $saved_jobs as $job ) {

				// See if the saved job exists in the new jobs, and if the job matches exactly (meaning it hasn't updated).
				if ( isset( $new_jobs[ $job->option_id ] ) && $new_jobs[ $job->option_id ]->option_value === $job->option_value ) {
					
					// Make sure the status is queued or processing, others don't matter.
					$job_data = json_decode( $job->option_value );
					if ( 'queued' === $job_data->status || 'processing' === $job_data->status ) {
						
						$needs_kick = true;
						break;
					}
				}
			}

			/**
			 * Square puts a transient in place when a process starts. That transient should last for 60 seconds, but 
			 * sometimes it lingers. Here we check to see if that transient is over 2 minutes old, and if so, we continue.
			 */
			if ( $needs_kick ) {
				$process_lock = get_transient( 'wc_square_background_sync_process_lock' );

				if ( $process_lock ) {
					$process_lock = explode( ' ', $process_lock );
					if ( ( time() - $process_lock[1] ) > 120 ) {
						delete_transient( 'wc_square_background_sync_process_lock' );
					} else {
						$needs_kick = false;
					}
				}
			}

			// Kick it to get it going again, or update the saved jobs in the db.
			if ( $needs_kick ) {
				WC_Square_Helper::log( 'Kicking these current jobs: ' . print_r( $current_jobs, true ) );
				$kicker = new WC_Square_Sync_Kicker_Job();
			} else {
				//update_option( 'wc_square_sync_kicker_jobs', $current_jobs );
				$wpdb->replace( 
					"{$wpdb->prefix}options", [ 
						'option_name'  => 'wc_square_sync_kicker_jobs',
						'option_value' => serialize( $current_jobs ),
						'autoload'     => 'no',
					]
				);
			}

			return;
		}
	}
	//new WC_Square_Sync_Kicker();
}
