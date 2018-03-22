<?php
/**
* Plugin Name: Connects - MailWizz Addon
* Plugin URI:
* Description: Use this plugin to integrate Mailwizz with Connects.
* Version: 1.0.0
* Author: Brainstorm Force.
* Author URI: https://www.brainstormforce.com/
* License: http://themeforest.net/licenses
*/

if(!class_exists('Smile_Mailer_Mailwizz')) {
    class Smile_Mailer_Mailwizz {
        private $slug;
        private $setting;
        private $group;
        function __construct(){
           
            require_once('MailWizzApi/Autoloader.php');

            add_action( 'wp_ajax_get_mailwizz_data', array( $this,'get_mailwizz_data' ));
            add_action( 'wp_ajax_update_mailwizz_authentication', array( $this,'update_mailwizz_authentication' ));
            add_action( 'admin_init', array( $this, 'enqueue_scripts' ) );
            add_action( 'wp_ajax_disconnect_mailwizz', array( $this, 'disconnect_mailwizz' ));
            add_action( 'wp_ajax_mailwizz_add_subscriber', array( $this, 'mailwizz_add_subscriber' ));
            add_action( 'wp_ajax_nopriv_mailwizz_add_subscriber', array( $this, 'mailwizz_add_subscriber' ));

            $this->setting  = array(
                'name' => 'Mailwizz',
                'parameters' => array( 'api_key' ),
                'where_to_find_url' => 'http://kb.mailwizz.com/accounts/management/about-api-keys',
                'logo_url' => plugins_url('images/logo.png', __FILE__)
            );
            $this->slug = 'mailwizz';
            $this->group = array();

            $this->components = array(
                'cache' => array(
                    'class'     => 'MailWizzApi_Cache_File',
                    'filesPath' => 'MailWizzApi\Cache\data\cache',
                ),
            );
            
        }

        /**
         * Function Name: enqueue_scripts
         * Function Description: Add custon scripts
         * @since 1.0
         */

        function enqueue_scripts() {

            if( function_exists( 'cp_register_addon' ) ) {
                cp_register_addon( $this->slug, $this->setting );
            }
            $data  =  get_option( 'convert_plug_debug' );
            wp_register_script( $this->slug.'-script', plugins_url('js/'.$this->slug.'-script.js', __FILE__), array('jquery'), '1.1', true );
            wp_enqueue_script( $this->slug.'-script' );
            add_action( 'admin_head', array( $this, 'hook_css' ) );  

        }

        /**
         * Function Name: hook_css
         * Function Description: Adds background style script for mailer logo.
         * @since 1.0
         */
        function hook_css() {
            if( isset( $this->setting['logo_url'] ) ) {
                if( $this->setting['logo_url'] != '' ) {
                    $style = '<style>table.bsf-connect-optins td.column-provider.'.$this->slug.'::after {background-image: url("'.$this->setting['logo_url'].'");}.bend-heading-section.bsf-connect-list-header .bend-head-logo.'.$this->slug.'::before {background-image: url("'.$this->setting['logo_url'].'");}.cn-form-check > label { float: left; width: 75%;}.cn-form-check { margin-top: 15px; width: 100%; display: inline-block;}.bsf-cnlist-form-row div { cursor: pointer;}.cn-form-check .switch-wrapper { float: left; width: 7%; margin-top: 0;}span.cp-group-notice { float: left;}.bsf-cnlist-form-row #mailwizz_group_id_list{display:none;}.bsf-cnlist-form-row select#mailwizz-group {padding-left: 0!important;padding-right: 0!important;}.bsf-cnlist-form-row select#mailwizz-group option{padding-left: 10px;padding-right: 10px;}</style>';
                    echo $style;
                }
            }
        }

        /**
        * retrieve mailer info
        * @since 1.0
        */
        function get_mailwizz_data(){
        	
            if ( ! current_user_can( 'access_cp' ) ) {
                die(-1);
            }

            $isKeyChanged = false;
            $list_arr = array();
            $connected = false;
            ob_start();
            $mw_api = get_option($this->slug.'_api');
            $mw_public_key = get_option($this->slug.'_public_key');
            $mw_private_key = get_option($this->slug.'_private_key');

            if( $mw_api ) {
            	$request = $this->get_mailwizz_lists(  $mw_api, $mw_public_key, $mw_private_key );
                if( isset( $request->status ) ) {
                    if( $request->status == 'error' && $request->code == 104  ) {
                        $formstyle = '';
                        $isKeyChanged = true;
                    }
                } else {
                    $formstyle = 'style="display:none;"';
                }

            } else {
                $formstyle = '';
            }
            ?>
            <div class="bsf-cnlist-form-row" <?php echo $formstyle; ?>>
                <label for="cp-list-name" ><?php _e( $this->setting['name'] . " API URL", "smile" ); ?></label>
                <input type="text" autocomplete="off" id="<?php echo $this->slug; ?>_api_key" name="<?php echo $this->slug; ?>-auth-key" value="<?php echo esc_attr( $mw_api ); ?>"/>
            </div>
             <div class="bsf-cnlist-form-row" <?php echo $formstyle; ?>>
                <label for="cp-list-name" ><?php _e( $this->setting['name'] . "Publick Key", "smile" ); ?></label>
                <input type="text" autocomplete="off" id="<?php echo $this->slug; ?>_public_key" name="<?php echo $this->slug; ?>-public-key" value="<?php echo esc_attr( $mw_public_key ); ?>"/>
            </div>
             <div class="bsf-cnlist-form-row" <?php echo $formstyle; ?>>
                <label for="cp-list-name" ><?php _e( $this->setting['name'] . " Private Key", "smile" ); ?></label>
                <input type="text" autocomplete="off" id="<?php echo $this->slug; ?>_private_key" name="<?php echo $this->slug; ?>-private-key" value="<?php echo esc_attr( $mw_private_key ); ?>"/>
            </div>

            <div class="bsf-cnlist-form-row <?php echo $this->slug; ?>-list">
                <?php
                if( $mw_api != '' && !$isKeyChanged ) {
                    $mc_lists = $request;

                    if( !empty( $mc_lists ) ){
                        $connected = true;
                    ?>
                    <label for="<?php echo $this->slug; ?>-list"><?php echo __( "Select List", "smile" ); ?></label>
                        <select id="<?php echo $this->slug; ?>-list" class="bsf-cnlist-select" name="<?php echo $this->slug; ?>-list">
                        <?php
                        foreach($mc_lists as $id => $name) {
                        ?>
                        <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                        <?php
                        }
                        ?>
                        </select>
                        <?php
                    } else {
                    ?>
                        <label for="<?php echo $this->slug; ?>-list"><?php echo __( "You need at least one list added in " . $this->setting['name'] . " before proceeding.", "smile" ); ?></label>
                    <?php
                    }
                }
                ?>
            </div>

            <div class="bsf-cnlist-form-row">
                <?php if( $mw_api == "" ) { ?>
                    <button id="auth-<?php echo $this->slug; ?>" class="button button-secondary auth-button" disabled><?php _e( "Authenticate " . $this->setting['name'], "smile" ); ?></button><span class="spinner" style="float: none;"></span>
                <?php } else {
                        if( $isKeyChanged ) {?>
                    <div id="update-<?php echo $this->slug; ?>" class="update-mailer" data-mailerslug="<?php echo $this->setting['name']; ?>" data-mailer="<?php echo $this->slug; ?>"><span><?php _e( "Your credentials seems to be changed.</br>Use different '". $this->setting['name'] ."' credentials?", "smile" ); ?></span></div><span class="spinner" style="float: none;"></span>
                <?php
                        } else {
                ?>
                    <div id="disconnect-<?php echo $this->slug; ?>" class="button button-secondary" data-mailerslug="<?php echo $this->setting['name']; ?>" data-mailer="<?php echo $this->slug; ?>"><span><?php _e( "Use different '".$this->setting['name']."' account?", "smile" ); ?></span></div><span class="spinner" style="float: none;"></span>
                <?php
                        }
                ?>
                <?php } ?>
            </div>

            <?php
            $content = ob_get_clean();

            $result['data'] = $content;
            $result['helplink'] = esc_url( $this->setting['where_to_find_url'] );
            $result['isconnected'] = $connected;
            echo json_encode($result);
            exit();

        }
     
     	 /**
         * [get_mailwiz_api description]
         */
        function cp_get_mailwiz_api( $api_url = '', $public_key = '', $private_key = '' ){
        	if ( class_exists( 'MailWizzApi_Autoloader' ) ) {                   
                $api_url = $api_url . '/index.php';
                MailWizzApi_Autoloader::register(); 
                if ( class_exists( 'MailWizzApi_Config' ) ) {
                    $config = new MailWizzApi_Config(
                        array(
                            'apiUrl'     => $api_url,
                            'publicKey'  => $public_key,
                            'privateKey' => $private_key,
                            'components' => $this->components,
                        )
                    );
                }
            }                       
        }


        /**
        * Add subscriber to mailwizz
        * @since 1.0
        */
        function mailwizz_add_subscriber() {

            $ret = true;
            $data = array();
            $email_status = false;
            $style_id = isset( $_POST['style_id'] ) ? esc_attr( $_POST['style_id'] ) : '';
            $group_id = $group_ids = '';

            if( $style_id !=='' ){
                check_ajax_referer( 'cp-submit-form-'.$style_id );
            }

            $api_key = get_option( 'mailwizz_api' );
            $public_key = get_option( 'mailwizz_public_key' );
            $private_key = get_option( 'mailwizz_private_key' );

            //$config = cp_get_mailwiz_api( $api_key, $public_key, $private_key );
           
            $contact = array_map( 'sanitize_text_field', wp_unslash( $_POST['param'] ) );
            $list_id = isset( $_POST['list_id'] ) ? esc_attr( $_POST['list_id'] ) : '';

            $contact['source'] = ( isset( $_POST['source'] ) ) ? esc_attr( $_POST['source'] ) : '';
            $msg = isset( $_POST['message'] ) ? $_POST['message'] : __( 'Thanks for subscribing. Please check your mail and confirm the subscription.', 'smile' );

            $optinvar   = get_option( 'convert_plug_settings' );
            $d_optin    = isset( $optinvar['cp-double-optin'] ) ? $optinvar['cp-double-optin'] : 1;
            $redirect  = isset( $_POST['redirect'] ) ? true : false;

            $debug_data = get_option( 'convert_plug_debug' );
            $sub_def_action = isset( $debug_data['cp-post-sub-action'] ) ? $debug_data['cp-post-sub-action'] : 'process_success';

            if ( is_user_logged_in() && current_user_can( 'access_cp' ) ) {
                $default_error_msg = __( 'THERE APPEARS TO BE AN ERROR WITH THE CONFIGURATION.', 'smile' );
            } else {
                $default_error_msg = __( 'THERE WAS AN ISSUE WITH YOUR REQUEST. Administrator has been notified already!', 'smile' );
            }
           

            //    Check Email in MX records
            if( isset( $contact['email'] ) ) {
                $email_status = ( !( isset( $_POST['only_conversion'] ) ? true : false ) ) ? apply_filters('cp_valid_mx_email', $contact['email'] ) : false;
            }

            if( isset( $contact['email'] ) && $email_status ) {

                if( function_exists( "cp_add_subscriber_contact" ) ){
                    $isuserupdated = cp_add_subscriber_contact( '', $contact );
                }

                if ( !$isuserupdated ) {  // if user is updated don't count as a conversion
                    // update conversions
                    smile_update_conversions($style_id);
                }

                if( isset( $contact['email'] ) ) {

                    $status = 'success';
                    $merge_arr = array();
                    unset( $contact['source'] );

                    $email = $contact['email'];

                    $data = array(
                        'EMAIL' => $email,                        
                    );

                    foreach( $contact as $key => $p ) {
                        if( $key != 'email' && $key != 'user_id' && $key != 'date' ){
                            $data[$key] = $p;
                        }
                    }                    	

                    $json_data = $data;

                    if ( class_exists( 'MailWizzApi_Autoloader' ) ) {                   
		                $api_url = $api_key . '/index.php';
		                MailWizzApi_Autoloader::register(); 
		                if ( class_exists( 'MailWizzApi_Config' ) ) {               	
		                	
		                    $config = new MailWizzApi_Config(
		                        array(
		                            'apiUrl'     => $api_url,
		                            'publicKey'  => $public_key,
		                            'privateKey' => $private_key,
		                            'components' => $this->components,
		                        )
		                    );

		                    MailWizzApi_Base::setConfig( $config );
		                    // Add subscribers.
							$endpoint = new MailWizzApi_Endpoint_ListSubscribers();
							
							//check if subscriber is already subscribed or not?
							$res = $endpoint->emailSearch( $list_id, $email);
							$resp_status   = $res->body->itemAt( 'status' );
							
							if( $resp_status == 'error'){
								
								$resp     = $endpoint->createUpdate( $list_id, $data );
								$res_status   = $resp->body->itemAt( 'status' );

								if( $res_status !== 'success'){
									$ret = false;
		                            $status = 'error';
		                            $msg = $default_error_msg;
								}

							}else{
								//Already subscribed.
								if ( $sub_def_action !== 'process_success' ) {

		                            if( $redirect ) {
		                                $ret = false;
		                                $status = 'error';
		                                $msg = $default_error_msg;
		                            }

		                            //  Show message for already subscribed users.
		                            $msg = ( $optinvar['cp-default-messages'] ) ? isset( $optinvar['cp-already-subscribed']) ? stripslashes( $optinvar['cp-already-subscribed'] ) : __( 'Already Subscribed!', 'smile' ) : __( 'Already Subscribed!', 'smile' );

		                        } else {
		                        	
		                            $resp     = $endpoint->createUpdate( $list_id, $data );
									$res_status   = $resp->body->itemAt( 'status' );

									if( $res_status !== 'success'){
										$ret = false;
			                            $status = 'error';
			                            $msg = $default_error_msg;
									}
		                        }
							}
							
		                }
		            } else{
		            	$ret = false;
                        $status = 'error';
                        $msg = __( 'Please check your MailWizz account credentials.', 'smile' );
		            }
                }
            } else {
                if( isset( $_POST['only_conversion'] ) ? true : false ) {
                    // update conversions
                    $status = 'success';
                    smile_update_conversions( $style_id );
                    $ret = true;
                } else if( isset( $contact['email'] ) ) {
                    $msg = ( isset( $_POST['msg_wrong_email']  )  && $_POST['msg_wrong_email'] !== '' ) ? $_POST['msg_wrong_email'] : __( 'Please enter correct email address.', 'smile' );
                    $status = 'error';
                    $ret = false;
                } else if( !isset( $contact['email'] ) ) {
                    $msg  = $default_error_msg;
                    $errorMsg = __( 'Email field is mandatory to set in form.', 'smile' );
                    $status = 'error';
                }
            }

            if ( is_user_logged_in() && current_user_can( 'access_cp' ) ) {
                $detailed_msg = $errorMsg;
            } else {
                $detailed_msg = '';
            }

            if( $detailed_msg !== '' && $detailed_msg !== null ) {
                $page_url = isset( $_POST['cp-page-url'] ) ? esc_url( $_POST['cp-page-url'] ) : '';

                // notify error message to admin
                if( function_exists('cp_notify_error_to_admin') ) {
                    $result   = cp_notify_error_to_admin($page_url);
                }
            }

            if( isset( $_POST['source'] ) ) {
                return $ret;
            } else {
                print_r(json_encode(array(
                    'action' => ( isset( $_POST['message'] ) ) ? 'message' : 'redirect',
                    'email_status' => $email_status,
                    'status' => $status,
                    'message' => $msg,
                    'detailed_msg' => $detailed_msg,
                    'url' => ( isset( $_POST['message'] ) ) ? 'none' : esc_url( $_POST['redirect'] ),
                )));

                exit();
            }
        }

        /**
        * Authentication
        * @since 1.0
        */
        function update_mailwizz_authentication(){

            if ( ! current_user_can( 'access_cp' ) ) {
                die(-1);
            }

            $api_url = isset( $_POST['authentication_token'] ) ? sanitize_text_field( $_POST['authentication_token'] ) : '';
            $public_key = isset( $_POST['public_key'] ) ? sanitize_text_field( $_POST['public_key'] ) : '';
            $private_key = isset( $_POST['private_key'] ) ? sanitize_text_field( $_POST['private_key'] ) : '';
            $mc_lists = array();
            $html = $query = '';
           
            if( $api_url == "" ) {
                print_r( json_encode(array(
                    'status' => "error",
                    'message' => __( "Please provide valid API URL for your ".$this->setting['name']." account.", "smile" )
                )));
                exit();
            }

            if( $public_key == "" ) {
                print_r( json_encode(array(
                    'status' => "error",
                    'message' => __( "Please provide valid Public Key for your ".$this->setting['name']." account.", "smile" )
                )));
                exit();
            }

            if( $private_key == "" ) {
                print_r( json_encode(array(
                    'status' => "error",
                    'message' => __( "Please provide valid Private Key for your ".$this->setting['name']." account.", "smile" )
                )));
                exit();
            }

            $request = $this->get_mailwizz_lists( $api_url, $public_key, $private_key );
            ob_start();

            if( isset( $request ) ) {
                if( $request == '' ) {
                    print_r(json_encode(array(
                        'status' => "error",
                        'message' => 'Please check your URL, Public key and Private Key!'
                    )));
                    exit();
                }
            }

            $lists = $request;            

            if( count( $lists ) < 1 ) {                                    	
                print_r(json_encode(array(
                    'status' => "error",
                    'message' => __( "You have zero lists in your " . $this->setting['name'] . " account. You must have at least one list before integration." , "smile" )
                )));
                exit();
            }
            ?>

            <?php
            if( count( $lists ) > 0 ) {                  	 
            ?>
                <label for="<?php echo $this->slug; ?>-list"><?php _e( "Select List", "smile" ); ?></label>
                <select id="<?php echo $this->slug; ?>-list" class="bsf-cnlist-select" name="<?php echo $this->slug; ?>-list">
                	<?php 
                	foreach($lists as $offset => $list) { 
                		 $query .= $offset.'|'.$list.',';
                         $mw_lists[$offset] = $list; ?>
                		<option value="<?php echo $offset ?>"><?php echo $list ?></option>
                	<?php
                		}?>
                	</select>                	                    
            <?php          
        } else { ?>
                <label for="<?php echo $this->slug; ?>-list">
                    <?php echo __( "You need at least one list added in " . $this->setting['name'] . " before proceeding.", "smile" ); ?>
                </label>
            <?php } ?>
            <?php    ?>
            <div class="bsf-cnlist-form-row">
                <div id="disconnect-<?php echo $this->slug; ?>" class="disconnect-mailer" data-mailerslug="<?php echo $this->slug; ?>" data-mailer="<?php echo $this->setting['name']; ?>">
                    <span>
                        <?php _e( "Use different '".$this->setting['name']."' account?", "smile" ); ?>
                    </span>
                </div>
                <span class="spinner" style="float: none;"></span>
            </div>
            <?php
            $html .= ob_get_clean();
            update_option( $this->slug.'_api', $api_url );
            update_option( $this->slug.'_public_key', $public_key );
            update_option( $this->slug.'_private_key', $private_key );
            update_option( $this->slug.'_lists', $mw_lists );

            print_r(json_encode(array(
                'status' => "success",
                'message' => $html
            )));

            exit();
        }

        /**
        * Disconnect mailwizz
        * @since 1.0
        */
        function disconnect_mailwizz(){
            delete_option( 'mailwizz_api' );
            delete_option( 'mailwizz_public_key' );
            delete_option( 'mailwizz_private_key' );
            delete_option( 'mailwizz_lists' );

            $smile_lists = get_option('smile_lists');
            if( !empty( $smile_lists ) ){
                foreach( $smile_lists as $key => $list ) {
                    $provider = $list['list-provider'];
                    if( strtolower( $provider ) == strtolower( $this->slug ) ){
                        $smile_lists[$key]['list-provider'] = "Convert Plug";
                        $contacts_option = "cp_" . $this->slug . "_" . preg_replace( '#[ _]+#', '_', strtolower( $list['list-name'] ) );
                        $contact_list = get_option( $contacts_option );
                        $deleted = delete_option( $contacts_option );
                        $status = update_option( "cp_connects_" . preg_replace( '#[ _]+#', '_', strtolower( $list['list-name'] ) ), $contact_list );
                    }
                }
                update_option( 'smile_lists', $smile_lists );
            }
            print_r(json_encode(array(
                'message' => "disconnected",
            )));
            exit();
        }
       
        /**
         * Function Name: get_mailwizz_lists
         * Function Description: Get mailwizz list
         * @since 1.0
         */
        function get_mailwizz_lists( $api_url = '', $public_key = '', $private_key = '' ) {
                        
            if( $api_url !== '' && $public_key !== '' && $private_key !== '' ) {
                $data = array();

                if ( class_exists( 'MailWizzApi_Autoloader' ) ) {
                 
                    $api_url = $api_url . '/api/index.php';
                    MailWizzApi_Autoloader::register();

                    if ( class_exists( 'MailWizzApi_Config' ) ) {
                        $config = new MailWizzApi_Config(
                            array(
                                'apiUrl'     => $api_url,
                                'publicKey'  => $public_key,
                                'privateKey' => $private_key,
                                'components' => $this->components,
                            )
                        );
                       
                       	MailWizzApi_Base::setConfig( $config );
                       	$endpoint = new MailWizzApi_Endpoint_Lists();
                       	$res      = $endpoint->getLists( 1, 1000 );
                       	$status   = $res->body->itemAt( 'status' );

                       	if( $status !== 'success'){
		                    return array();
		                }		

		                $campaigns = $res->body->itemAt( 'data' );
		                if ( $campaigns['count'] > 0 ) {
		                	$lists = array();
							foreach ( $campaigns['records'] as $offset => $cm ) {
								$lists[ $cm['general']['list_uid'] ] = $cm['general']['name'];
							}
							return $lists;
		                }	               
                    }
                }                 
            }
            return array();
        }

    }
    new Smile_Mailer_Mailwizz;
}

?>
