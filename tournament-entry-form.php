<?php
/**
 * Plugin Name: Tournament Entry Form
 * Description: Adds a "Tournament" post type linked to a Contact Form 7 entry form, and records every submission in a dedicated database table that can be reviewed in the admin.
 * Version: 1.0.0
 * Author: CM30
 * Text Domain: tournament-entry-form
 * Requires Plugins: contact-form-7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TEF_VERSION', '1.0.0' );
define( 'TEF_TABLE_NAME', 'submissions' ); // Gets prefixed with $wpdb->prefix -> e.g. wp_submissions
define( 'TEF_PLUGIN_FILE', __FILE__ );
define( 'TEF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Activation / Deactivation
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'tef_activate' );
function tef_activate() {
	tef_create_submissions_table();
	tef_register_post_type(); // Needed before flushing rewrite rules.
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'tef_deactivate' );
function tef_deactivate() {
	flush_rewrite_rules();
}

/**
 * Creates (or updates, via dbDelta) the custom submissions table.
 */
function tef_create_submissions_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . TEF_TABLE_NAME;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		tournament_id BIGINT UNSIGNED NULL,
		form_id BIGINT UNSIGNED NULL,
		name VARCHAR(255) NOT NULL,
		email VARCHAR(255) NOT NULL,
		discord_username VARCHAR(255) NULL,
		friend_code VARCHAR(100) NULL,
		submitted_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY tournament_id (tournament_id),
		KEY form_id (form_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/* -------------------------------------------------------------------------
 * Custom Post Type: tournament
 * ---------------------------------------------------------------------- */

add_action( 'init', 'tef_register_post_type' );
function tef_register_post_type() {
	$labels = array(
		'name'               => __( 'Tournaments', 'tournament-entry-form' ),
		'singular_name'      => __( 'Tournament', 'tournament-entry-form' ),
		'add_new'            => __( 'Add New', 'tournament-entry-form' ),
		'add_new_item'       => __( 'Add New Tournament', 'tournament-entry-form' ),
		'edit_item'          => __( 'Edit Tournament', 'tournament-entry-form' ),
		'new_item'           => __( 'New Tournament', 'tournament-entry-form' ),
		'view_item'          => __( 'View Tournament', 'tournament-entry-form' ),
		'search_items'       => __( 'Search Tournaments', 'tournament-entry-form' ),
		'not_found'          => __( 'No tournaments found', 'tournament-entry-form' ),
		'not_found_in_trash' => __( 'No tournaments found in Trash', 'tournament-entry-form' ),
		'all_items'          => __( 'All Tournaments', 'tournament-entry-form' ),
		'menu_name'          => __( 'Tournaments', 'tournament-entry-form' ),
	);

	register_post_type(
		'tournament',
		array(
			'labels'        => $labels,
			'public'        => true,
			'show_in_menu'  => true,
			'menu_icon'     => 'dashicons-awards',
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'has_archive'   => true,
			'rewrite'       => array( 'slug' => 'tournaments' ),
			'show_in_rest'  => true,
		)
	);
}

/* -------------------------------------------------------------------------
 * Meta box: link a Contact Form 7 form to the tournament
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', 'tef_add_meta_box' );
function tef_add_meta_box() {
	add_meta_box(
		'tef_form_meta_box',
		__( 'Tournament Entry Form', 'tournament-entry-form' ),
		'tef_render_meta_box',
		'tournament',
		'side',
		'default'
	);
}

function tef_render_meta_box( $post ) {
	wp_nonce_field( 'tef_save_meta', 'tef_meta_nonce' );

	$form_id     = get_post_meta( $post->ID, 'form', true );
	$cf7_active  = function_exists( 'wpcf7' );
	$forms       = $cf7_active ? get_posts(
		array(
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	) : array();

	if ( ! $cf7_active ) {
		echo '<p style="color:#b32d2e;">' . esc_html__( 'Contact Form 7 does not appear to be active. Install/activate it to select a form.', 'tournament-entry-form' ) . '</p>';
	}

	echo '<p><label for="tef_form_id">' . esc_html__( 'Entry form:', 'tournament-entry-form' ) . '</label></p>';
	echo '<select name="tef_form_id" id="tef_form_id" style="width:100%;" ' . disabled( $cf7_active, false, false ) . '>';
	echo '<option value="">' . esc_html__( '— None —', 'tournament-entry-form' ) . '</option>';

	foreach ( $forms as $form ) {
		printf(
			'<option value="%1$d" %2$s>%3$s</option>',
			(int) $form->ID,
			selected( (string) $form_id, (string) $form->ID, false ),
			esc_html( $form->post_title )
		);
	}
	echo '</select>';

	if ( $form_id ) {
		echo '<p style="margin-top:8px;">' . esc_html__( 'Shortcode used on the tournament page:', 'tournament-entry-form' ) . '<br><code>[contact-form-7 id="' . esc_attr( $form_id ) . '"]</code></p>';
		echo '<p class="description">' . esc_html__( 'This form is automatically appended below the tournament content.', 'tournament-entry-form' ) . '</p>';
	}
}

add_action( 'save_post_tournament', 'tef_save_meta_box' );
function tef_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['tef_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tef_meta_nonce'] ) ), 'tef_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['tef_form_id'] ) ) {
		$form_id = sanitize_text_field( wp_unslash( $_POST['tef_form_id'] ) );
		if ( '' === $form_id ) {
			delete_post_meta( $post_id, 'form' );
		} else {
			update_post_meta( $post_id, 'form', absint( $form_id ) );
		}
	}
}

/* -------------------------------------------------------------------------
 * Automatically render the linked CF7 form on the tournament's page
 * ---------------------------------------------------------------------- */

add_filter( 'the_content', 'tef_append_form_to_content' );
function tef_append_form_to_content( $content ) {
	if ( is_singular( 'tournament' ) && in_the_loop() && is_main_query() ) {
		$form_id = get_post_meta( get_the_ID(), 'form', true );
		if ( $form_id && function_exists( 'wpcf7' ) ) {
			$content .= '<div class="tef-entry-form">' . do_shortcode( '[contact-form-7 id="' . intval( $form_id ) . '"]' ) . '</div>';
		}
	}
	return $content;
}

/* -------------------------------------------------------------------------
 * Capture CF7 submissions into the submissions table
 * ---------------------------------------------------------------------- */

add_action( 'wpcf7_mail_sent', 'tef_capture_submission' );
function tef_capture_submission( $contact_form ) {
	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}

	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}

	$data = $submission->get_posted_data();

	// Field names below match Contact Form 7's default naming convention.
	// Adjust the keys here if your form uses different field names.
	$name        = tef_extract_field( $data, array( 'your-name', 'name' ) );
	$email       = tef_extract_field( $data, array( 'your-email', 'email' ) );
	$discord     = tef_extract_field( $data, array( 'discord-username', 'discord', 'your-discord' ) );
	$friend_code = tef_extract_field( $data, array( 'friend-code', 'friendcode', 'your-friend-code' ) );

	// If the form is displayed on the tournament's own page (via the auto-appended
	// shortcode above, or a manually placed shortcode on that page), CF7 records
	// the page it was shown on -- we use that to link the submission back to the tournament.
	$container_id = $submission->get_meta( 'container_post_id' );
	$tournament_id = null;
	if ( $container_id && 'tournament' === get_post_type( $container_id ) ) {
		$tournament_id = (int) $container_id;
	}

	global $wpdb;
	$table = $wpdb->prefix . TEF_TABLE_NAME;

	$wpdb->insert(
		$table,
		array(
			'tournament_id'     => $tournament_id,
			'form_id'           => $contact_form->id(),
			'name'              => sanitize_text_field( $name ),
			'email'             => sanitize_email( $email ),
			'discord_username'  => sanitize_text_field( $discord ),
			'friend_code'       => sanitize_text_field( $friend_code ),
			'submitted_at'      => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Pulls the first matching field out of the CF7 posted data array.
 */
function tef_extract_field( $data, $keys ) {
	foreach ( $keys as $key ) {
		if ( isset( $data[ $key ] ) ) {
			$value = $data[ $key ];
			return is_array( $value ) ? implode( ', ', $value ) : $value;
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * Public shortcode: [tournament_entrants]
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'tef_enqueue_frontend_style' );
function tef_enqueue_frontend_style() {
	wp_register_style( 'tef-entrants', TEF_PLUGIN_URL . 'assets/tef-style.css', array(), TEF_VERSION );
	wp_enqueue_style( 'tef-entrants' );
}

add_shortcode( 'tournament_entrants', 'tef_render_entrants_shortcode' );
/**
 * Usage:
 *   [tournament_entrants]                           - on a tournament page, lists that tournament's entrants
 *   [tournament_entrants id="123"]                   - list entrants for a specific tournament, from anywhere
 *   [tournament_entrants fields="name,discord"]      - choose which columns to show (default: name,discord)
 *                                                       allowed: name, email, discord, friend_code
 *   [tournament_entrants limit="20"]                 - cap the number of rows shown (default: no limit)
 *   [tournament_entrants title="Entrants"]           - optional heading above the list
 *
 * Note: "email" is deliberately left out of the default field list since this
 * list is public-facing. Only add it explicitly if you're comfortable showing
 * entrants' email addresses to site visitors.
 */
function tef_render_entrants_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'     => 0,
			'fields' => 'name,discord',
			'limit'  => 0,
			'title'  => '',
		),
		$atts,
		'tournament_entrants'
	);

	$tournament_id = absint( $atts['id'] );
	if ( ! $tournament_id && is_singular( 'tournament' ) ) {
		$tournament_id = get_the_ID();
	}
	if ( ! $tournament_id || 'tournament' !== get_post_type( $tournament_id ) ) {
		return '<p class="tef-entrants-error">' . esc_html__( 'No tournament specified.', 'tournament-entry-form' ) . '</p>';
	}

	$allowed_fields = array( 'name', 'email', 'discord', 'friend_code' );
	$requested      = array_filter( array_map( 'trim', explode( ',', strtolower( $atts['fields'] ) ) ) );
	$fields         = array_values( array_intersect( $allowed_fields, $requested ) );
	if ( empty( $fields ) ) {
		$fields = array( 'name' );
	}

	$field_labels = array(
		'name'        => __( 'Name', 'tournament-entry-form' ),
		'email'       => __( 'Email', 'tournament-entry-form' ),
		'discord'     => __( 'Discord', 'tournament-entry-form' ),
		'friend_code' => __( 'Friend Code', 'tournament-entry-form' ),
	);
	$field_columns = array(
		'name'        => 'name',
		'email'       => 'email',
		'discord'     => 'discord_username',
		'friend_code' => 'friend_code',
	);

	global $wpdb;
	$table = $wpdb->prefix . TEF_TABLE_NAME;
	$limit = absint( $atts['limit'] );

	$sql    = "SELECT * FROM {$table} WHERE tournament_id = %d ORDER BY submitted_at ASC";
	$params = array( $tournament_id );
	if ( $limit > 0 ) {
		$sql     .= ' LIMIT %d';
		$params[] = $limit;
	}
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

	ob_start();
	echo '<div class="tef-entrants">';

	if ( $atts['title'] ) {
		echo '<h3 class="tef-entrants-title">' . esc_html( $atts['title'] ) . '</h3>';
	}

	if ( empty( $rows ) ) {
		echo '<p class="tef-entrants-empty">' . esc_html__( 'No entrants yet.', 'tournament-entry-form' ) . '</p>';
	} else {
		echo '<table class="tef-entrants-table">';
		echo '<thead><tr><th class="tef-entrants-num">#</th>';
		foreach ( $fields as $field ) {
			echo '<th>' . esc_html( $field_labels[ $field ] ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		$row_number = 1;
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td class="tef-entrants-num">' . (int) $row_number++ . '</td>';
			foreach ( $fields as $field ) {
				$value = isset( $row[ $field_columns[ $field ] ] ) ? $row[ $field_columns[ $field ] ] : '';
				echo '<td>' . esc_html( $value ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="tef-entrants-count">' . esc_html(
			sprintf(
				/* translators: %d: number of entrants */
				_n( '%d entrant', '%d entrants', count( $rows ), 'tournament-entry-form' ),
				count( $rows )
			)
		) . '</p>';
	}

	echo '</div>';

	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Admin: view submissions
 * ---------------------------------------------------------------------- */

require_once TEF_PLUGIN_DIR . 'includes/class-tef-submissions-list-table.php';

add_action( 'admin_menu', 'tef_admin_menu' );
function tef_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=tournament',
		__( 'Submissions', 'tournament-entry-form' ),
		__( 'Submissions', 'tournament-entry-form' ),
		'manage_options',
		'tef-submissions',
		'tef_render_submissions_page'
	);
}

function tef_render_submissions_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'tournament-entry-form' ) );
	}

	// Handle CSV export.
	if ( isset( $_GET['tef_export'] ) && 'csv' === $_GET['tef_export'] && check_admin_referer( 'tef_export_csv' ) ) {
		tef_export_submissions_csv();
	}

	// Handle row deletion.
	if ( isset( $_GET['action'], $_GET['submission'] ) && 'delete' === $_GET['action'] ) {
		check_admin_referer( 'tef_delete_submission' );
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . TEF_TABLE_NAME, array( 'id' => absint( $_GET['submission'] ) ), array( '%d' ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Submission deleted.', 'tournament-entry-form' ) . '</p></div>';
	}

	$list_table = new TEF_Submissions_List_Table();
	$list_table->prepare_items();

	$export_url = wp_nonce_url(
		add_query_arg( 'tef_export', 'csv' ),
		'tef_export_csv'
	);
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Tournament Submissions', 'tournament-entry-form' ); ?></h1>
		<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'tournament-entry-form' ); ?></a>
		<hr class="wp-header-end">
		<form method="get">
			<input type="hidden" name="post_type" value="tournament" />
			<input type="hidden" name="page" value="tef-submissions" />
			<?php
			$list_table->search_box( __( 'Search submissions', 'tournament-entry-form' ), 'tef-submission-search' );
			$list_table->display();
			?>
		</form>
	</div>
	<?php
}

function tef_export_submissions_csv() {
	global $wpdb;
	$table = $wpdb->prefix . TEF_TABLE_NAME;
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=submissions-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'ID', 'Tournament', 'Form ID', 'Name', 'Email', 'Discord Username', 'Friend Code', 'Submitted At' ) );

	foreach ( $rows as $row ) {
		$tournament_title = $row['tournament_id'] ? get_the_title( $row['tournament_id'] ) : '';
		fputcsv(
			$out,
			array(
				$row['id'],
				$tournament_title,
				$row['form_id'],
				$row['name'],
				$row['email'],
				$row['discord_username'],
				$row['friend_code'],
				$row['submitted_at'],
			)
		);
	}

	fclose( $out );
	exit;
}
