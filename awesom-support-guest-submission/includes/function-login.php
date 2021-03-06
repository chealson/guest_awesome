<?php
//global $loginstatus;
add_action( 'wpgs_do_register', 'wpgs_register_account' );
/**
 * Register user account.
 *
 * This function is hooked onto wpgs_do_register so that the registration process can be triggered
 * when the registration form is submitted.
 *
 * @param array $data User data
 *
 * @since  1.0.0
 * @return void
 */
function wpgs_register_account( $data ) {

	// Get the redirect URL
	$Path=$_SERVER['REQUEST_URI'];
	$redirect_to = home_url().$Path;

	if ( isset( $data['redirect_to'] ) ) {
		$redirect_to = wp_sanitize_redirect( $data['redirect_to'] ); // If a redirect URL is specified we use it
	} else {

		global $post;

		// Otherwise we try to get the URL of the originating page
		if ( isset( $post ) && $post instanceof WP_Post ) {
			$redirect_to = wp_sanitize_redirect( get_permalink( $post->ID ) );
		}

	}

	/* Make sure registrations are open */
	$registration = wpgs_get_option( 'allow_registrations', 'allow' );

	if ( 'allow' !== $registration ) {
		wpgs_add_error( 'registration_not_allowed', __( 'Registrations are currently not allowed.', 'awesome-support' ) );
		wp_redirect( $redirect_to );
		exit;
	}

	$user               = array();
	$user['email']      = isset( $data['wpgs_email'] ) && ! empty( $data['wpgs_email'] ) ? sanitize_email( $data['wpgs_email'] ) : false;
	$user['first_name'] = isset( $data['wpgs_first_name'] ) && ! empty( $data['wpgs_first_name'] ) ? sanitize_text_field( $data['wpgs_first_name'] ) : false;
	$user['last_name']  = isset( $data['wpgs_last_name'] ) && ! empty( $data['wpgs_last_name'] ) ? sanitize_text_field( $data['wpgs_last_name'] ) : false;
	$user['pwd']        = isset( $data['wpgs_password'] ) && ! empty( $data['wpgs_password'] ) ? $data['wpgs_password'] : false;
	$error              = false;

	foreach ( $user as $field => $value ) {

		if ( empty( $value ) ) {

			if ( false === $error ) {
				$error = new WP_Error();
			}

			$error->add( 'missing_field_' . $field, sprintf( esc_html__( 'The %s field is mandatory for registering an account', 'awesome-support' ), ucwords( str_replace( '_', ' ', $field ) ) ) );

		}

	}

	/**
	 * Give a chance to third-parties to add new checks to the account registration process
	 *
	 * @since 3.2.0
	 * @var bool|WP_Error
	 */
	$errors = apply_filters( 'wpgs_register_account_errors', $error, $user['first_name'], $user['last_name'], $user['email'] );

	if ( false !== $errors ) {

		$notice = implode( '<br>', $errors->get_error_messages() );

		wpgs_add_error( 'registration_error', $notice );
		wp_redirect( $redirect_to );

		exit;

	}

	/**
	 * wpgs_pre_register_account hook
	 *
	 * This hook is triggered all the time
	 * even if the checks don't pass.
	 *
	 * @since  3.0.1
	 */
	do_action( 'wpgs_pre_register_account', $data );

	if ( wpgs_get_option( 'terms_conditions', false ) && ! isset( $data['terms'] ) ) {
		wpgs_add_error( 'accept_terms_conditions', __( 'You did not accept the terms and conditions.', 'awesome-support' ) );
		wp_redirect( $redirect_to );
		exit;
	}

	$username   = sanitize_user( strtolower( $user['first_name'] ) . strtolower( $user['last_name'] ) );
	$user_check = get_user_by( 'login', $username );

	/* Check for existing username */
	if ( is_a( $user_check, 'WP_User' ) ) {
		$suffix = 1;
		do {
			$alt_username = sanitize_user( $username . $suffix );
			$user_check   = get_user_by( 'login', $alt_username );
			$suffix ++;
		} while ( is_a( $user_check, 'WP_User' ) );
		$username = $alt_username;
	}

	/**
	 * wpgs_insert_user_data filter
	 *
	 * @since  3.1.5
	 * @var    array User account arguments
	 */
	$args = apply_filters( 'wpgs_insert_user_data', array(
		'user_login'   => $username,
		'user_email'   => $user['email'],
		'first_name'   => $user['first_name'],
		'last_name'    => $user['last_name'],
		'display_name' => "{$user['first_name']} {$user['last_name']}",
		'user_pass'    => $user['pwd'],
		'role'         => 'wpgs_user'
	) );

	/**
	 * wpgs_register_account_before hook
	 *
	 * Fired right before the user is added to the database.
	 */
	do_action( 'wpgs_register_account_before', $args );

	$user_id = wp_insert_user( apply_filters( 'wpgs_user_registration_data', $args ) );

	if ( is_wp_error( $user_id ) ) {

		/**
		 * wpgs_register_account_before hook
		 *
		 * Fired right after a failed attempt to register a user.
		 *
		 * @since  3.0.1
		 */
		do_action( 'wpgs_register_account_failed', $user_id, $args );

		$error = $user_id->get_error_message();

		wpgs_add_error( 'missing_fields', $error );
		wp_redirect( $redirect_to );

		exit;

	} else {

		/**
		 * wpgs_register_account_before hook
		 *
		 * Fired right after the user is successfully added to the database.
		 *
		 * @since  3.0.1
		 */
		do_action( 'wpgs_register_account_after', $user_id, $args );

		if ( true === apply_filters( 'wpgs_new_user_notification', true ) ) {
			wp_new_user_notification( $user_id );
		}

		if ( headers_sent() ) {
			wpgs_add_notification( 'account_created', __( 'Your account has been created. Please log-in.', 'awesome-support' ) );
			wp_redirect( $redirect_to );
			exit;
		}

		if ( ! is_user_logged_in() ) {

			/* Automatically log the user in */
			wp_set_current_user( $user_id, $user['email'] );
			wp_set_auth_cookie( $user_id );

			wp_redirect( $redirect_to );
			exit;
		}

	}

}

