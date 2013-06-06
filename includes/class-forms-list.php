<?php
/**
 * Class that builds our Entries table
 * 
 * @since 1.2
 */
class VisualFormBuilder_Forms_List extends WP_List_Table {
	
	function __construct(){
		global $status, $page, $wpdb;
				
		// Setup global database table names
		$this->field_table_name   = $wpdb->prefix . 'visual_form_builder_fields';
		$this->form_table_name    = $wpdb->prefix . 'visual_form_builder_forms';
		$this->entries_table_name = $wpdb->prefix . 'visual_form_builder_entries';
		
		// Set parent defaults
		parent::__construct( array(
			'singular'  => 'form',
			'plural'    => 'forms',
			'ajax'      => false
		) );
		
		// Handle our bulk actions
		$this->process_bulk_action();
	}

	/**
	 * Display column names
	 * 
	 * @since 1.2
	 * @returns $item string Column name
	 */
	function column_default( $item, $column_name ){
		switch ( $column_name ) {
			case 'id':
			case 'form_id' :
			case 'entries' :
				return $item[ $column_name ];
		}
	}
	
	/**
	 * Builds the on:hover links for the Form column
	 * 
	 * @since 1.2
	 */
	function column_form_title( $item ){
		
		$actions = array();
		
		// Edit Form
		$form_title = sprintf( '<strong><a href="?page=%s&form=%s" id="%3$s" class="view-form">%s</a></strong>', $_REQUEST['page'], $item['form_id'], $item['form_title'] );
		$actions['edit'] = sprintf( '<a href="?page=%s&action=%s&form=%s" id="%3$s" class="view-form">%s</a>', $_REQUEST['page'], 'edit', $item['form_id'], __( 'Edit', 'visual-form-builder' ) );

		// Delete Form
		$actions['delete'] = sprintf( '<a href="?page=%s&action=%s&form=%s" id="%3$s" class="view-form">%s</a>', $_REQUEST['page'], 'delete', $item['form_id'], __( 'Delete', 'visual-form-builder' ) );
		
		return sprintf( '%1$s %2$s', $form_title, $this->row_actions( $actions ) );
	}
	
	function column_entries( $item ) {
		$this->comments_bubble( $item['form_id'], $item['entries'] );		
	}
	
	function comments_bubble( $form_id, $count ) {
				
		echo sprintf(
			'<div class="entries-count-wrapper"><a href="%1$s" title="%2$s" class="vfb-meta-entries-total"><span class="entries-count">%4$s</span></a> %3$s</div>',
			esc_url( add_query_arg( array( 'form-filter' => $form_id ), admin_url( 'admin.php?page=vfb-entries' ) ) ),
			esc_attr__( 'Entries Total', 'visual-form-builder-pro' ),
			__( 'Total', 'visual-form-builder-pro' ),
			number_format_i18n( $count['total'] )
		);
		
		if ( $count['today'] )
			echo '<strong>';
		
		echo sprintf(
			'<div class="entries-count-wrapper"><a href="%1$s" title="%2$s" class="vfb-meta-entries-total"><span class="entries-count">%4$s</span></a> %3$s</div>',
			esc_url( add_query_arg( array( 'form-filter' => $form_id, 'today' => 1 ), admin_url( 'admin.php?page=vfb-entries' ) ) ),
			esc_attr__( 'Entries Today', 'visual-form-builder-pro' ),
			__( 'Today', 'visual-form-builder-pro' ),
			number_format_i18n( $count['today'] )
		);
		
		if ( $count['today'] )
			echo '</strong>';
	}
	
