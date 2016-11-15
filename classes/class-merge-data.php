<?php
if ( ! defined( 'WP_CLI' ) ) {
	die;
}

/**
 * Handles merging of form data
 */
class WP_CLI_Gforms_Merge_Data extends WP_CLI_Command {
	protected $source_form;
	protected $target_form;

	/**
	 * Searches for identical fields (ID/type) in the source and target form, then merges entry data for these fields
	 * from the source form to the target form.
	 *
	 * Both forms must have an entry from the same user.
	 *
	 * ## OPTIONS
	 *
	 * <source-form>
	 * : The form to copy data from
	 *
	 * <target-form>
	 * : The form to copy data to
	 *
	 * [--show-map]
	 * : Shows the field map without updating any data
	 *
	 * ## EXAMPLES
	 *
	 *     wp gforms merge-data 1 2
	 *
	 * @when after_wp_load
	 * @subcommand merge-data
	 */
	public function merge_data( $args, $assoc_args ) {
		$this->source_form = GFAPI::get_form( $args[0] );
		if ( false === $this->source_form ) {
			WP_CLI::error( sprintf( 'Source form %d not found.', $args[0] ) );
		}

		$this->target_form = GFAPI::get_form( $args[1] );
		if ( false === $this->target_form ) {
			WP_CLI::error( sprintf( 'Source form %d not found.', $args[1] ) );
		}

		$map = $this->get_field_map();
		if ( empty( $map ) ) {
			WP_CLI::error( 'No fields could be mapped' );
		}

		$this->print_map( $map );
		if ( ! empty( $assoc_args['show-map'] ) ) {
			return;
		}

		$source_entries = GFAPI::get_entries( array( $this->source_form['id'] ) );
		if ( 0 === count( $source_entries ) ) {
			WP_CLI::error( 'The source forms has no entries' );
		}

		WP_CLI::line( sprintf( 'Found %d source entries found', count( $source_entries ) ) );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing source entries', count( $source_entries ) );

		foreach ( $source_entries as $entry ) {
			$this->copy_entry_data( $entry, $map );
			$progress->tick();
		}

		$progress->finish();
	}

	protected function get_field_map() {
		$map = array();
		foreach ( $this->source_form['fields'] as $source_field ) {
			/* @var GF_Field $source_field */

			foreach ( $this->target_form['fields'] as $target_field ) {
				/* @var GF_Field $target_field */

				if ( ! empty( $source_field->displayOnly ) || ! empty( $target_field->displayOnly ) ) {
					continue;
				}

				if ( $target_field->id !== $source_field->id ) {
					continue;
				}

				if ( $target_field->type !== $source_field->type ) {
					continue;
				}

				$map[ (int) $source_field->id ] = (int) $target_field->id;
			}
		}

		return $map;
	}

	protected function print_map( array $map ) {
		$source_field_names = wp_list_pluck( $this->source_form['fields'], 'label', 'id' );
		$target_field_names = wp_list_pluck( $this->source_form['fields'], 'label', 'id' );

		$source_field_types = wp_list_pluck( $this->source_form['fields'], 'type', 'id' );
		$target_field_types = wp_list_pluck( $this->source_form['fields'], 'type', 'id' );

		WP_CLI::line();
		WP_CLI::line( 'Build field map:' );

		$table = new cli\Table();
		$table->setHeaders(
			array(
				sprintf( 'Source Form (%s)', $this->source_form['title'] ),
				'ID',
				'Type',
				sprintf( 'Target (%s)', $this->target_form['title'] ),
				'ID',
				'Type',
			)
		);

		foreach ( $map as $source_id => $target_id ) {
			$table->addRow(
				array(
					$source_field_names[ $source_id ],
					$source_id,
					$source_field_types[ $source_id ],
					$target_field_names[ $target_id ],
					$target_id,
					$target_field_types[ $target_id ],
				)
			);
		}

		$table->display();
		WP_CLI::line();
	}

	protected function copy_entry_data( $source_entry, array $map ) {
		if ( empty( $source_entry['created_by'] ) ) {
			return false;
		}

		$target_entry = GFAPI::get_entries(
			array( $this->target_form['id'] ),
			array(
				'field_filters' => array(
					array(
						'key'   => 'created_by',
						'value' => $source_entry['created_by'],
					),
				),
			)
		);

		$target_entry = reset( $target_entry );
		if ( empty( $target_entry ) ) {
			return false;
		}

		foreach ( $source_entry as $source_field_id => $source_value ) {
			// Skip non field values
			if ( ! is_numeric( $source_field_id ) ) {
				continue;
			}

			$source_id_base = intval( floor( $source_field_id ) );
			if ( ! array_key_exists( $source_id_base, $map ) ) {
				continue;
			}

			$source_id_decimal = $source_field_id - $source_id_base;
			GFAPI::update_entry_field( $target_entry['id'], $map[ $source_id_base ] + $source_id_decimal, $source_value );
		}

		foreach ( $map as $source_id => $target_id ) {
			$target_entry[ $target_id ] = $source_entry[ $source_id ];
		}

		return true;
	}
}