add_action( 'wpgs_do_login', 'wpgs_try_login' );
/**
 * Try to log the user in.
 *
 * This function is hooked onto wpgs_do_login so that the login process can be triggered
 * when the login form is submitted.
 *
 * @since 2.0
 *
 * @param array $data Function arguments (the superglobal vars if the function is triggered by wpgs_do_login)
 *
 * @return void
 */
function wpgs_try_login( $data ) {

	/**
	 * Try to log the user if credentials are submitted.
	 */
	if ( isset( $data['wpgs_log'] ) ) {

		// Get the redirect URL
		$redirect_to = home_url();

		if ( isset( $data['redirect_to'] ) ) {
			$redirect_to = wp_sanitize_redirect( $data['redirect_to'] ); // If a redirect URL is specified we use it
		} else {

			global $post;

			// Otherwise we try to get the URL of the originating page
			if ( isset( $post ) && $post instanceof WP_Post ) {
				$redirect_to = wp_sanitize_redirect( get_permalink( $post->ID ) );
			}

		}

		$credentials = array(
				'user_login' => $data['wpgs_log'],
		);

		if ( isset( $data['rememberme'] ) ) {
			$credentials['remember'] = true;
		}

		$credentials['user_password'] = isset( $data['wpgs_pwd'] ) ? $data['wpgs_pwd'] : '';

		/**
		 * Give a chance to third-parties to add new checks to the login process
		 *
		 * @since 3.2.0
		 * @var bool|WP_Error
		 */
		$login = apply_filters( 'wpgs_try_login', false );

		if ( is_wp_error( $login ) ) {
			$error = $login->get_error_message();
			wpgs_add_error( 'login_failed', $error );
			wp_redirect( $redirect_to );
			exit;
		}

		$login = wp_signon( $credentials );

		if ( is_wp_error( $login ) ) {

			$code = $login->get_error_code();
			$error = $login->get_error_message();

			// Pre-populate the user login if the problem is with the password
			if ( 'incorrect_password' === $code ) {
				$redirect_to = add_query_arg( 'wpgs_log', $credentials['user_login'], $redirect_to );
			}

			wpgs_add_error( 'login_failed', $error );
			wp_redirect( $redirect_to );
			exit;

		} elseif ( $login instanceof WP_User ) {
			wp_redirect( $redirect_to );
			exit;
		} else {
			wpgs_add_error( 'login_failed', __( 'We were unable to log you in for an unknown reason.', 'awesome-support' ) );
			wp_redirect( $redirect_to );
			exit;
		}

	}

}

