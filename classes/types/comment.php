<?php

namespace Icspresso\Types;

class Comment extends Base {

	public $name             = 'comment';
	public $index_hooks      = array( 'wp_insert_comment', 'edit_comment' );
	public $delete_hooks     = array( 'deleted_comment' );
	public $mappable_hooks   = array(
		'added_comment_meta'   => 'update_comment_meta_callback',
		'updated_comment_meta' => 'update_comment_meta_callback',
		'deleted_comment_meta' => 'update_comment_meta_callback'
	);

	/**
	 * Called when comment meta is added/deleted/updated
	 *
	 * @param $meta_id
	 * @param $user_id
	 */
	public function update_comment_meta_callback( $meta_id, $user_id ) {

		$this->index_callback( $user_id );
	}

	/**
	 * Queue the indexing of an item - called when a comment is modified or added to the database
	 *
	 * @param $item
	 * @param array $args
	 */
	public function index_callback( $item, $args = array()  ) {

		$comment = (array) get_comment( $item );

		if ( ! $comment ) {
			return;
		}

		$this->add_action( 'index_item', $item );
	}

	/**
	 * Queue the deletion of an item - called when a comment is deleted from the database
	 *
	 * @param $user_id
	 * @param array $args
	 */
	public function delete_callback( $user_id, $args = array()  ) {

		$this->add_action( 'delete_item', $user_id );
	}

	/**
	 * Parse an item for indexing - accepts comment ID or comment object
	 *
	 * @param $item
	 * @param array $args
	 * @return array|bool
	 */
	public function parse_item_for_index( $item, $args = array() ) {

		//get a valid user object as array (populate if only id is supplied)
		if ( is_numeric( $item ) ) {
			$item = (array) get_comment( $item );
			//make sure ID is a parameter, we want a common parameter for the object id across types
			$item['ID'] = $item['comment_ID'];
		} else {
			$item = (array) $item;
			$item['ID'] = $item['comment_ID'];
		}

		if ( empty( $item['ID'] ) ) {
			return false;
		}

		$item['comment_date_timestamp'] = strtotime( $item['comment_date'] );

		$item['meta'] = get_metadata( 'comment', (int) $item['ID'], '', true );

		foreach ( $item['meta'] as $meta_key => $meta_array ) {
			$item['meta'][$meta_key] = reset( $meta_array );
		}

		return $this->filter_item( $item );
	}

	/**
	 * Get paginated comments for use by index_all base class method
	 *
	 * @param $page
	 * @param $per_page
	 * @return array
	 */
	public function get_items( $page, $per_page ) {

		global $wpdb;

		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments ORDER BY comment_ID DESC LIMIT %d, %d", ( $page > 0 ) ? $per_page * ( $page -1 ) : 0, $per_page ) );

		return $comments;
	}

	/**
	 * Get paginated comments ids for use by index_pending base class method
	 *
	 * @param $page
	 * @param $per_page
	 * @return array
	 */
	public function get_items_ids( $page, $per_page ) {

		global $wpdb;

		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments ORDER BY comment_ID DESC LIMIT %d, %d", ( $page > 0 ) ? $per_page * ( $page -1 ) : 0, $per_page ) );

		return $comments;
	}

	/*
	 * Get an integer count of the number of items which can potentially be indexed in the database
	 *
	 * Should serve to return a count which matches the same number of items which can be obtained from use of the get_items method
	 *
	 * @return int
	 */
	function get_items_count() {

		global $wpdb;

		$r = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments" );

		return  (int) $r;
	}

}