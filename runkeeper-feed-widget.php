<?php
/*
* Plugin Name: Runkeeper feed widget
* Version: 1.0
* Plugin URI: http://pragmaticrunner.com
* Description:	A widget which displays the last 5 activites from runkeeper. 
					It uses the the WordPress 2.8 widget API and the Health Graph API
* Author: Michael Cartwright
* Author URI: http://pragmaticrunner.com
*/
class RunkeeperFeedWidget extends WP_Widget 
{
	/**
	* Declares the RunkeeperFeedWidget class.
	*
	*/
	
	function RunkeeperFeedWidget() {
		$this->pluginURL = WP_CONTENT_URL . "/plugins/" . plugin_basename(dirname(__FILE__));
		$widget_ops = array('classname' => 'widget_runkeeper_feed', 'description' => __( "Runkeeper Feed Widget") );
		$control_ops = array('width' => 300, 'height' => 300);
		$this->WP_Widget('RunkeeperFeed', __('Runkeeper Feed'), $widget_ops, $control_ops);
		add_action("wp_head", array(&$this,"serveHeader"));
	}

	/**
	* Displays the Widget
	*
	*/

	function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', empty($instance['title']) ? 'Fitness Feed' : $instance['title']);
		$authCode = empty($instance['authCode']) ? '' : $instance['authCode'];
		$feedLength = empty($instance['feedLength']) ? '5' : $instance['feedLength'];
		$displaySize = empty($instance['displaySize']) ? 'medium' : $instance['displaySize'];

		# Before the widget
		echo $before_widget;
		
		// Open runkeeper widget wrapper
		echo '<div class="rk-wdgt-wpr-' . $displaySize .'">';
		echo '<div class="rk-logo">&nbsp;</div>';

		# The title
		if ( $title )
			echo $before_title . $title . $after_title;
		
		if (empty($authCode)) {
			echo '<div style="text-align:center;padding:10px;">';
			echo '<p style="font-family:Helvetica Neue, Arial, sans-serif; font-size: 14px">You need to connect to runkeeper first</p>';
			echo '<a href="https://runkeeper.com/apps/authorize?client_id=a073011243034bc5a53a2c7c3b9919f0&redirect_uri=http://pragmaticrunner.com/process.php&response_type=code" title="Connect to RunKeeper, powered by the Health Graph">
					<img src="http://static1.runkeeper.com/images/assets/connect-blue-black-200x38.png" style="height: 38px; width: 200px; display: block; border: 0;" alt="Connect to RunKeeper, powered by the Health Graph" />
					</a>';
			echo '</div>';
		}
		else {
			// create a new cURL resource

			$ch = curl_init();

			// set URL and other appropriate options
			$headers = array( 
							'Authorization: Bearer ' . $authCode, 
							"Accept: application/vnd.com.runkeeper.FitnessActivityFeed+json", 
							);
			curl_setopt($ch, CURLOPT_URL, "https://api.runkeeper.com/fitnessActivities");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);

			// grab URL and pass it to the browser, decoding json into result
			$result = json_decode(curl_exec($ch));
		
			$headers = array( 
							'Authorization: Bearer ' . $authCode, 
							"Accept: application/vnd.com.runkeeper.Profile+json", 
							);
			curl_setopt($ch, CURLOPT_URL, "https://api.runkeeper.com/profile");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
			// grab URL and pass it to the browser, decoding json into result
			$profile = json_decode(curl_exec($ch));

			# Make the Runkeeper Feed widget
			echo '<div class="fitness-feed">';
				echo '<div class="major-feed">';
					echo '<div class="date">';
						$format = 'D, j M Y H:i:s';
						// Mon, 29 Aug 2011 18:47:00
						$date = DateTime::createFromFormat($format, $result->{'items'}[0]->{'start_time'});
						echo date_format($date, 'd/m/Y :: H:i');
					echo '</div>';
					echo '<div class="latest">';
						echo "<a href='" . $profile->{'profile'} . substr_replace($result->{'items'}[0]->{'uri'}, 'activity', 1, 17) . "'>" . $result->{'items'}[0]->{'type'} . "</a>";
					echo '</div>';
					echo '<div class="activity">';
						echo '<div class="activity-reflection">&nbsp;</div>';
						echo '<div class="distance">';
							echo '<div class="label">Distance</div>';
							echo '<div class="result">';
								echo round($result->{'items'}[0]->{'total_distance'}/1000, 2);
							echo '</div>';
							echo '<div class="unit">km</div>';
						echo '</div>';
						echo '<div class="divider">&nbsp;</div>';
						echo '<div class="duration">';
							echo '<div class="label">Duration</div>';
							echo '<div class="result">';
								echo sec2hms($result->{'items'}[0]->{'duration'});
							echo '</div>';
							echo '<div class="unit">h:m:s</div>';
						echo '</div>';
					echo '</div>';
				echo '</div>';

			// More than one feed selected
			if ( $feedLength > 1 ) {
				for( $i=1; $i<$feedLength; $i++ ) {
					echo '<div class="minor-feed">';
						echo '<div class="minor-type">';
							echo "<a href='" . $profile->{'profile'} . substr_replace($result->{'items'}[$i]->{'uri'}, 'activity', 1, 17) . "'>" . truncate($result->{'items'}[$i]->{'type'}, 10, '...') . "</a>";
							$format = 'D, j M Y H:i:s';
							// Mon, 29 Aug 2011 18:47:00
							$date = DateTime::createFromFormat($format, $result->{'items'}[$i]->{'start_time'});
							echo date_format($date, 'd/m/Y - H:i');
						echo '</div>';
						echo '<div class="minor-result-km">';
							echo round($result->{'items'}[$i]->{'total_distance'}/1000, 2) . ' km';
						echo '</div>';
						echo '<div class="minor-result-time">';
							echo sec2hms($result->{'items'}[$i]->{'duration'}) . ' h:m:s';
						echo '</div>';
					echo '</div>';
					
				}
			}
		
