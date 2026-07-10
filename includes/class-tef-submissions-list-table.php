<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TEF_Submissions_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'id'          => __( 'ID', 'tournament-entry-form' ),
			'tournament'  => __( 'Tournament', 'tournament-entry-form' ),
			'name'        => __( 'Name', 'tournament-entry-form' ),
			'email'       => __( 'Email', 'tournament-entry-form' ),
			'discord'     => __( 'Discord Username', 'tournament-entry-form' ),
			'friend_code' => __( 'Friend Code', 'tournament-entry-form' ),
			'submitted'   => __( 'Submitted', 'tournament-entry-form' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'id'        => array( 'id', true ),
			'name'      => array( 'name', false ),
			'submitted' => array( 'submitted_at', false ),
		);
	}

	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . TEF_TABLE_NAME;

		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$where  = '';
		$params = array();

		if ( ! empty( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where  = ' WHERE name LIKE %s OR email LIKE %s OR discord_username LIKE %s OR friend_code LIKE %s';
			$params = array( $like, $like, $like, $like );
		}

		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		$order   = ( ! empty( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ) ? 'ASC' : 'DESC';

		// Map display column names to actual DB columns.
		$orderby_map = array(
			'id'        => 'id',
			'name'      => 'name',
			'submitted' => 'submitted_at',
		);
		$orderby = isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : 'id';

		$count_sql = "SELECT COUNT(*) FROM {$table}{$where}";
		if ( $params ) {
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			$total_items = (int) $wpdb->get_var( $count_sql );
		}

		$offset = ( $current_page - 1 ) * $per_page;

		$data_sql    = "SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$data_params = array_merge( $params, array( $per_page, $offset ) );
		$this->items = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return esc_html( $item['id'] );
			case 'name':
				return esc_html( $item['name'] );
			case 'email':
				return esc_html( $item['email'] );
			case 'discord':
				return esc_html( $item['discord_username'] );
			case 'friend_code':
				return esc_html( $item['friend_code'] );
			case 'submitted':
				return esc_html( $item['submitted_at'] );
			default:
				return '';
		}
	}

	protected function column_tournament( $item ) {
		if ( empty( $item['tournament_id'] ) ) {
			return '&#8212;';
		}
		$title = get_the_title( $item['tournament_id'] );
		if ( ! $title ) {
			return '&#8212;';
		}
		$edit_link = get_edit_post_link( $item['tournament_id'] );
		return $edit_link ? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
	}

	protected function column_name( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'delete',
					'submission' => $item['id'],
				)
			),
			'tef_delete_submission'
		);

		$actions = array(
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this submission?', 'tournament-entry-form' ) ),
				esc_html__( 'Delete', 'tournament-entry-form' )
			),
		);

		return sprintf( '%1$s %2$s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
	}

	public function no_items() {
		esc_html_e( 'No submissions yet.', 'tournament-entry-form' );
	}
}