/**
 * Checks if a user can view a ticket.
 *
 * @since  2.0.0
 *
 * @param  integer $post_id ID of the post to display
 *
 * @return boolean
 */
function wpgs_can_view_ticket( $post_id ) {

	/**
	 * Set the return value to false by default to avoid giving unwanted access.
	 */
	$can = false;

	/**
	 * Get the post data.
	 */
	$post      = get_post( $post_id );
	$author_id = intval( $post->post_author );

	if ( is_user_logged_in() ) {
		if ( get_current_user_id() === $author_id && current_user_can( 'view_ticket' ) || current_user_can( 'edit_ticket' ) ) {
			$can = true;
		}
	}

	return apply_filters( 'wpgs_can_view_ticket', $can, $post_id, $author_id );

}

/**
 * Check if the current user can reply from the frontend.
 *
 * @since  2.0.0
 *
 * @param  boolean $admins_allowed Shall admins/agents be allowed to reply from the frontend
 * @param  integer $post_id        ID of the ticket to check
 *
 * @return boolean                 True if the user can reply
 */
function wpgs_can_reply_ticket( $admins_allowed = false, $post_id = null ) {

	if ( is_null( $post_id ) ) {
		global $post;
		$post_id = $post->ID;
	}

	$admins_allowed = apply_filters( 'wpgs_can_agent_reply_frontend', $admins_allowed ); /* Allow admins to post through front-end. The filter overwrites the function parameter. */
	$post           = get_post( $post_id );
	$author_id      = $post->post_author;

	if ( is_user_logged_in() ) {

		global $current_user;

		if ( ! current_user_can( 'reply_ticket' ) ) {
			return false;
		}

		$user_id = $current_user->data->ID;

		/* If the current user is the author then yes */
		if ( $user_id == $author_id ) {
			return true;
		} else {

			if ( current_user_can( 'edit_ticket' ) && true === $admins_allowed ) {
				return true;
			} else {
				return false;
			}

		}

	} else {
		return false;
	}

}

/**
 * Get user role nicely formatted.
 *
 * @since  3.0.0
 *
 * @param  string $role User role
 *
 * @return string       Nicely formatted user role
 */
function wpgs_get_user_nice_role( $role ) {

	/* Remove the prefix on wpgs roles */
	if ( 'wpgs_' === substr( $role, 0, 5 ) ) {
		$role = substr( $role, 5 );
	}

	/* Remove separators */
	$role = str_replace( array( '-', '_' ), ' ', $role );

	return ucwords( $role );

}

/**
 * Check if the current user has the permission to open a ticket
 *
 * If a ticket ID is given we make sure the ticket author is the current user.
 * This is used for checking if a user can re-open a ticket.
 *
 * @param int $ticket_id
 *
 * @return bool
 */
function wpgs_can_submit_ticket( $ticket_id = 0 ) {

	$can = false;

	if ( is_user_logged_in() ) {

		if ( current_user_can( 'create_ticket' ) ) {
			$can = true;
		}

		if ( 0 !== $ticket_id ) {

			$ticket = get_post( $ticket_id );

			if ( is_object( $ticket ) && is_a( $ticket, 'WP_Post' ) && get_current_user_id() !== (int) $ticket->post_author ) {
				$can = false;
			}

		}

	}

	return apply_filters( 'wpgs_can_submit_ticket', $can );

}

/**
 * Get a list of users that belong to the plugin.
 *
 * @since 3.1.8
 *
 * @param array $args Arguments used to filter the users
 *
 * @return array An array of users objects
 */
function wpgs_get_users( $args = array() ) {

	$defaults = array(
		'exclude'     => array(),
		'cap'         => '',
		'cap_exclude' => '',
		'search'      => array(),
	);

	/* The array where we save all users we want to keep. */
	$list = array();

	/* Merge arguments. */
	$args  = wp_parse_args( $args, $defaults );
	$users = new wpgs_Member_Query( $args );

	return apply_filters( 'wpgs_get_users', $users );

}

