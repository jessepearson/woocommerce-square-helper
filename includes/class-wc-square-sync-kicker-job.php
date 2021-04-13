<?php
/**
 * Handles actually starting the job up again if it has stalled.
 *
 * Extends the Background_Job class from the Square extension and uses its code to actually process the jobs.
 * It had to be done this way since handle() cannot be called outside of the class itself.
 * Additionally, handle() is included here due to wp_die() at the end was causing the scheduled action to get stuck.
 *
 * @package WC_Square_Helper
 * @since   1.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Square_Sync_Kicker_Job' ) ) {

	class WC_Square_Sync_Kicker_Job extends \WooCommerce\Square\Handlers\Background_Job {

		/**
		 * Constructor.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		public function __construct() {
			parent::__construct();
			$this->handle();
		}

		/**
		 * Handles starting up the job process again. Taken from the Background_Job class.
		 *
		 * @since   1.0.6
		 * @version 1.0.6
		 */
		protected function handle() {

			if ( $this->is_process_running() ) {
				// background process already running
				return;
			}

			if ( $this->is_queue_empty() ) {
				// no data to process
				return;
			}

			$this->lock_process();

			// Get next job in the queue
			$job = $this->get_job();
			WC_Square_Helper::log( 'Processing job: '. print_r( $job, true ) );

			// handle PHP errors from here on out
			register_shutdown_function( [ $this, 'handle_shutdown' ], $job );

			// Start processing
			$this->process_job( $job );

			$this->unlock_process();

			// Start next job or complete process
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->complete();
			}

			//wp_die();
		}
	}
}
