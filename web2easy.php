<?php
/**
 * @package web2easy
 */

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

/*
Plugin Name: Web2easy
Plugin URI: http://web2easy.com
Description: A simple, powerful and elegant Wordpress editor which creates the most wonderful blog posts imaginable. You'll never use a standard editor again.
Version: 0.1
Author: Just2easy
Author URI: http://www.just2easy.com
License: GPL2
*/
require_once( 'web2easy-admin.php' );

class Web2easy
{
	function Web2easy()
	{
		$this->add_action('init',				'init');
		$this->add_filter('the_content',		'replaceEmbededObjects');
		
		$this->add_filter('post_row_actions',	'postAdminRowActions', 10, 2);
	}
	
	function add_action ($action, $function = '', $priority = 10, $accepted_args = 1)
	{
		add_action ($action, array (&$this, $function == '' ? $action : $function), $priority, $accepted_args);
	}
	
	function add_filter ($filter, $function = '', $priority = 10, $accepted_args = 1)
	{
		add_filter ($filter, array (&$this, $function == '' ? $filter : $function), $priority, $accepted_args);
	}
	
	function init(){
		if ( isset( $GLOBALS['web2easy_admin_menu'] ) )
			return;

		if ( !is_admin() )
			return;
		
		$GLOBALS['web2easy_admin_menu'] =& new Web2easy_Admin( );
		add_action( 'admin_menu', array( &$GLOBALS['web2easy_admin_menu'], 'init' ) );
	}
	
	function postAdminRowActions($actions, $post){
		$w2eFile = get_post_meta($post->ID, "web2easypost", true);

        if (!empty($w2eFile)){     	
        	$post_type_object = get_post_type_object( $post->post_type );
    		if ( !empty($post_type_object) ){
    			//check capabilites
    			if ( current_user_can( $post_type_object->cap->edit_posts, $post->ID ) ){
    				$link  = add_query_arg(array('page'=>'web2easy/web2easy-admin.php', 'w2efile'=>$w2eFile), esc_url( admin_url( 'admin.php' ) ) );
    				$actions['editw2e'] = "<a href='" . $link . "' title='" . esc_attr(__('edit file in Web2easy')) . "'>" . __('Edit Web2easy') . "</a>";
    			}
    		}
        }
  		return $actions;
    }
	
	function replaceEmbededObjects($text)
	{
		/* Example W2e embed code - width & height attr are optional
		 * [w2e 'width':'100%','height':'100%','src':'http://web2easy.com/some+url'] */
		if (preg_match("/.*\[w2e\s+(.*)\].*/", $text, $matches)==1){
			return $this->render('w2e', $matches[1]);
		}
		
		return $text;
	}
	
	function render($object, $params){
		/*
		 * $params eg = 'width':'100%','height':'100px','src':'http://web2easy.com/some+url/'
		 * remove 1st & last quote mark
		 * then get array of parameters array where each value will be a string, something like 
			Array
			(
			    [0] => width':'100%
			    [1] => height':'100px
			    [2] => src':'http://web2easy.com/some+url/
		
			)
		 * Then loop through every item and explode each string so we end up with
		 	Array
			(
			    [0] => Array(width => 100%)
			    [1] => Array(height => 100px)
			    [2] => Array(src => http://web2easy.com/some+url/)
		
			)
		*/
		$params = explode('\',\'', trim($params, "'"));
		
		$output = "";
		if ($object=='w2e'){
			$output = $this->getW2eOutput($params);
		}
		
		ob_start();
		echo $output;
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	function getW2eOutput($params){
		$w2e = '<iframe src="%src%" width="%width%" height="%height%" frameborder="0">iframe content is not visible here.</iframe>';
		
		foreach ($params as $param){
			//Now for each value in Array above explode to get a $key and $value
			$param = explode('\':\'', $param);
			
			if (sizeof($param)==2){
				$key = $param[0];
				$value = $param[1];

				$w2e = str_replace("%$key%", $value, $w2e);
			}
		}
		
		if (strpos($w2e, "%src%")!==false){
			//if the placeholder for the src attr still exists something went wrong
			return "ooops something went wrong embeding your web2easy.com file.";
		}
		
		$protocol = !empty($_SERVER["HTTPS"])?'https:':'http:';
		$w2e = str_replace(array("http:", "https:"), array($protocol, $protocol), $w2e);
		
		//Optional values
		$w2e = str_replace("%width%", "100%", $w2e);
		$w2e = str_replace("%height%", "100%", $w2e);
		
		return $w2e;
	}
}

$web2easy = new Web2easy();
?>