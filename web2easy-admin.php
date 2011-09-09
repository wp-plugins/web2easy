<?php
/*	Copyright 2011  Just2easy  (email : sales@j2e.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * The Web2easy admin class.
 *
 * @since 3.0.0
 * @package web2easy
 * @subpackage Administration
 */
class Web2easy_Admin {

	/**
	 * Callback for administration header.
	 *
	 * @var callback
	 * @since 3.0.0
	 * @access private
	 */
	var $admin_header_callback;

	/**
	 * Holds the page menu hook.
	 *
	 * @var string
	 * @since 3.0.0
	 * @access private
	 */
	var $page = '';

	/**
	 * PHP4 Constructor - Register administration header callback.
	 *
	 * @since 3.0.0
	 * @param callback $admin_header_callback
	 * @return Custom_Logo
	 */
	function Web2easy_Admin($admin_header_callback = '') {
		$this->admin_header_callback = $admin_header_callback;
	}
	
	function install() {
		global $current_user;
		get_currentuserinfo();
		
		$user = get_user_meta($current_user->ID, 'web2easy_username', true);
		
		function random_password($l = 10){
		    $c = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxwz0123456789";
		    for(;$l > 0;$l--) $s .= $c{rand(0,strlen($c))};
		    return str_shuffle($s);
		}
		
		if (empty($user)){
			if (!empty($current_user->user_email)){
				$email = $current_user->user_email;
				$user = str_replace("@", "-", $email);
				$pass = random_password();
				$firstName = $current_user->display_name;
				
				$curl_connection = curl_init("https://web2easy.com/account.php");	 
				curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 20);
				curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
		
				curl_setopt($curl_connection, CURLOPT_POST,5);
				curl_setopt($curl_connection, CURLOPT_POSTFIELDS, "action=create&userName={$user}&password={$pass}&email={$email}&firstName={$firstName}");
		
				$curlResult = curl_exec($curl_connection);
				curl_close($curl_connection);
				
				$curlResult = json_decode($curlResult, true);
				
				if (empty($curlResult['error']) && !empty($curlResult['accountName'])){
					$user = $curlResult['accountName']; //in case the account name had to be changed on creation
					
					update_user_meta($current_user->ID, 'web2easy_username', $user);
					update_user_meta($current_user->ID, 'web2easy_password', sha1($pass));
				}
			}
		}
	}

	/**
	 * Set up the hooks for the Custom Logo admin page.
	 *
	 * @since 3.0.0
	 */
	function init() {
		if ( ! current_user_can('publish_posts') )
			return;
			
		//create new top-level menu
		$this->page = $page = add_menu_page(__('Web2easy'), __('Web2easy'), 'publish_posts', __FILE__, array(&$this, 'admin_page'), plugin_dir_url( __FILE__ ).'logo.png');

		add_action("load-$page", array(&$this, 'admin_load'));
		add_action("load-$page", array(&$this, 'take_action'), 49);

		if ( $this->admin_header_callback )
			add_action("admin_head-$page", $this->admin_header_callback, 51);
	}

	/**
	 * Set up the enqueue for the CSS & JavaScript files.
	 *
	 * @since 3.0.0
	 */
	function admin_load() {
		add_contextual_help( $this->page, '<p>' . __( 'This is a friendly easy to use html editor that allows you to create blog posts easily' ) . '</p>' .
		'<p>' . __( 'Create a document using the editor below, adding text, pictures and drawings to create a blog post.' ) . '</p>' .
		'<p>' . __( 'Save the document and the "Publish" button will become enabled for you to publish your blog post, but only do this when the document is complete.' ) . '</p>' .
		'<p>' . __( 'Or click the "Save Draft" button to change any of the settings of the post before publishing.' ) . '</p>' .
		'<p>' . __( 'You can open old files and re-save them, in doing this it will update any blog posts using that file.' ) . '</p>');
		
		$this->install();
	}

	/**
	 * Execute Custom Logo modification.
	 *
	 * @since 3.0.0
	 */
	function take_action() {

		if ( empty($_POST) )
			return;

		if ( isset($_POST['publish']) || isset($_POST['save']) ) {
			check_admin_referer('custom-w2e-publish', '_wpnonce-custom-w2e-publish');
			
			// Create post object
			$my_post = array(
				'post_title' => $_POST['w2efile'],
				'post_excerpt' => '<img src="'.$_POST['w2ethumb'].'" />',//style="width:180px;"/>',
				'post_content' => "[w2e 'src':'".$_POST['w2eurl']."']",
				'post_status' => isset($_POST['save']) ? 'draft' : 'publish'
			);

			$postId = wp_insert_post( $my_post );
			add_post_meta($postId, 'web2easypost', urlencode($_POST['w2efile']));
			
			$this->permalink = get_permalink($postId);
			$this->status = isset($_POST['save']) ? 'Draft post published.' : 'Post published.';
			return;
		}
	}

	/**
	 * Display the Custom Logo page.
	 *
	 * @since 3.0.0
	 */
	function admin_page() {
		function getLink(){
			global $current_user;
			get_currentuserinfo();
			$user = get_user_meta($current_user->ID, 'web2easy_username', true);
			$pass = get_user_meta($current_user->ID, 'web2easy_password', true);
			
			if (!empty($_REQUEST['w2efile'])){
				$file = urlencode($user).'/'.$_REQUEST['w2efile'];
			}
			$xdomain = urlencode(plugins_url( 'xdomain.html' , __FILE__ ));
		
			$https = array_key_exists('HTTPS',$_SERVER) && !empty($_SERVER['HTTPS']) ? true : false;
			$protocol = $https ? 'https' : 'http';
			$link = $protocol . "://web2easy.com/{$file}?idp=loginW2e&username={$user}&password={$pass}&w2eplugin={$xdomain}";
			return $link;
		}
?>
<div class="wrap" id="web2easy-admin">
	<table class="form-table">
		<tbody>
			<tr>
				<td>
					<h2 style="display:inline-block;zoom:1;*display:inline;"><?php _e('Web2easy'); ?></h2>
					<?php if ( !empty($this->status) ) { ?>
						<div id="message" class="updated" style="display:inline-block;zoom:1;*display:inline;">
						<p><?php printf( __( $this->status . ' <a href="%s">Visit your site</a> to see how it looks.' ), $this->permalink ); ?></p>
						</div>
					<?php 
					}else{ ?>
					<form id="w2eForm" style="display:inline-block;zoom:1;*display:inline;vertical-align:text-bottom;" method="post" action="">
						<input id="w2efile" name="w2efile" type="hidden" value="" />
						<input id="w2ethumb" name="w2ethumb" type="hidden" value="" />
						<input id="w2eurl" name="w2eurl" type="hidden" value="" />
						<?php wp_nonce_field('custom-w2e-publish', '_wpnonce-custom-w2e-publish'); ?>
						<?php submit_button( __( 'Publish' ), 'primary', 'publish', false, 'disabled="true"' );
						      submit_button( __( 'Save Draft' ), 'secondary', 'save', false, array('disabled'=>'true','style'=>'display:inline-block;zoom:1;*display:inline;') ); ?><br/>
						<?php //_e('This will publish your web2easy file to your blog.') ?>
					</form>
					<?php }?>
					<div style="height:5px;width:10px;"/>
				</td>
			</tr>
		</tbody>
	</table>
	<iframe id='w2e' src='<?php echo getLink(); ?>' frameborder='0' style='width:100%;min-height:700px;'>
		<p>Your browser does not support iframes.</p>
	</iframe>
</div>
<?php
	}
}
?>
