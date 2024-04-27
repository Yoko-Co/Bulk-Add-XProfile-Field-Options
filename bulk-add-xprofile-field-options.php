<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Bulk Add XProfile Field Options
 * Description: A utility plugin to bulk add options to BuddyPress XProfile fields.
 * Version: 0.1
 * Requires at least: 6.2
 * Requires Plugins: buddypress
 * Author: Yoko Co
 * Author URI: https://yokoco.com
 * Text Domain: yoko-xprofile
 * License: GPL-3-or-later
 *
 * @package Bulk_Add_XProfile_Field_Options
 */

namespace Yoko\BulkAddXProfileFieldOptions;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manages the addition of bulk options to XProfile fields.
 */
class BulkAddXProfileFieldOptions {
	/**
	 * Initializes the plugin by setting up hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_post_add_options_to_xprofile_field', array( __CLASS__, 'process_submission' ) );
	}

	/**
	 * Adds a submenu page to the WordPress admin Tools menu.
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Bulk Add XProfile Field Options', 'yoko-xprofile' ),
			__( 'Bulk Add XProfile Field Options', 'yoko-xprofile' ),
			'manage_options',
			'bulk-add-xprofile-field-options',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Renders the admin page for the plugin.
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div id="message" class="notice is-dismissible error"><p>' . esc_html__( 'You do not have permission to access this page.', 'yoko-xprofile' ) . '</p></div>';
			return;
		}

		if ( ! function_exists( 'bp_is_active' ) ) {
			echo '<div id="message" class="notice is-dismissible error"><p>' . esc_html__( 'BuddyPress is not active.', 'yoko-xprofile' ) . '</p></div>';
			return;
		}

		// Get a list of all XProfile fields.
		$xprofile_fields_groups = bp_xprofile_get_groups(
			array(
				'fetch_fields'      => true,    // Ensure that fields within each group are fetched.
				'fetch_field_data'  => false,   // Set to true if you want to fetch data for each field.
				'hide_empty_groups' => true,    // Fetch only groups that have fields.
				'hide_empty_fields' => false,   // Fetch even the fields that have no data.
				'user_id'           => false,   // Use false to ignore data specific to a user, true to include it.
			)
		);

		// Flatten the array of fields.
		$xprofile_fields = array();
		foreach ( $xprofile_fields_groups as $group ) {
			$xprofile_fields = array_merge( $xprofile_fields, $group->fields );
		}

		// Display a notice if there is one.
		if (
			isset( $_GET['status'] ) &&
			in_array( $_GET['status'], array( 'error', 'success' ), true ) &&
			isset( $_GET['nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'add_options_to_xprofile_field_results' )
		) {
			$notice_classes = 'notice is-dismissible';

			if ( 'error' === $_GET['status'] ) {
				$notice_classes .= ' error';
			} elseif ( 'success' === $_GET['status'] ) {
				$notice_classes .= ' updated';
			}

			if ( isset( $_GET['message'] ) ) {
				$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
				echo '<div id="message" class="' . esc_attr( $notice_classes ) . '"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
		?>

		<div class="wrap">
			<h1>Add Options to XProfile Field</h1>
			<form method="post" action="<?php echo esc_url_raw( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'add_options_to_xprofile_field_action', 'add_options_to_xprofile_field_nonce' ); ?>
				<input type="hidden" name="action" value="add_options_to_xprofile_field">

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="xprofile_field"><?php echo esc_html__( 'XProfile field to populate', 'yoko-xprofile' ); ?></label></th>
							<td>
								<select id="xprofile_field" name="xprofile_field">
									<option value="0"><?php echo esc_html__( 'Select an XProfile field', 'yoko-xprofile' ); ?></option>
									<?php
									foreach ( $xprofile_fields as $single_xprofile_field ) {
										?>
										<option value="<?php echo esc_attr( $single_xprofile_field->id ); ?>"><?php echo esc_html( $single_xprofile_field->name ); ?></option>
										<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="xprofile_field_options"><?php echo esc_html__( 'Options to add', 'yoko-xprofile' ); ?></label></th>
							<td>
								<textarea name="xprofile_field_options" rows="10" cols="50"></textarea>
								<p class="description">
									<?php echo esc_html__( 'Enter your list of options, one per line. These will be appended after any existing options.', 'yoko-xprofile' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>				
				<?php submit_button( __( 'Add Options', 'yoko-xprofile' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Processes the submission from the admin page form.
	 */
	public static function process_submission() {
		global $wpdb, $bp;

		// Set up a nonce for the result message display.
		$nonce = wp_create_nonce( 'add_options_to_xprofile_field_results' );

		// Check if our nonce is set and verify it.
		if (
			! empty( $_POST['add_options_to_xprofile_field_nonce'] ) &&
			! empty( $_POST['xprofile_field'] ) &&
			! empty( $_POST['xprofile_field_options'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['add_options_to_xprofile_field_nonce'] ) ), 'add_options_to_xprofile_field_action' )
		) {
			$xprofile_field = (int) sanitize_textarea_field( wp_unslash( $_POST['xprofile_field'] ) );
			$new_options    = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['xprofile_field_options'] ) ) );

			// Get the XProfile field object.
			$field = xprofile_get_field( $xprofile_field );

			// Get the highest option order for the field.
			$highest_option_order = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this result may change with every run.
				$wpdb->prepare(
					'SELECT MAX(option_order) FROM %i WHERE parent_id = %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder -- this is a valid identifier with our "Requires at least" version.
					$bp->profile->table_name_fields,
					$field->id
				)
			);

			// If there are no existing options, set the highest option order to 0.
			if ( ! $highest_option_order ) {
				$highest_option_order = 0;
			}

			// Add the new field options.
			$i = $highest_option_order;
			foreach ( $new_options as $option ) {
				xprofile_insert_field(
					array(
						'field_group_id' => $field->group_id,
						'parent_id'      => $field->id,
						'type'           => 'option',
						'name'           => $option,
						'option_order'   => ++$i,
					)
				);
			}

			// Redirect back to the settings page with a success message.
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'bulk-add-xprofile-field-options',
						'status'  => 'success',
						'message' => 'Options added successfully.',
						'nonce'   => $nonce,
					),
					admin_url( 'tools.php' )
				)
			);
			exit;
		} else {
			// Redirect back with an error message if nonce verification fails.
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'bulk-add-xprofile-field-options',
						'status'  => 'error',
						'message' => 'There was an error adding the options.',
						'nonce'   => $nonce,
					),
					admin_url( 'tools.php' )
				)
			);
			exit;
		}
	}
}

BulkAddXProfileFieldOptions::init();
