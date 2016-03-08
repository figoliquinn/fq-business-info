<?php
/*
Plugin Name: FQ Business Info
Plugin URI: http://www.figoliquinn.com/
Description: Add general business settings to your wordpress theme
Version: 1.0.0
Author: Figoli Quinn
Author URI: http://www.figoliquinn.com/
License: GPL
Copyright: Figoli Quinn
*/
defined( 'ABSPATH' ) or die( 'No access!' );







function fq_business_info_init() {
	
	if( is_admin() && class_exists('FQ_Settings') ) {

		$settings = new FQ_Settings();
		$settings->parent_slug	= false;
		$settings->menu_slug	= 'business-info-settings';
		$settings->menu_title	= 'Business Info Settings';
		$settings->page_title	= 'Business Info Settings';
		$settings->page_intro	= 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin dapibus pulvinar lacus, id pharetra ipsum ultricies quis. Nullam a placerat dui. In turpis turpis, ultricies vel sodales pulvinar, rhoncus id sapien. Aenean egestas ante quis libero vestibulum porta. Sed faucibus id nibh ac molestie. Sed blandit urna a molestie ultricies. Duis scelerisque varius enim, a dapibus sem aliquet eu. Ut in turpis sed neque facilisis vulputate eu id ex. Nam gravida tempus lectus quis elementum.';
		$settings->settings	= array(
			array(
				'label' => 'Phone #',
				'name' => 'business-phone',
				'type' => 'text',
				'class' => 'regular-text',
				'value' => '',
			),
			array(
				'label' => 'Address',
				'name' => 'business-address',
				'type' => 'text',
				'class' => 'regular-text',
				'value' => '',
			),
			array(
				'label' => 'Business Hours',
				'name' => 'business-hours',
				'type' => 'textarea', // select, radio, checkbox, textarea, upload, OR text
				'class' => 'regular-text', // large-text, regular-text
				'value' => '', // default value
				'description' => 'Enter a comma-seperated list of email addresses to send contact form submissions.',
				'options' => array("Small","Medium","Large"),
				'rows' => 5,
			),
		);

	}

}
add_action( 'init', 'fq_business_info_init' );





function fq_business_info_activate() {
	
	add_option( 'fq_business_info_activated', time() );
	fq_business_info_init();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__ , 'fq_business_info_activate' );