			echo '</div>';

			// close cURL resource, and free up system resources
			curl_close($ch);
		}
		
		echo '<div class="rk-footer">&nbsp;</div>';
		
		// Close runkeeper widget wrapper
		echo '</div>';

		# After the widget
		echo $after_widget;
	}

	/**
	* Saves the widgets settings.
	*
	*/
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		$instance['authCode'] = strip_tags(stripslashes($new_instance['authCode']));
		$instance['feedLength'] = strip_tags(stripslashes($new_instance['feedLength']));
		$instance['displaySize'] = strip_tags(stripslashes($new_instance['displaySize']));

		return $instance;
	}

	/**
	* Creates the edit form for the widget.
	*
	*/
	function form($instance) {
	
		//Defaults
		$instance = wp_parse_args( (array) $instance, array('title'=>'Runkeeper Fitness Feed', 'authCode'=>'', 'feedLength'=>'1', 'styleSheet'=>'yes', 'displaySize'=>'medium' ) );

		$title = htmlspecialchars($instance['title']);
		$authCode = htmlspecialchars($instance['authCode']);
		$feedLength = htmlspecialchars($instance['feedLength']);
		$styleSheet = htmlspecialchars($instance['styleSheet']);
		$displaySize = htmlspecialchars($instance['displaySize']);
	
		// Set up feed select options
		$count_nums = array('1', '5', '10', '15', '20');
		$feed_string = '';
		for( $i=0; $i<count( $count_nums ); $i++ ) {
			$feed_string .= '<option value="'. $count_nums[$i] . '" ';
		
			if( $count_nums[$i] == $feedLength ) { $feed_string .= ' selected="selected"'; }
		
			$feed_string .= "> " . $count_nums[$i] . " </option>";
		}
	
		// Set up display size options
		$count_size = array('small', 'medium', 'large');
		$display_string = '';
		for( $i=0; $i<count( $count_size ); $i++ ) {
			$display_string .= '<option value="'. $count_size[$i] . '" ';
		
			if( $count_size[$i] == $displaySize ) { $display_string .= ' selected="selected"'; }
		
			$display_string .= "> " . $count_size[$i] . " </option>";
		}

		# Output the options
		# Title
		echo '<p style="text-align:right;"><label for="' . $this->get_field_name('title') . '">' . __('Title:') . ' <input style="width: 250px;" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="' . $title . '" /></label></p>';
		# Auth Code
		echo '<p style="text-align:right;"><label for="' . $this->get_field_name('authCode') . '">' . __('Auth code:') . ' <input style="width: 200px;" id="' . $this->get_field_id('authCode') . '" name="' . $this->get_field_name('authCode') . '" type="text" value="' . $authCode . '" /></label></p>';
		# Feed Length
		echo '<p style="text-align:right;"><label for="' . $this->get_field_name('feedLength') . '">' . __('Feed length:') . ' <select id="' . $this->get_field_id('feedLength') . '" name="' . $this->get_field_name('feedLength') . '">' . $feed_string . '</select></label></p>';
		# Display Size
		echo '<p style="text-align:right;"><label for="' . $this->get_field_name('displaySize') . '">' . __('Display size:') . ' <select id="' . $this->get_field_id('displaySize') . '" name="' . $this->get_field_name('displaySize') . '">' . $display_string . '</select></label></p>';

	}
	
	function serveHeader() {
		$siteurl = get_option('siteurl');
		$async = <<<EOT
			<link rel="stylesheet" type="text/css" media="all" href="$this->pluginURL/rk_style.css">
EOT;
		echo $async;
	}
	
}// END class

/**
* Register Runkeeper Feed widget.
*
* Calls 'widgets_init' action after the Runkeeper Feed widget has been registered.
*/
function RunkeeperFeedInit() {
	register_widget('RunkeeperFeedWidget');
}
add_action('widgets_init', 'RunkeeperFeedInit');


/**
* Converts seconds to h:m:s notation.
*/
function sec2hms ($sec, $padHours = false) {

	// start with a blank string
	$hms = "";

	// do the hours first: there are 3600 seconds in an hour, so if we divide
	// the total number of seconds by 3600 and throw away the remainder, we're
	// left with the number of hours in those seconds
	$hours = intval(intval($sec) / 3600); 

	// add hours to $hms (with a leading 0 if asked for)
	$hms .= ($padHours) ? str_pad($hours, 2, "0", STR_PAD_LEFT). ":" : $hours. ":";

	// dividing the total seconds by 60 will give us the number of minutes
	// in total, but we're interested in *minutes past the hour* and to get
	// this, we have to divide by 60 again and then use the remainder
	$minutes = intval(($sec / 60) % 60); 

	// add minutes to $hms (with a leading 0 if needed)
	$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";

	// seconds past the minute are found by dividing the total number of seconds
	// by 60 and using the remainder
	$seconds = intval($sec % 60); 

	// add seconds to $hms (with a leading 0 if needed)
	$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

	// done!
	return $hms;
    
}

/**
* Truncates string to given length
*/
function truncate($substring, $max = 50, $rep = '') { 

	if(strlen($substring) >= 1) { 
		$string = $substring; 
	} 
	$leave = $max - strlen ($rep); 

	if(strlen($string) > $max) { 
		return substr_replace($string, $rep, $leave); 
	}
	else { 
		return $string; 
	} 
    
}

?>