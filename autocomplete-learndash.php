<?php
/**
 * @package Autocomplete LearnDash Lessons and Topics
 * @version 1.5
 */
/*
Plugin Name: Autocomplete LearnDash Lessons and Topics
Plugin URI: https://www.nextsoftwaresolutions.com/autocomplete-learndash-lessons-and-topics
Description: This plugin will Autocomplete LearnDash Lessons or Topic in the background on visiting the Lesson or Topic page. User will still see the Mark Complete button for the first time, and can click it to go to the next lesson.
Author: Next Software Solutions
Version: 1.5
Author URI: https://www.nextsoftwaresolutions.com
*/

class autocomplete_learndash {
	function __construct() {
		add_action("wp_head", array($this, "autocomplete_learndash"));
		add_action("wp", array($this, "init"));

		if(!class_exists('grassblade_addons'))
		require_once(dirname(__FILE__)."/addon_plugins/functions.php");

		add_action( 'admin_menu', array($this,'menu'), 10);
		add_action( 'add_meta_boxes', array($this, 'add_field'), 10, 1 );
		add_action( 'save_post', array($this, 'save_field'), 10, 1 );
	}

	function add_field($post_type) {
		if(empty($post_type) || $post_type != 'sfwd-courses')
			return;

		$enabled = get_option("autocomplete_learndash", true);

		if(!empty($enabled))
		add_meta_box(
			'grassblade_auto_complete',
			__( 'Auto Complete', 'grassblade' ),
			array($this, 'render_field'),
			$post_type,
			'side',
			'high'
		);
	}

	function render_field($post) {
		if(empty($post) || $post->post_type != "sfwd-courses")
		return;

		$grassblade_auto_complete = learndash_get_setting($post->ID, "grassblade_auto_complete");
		?>
		<p>Auto-complete lessons and topics of this course.</p>
		<select name="grassblade_auto_complete">
			<option value="enabled" <?php if(empty($grassblade_auto_complete) || $grassblade_auto_complete == "enabled") echo 'selected="selected"'; ?>>Enabled</option>
			<option value="disabled" <?php if($grassblade_auto_complete == "disabled") echo 'selected="selected"'; ?>>Disabled</option>
		</select>
		<?php
	}

	function save_field($post_id) {
		if( !function_exists('learndash_get_setting') || empty($_POST["grassblade_auto_complete"]))
			return;

		$course = get_post($post_id);
		if(empty($course) || $course->post_type != "sfwd-courses")
			return;

		$grassblade_auto_complete = learndash_get_setting($course->ID, "grassblade_auto_complete");

		if(!empty($grassblade_auto_complete) && $grassblade_auto_complete == $_POST["grassblade_auto_complete"] || !in_array($_POST["grassblade_auto_complete"], ["enabled", "disabled"]))
			return;

		learndash_update_setting($course->ID, "grassblade_auto_complete", strip_tags($_POST["grassblade_auto_complete"]));
	}

	function init() {
		global $post;

		if( !empty($post) && in_array($post->post_type, array("sfwd-lessons", "sfwd-topic")) )
		wp_enqueue_script( 'jquery' );
	}
	function menu() {
		global $submenu, $admin_page_hooks;
		$icon = plugin_dir_url(__FILE__)."img/icon-gb.png";

		if(empty( $admin_page_hooks[ "grassblade-lrs-settings" ] )) {
			add_menu_page("GrassBlade", "GrassBlade", "manage_options", "grassblade-lrs-settings", array($this, 'menu_page'), $icon, null);
		}

		add_submenu_page("grassblade-lrs-settings", "Autocomplete LearnDash Lessons & Topics", "Autocomplete LearnDash Lessons & Topics", 'manage_options','grassblade-autocomplete_learndash', array($this, 'menu_page'));
	}

	function menu_page() {

		if(!current_user_can("manage_options"))
			return;

		$enabled = get_option("autocomplete_learndash");

		if( !empty($_POST["submit"]) && check_admin_referer('autocomplete_learndash') ) {
			$enabled = intVal(isset($_POST["autocomplete_learndash"]));
			update_option("autocomplete_learndash", $enabled);
		}

		if($enabled === false) {
			$enabled = 1;
			update_option("autocomplete_learndash", $enabled);
		}

		?>
		<style type="text/css">
			div#autocomplete_learndash {
				padding: 30px;
				background: white;
				margin: 50px;
				border-radius: 5px;
			}
			div#autocomplete_learndash input[type=checkbox] {
				margin-left: 50px;
			}
		</style>
		<div id="autocomplete_learndash" class="wrap">
			<h3>Autocomplete LearnDash Lessons and Topics</h3>
			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
				<?php wp_nonce_field( 'autocomplete_learndash' ); ?>
				<p style="padding: 20px;"><b><?php _e("Enable"); ?></b> <input name="autocomplete_learndash" type="checkbox" value="1" <?php if($enabled) echo 'checked="checked"'; ?>> </p>

				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e("Save Changes"); ?>"></p>

			</form>
		</div>
		<?php
	}
	function autocomplete_learndash() {
		if(!function_exists('sfwd_lms_has_access'))
			return;

		$enabled = get_option("autocomplete_learndash", true);

		if(empty($enabled))
			return;

		global $post;
		$course_id = learndash_get_course_id($post);
		$grassblade_auto_complete = learndash_get_setting($course_id, "grassblade_auto_complete");

		if(!empty($post) && ( empty($grassblade_auto_complete) || $grassblade_auto_complete == "enabled" ) && in_array($post->post_type, array("sfwd-lessons", "sfwd-topic"))) {
			?>
			<script>
				jQuery(document).ready( function() {
					setTimeout(function() {
						//console.log("auto complete");
						if(jQuery(".sfwd-mark-complete").length) //Mark Complete Button Exists
						if(jQuery("#sfwd-mark-complete, .learndash-wrapper form.sfwd-mark-complete, form.sfwd-mark-complete").is(":visible")) //Mark Complete Button is Visible
						if(typeof gb_data != "object" || typeof gb_data.mark_complete_button == "undefined" || gb_data.mark_complete_button == "") //GrassBlade Completion Tracking is not blocking completion.
						jQuery("#sfwd-mark-complete, .learndash-wrapper form.sfwd-mark-complete, form.sfwd-mark-complete").each(function(i, form){
							//console.log(i, form, jQuery(form).is(":visible"), jQuery(form).children("input[type=submit]").attr("disabled"), jQuery(form).is(":visible") && !jQuery(form).children("input[type=submit]").attr("disabled"));
							if( jQuery(form).is(":visible") && !jQuery(form).children("input[type=submit]").attr("disabled") && !jQuery(".learndash_mark_complete_button").hasClass("learndash_mark_incomplete_button") ) {
								jQuery.ajax({ type: "POST", url: window.location.href, data: jQuery(form).serialize()});
								return;
							}
						});
					}, 2000);
				});
			</script>
			<?php
		}
	}
}

new autocomplete_learndash();
