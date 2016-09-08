<?php	
add_action('wpas_autologin_before_submite', 'guest_submit_autologin');
global $post;

//$submit        = get_permalink( wpas_get_option( 'ticket_list' ) );
function guest_submit_autologin(){
	if ( email_exists( $_POST['wpas_email'])) {

		//wp_login($_POST['wpas_log'],$_POST['wpas_pwd']);
		if ( ! is_user_logged_in() ) {

			$user = get_user_by( 'email', $_POST['wpas_email'] );
			// Automatically log the user in
			wp_set_current_user( $user->ID, $_POST['wpas_email'] );
			wp_set_auth_cookie( $user->ID );
			//wpas_add_error( 'unknown_user1', $user->ID, 'awesome-support' );
			$login = apply_filters( 'wpas_try_login', false );
			//wp_redirect( $redirect_to );
			
		}

		//global $current_user;

		//$user_id = $current_user->ID;



	} else {


		//add_action( 'wpas_submission_form_inside_before_email','passform',20,1);
		// Save the input

		wpas_save_values();

		// Redirect to submit page
		wpas_add_error( 'unknown_user', __( 'There is no email address in system!', 'awesome-support' ) );
		$Path=$_SERVER['REQUEST_URI'];
		$URI=home_url().$Path;
		
		wp_redirect( $URI);		
		exit;
		
	
	}
	//return true;
}

add_action('wpas_do_register_submit_new_ticket','registersubmit');

function registersubmit(){
	$data = array('redirect_to' => 'http://localhost/roverrt/my-ticket/','wpas_email' => '123ggg@gmail.com','wpas_first_name' => 'first32','wpas_last_name' => 'last32','wpas_password' =>'password31');
wpas_register_account($data);
}
//add_action('register_submit_new_ticket',array('wpas_email' => 'ggg@gmail.com','first_name' => 'first','last_name' => 'last','pwd' =>'password'));
										
										
//do_action('wpas_do_login');
