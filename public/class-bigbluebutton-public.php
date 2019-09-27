<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://blindsidenetworks.com
 * @since      3.0.0
 *
 * @package    Bigbluebutton
 * @subpackage Bigbluebutton/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Bigbluebutton
 * @subpackage Bigbluebutton/public
 * @author     Blindside Networks <contact@blindsidenetworks.com>
 */
class Bigbluebutton_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    3.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    3.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bigbluebutton_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bigbluebutton_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/bigbluebutton-public.css', array(), $this->version, 'all');

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    3.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bigbluebutton_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bigbluebutton_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$translations = array(
			'view' => __('View', 'bigbluebutton'),
			'hide' => __('Hide'),
			'edit' => __('Edit'),
			'published' => __('Published'),
			'unpublished' => __('Unpublished'),
			'protected' => __('Protected', 'bigbluebutton'),
			'unprotected' => __('Unprotected', 'bigbluebutton'),
			'ajax_url' => admin_url('admin-ajax.php')
		);

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/bigbluebutton-public.js', array('jquery'), $this->version, false);
		wp_localize_script($this->plugin_name, 'php_vars', $translations);
	}

	/**
	 * Add font awesome icons.
	 * 
	 * @since	3.0.0
	 */
	public function enqueue_font_awesome_icons() {
    	wp_enqueue_style( 'fontawesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css', array(), '4.2.0' );
	}

	/**
	 * Display join room button in the bbb-room post.
	 * 
	 * @since	3.0.0
	 * 
	 * @param	String	$content	Post content as string.
	 * @return	String	$content	Post content as string.
	 */
	public function bbb_room_join_form_content($content) {
		global $pagenow;

		if ($pagenow == 'edit.php' || $pagenow == 'post.php') {
			return $content;
		}

		$room_id = get_the_ID();
		$meta_nonce = wp_create_nonce('bbb_join_room_meta_nonce');

		// only access the meeting using a code if there is no other way
		$access_using_code = current_user_can('join_with_access_code_bbb_room');
		$access_as_moderator = (current_user_can('join_as_moderator_bbb_room') || get_current_user_id() == get_post($room_id)->post_author);
		$access_as_viewer = current_user_can('join_as_viewer_bbb_room');

		if ($room_id === null || $room_id === false || ! isset(get_post($room_id)->post_type) || 
			get_post($room_id)->post_type != 'bbb-room') {
			return $content;
		}

		// add join form to post content
		$html_form = $this->get_join_form_as_string($room_id, $meta_nonce, $access_as_moderator, $access_as_viewer, $access_using_code);
		$content .= $html_form;

		// add recordings list to post content if the room is recordable
		$room_can_record = get_post_meta($room_id, 'bbb-room-recordable', true);
		$manage_recordings = current_user_can('manage_bbb_room_recordings');
		$view_extended_recording_formats = current_user_can('view_extended_bbb_room_recording_formats');

		if ($room_can_record == 'true') {
			$recordings = ($manage_recordings ? BigbluebuttonApi::get_recordings($room_id, 'published,unpublished') : BigbluebuttonApi::get_recordings($room_id, 'published'));
			$filtered_recordings = $this->filter_recordings($recordings, $manage_recordings);
			$html_recordings = $this->get_optional_recordings_view_as_string($room_id, $filtered_recordings, $manage_recordings, $view_extended_recording_formats);
			$content .= $html_recordings;
		}
		
		return $content;
	}

	/**
	 * Get join meeting form as an HTML string.
	 * 
	 * @since	3.0.0
	 * 
	 * @param	Integer		$room_id				Post ID of the room.
	 * @param	String		$meta_nonce				Nonce for join meeting form.
	 * @param	Boolean		$access_as_moderator	Check for if the current user can enter meetings as a moderator.
	 * @param	Boolean		$access_as_viewer		Check for if the current user can enter meetings as a viewer.
	 * @param	Boolean		$access_using_code		Check for if the current user can enter meetings using an access code.
	 * 
	 * @return	String		$form					Join meeting form stored in a variable.
	 */
	private function get_join_form_as_string($room_id, $meta_nonce, $access_as_moderator, $access_as_viewer, $access_using_code) {
		ob_start();
		include('partials/bigbluebutton-join-display.php');
		$form = ob_get_contents();
		ob_end_clean();
		return $form;
	}

	/**
	 * Get recordings with Show/Hide buttons as an HTML string.
	 * 
	 * @since	3.0.0
	 * 
	 * @param	Integer		$room_id				Post ID of the room.
	 * @param	Array		$recordings				List of recordings for the room.
	 * @param	Boolean		$manage_bbb_recordings	Recordings table stored in a variable.
	 * 
	 * @return	String		$recordings				Recordings table stored in a variable.
	 */
	private function get_optional_recordings_view_as_string($room_id, $recordings, $manage_bbb_recordings, $view_extended_recording_formats) {
		$columns = 5;
		if ($manage_bbb_recordings) {
			$columns++;
		}
		ob_start();
		$meta_nonce = wp_create_nonce('bbb_manage_recordings_nonce');
		$date_format = (get_option('date_format') ? get_option('date_format') : 'Y-m-d');
		$default_bbb_recording_format = 'presentation';
		include('partials/bigbluebutton-optional-recordings-display.php');
		$recordings = ob_get_contents();
		ob_end_clean();
		return $recordings;
	}

	/**
	 * Filter recordings based on whether the user can manage them or not.
	 * 
	 * Assign icon classes and title based on recording published and protected status.
	 * If the user cannot manage recordings, hide them.
	 * Get recording name and description from metadata.
	 * 
	 * @since	3.0.0
	 * 
	 * @param	Array	$recordings		List of recordings.
	 * @return	Array	$recordings		List of recordings with classes and titles for manage recording icons.
	 */
	private function filter_recordings($recordings, $manage_recordings) {
		$filtered_recordings = array();
		foreach($recordings as $recording) {
			if (!isset($recording->metadata->{'recording-name'})) {
				$recording->metadata->{'recording-name'} = $recording->name;
			}
			if (!isset($recording->metadata->{'recording-description'})) {
				$recording->metadata->{'recording-description'} = "";
			}
			if ($manage_recordings) {
				$recording = $this->filter_managed_recording($recording);
				array_push($filtered_recordings, $recording);
			} else if ($recording->published == 'true') {
				array_push($filtered_recordings, $recording);
			}
		}
		return $filtered_recordings;
	}

	/**
	 * Assign classes and title for the icon based on the recording's publish and protect status.
	 * 
	 * @since	3.0.0
	 * 
	 * @param	SimpleXMLElement	$recording	A recording to be inspected.
	 * @return	SimpleXMLElement	$recording	A recording that has been inspected.
	 */
	private function filter_managed_recording($recording) {
		if ($recording->protected == 'true') {
			$recording->protected_icon_classes = "fa fa-lock fa-icon bbb-icon bbb_protected_recording is_protected";
			$recording->protected_icon_title = __('Protected', 'bigbluebutton');
		} else if ($recording->protected == 'false') {
			$recording->protected_icon_classes = "fa fa-unlock fa-icon bbb-icon bbb_protected_recording not_protected";
			$recording->protected_icon_title = __('Unprotected', 'bigbluebutton');
		}

		if ($recording->published == 'true') {
			$recording->published_icon_classes = "fa fa-eye fa-icon bbb-icon bbb_published_recording is_published";
			$recording->published_icon_title = __('Published');
		} else {
			$recording->published_icon_classes = "fa fa-eye-slash fa-icon bbb-icon bbb_published_recording not_published";
			$recording->published_icon_title = __('Unpublished');
		}
		return $recording;
	}
}