/**
 * Get all Awesome Support members
 *
 * @since 3.3
 * @return array
 */
function wpgs_get_members() {

	global $wpdb;

	$query = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE 1 LIMIT 0, 2000" );

	if ( empty( $query ) ) {
		return $query;
	}

	return wpgs_users_sql_result_to_wpgs_member( $query );

}

/**
 * Get all Awesome Support members by their user ID
 *
 * @since 3.3
 *
 * @param $ids
 *
 * @return array
 */
function wpgs_get_members_by_id( $ids ) {

	if ( ! is_array( $ids ) ) {
		$ids = (array) $ids;
	}

	// Prepare the IDs query var
	$ids = implode( ',', $ids );

	global $wpdb;

	$query = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE ID IN ('$ids')" );

	if ( empty( $query ) ) {
		return $query;
	}

	return wpgs_users_sql_result_to_wpgs_member( $query );

}

/**
 * Transform a users SQL query into wpgs_Member_User objects
 *
 * @param array  $results SQL results
 * @param string $class   The wpgs_Member subclass to use. Possible values are user and agent
 *
 * @return array
 */
function wpgs_users_sql_result_to_wpgs_member( $results, $class = 'user' ) {

	$users      = array();
	$class_name = '';

	switch ( $class ) {

		case 'user':
			$class_name = 'wpgs_Member_User';
			break;

		case 'agent':
			$class_name = 'wpgs_member_Agent';
			break;

	}

	if ( empty( $class_name ) ) {
		return array();
	}

	foreach ( $results as $user ) {

		$usr = new $class_name( $user );

		if ( true === $usr->is_member() ) {
			$users[] = $usr;
		}

	}

	return $users;

}

/**
 * Count the total number of users in the database
 *
 * @since 3.3
 * @return int
 */
function wpgs_count_wp_users() {

	$count = get_transient( 'wpgs_wp_users_count' );

	if ( false === $count ) {

		global $wpdb;

		$query = $wpdb->get_results( "SELECT ID FROM $wpdb->users WHERE 1" );
		$count = count( $query );

		set_transient( 'wpgs_wp_users_count', $count, apply_filters( 'wpgs_wp_users_count_transient_lifetime', 604800 ) ); // Default to 1 week

	}

	return $count;

}

/**
 * Check if the WP database has too many users or not
 *
 * @since 3.3
 * @return bool
 */
function wpgs_has_too_many_users() {

	// We consider 3000 users to be too many to query at once
	$limit = apply_filters( 'wpgs_has_too_many_users_limit', 3000 );

	if ( wpgs_count_wp_users() > $limit ) {
		return true;
	}

	return false;

}

add_action( 'user_register',  'wpgs_clear_get_users_cache' );
add_action( 'delete_user',    'wpgs_clear_get_users_cache' );
add_action( 'profile_update', 'wpgs_clear_get_users_cache' );
/**
 * Clear all the users lists transients
 *
 * If a new admin / agent is added, deleted or edited while the users list transient
 * is still valid then the user won't appear / disappear from the users lists
 * until the transient expires. In order to avoid this issue we clear the transients
 * when one of the above actions is executed.
 *
 * @since 3.2.0
 * @return void
 */