	/**
	 * Used for checkboxes and bulk editing
	 * 
	 * @since 1.2
	 */
	function column_cb( $item ){
		return sprintf( '<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], $item['form_id'] );
	}
	
	/**
	 * Builds the actual columns
	 * 
	 * @since 1.2
	 */
	function get_columns(){
		$columns = array(
			'cb' 			=> '<input type="checkbox" />', //Render a checkbox instead of text
			'form_title' 	=> __( 'Form' , 'visual-form-builder'),
			'form_id' 		=> __( 'Form ID' , 'visual-form-builder'),
			'entries'		=> __( 'Entries', 'visual-form-builder' ),
		);
		
		return $columns;
	}
	
	/**
	 * A custom function to get the entries and sort them
	 * 
	 * @since 1.2
	 * @returns array() $cols SQL results
	 */
	function get_forms( $orderby = 'form_id', $order = 'ASC', $per_page, $offset = 0, $search = '' ){
		global $wpdb;
		
		// Set OFFSET for pagination
		$offset = ( $offset > 0 ) ? "OFFSET $offset" : '';
 				
		$where = apply_filters( 'vfb_pre_get_entries', '' );
		
		// If the form filter dropdown is used
		if ( $this->current_filter_action() )
			$where .= ' AND forms.form_id = ' . $this->current_filter_action();
				
		$sql_order = sanitize_sql_orderby( "$orderby $order" );
		$cols = $wpdb->get_results( "SELECT forms.form_id, forms.form_title FROM $this->form_table_name AS forms WHERE 1=1 $where $search ORDER BY $sql_order LIMIT $per_page $offset" );
		
		return $cols;
	}
			
	/**
	 * Get the number of entries for use with entry statuses
	 * 
	 * @since 2.1
	 * @returns array $stats Counts of different entry types
	 */
	function get_entries_count( $form_id ) {
		global $wpdb;
				
		$entries = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM $this->entries_table_name AS entries WHERE entries.entry_approved = 1 AND form_id = %d", $form_id ) );
				
		return $entries;
	}
	
	/**
	 * Get the number of entries for use with entry statuses
	 * 
	 * @since 2.1
	 * @returns array $stats Counts of different entry types
	 */
	function get_entries_today_count( $form_id ) {
		global $wpdb;
				
		$entries = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM $this->entries_table_name AS entries WHERE entries.entry_approved = 1 AND form_id = %d AND date_submitted >= curdate()", $form_id ) );
				
		return $entries;
	}
	
	/**
	 * Get the number of forms
	 * 
	 * @since 2.2.7
	 * @returns int $count Form count
	 */
	function get_forms_count() {
		global $wpdb;
		
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $this->form_table_name" );
		
		return $count;
	}

	/**
	 * Setup which columns are sortable. Default is by Date.
	 * 
	 * @since 1.2
	 * @returns array() $sortable_columns Sortable columns
	 */
	function get_sortable_columns() {		
		$sortable_columns = array(
			'id' 			=> array( 'id', false ),
			'form_id'		=> array( 'form_id', false ),
			'form_title'	=> array( 'form_title', true ),
			'entries'		=> array( 'entries_count', false ),
		);
		
		return $sortable_columns;
	}
	
	/**
	 * Define our bulk actions
	 * 
	 * @since 1.2
	 * @returns array() $actions Bulk actions
	 */
	function get_bulk_actions() {
		$actions = array();
		
		// Build the row actions
		$actions['delete'] = __( 'Delete Permanently', 'visual-form-builder' );
						
		return $actions;
	}
	
	/**
	 * Process ALL actions on the Entries screen, not only Bulk Actions
	 * 
	 * @since 1.2
	 */
	function process_bulk_action() {
		global $wpdb;
		
		$form_id = '';
		
		// Set the Entry ID array		
		if ( isset( $_REQUEST['entry'] ) ) {
			if ( is_array( $_REQUEST['entry'] ) )
				$form_id = $_REQUEST['entry'];
			else
				$form_id = (array) $_REQUEST['entry'];
		}
		
		switch( $this->current_action() ) {			
			case 'trash' :
				foreach ( $form_id as $id ) {
					$id = absint( $id );
					$wpdb->update( $this->entries_table_name, array( 'entry_approved' => 'trash' ), array( 'entries_id' => $id ) );
				}
			break;
			
			case 'delete' :
				foreach ( $form_id as $id ) {
					$id = absint( $id );
					$wpdb->query( $wpdb->prepare( "DELETE FROM $this->entries_table_name WHERE entries_id = %d", $id ) );
				}
			break;
			
		}
	}
				
	/**
	 * Set our forms filter action
	 * 
	 * @since 1.2
	 * @returns int Form ID
	 */
	function current_filter_action() {
		if ( isset( $_REQUEST['form-filter'] ) && -1 != $_REQUEST['form-filter'] )
			return $_REQUEST['form-filter'];
	
		return false;
	}
	
	/**
	 * Display Search box
	 * 
	 * @since 1.4
	 * @returns html Search Form
	 */
	function search_box( $text, $input_id ) {
	    parent::search_box( $text, $input_id );
	}
	
	/**
	 * Prepares our data for display
	 * 
	 * @since 1.2
	 */
	function prepare_items() {
		global $wpdb;
		
		// get the current user ID
		$user = get_current_user_id();
		
		// get the current admin screen
		$screen = get_current_screen();
		
		// retrieve the "per_page" option
		$screen_option = $screen->get_option( 'per_page', 'option' );
		
		// retrieve the value of the option stored for the current user
		$per_page = get_user_meta( $user, $screen_option, true );
		
		// get the default value if none is set
		if ( empty ( $per_page) || $per_page < 1 )
			$per_page = $screen->get_option( 'per_page', 'default' );
		
		// Get the date/time format that is saved in the options table
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
				
		// What page are we looking at?
		$current_page = $this->get_pagenum();
		
		// Use offset for pagination
		$offset = ( $current_page - 1 ) * $per_page;
		
		// Get column headers
		$columns  = $this->get_columns();
		$hidden   = array();
		
		// Get sortable columns
		$sortable = $this->get_sortable_columns();
		
		// Build the column headers
		$this->_column_headers = array($columns, $hidden, $sortable);

		// Get entries search terms
		$search_terms = ( !empty( $_REQUEST['s'] ) ) ? explode( ' ', $_REQUEST['s'] ) : array();
		
		$searchand = $search = '';
		// Loop through search terms and build query
		foreach( $search_terms as $term ) {
			$term = esc_sql( like_escape( $term ) );
			
			$search .= "{$searchand}((forms.form_title LIKE '%{$term}%') OR (forms.form_key LIKE '%{$term}%') OR (forms.form_email_subject LIKE '%{$term}%'))";
			$searchand = ' AND ';
		}
		
		$search = ( !empty($search) ) ? " AND ({$search}) " : '';
				
		// Set our ORDER BY and ASC/DESC to sort the entries
		$orderby  = ( !empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'form_id';
		$order    = ( !empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';
		
		// Get the sorted entries
		$forms = $this->get_forms( $orderby, $order, $per_page, $offset, $search );
		
		$data = array();
		
		// Loop trough the entries and setup the data to be displayed for each row
		foreach ( $forms as $form ) :
			
			$entries_counts = array(
				'total' => $this->get_entries_count( $form->form_id ),
				'today' => $this->get_entries_today_count( $form->form_id ),
			);
			
			$data[] = array(
				'id' 			=> $form->form_id,
				'form_id'		=> $form->form_id,
				'form_title' 	=> stripslashes( $form->form_title ),
				'entries'		=> $entries_counts,
			);
		endforeach;
		
		// How many forms do we have?
		$total_items = $this->get_forms_count();
		
		// Add sorted data to the items property
		$this->items = $data;
		
		// Register our pagination
		$this->set_pagination_args( array(
			'total_items'	=> $total_items,
			'per_page'		=> $per_page,
			'total_pages'	=> ceil( $total_items / $per_page )
		) );
	}
	
	/**
	 * Display the pagination.
	 * Customize default function to work with months and form drop down filters
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	function pagination( $which ) {
		
		if ( empty( $this->_pagination_args ) )
			return;

		extract( $this->_pagination_args, EXTR_SKIP );

		$output = '<span class="displaying-num">' . sprintf( _n( '1 form', '%s forms', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

		$current = $this->get_pagenum();

		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

		$page_links = array();
		
		// Added to pick up the months dropdown
		$m = isset( $_REQUEST['m'] ) ? (int) $_REQUEST['m'] : 0;
		
		$disable_first = $disable_last = '';
		if ( $current == 1 )
			$disable_first = ' disabled';
		if ( $current == $total_pages )
			$disable_last = ' disabled';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'first-page' . $disable_first,
			esc_attr__( 'Go to the first page' ),
			esc_url( remove_query_arg( 'paged', $current_url ) ),
			'&laquo;'
		);
		
		// Modified the add_query_args to include my custom dropdowns
		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page' ),
			esc_url( add_query_arg( array( 'paged' => max( 1, $current-1 ), 'm' => $m, 'form-filter' => $this->current_filter_action() ), $current_url ) ),
			'&lsaquo;'
		);

		if ( 'bottom' == $which )
			$html_current_page = $current;
		else
			$html_current_page = sprintf( "<input class='current-page' title='%s' type='text' name='paged' value='%s' size='%d' />",
				esc_attr__( 'Current page' ),
				$current,
				strlen( $total_pages )
			);

		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span>';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'next-page' . $disable_last,
			esc_attr__( 'Go to the next page' ),
			esc_url( add_query_arg( array( 'paged' => min( $total_pages, $current+1 ), 'm' => $m, 'form-filter' => $this->current_filter_action() ), $current_url ) ),
			'&rsaquo;'
		);
		
		// Modified the add_query_args to include my custom dropdowns
		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'last-page' . $disable_last,
			esc_attr__( 'Go to the last page' ),
			esc_url( add_query_arg( array( 'paged' => $total_pages, 'm' => $m, 'form-filter' => $this->current_filter_action() ), $current_url ) ),
			'&raquo;'
		);

		$pagination_links_class = 'pagination-links';
		if ( ! empty( $infinite_scroll ) )
			$pagination_links_class = ' hide-if-js';
		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages )
			$page_class = $total_pages < 2 ? ' one-page' : '';
		else
			$page_class = ' no-pages';

		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;
	}
	
}
?>