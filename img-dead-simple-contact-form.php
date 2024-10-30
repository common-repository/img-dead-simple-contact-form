<?php
/*
Plugin Name: IMG Dead Simple Contact Form
Plugin URI: http://wordpress.org/extend/plugins/img-dead-simple-contact-form/
Description: A dead simple way to send email via a contact form
Author: Phil Thompson
Version: 0.95
Author URI: http://imgiseverything.co.uk/
*/

/*
Start the session if it hasn't already (mean) probably overkill but it 
prevents PHP errors sometimes
*/
if(!isset($_SESSION)){
	session_start();
}


// Hidden form field name attribute that robots will hopefull fill in.
define('EMPTY_FORM_VALUE', 'IrTfEmSNhWsVQRzTy7gPAr0J7zEte8oSkjlEDJfgs67dfgter7845648Q6q');


class IMGDeadSimpleContactForm{

	/**
	 *	@var	string
	 *	Value used as a WordPress nonce in the form
	 */
	public $_nonce = 'dscf';
	
	/**
	 *	Constructor
	 *	@return	void
	 */
	public function __construct(){
		// Initialise the session referer to track users
		if (!isset($_SESSION)){
			session_start();
		}
		
		
		// Track user journey
		$this->get_search_term();
		$this->user_path();
		
		if(function_exists('add_shortcode')){
			add_shortcode('dscf', array($this, 'shortcodeDSCF'));
		}

		if(function_exists('add_action')){
			add_action('admin_menu', array($this, 'addMenuPage'));		
		}
		

		
	}
	
	
	/**
	 *	sendEmail
	 *	Sedn the email - with WordPress's built-in wp_mail()
	 *	@return	mixed	bool/array - list of errors if failure.
	 */
	public function sendEmail(){
	
	
		$form_errors = array();
		
	
		// Check required fields are present and email addresses are valid
		if(
			!empty($_POST['full_name']) 
			&& !empty($_POST['email_address']) 
			&& is_email($_POST['email_address']) !== false 
			&& !empty($_POST['message'])
			
			&& empty($_POST[EMPTY_FORM_VALUE]) // <- Bots fill this is… so we want it to be empty
		){
		
		
			// Check for spam - throws 403 if any is found.
			$this->check_for_email_form_spam($_POST['full_name']);
			$this->check_for_email_form_spam($_POST['email_address']);
			$this->check_for_email_form_spam($_POST['message']);
		
		
			// Now start to build up the email values
			$to = get_option('dscf_email');
			
			$headers = "From:" . $_POST['full_name'] . " <" . $_POST['email_address'] . ">\r\nReply-To:" . $_POST['full_name'] . " <" . $_POST['email_address'] . ">\r\nMIME-Version: 1.0\nX-Mailer: PHP/" . phpversion() . "\n";
			$subject = 'Website contact form';
			$message .= $_POST['message'] . "\n\nFrom: " . $_POST['full_name'] . "\n" . $_POST['email_address'];
			
			
			if(!empty($_POST['telephone'])){
				$message .= "\nTelephone: " . $_POST['telephone'];
			}
			
			
			$message .= "\n\nUser journey:\n" . $this->readable_path();
			
			// Send email
			wp_mail($to, $subject, $message, $headers);
	
			$form_success = true;
			
			
			
		} else{
		
			// Fields are missing/invalid
			
			if(empty($_POST['full_name'])){
				$form_errors['Full name'] = 'is missing';
			}
			
			if(empty($_POST['email_address'])){
				$form_errors['Email address'] = 'is missing';		
			} else if(is_email($_POST['email_address']) === false){
				$form_errors['Email address'] = 'is invalid';
			}
			
			if(empty($_POST['message'])){
				$form_errors['Your message'] = 'is missing';
			}
			
			
			// Spam attempt
			if(!empty($_POST[EMPTY_FORM_VALUE])){
				$form_errors['Hmm'] = ' something was not quite right';
			}
			
			return $form_errors;
			
		}

		
		
	}
	
	
	/**
	 *	check_for_email_form_spam
	 *	prevents email injection attacks by checking supplied
	 *  string for common spam characteristics and return the string
	 *	with those elements removed
	 *
	 *	@param	string email body content or subject or email address
	 *	@return	boolean
	 */
	public function check_for_email_form_spam($string) {
	
		$spam_terms = array(
			"/%0a/", 
			"/%0d/", 
			"/Content-Type:/i", 
			"/Content-Transfer-Encoding:/i",
			"/mime-version:/i",
			"/multipart\/mixed/i",
			"/bcc:/i", 
			"/to:/i", 
			"/cc:/i"
		);
	
		$safe = (preg_replace($spam_terms, "", strtolower($string)));
		if($safe != strtolower($string)){
			header("HTTP/1.0 403 Forbidden");
			exit;
		} else{
			return true;
		}
		
	}
	
	
	/**
	 *	get_search_term
	 *	If gthe user came from a search engine then which one was it and what keyword did they use?
	 *	@return	void
	 */
	public function get_search_term() {
	
		$query = $_SERVER['HTTP_REFERER'];
		parse_str($query, $ref_get);
		
		$pattern = '';
		$query_string = '';
		$engine = '';
		
		if(strpos($query, "google") !== false){ // hmm this isn't right I suspect:(
			$engine = 'Google';
		} else if(strpos($query, "msn." !== false) || strpos($query, "live") !== false){
			$engine = 'MSN/Live';
		}  else if(strpos($query, "bing.") !== false){
			$engine = 'Bing';
		} else if(strpos($query, "yahoo.") !== false){
			$engine = 'Yahoo';
		} else if(strpos($query, "ask." ) !== false){
			$engine = 'Ask Jeeves';
		}

		
		if (!isset($_SESSION['keyword']) && !empty($ref_get['q'])){
			$_SESSION['keyword'] = urldecode($ref_get['q']) . ' (' . $engine . ')';
		}
		
	}	
	/**
	 * 	tu_user_path
	 *	Tracks the user's path around the site
	 *	@return	void
	 */
	public function user_path() {

		if(!isset($_SESSION['user_path'])){
			$_SESSION['user_path'] = array();
		} 
		
		// add last referrer to the path - unless the user's on the same page
		if(
			$_SERVER['HTTP_REFERER'] != end($_SESSION['user_path'])
			&& strpos($_SERVER['HTTP_REFERER'], '.css') === false
			&& strpos($_SERVER['HTTP_REFERER'], '.js') === false
			&& strpos($_SERVER['HTTP_REFERER'], '/wp-admin') === false
		){
			
			$_SESSION['user_path'][] = $_SERVER['HTTP_REFERER'];
			
		}
		
		//print_r($_SESSION['user_path']);  // Debugging
		
	}
	
	
	/**
	 * 	tu_readable_path
	 *	Takes the session tracking data and turns it into human-readable
	 *	plain text
	 *	@return string $path
	 */
	public function readable_path(){
	
		$path = '';
		
		if(!empty($_SESSION['keyword'])){
			$path .= 'User came via the search: ' . $_SESSION['keyword'] . "\n\n";
		}
	
		if(!empty($_SESSION['user_path'])){
			$i = 0;
			foreach($_SESSION['user_path'] as $page){
			
				if($i == 0){
					$path .= 'Referrer: ';
				} else if(!empty($page)){
					$path .= 'Page ' . $i;
				}
				
				$path .= ': ' . $page . "\n";
				
				
				$i++;
			}
			
			//$path .= 'Last page: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; // Debugging
		}
		
		return $path;
		
	}
	
	
	/**
	 *	addMenuPage
	 */
	public function addMenuPage(){

		if(function_exists('add_menu_page')){
			add_menu_page( 'Dead Simple Contact Form', 'Dead Simple Contact Form', 'manage_options', 'dscf', array($this, 'adminSettings'), null );
		}
		
	}
	
	
	/**
	 *	adminSettings
	 */
	public function adminSettings(){
	
		// Only allow the right people to update this page
	
		if (!current_user_can('manage_options'))  {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}
		

		$error = $updates = 0;
		
		
		// Update the values (if set and posted)
		if($_SERVER['REQUEST_METHOD'] == 'POST'){
		
			if ( !wp_verify_nonce( $_POST[$this->_nonce], plugin_basename(__FILE__) ) ){
	    		wp_die( 'Oops something went wrong here.' );
			}
		
			
			// Only the dscf_email is required so check that is present before updating any options
			if(!empty($_POST['dscf_email'])){
				
				update_option('dscf_email', $_POST['dscf_email']);			
				update_option('dscf_intro_message', $_POST['dscf_intro_message']);
				update_option('dscf_thankyou_message', $_POST['dscf_thankyou_message']);
				update_option('dscf_ask_for_telephone', $_POST['dscf_ask_for_telephone']);	
				$updates++;
				
			} else{
				$errors++;
			}
		
		}
	
		// Grab the set values
	
		$dscf_email = get_option('dscf_email');
		
		if(empty($dscf_email)){
			$dscf_email = get_option('admin_email');
		}
		
		$dscf_intro_message = stripslashes(get_option('dscf_intro_message'));
		$dscf_thankyou_message = stripslashes(get_option('dscf_thankyou_message'));
		
		
		$ask_for_telephone = get_option('dscf_ask_for_telephone');

	
?>	
		<div class="wrap">
			<h2>IMG Dead Simple Contact Form Settings</h2>
			<?php if($updates > 0): ?>
			<div class="updated" id="message">
				<p><strong>Settings saved.</strong></p>
			</div>
			<?php elseif($errors > 0): ?>
			<div class="error" id="message">
				<p><strong>Please make sure you enter an email address.</strong></p>
			</div>
			<?php endif; ?>
			<form action="/wp-admin/admin.php?page=dscf" method="post">
				<?php wp_nonce_field( plugin_basename(__FILE__), $this->_nonce ); ?>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<label for="dscf_email">Email address</label>
							</th>
							<td>
								<input type="email" class="regular-text" value="<?php echo $dscf_email; ?>" id="dscf_email" name="dscf_email">
								<span class="description">This is the email address where contact forms get sent to.</span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="dscf_intro_message">Introductory message</label>
							</th>
							<td>
								<?php wp_editor( $dscf_intro_message, 'dscf_intro_message', array('textarea_rows' => 3) ); ?>
								<span class="description">The message to display to users above the form.</span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="dscf_thankyou_message">Thank you message</label>
							</th>
							<td>
								<?php wp_editor( $dscf_thankyou_message, 'dscf_thankyou_message', array('textarea_rows' => 3) ); ?>
								<span class="description">The message to display to users upon a successful form submission.</span>
							</td>
						</tr>
						<tr valign="top">
							<td>Extra fields</td>
							<td>
								<input type="hidden" value="no" name="dscf_ask_for_telephone">
								<input type="checkbox" value="yes" id="ask_for_telephone" name="dscf_ask_for_telephone"<?php echo ($ask_for_telephone == 'yes') ? ' checked="checked"' : ''; ?>>
								<label for="ask_for_telephone">Ask users for their telephone number?</label>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit"><input type="submit" value="Save Changes" class="button-primary" id="submit" name="submit"></p>
			</form>	
		</div>


<?php		
	
	}
	
	
	
