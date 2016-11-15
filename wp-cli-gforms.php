<?php
/**
 * @since             1.0.0
 * @package           WP_CLI_Gforms
 *
 * @wordpress-plugin
 * Plugin Name:       WP-CLI-Gforms
 * Description:       Adds WP-CLI commands for gravity forms
 * Version:           1.0.0
 * Author:            Thomas Lhotta
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( defined( 'WP_CLI' ) ) {
	require_once 'classes/class-merge-data.php';
	WP_CLI::add_command( 'gforms', 'WP_CLI_Gforms_Merge_Data' );
}




