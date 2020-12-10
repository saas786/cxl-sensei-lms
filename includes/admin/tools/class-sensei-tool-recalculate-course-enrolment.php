<?php
/**
 * File containing Sensei_Tool_Recalculate_Course_Enrolment class.
 *
 * @package sensei-lms
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sensei_Tool_Recalculate_Course_Enrolment class.
 *
 * @since 3.7.0
 */
class Sensei_Tool_Recalculate_Course_Enrolment implements Sensei_Tool_Interface {
	const NONCE_ACTION = 'recalculate-course-enrolment';

	/**
	 * Get the ID of the tool.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'recalculate-course-enrolment';
	}

	/**
	 * Get the name of the tool.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Recalculate Course Enrollment', 'sensei-lms' );
	}

	/**
	 * Get the description of the tool.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Invalidate the cached enrollment and trigger recalculation for all users in a specific course.', 'sensei-lms' );
	}

	/**
	 * Is the tool a single action?
	 *
	 * @return bool
	 */
	public function is_single_action() {
		return false;
	}

	/**
	 * Run the tool.
	 */
	public function run() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked in `process_input`.
		if ( ! empty( $_POST['course_id'] ) ) {
			$results = $this->process_input();

			if ( $results ) {
				wp_safe_redirect( Sensei_Tools::instance()->get_tools_url() );
				wp_die();
			}
		}

		$course_query_args = [
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_type'      => 'course',
			'post_status'    => 'any',
		];
		$course_search     = new WP_Query( $course_query_args );

		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Variable used in view.
		$courses = false;
		if ( $course_search->found_posts < 100 ) {
			$courses = $course_search->get_posts();
		}

		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Variable used in view.
		$tool_id = $this->get_id();

		include __DIR__ . '/views/html-recalculate-course-enrolment-form.php';
	}

	/**
	 * Process form input.
	 *
	 * @return false|array
	 */
	private function process_input() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Don't modify the nonce.
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), self::NONCE_ACTION ) ) {
			Sensei_Tools::instance()->trigger_invalid_request( $this );

			return false;
		}

		if ( empty( $_POST['course_id'] ) ) {
			Sensei_Tools::instance()->add_user_message( __( 'Please select a course.', 'sensei-lms' ), true );

			return false;
		}

		$course_id = intval( $_POST['course_id'] );

		$course = get_post( $course_id );
		if ( ! $course || 'course' !== get_post_type( $course ) ) {
			Sensei_Tools::instance()->add_user_message( __( 'Invalid course ID selected.', 'sensei-lms' ), true );

			return false;
		}

		$course_enrolment = Sensei_Course_Enrolment::get_course_instance( $course_id );
		$course_enrolment->recalculate_enrolment();

		Sensei_Tools::instance()->add_user_message( __( 'Course enrollment has been queued for recalculation.', 'sensei-lms' ) );

		return true;
	}
}