	/**
	 *	shortcodeDSCF
	 *	@param	array	attributes
   	 *	@param	string	text within enclosing form of shortcode element
     *	@param	string	the shortcode found, when == callback name
	 */
	public function shortcodeDSCF($atts, $content = null, $code = ''){
	
		global $post;
		
		$form_errors_display = '';
		
		$form_sent = $form_success = false;
		
		
		$ask_for_telephone = get_option('dscf_ask_for_telephone');
		
		
		// Check the contact form has been sent and then process it
		if(
			!isset($_GET['sent'])
			&& $_SERVER['REQUEST_METHOD'] == 'POST' 
			&& !empty($_POST['action']) && $_POST['action'] == 'email' 
			&& wp_verify_nonce($_POST[$this->_nonce], plugin_basename(__FILE__))
		){
			$form_sent = true;
			$form_errors = $this->sendEmail();
			
			// Everything went well so redirect the user to /this-page/?sent so we know
			// NOTE: Sometimes $_GET variables get ignored on some servers!
			if(empty($form_errors)){
				$form_success = true;
				//wp_redirect('/contact/?sent');
			}
			
		}
		
		// Create form errors to display at above the form fields (we'll also display inline errors too)
		if(!empty($form_errors)){ 
	
			$form_errors_display .= '<label class="error">Please correct the following errors:</label>
			<ul class="errors">'; 
			
			foreach($form_errors as $field => $error){
				$form_errors_display .= '<li><label class="error">' . $field . ' ' . $error . '</label></li>'; 
			}
			
			$form_errors_display .= '</ul>';
			
		}
		
		
		// Form was successful
		if(isset($_GET['sent'])){
			$form_sent = $form_success = true;
		}
		
		if($form_sent !== true || ($form_sent === true && $form_success !== true)){
		
		$html .= wpautop(stripslashes(get_option('dscf_intro_message')));

		
		$html .= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
			<fieldset>';
		$html .= $form_errors_display; 
		$html .= wp_nonce_field( plugin_basename(__FILE__), $this->_nonce, true, false );
		
		
		// Full name field
		$html .= '<div class="field' . $this->errorClass($form_errors, 'Full name') . '">
					<label for="FullName">Full name <span class="required" title="This field is required">*</span></label>
					<input type="text" name="full_name" id="FullName" value="' . $this->get_parameter($_POST, 'full_name') . '" required="required" aria-required="true" />
				</div>';
			
		// Email address field
		$html .= '<div class="field' . $this->errorClass($form_errors, 'Email address') . '">
					<label for="EmailAddress">Email address <span class="required" title="This field is required">*</span></label>
					<input type="email" name="email_address" id="EmailAddress" value="' . $this->get_parameter($_POST, 'email_address') . '" required="required" aria-required="true" />
				</div>';
				
		if($ask_for_telephone == 'yes'){
			// Telephone field
			$html .= '<div class="field' . $this->errorClass($form_errors, 'Telephone') . '">
					<label for="Telephone">Telephone</label>
					<input type="tel" name="telephone" id="Telephone" value="' . $this->get_parameter($_POST, 'telephone') . '" />
				</div>';
		}
		
		// Message textarea
		$html .= '<div class="field' . $this->errorClass($form_errors, 'Your message') . '">
					<label for="YourMessage">Message <span class="required" title="This field is required">*</span></label>
					<textarea name="message" id="YourMessage" required="required" aria-required="true" rows="10" cols="40">' . $this->get_parameter($_POST, 'message') . '</textarea>
				</div>';
		
		$html .= '
				<input type="text" class="hidden" name="' . EMPTY_FORM_VALUE . '" value="" />
				<input type="hidden" name="action" value="email" />
				<div class="group buttons field">
					<button type="submit">Send</button>
				</div>
			</fieldset>
		</form>';
	
		} else{
			$html = wpautop(stripslashes(get_option('dscf_thankyou_message')));
		}
		
		
		
		return $html;
	
	
	}
	
	/**
	 *	errorClass
	 *	
	 *	@param	array (list of errors)
	 *	@param	string	e.g. 'Full name' or 'Email address'
	 *	@return string either ' class'  or null;
	 */
	public function errorClass($form_errors, $field){
		return (!empty($form_errors) && array_key_exists($field, $form_errors) === true) ? ' error' : '';
	}
	
	
	
	/**
	 *	get_parameter
	 *	Display array parameters on the page and avoid
	 *	php errors for missing array keys
	 *
	 *	@param 	string $key
	 *	@return string $value;
	 *	@usage	echo get_parameter($_POST, 'foo', 'bar'); or go deep yo
	 *			get_parameter($_POST, 'foo', get_parameter($_GET, 'foo', 'bar'));
	 */
	public function get_parameter($object, $key, $default = ''){
	
		$value = $default;
		
		if(isset($object[$key])){
			$value = $object[$key];
		}
		
		return $value;
	}
	


}



$objDSCF = new IMGDeadSimpleContactForm();