function fq_business_info_deactivate() {

    delete_option( 'fq_business_info_activated' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__ , 'fq_business_info_deactivate' );










/**
 * FUNCTION: process_hours_from_text()
 * Takes a string of business hours and turns it into an array
 *
 *
 */
function process_hours_from_text($hours = '
			Monday 9am-5pm
			Tuesday 9am-5pm
			Wednesday 9-3
			Thursday 9am - 5pm
			Friday 9am - 5:50pm
			Saturday 9am - 5pm
			Sunday 9am - 5pm
') {
	

	$hours = str_replace("Noon","12pm",$hours);
	$hours = str_replace("Midnight","12am",$hours);

	$weekdays = array(
		'monday'=>1,
		'mon'=>1,
		'm'=>1,
		'tuesday'=>2,
		'tues'=>2,
		'tue'=>2,
		't'=>2,
		'wednesday'=>3,
		'wed'=>3,
		'w'=>3,
		'thursday'=>4,
		'thurs'=>4,
		'thu'=>4,
		'th'=>4,
		'friday'=>5,
		'fri'=>5,
		'f'=>5,
		'saturday'=>6,
		'sat'=>6,
		's'=>6,
		'sunday'=>7,
		'sun'=>7,
		'su'=>7,
	);

	if(count(explode("\n",trim($hours)))==1&&strstr($hours,",")){ $hours = str_replace(",","\n",$hours); }

	$hours = array_filter(explode("\n",strtolower($hours)),"trim");

	$clean_hours = array();

	foreach($hours as $n => $line){
	
		preg_match("/(?P<weekday>[A-Za-z]+)(\D+)(?P<open>[0-9apm \:]+)(\D+)(?P<close>[0-9apm \:]+)/",$line,$matches);
		extract($matches);
		$weekday_number = isset($weekdays[$weekday]) ? $weekdays[$weekday] : 0;
		$open = !preg_match("/[a-zA-Z]/",$open) ? $open.'am' : $open;
		$close = !preg_match("/[a-zA-Z]/",$close) ? $close.'pm' : $close;
		$open_hour = date('H:i',strtotime($open));		
		$close_hour = date('H:i',strtotime($close));
		if($weekday_number) { $clean_hours[$weekday_number] = array($open_hour,$close_hour); }
	}
	return $clean_hours;
	
}





/**
 * FUNCTION: are_we_open()
 * Check business hours versus current time.
 * Default hours are 9am - 5pm, Monday - Friday
 * Returns the number of minutes left open, zero if closed.
 *
 *
 */
function are_we_open($hours=array()) {


	$timezone = get_option('timezone_string');

	date_default_timezone_set($timezone?$timezone:'America/Los_Angeles');

	if(!$hours){

		$hours = array(
			false, // ignore key '0'
			array('9:00','17:00'), // monday
			array('9:00','17:00'), // tuesday
			array('9:00','17:00'), // wednesday
			array('9:00','17:00'), // thursday
			array('9:00','17:00'), // friday
			array('0:00','0:00'), // saturday
			array('0:00','0:00'), // sunday
		);
		$hours = process_hours_from_text(get_option('business-hours'));
	}
	
	$now = time();
	$today = date('N'); // numeric day matches above array keys
	
	$open = false;
	foreach($hours as $day => $time) {

		if( $today == $day ) {
			
			// int mktime ([ int $hour = date("H") [, int $minute = date("i") [, int $second = date("s") [, int $month = date("n") [, int $day = date("j") [, int $year = date("Y") [, int $is_dst = -1 ]]]]]]] )
			$start = explode(':',$time[0]);
			$close = explode(':',$time[1]);
			$start_time = mktime((int)$start[0],(int)$start[1],0);
			$close_time = mktime((int)$close[0],(int)$close[1],0);
			// correct for closing times after 11:59pm
			if((int)$close[0]<12){
				$close_time = $close_time + (60*60*24);
			}
			if( $now >= $start_time && $now <= $close_time ) {
				$open = true;
			}
		}
	}
		
	if($open) {
		
		$difference = $close_time - $now;
		$minutes_til_close = round($difference/60);	
		return $minutes_til_close;
	}
	return 0;

}





/**
 * SHORTCODE: Displays Business Hours
 *
 *
 */
function display_business_hours( $atts = array() , $content = '' , $tag = '' ) {
	

	$atts = shortcode_atts( array(
	    'hours' => get_option('business-hours'),
	), $atts );
	extract($atts);

	$weekdays = array(
		'',
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
		'Sunday',
	);

	$hours = process_hours_from_text($hours);

	$text = array();
	
	foreach($weekdays as $d => $day) { 
		
		if($d) {
			if( isset($hours[$d]) && is_array($hours[$d]) ) {
			
				$text[] = str_replace(':00','',$day.': '.date('g:ia',strtotime($hours[$d][0])).' - '.date('g:ia',strtotime($hours[$d][1]))); 
			}
			else {
				
				$text[] = 'Closed '.$day;
			}
		}
		
	}
	
	$text = '<dl><dt>'.display_are_we_open(array('hours'=>$hours)).'</dt><dd>'.implode('</dd><dd>',$text).'</dd></dl>';

	$text = str_replace("12pm","Noon",$text);
	$text = str_replace("12am","Midnight",$text);
	
	return $text;
	
}
add_shortcode( 'business_hours' , 'display_business_hours' );





/**
 * SHORTCODE: Display if Business is Open 
 *
 *
 */
function display_are_we_open( $atts = array() , $content = '' , $tag = '' ) {


	$atts = shortcode_atts( array(
	    'hours' => get_option('business-hours'),
	), $atts );
	extract($atts);


	if($hours) {
		
		$minutes = are_we_open($hours);
	
		if( !$minutes ){
			
			return "Sorry, we're closed.";
		}
		elseif( $minutes < 10 ) {
		
			return "We are open for another few minutes.";
		}
		elseif( $minutes < 60 ) {
		
			return "We are open for ".$minutes." more minutes.";
		}
		return "We are open!";
	}

}
add_shortcode( 'are_we_open' , 'display_are_we_open' );





/*
fq_settings()->add_setting(array(
	'label'=> 'Hours of Operations',
	'name'=> 'business-hours',
	// select, radio, checkbox, textarea, upload, OR text
	'type'=> 'textarea', 
	// default value
	'value'=> '', 
	'description'=>'',
	'options'=>array("Small","Medium","Large"),
	'rows'=>7,
 ));
*/





/**
 * WIDGET: Business Hours
 *
 *
 */
class FQ_Business_Hours extends WP_Widget {



	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		
		parent::__construct(
			'fq_business_hours', // Base ID
			'Business Hours', // Name
			array( 'description' => 'This is a short description.' ) // Args
		);
	}



	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
	
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}
		echo display_business_hours();
		echo $args['after_widget'];
	}



	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : '';
		
		echo '
		<p>
			To edit the business hours go to <a href="'.admin_url('admin.php?page=business-info-settings').'">Theme Settings</a> page.
		</p>
		<p>
			<label for="'.$this->get_field_id( 'title' ).'">Title:</label> 
			<input class="widefat" id="'.$this->get_field_id( 'title' ).'" name="'.$this->get_field_name( 'title' ).'" 
			type="text" value="'.esc_attr( $title ).'">
		</p>
		';

	}



	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}


} // end of class
add_action( 'widgets_init' , function(){ register_widget('FQ_Business_Hours'); } );