function wpgs_clear_get_users_cache() {

	global $wpdb;

	$wpdb->get_results( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE '%s'", '_transient_wpgs_list_users_%' ) );

}

/**
 * List users.
 *
 * Returns a list of users based on the required
 * capability. If the capability is "all", all site
 * users are returned.
 *
 * @param  string $cap Minimum capability the user must have to be added to the list
 *
 * @return array       A list of users
 * @since  3.0.0
 */
function wpgs_list_users( $cap = 'all' ) {

	$list = array();

	/* List all users */
	$all_users = wpgs_get_users( array( 'cap' => $cap ) );

	foreach ( $all_users->members as $user ) {
		$user_id          = $user->ID;
		$user_name        = $user->display_name;
		$list[ $user_id ] = $user_name;
	}

	return apply_filters( 'wpgs_users_list', $list );

}

/**
 * Creates a dropdown list of users.
 *
 * @since  3.1.2
 * @param  array  $args Arguments
 * @return string       Users dropdown
 */
function wpgs_users_dropdown( $args = array() ) {

	global $current_user, $post;

	$defaults = array(
		'name'           => 'wpgs_user',
		'id'             => '',
		'class'          => '',
		'exclude'        => array(),
		'selected'       => '',
		'cap'            => '',
		'cap_exclude'    => '',
		'agent_fallback' => false,
		'please_select'  => false,
		'select2'        => false,
		'disabled'       => false,
		'data_attr'      => array()
	);

	$args = wp_parse_args( $args, $defaults );

	/* List all users */
	$all_users = wpgs_get_users( array( 'cap' => $args['cap'], 'cap_exclude' => $args['cap_exclude'], 'exclude' => $args['exclude'] ) );

	/**
	 * We use a marker to keep track of when a user was selected.
	 * This allows for adding a fallback if nobody was selected.
	 * 
	 * @var boolean
	 */
	$marker = false;

	$options = '';

	/* The ticket is being created, use the current user by default */
	if ( ! empty( $args['selected'] ) ) {
		$user = get_user_by( 'id', intval( $args['selected'] ) );
		if ( false !== $user && ! is_wp_error( $user ) ) {
			$marker = true;
			$options .= "<option value='{$user->ID}' selected='selected'>{$user->data->display_name}</option>";
		}
	}

	foreach ( $all_users->members as $user ) {

		/* This user was already added, skip it */
		if ( ! empty( $args['selected'] ) && $user->user_id === intval( $args['selected'] ) ) {
			continue;
		}

		$user_id       = $user->ID;
		$user_name     = $user->display_name;
		$selected_attr = '';

		if ( false === $marker ) {
			if ( false !== $args['selected'] ) {
				if ( ! empty( $args['selected'] ) ) {
					if ( $args['selected'] === $user_id ) {
						$selected_attr = 'selected="selected"';
					}
				} else {
					if ( isset( $post ) && $user_id == $post->post_author ) {
						$selected_attr = 'selected="selected"';
					}
				}
			}
		}

		/* Set the marker as true to avoid selecting more than one user */
		if ( ! empty( $selected_attr ) ) {
			$marker = true;
		}

		/* Output the option */
		$options .= "<option value='$user_id' $selected_attr>$user_name</option>";

	}

	/* In case there is no selected user yet we add the post author, or the currently logged user (most likely an admin) */
	if ( true === $args['agent_fallback'] && false === $marker ) {
		$fallback    = $current_user;
		$fb_selected = false === $marker ? 'selected="selected"' : '';
		$options .= "<option value='{$fallback->ID}' $fb_selected>{$fallback->data->display_name}</option>";
	}

	$contents = wpgs_dropdown( wp_parse_args( $args, $defaults ), $options );

	return $contents;

}

/**
 * Display a dropdown of the support users.
 *
 * Wrapper function for wpgs_users_dropdown where
 * the cap_exclude is set to exclude all users with
 * the capability to edit a ticket.
 *
 * @since  3.1.3
 * @param  array  $args Arguments
 * @return string       HTML dropdown
 */
function wpgs_support_users_dropdown( $args = array() ) {
	$args['cap_exclude'] = 'edit_ticket';
	$args['cap']         = 'create_ticket';
	echo wpgs_users_dropdown( $args );
}

/**
 * Wrapper function to easily get a user tickets
 *
 * This function is a wrapper for wpgs_get_user_tickets() with the user ID preset
 *
 * @since 3.2.2
 *
 * @param int    $user_id
 * @param string $ticket_status
 * @param string $post_status
 *
 * @return array
 */
function wpgs_get_user_tickets( $user_id = 0, $ticket_status = 'open', $post_status = 'any' ) {

	if ( 0 === $user_id ) {
		$user_id = get_current_user_id();
	}

	$args = array(
		'author' => $user_id,
	);

	$tickets = wpgs_get_tickets( $ticket_status, $args, $post_status );

	return $tickets;

}

add_filter( 'authenticate', 'wpgs_email_signon', 20, 3 );
/**
 * Allow e-mail to be used as the login.
 *
 * @since  3.0.2
 *
 * @param  WP_User|WP_Error|null $user     User to authenticate.
 * @param  string                $username User login
 * @param  string                $password User password
 *
 * @return object                          WP_User if authentication succeed, WP_Error on failure
 */
function wpgs_email_signon( $user, $username, $password ) {

	/* Authentication was successful, we don't touch it */
	if ( is_object( $user ) && is_a( $user, 'WP_User' ) ) {
		return $user;
	}

	/**
	 * If the $user isn't a WP_User object nor a WP_Error
	 * we don' touch it and let WordPress handle it.
	 */
	if ( ! is_wp_error( $user ) ) {
		return $user;
	}

	/**
	 * We only wanna alter the authentication process if the username was rejected.
	 * If the error is different, we let WordPress handle it.
	 */
	if ( 'invalid_username' !== $user->get_error_code() ) {
		return $user;
	}

	/**
	 * If the username is not an e-mail there is nothing else we can do,
	 * the error is probably legitimate.
	 */
	if ( ! is_email( $username ) ) {
		return $user;
	}

	/* Try to get the user with this e-mail address */
	$user_data = get_user_by( 'email', $username );

	/**
	 * If there is no user with this e-mail the error is legitimate
	 * so let's just return it.
	 */
	if ( false === $user_data || ! is_a( $user_data, 'WP_User' ) ) {
		return $user;
	}

	return wp_authenticate_username_password( null, $user_data->data->user_login, $password );

}

add_action( 'wp_ajax_nopriv_email_validation', 'wpgs_mailgun_check' );
/**
 * Check if an e-mail is valid during registration using the MailGun API
 *
 * @param string $data
 */
function wpgs_mailgun_check( $data = '' ) {

	if ( empty( $data ) ) {
		if ( isset( $_POST ) ) {
			$data = $_POST;
		} else {
			echo '';
			die();
		}
	}

	if ( ! isset( $data['email'] ) ) {
		echo '';
		die();
	}

	$mailgun = new wpgs_MailGun_EMail_Check();
	$check   = $mailgun->check_email( $data );

	if ( ! is_wp_error( $check ) ) {

		$check = json_decode( $check );

		if ( is_object( $check ) && isset( $check->did_you_mean ) && ! is_null( $check->did_you_mean ) ) {
			printf( __( 'Did you mean %s', 'awesome-support' ), "<strong>{$check->did_you_mean}</strong>?" );
			die();
		}

	}

	die();

}

add_action( 'wp_ajax_wpgs_get_users', 'wpgs_get_users_ajax' );
/**
 * Get AS users using Ajax
 *
 * @since 3.3
 *
 * @param array $args Query parameters
 *
 * @return void
 */
function wpgs_get_users_ajax( $args = array() ) {

	$defaults = array(
		'cap'         => 'edit_ticket',
		'cap_exclude' => '',
		'exclude'     => '',
		'q'           => '', // The search query
	);

	if ( empty( $args ) ) {
		foreach ( $defaults as $key => $value ) {
			if ( isset( $_POST[ $key ] ) ) {
				$args[ $key ] = $_POST[ $key ];
			}
		}
	}

	$args = wp_parse_args( $args, $defaults );

	/**
	 * @var wpgs_Member_Query $users
	 */
	$users = wpgs_get_users(
		array(
			'cap'         => array_map( 'sanitize_text_field', array_filter( (array) $args['cap'] ) ),
			'cap_exclude' => array_map( 'sanitize_text_field', array_filter( (array) $args['cap_exclude'] ) ),
			'exclude'     => array_map( 'intval', array_filter( (array) $args['exclude'] ) ),
			'search'      => array(
				'query'    => sanitize_text_field( $args['q'] ),
				'fields'   => array( 'user_nicename', 'display_name' ),
				'relation' => 'OR'
			)
		)
	);

	$result = array();

	foreach ( $users->members as $user ) {

		$result[] = array(
			'user_id'     => $user->ID,
			'user_name'   => $user->display_name,
			'user_email'  => $user->user_email,
			'user_avatar' => get_avatar_url( $user->ID, array( 'size' => 32, 'default' => 'mm' ) ),
		);

	}

	echo json_encode( $result );
	die();

}