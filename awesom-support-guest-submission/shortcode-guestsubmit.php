<?php
add_shortcode( 'ticket-guest-submit', 'wpas_gs_submit_form' );
/**
 * Submission for shortcode.
 */
function wpas_gs_submit_form() {

	global $post;

	/* Start the buffer */
	ob_start();

	/* Open main container */
	


$submit        = get_permalink( wpas_get_option( 'ticket_list' ) );
$registration  = wpas_get_option( 'allow_registrations', 'allow' ); // Make sure registrations are open
$redirect_to   = get_permalink( $post->ID );
$wrapper_class = 'allow' !== $registration ? 'wpas-submit-login-only' : 'wpas-submit-login-register';
?>



<div class="wpas wpas-submit-ticket">
<?php wpas_get_template( 'partials/ticket-navigation' ); ?>
	<?php do_action('wpas_before_login_form'); ?>

		<form class="wpas-form" role="form" method="post" action="<?php echo get_permalink( $post->ID ); ?>" id="wpas-new-ticket" enctype="multipart/form-data">
		<div class="wpas <?php echo $wrapper_class; ?>">
			<?php
			
			//wpmem_login_status();

		$fields = WPAS()->session->get( 'submission_form' );
		
		$current_user = wp_get_current_user();

		if ( 0 == $current_user->ID ) {
		 if ( !empty($fields)) {
		 	WPAS()->session->clean( 'submission_form');

		 		$loginstatus = 'failed';

			$full_name = new WPAS_Custom_Field( 'full_name', array(
				'name' => 'full_name',
				'args' => array(
					'required'    => true,
					'field_type'  => 'text',
					'label'       => __( 'Full Name', 'awesome-support' ),
					'placeholder' => __( 'Full Name', 'awesome-support' ),
					'sanitize'    => 'sanitize_text_field'
				)
			) );

			echo $full_name->get_output();

			}}

			$email = new WPAS_Custom_Field( 'email', apply_filters('emailintput',array(
				'name' => 'email',
				'args' => array(
					'required'    => true,
					'field_type'  => 'email',
					'label'       => __( 'Email', 'awesome-support' ),
					'placeholder' => __( 'Email', 'awesome-support' ),
					'sanitize'    => 'sanitize_text_field'
				)
			) ));

			echo $email->get_output();

			$pwd = new WPAS_Custom_Field( 'password', array(
				'name' => 'password',
				'args' => array(
					'required'    => true,
					'field_type'  => 'hidden',
					'label'       => __( 'Enter a password', 'awesome-support' ),
					'placeholder' => __( 'Password', 'awesome-support' ),
					'sanitize'    => 'sanitize_text_field'
				)
			) );

			echo $pwd->get_output();

			// $showpwd = new WPAS_Custom_Field( 'pwdshow', array(
			// 	'name' => 'pwdshow',
			// 	'args' => array(
			// 		'required'   => false,
			// 		'field_type' => 'checkbox',
			// 		'sanitize'   => 'sanitize_text_field',
			// 		'options'    => array( '1' => _x( 'Show Password', 'Login form', 'awesome-support' ) ),
			// 	)
			// ) );

			// echo $showpwd->get_output();

			/**
			 * wpas_after_registration_fields hook
			 * 
			 * @Awesome_Support::terms_and_conditions_checkbox()
			 */
			do_action( 'wpas_after_registration_fields' );
			// wpas_do_field( 'register', $redirect_to );
			// wp_nonce_field( 'register', 'user_registration', false, true );
			// wpas_make_button( __( 'Create Account', 'awesome-support' ), array( 'onsubmit' => __( 'Creating Account...', 'awesome-support' ) ) );
			echo "</div>";
			/**
			 * The wpas_submission_form_inside_before has to be placed
			 * inside the form, right in between the form opening tag
			 * and the subject field.
			 *
			 * @since  3.0.0
			 */
			do_action( 'wpas_submission_form_inside_before_subject' );

			/**
			 * Filter the subject field arguments
			 *
			 * @since 3.2.0
			 */
			$subject_args = apply_filters( 'wpas_subject_field_args', array(
				'name' => 'title',
				'args' => array(
					'required'   => true,
					'field_type' => 'text',
					'label'      => __( 'Subject', 'awesome-support' ),
					'sanitize'   => 'sanitize_text_field'
				)
			) );

			$subject = new WPAS_Custom_Field( 'title', $subject_args );
			echo $subject->get_output();

			/**
			 * The wpas_submission_form_inside_after_subject hook has to be placed
			 * right after the subject field.
			 *
			 * This hook is very important as this is where the custom fields are hooked.
			 * Without this hook custom fields would not display at all.
			 *
			 * @since  3.0.0
			 */
			do_action( 'wpas_submission_form_inside_after_subject' );

			/**
			 * Filter the description field arguments
			 *
			 * @since 3.2.0
			 */
			$body_args = apply_filters( 'wpas_description_field_args', array(
				'name' => 'message',
				'args' => array(
					'required'   => true,
					'field_type' => 'wysiwyg',
					'label'      => __( 'Description', 'awesome-support' ),
					'sanitize'   => 'sanitize_text_field'
				)
			) );

			$body = new WPAS_Custom_Field( 'message', $body_args );
			echo $body->get_output();			

			/**
			 * The wpas_submission_form_inside_before hook has to be placed
			 * right before the submission button.
			 *
			 * @since  3.0.0
			 */
			do_action( 'wpas_submission_form_inside_before_submit' );

			if ($loginstatus=='failed'){

			$loginfailed = 'failed';					
			wpas_do_field( 'register_submit_new_ticket' );
			wp_nonce_field( 'new_ticket', 'wpas_nonce', true, true );
			wpas_make_button( __( 'Register and Submit ticket', 'awesome-support' ), array( 'name' => 'wpas-submit' ) );

		

			}

			else{
			wpas_do_field( 'submit_new_ticket' );
			wp_nonce_field( 'new_ticket', 'wpas_nonce', true, true );
			wpas_make_button( __( 'Submit ticket', 'awesome-support' ), array( 'name' => 'wpas-submit' ) );			

			}


				?>
		</form>
</div><?php

	
	/* Get buffer content */
	$sc = ob_get_contents();

	/* Clean the buffer */
	ob_end_clean();

	/* Return shortcode's content */
	return $sc;

}