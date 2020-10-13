<?php
/**
 * File containing the Sensei_Import_Course_Content_Migrator class.
 *
 * @package sensei
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for migrating post content which contains Sensei blocks.
 */
class Sensei_Import_Block_Migrator {

	/**
	 * The course id.
	 *
	 * @var int
	 */
	private $course_id;

	/**
	 * The data port task.
	 *
	 * @var Sensei_Data_Port_Task
	 */
	private $task;

	/**
	 * The course import model.
	 *
	 * @var Sensei_Import_Model
	 */
	private $import_model;

	/**
	 * Sensei_Import_Course_Content_Migrator constructor.
	 *
	 * @param int                   $course_id    The course which gets migrated.
	 * @param Sensei_Data_Port_Task $task         The data port task which this migration is part of.
	 * @param Sensei_Import_Model   $import_model The import model.
	 */
	public function __construct( int $course_id, Sensei_Data_Port_Task $task, Sensei_Import_Model $import_model ) {
		$this->course_id    = $course_id;
		$this->task         = $task;
		$this->import_model = $import_model;
	}

	/**
	 * Migrates the imported post content to use the ids of the newly created lessons and modules.
	 *
	 * @param string $post_content The post content.
	 *
	 * @return string The migrated post content.
	 */
	public function migrate( string $post_content ) : string {
		if ( ! has_block( 'sensei-lms/course-outline', $post_content ) ) {
			return $post_content;
		}

		$blocks = parse_blocks( $post_content );

		$i = 0;
		foreach ( $blocks as $block ) {
			if ( 'sensei-lms/course-outline' === $block['blockName'] ) {
				$mapped_block = $this->map_outline_block_ids( $block );
				break;
			}
			$i++;
		}
		$blocks[ $i ] = $mapped_block;

		return serialize_blocks( $blocks );
	}

	/**
	 * Maps the ids of an outlined block to use the newly created values.
	 *
	 * @param array $outline_block The outline block.
	 *
	 * @return array The mapped block.
	 */
	private function map_outline_block_ids( array $outline_block ) : array {
		if ( empty( $outline_block['innerBlocks'] ) ) {
			return $outline_block;
		}

		$mapped_inner_blocks = [];
		foreach ( $outline_block['innerBlocks'] as $inner_block ) {
			if ( 'sensei-lms/course-outline-module' === $inner_block['blockName'] ) {
				$mapped_block = $this->map_module_block_id( $inner_block );
			} elseif ( 'sensei-lms/course-outline-lesson' === $inner_block['blockName'] ) {
				$mapped_block = $this->map_lesson_block_id( $inner_block );
			} else {
				$mapped_block = $inner_block;
			}

			if ( false !== $mapped_block ) {
				$mapped_inner_blocks[] = $mapped_block;
			}
		}

		$outline_block['innerBlocks'] = $mapped_inner_blocks;

		return $outline_block;
	}

	/**
	 * Map the ids of a lesson block.
	 *
	 * @param array $lesson_block The lesson block.
	 *
	 * @return bool|array The lesson block or false if the id couldn't be mapped.
	 */
	private function map_lesson_block_id( array $lesson_block ) {
		if ( empty( $lesson_block['attrs']['id'] ) ) {
			return false;
		}

		// We first check for the lesson id to be a lesson which was imported during the import process. If that fails
		// we check if the lesson already exists in the database. This could happen in case of a course update.
		$lesson_id = $this->task->get_job()->translate_import_id( Sensei_Data_Port_Lesson_Schema::POST_TYPE, 'id:' . $lesson_block['attrs']['id'] );

		if ( null === $lesson_id ) {

			$args = [
				'post_type'      => Sensei_Data_Port_Lesson_Schema::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'p'              => $lesson_block['attrs']['id'],
			];

			if ( isset( $lesson_block['attrs']['title'] ) ) {
				$args['title'] = $lesson_block['attrs']['title'];
			}

			if ( empty( get_posts( $args ) ) ) {
				$this->import_model->add_line_warning(
					// translators: The %1$d is the lesson id and the %2$s the lesson title.
					sprintf( __( 'Lesson with id %1$d and title %2$s which is referenced in course outline block not found.', 'sensei-lms' ), $lesson_block['attrs']['id'], $lesson_block['attrs']['title'] ),
					[
						'code' => 'sensei_data_port_course_lesson_not_found',
					]
				);

				return false;
			}
		} else {
			$lesson_block['attrs']['id'] = $lesson_id;
		}

		return $lesson_block;
	}

	/**
	 * Map the ids of a module block.
	 *
	 * @param array $module_block The module block.
	 *
	 * @return bool|array The mapped module block or false if the block couldn't be mapped.
	 */
	private function map_module_block_id( array $module_block ) {
		if ( empty( $module_block['attrs']['title'] ) ) {
			$this->import_model->add_line_warning(
				__( 'No title for module found.', 'sensei-lms' ),
				[
					'code' => 'sensei_data_port_module_title_not_found',
				]
			);

			return false;
		}

		$term = Sensei_Data_Port_Utilities::get_module_for_course( $module_block['attrs']['title'], $this->course_id );

		if ( is_wp_error( $term ) ) {
			$this->import_model->add_line_warning( $term->get_error_message(), [ 'code' => $term->get_error_code() ] );

			return false;
		}

		$module_inner_blocks = [];

		foreach ( $module_block['innerBlocks'] as $inner_block ) {
			if ( 'sensei-lms/course-outline-lesson' === $inner_block['blockName'] ) {
				$mapped_lesson_block = $this->map_lesson_block_id( $inner_block );

				if ( false !== $mapped_lesson_block ) {
					$module_inner_blocks[] = $mapped_lesson_block;
				}
			} else {
				$module_inner_blocks[] = $inner_block;
			}
		}

		$module_block['attrs']['id'] = $term->term_id;
		$module_block['innerBlocks'] = $module_inner_blocks;

		return $module_block;
	}
}
