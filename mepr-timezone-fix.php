<?php

/*
Plugin Name: Timezone Fix for MemberPress Coupons
Description: Allow MemberPress Coupons to use the timezone defined in Wordpress (instead of the default UTC).
Version: 1.0.1
Tested up to: 5.7
Author: Sunshine Plugins
Author URI: https://splugins.com

Copyright 2021 Sunshine Plugins (email: support@splugins.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

*/

if (!class_exists('SPFixMeprTZ')) {

	class SPFixMeprTZ {

		public $timezone_string;

		public $plugin_name = "Timezone Fix for MemberPress Coupons";

		public $start_time_h = "00";
		public $start_time_m = "00";
		public $start_time_s = "01";

		public $expire_time_h = "23";
		public $expire_time_m = "59";
		public $expire_time_s = "59";

		public function __construct( $timezone_string ) {

			$this->timezone_string = $timezone_string;
			$this->init();

		}

		function init() {

			// If MemberPress Coupon class exists
			if ( class_exists('MeprCoupon') ) {
			
				$this->display_admin_notice( 'enabled' );
				$this->fix_membpress_coupon_timezone();
				$this->fix_membpress_coupon_javascript();
				$this->fix_mepr_columns_list_page();	
			
			} else {

				$this->display_admin_notice( 'notfound' );

			}			

		}

		/**
		 * Display admin notice when snippet is enabled
		 */

		function display_admin_notice( $type ) {

			if ($type == 'enabled') {
				add_action( 'admin_notices', function() {

					if ( get_post_type() == 'memberpresscoupon' ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p><?php _e( '<span class="dashicons dashicons-clock"></span> The <strong>' . $this->plugin_name . '</strong> plugin is active. 
						Coupons with a Start or Expiry date will run from ' 
						. $this->start_time_h . ':' 
						. $this->start_time_m . ':'
						. $this->start_time_s . ' 
						to '
						. $this->expire_time_h . ':' 
						. $this->expire_time_m . ':'
						. $this->expire_time_s . ' 
						in the following timezone: <strong>'.$this->timezone_string.'</strong>', 'spfixmeprtz' ); ?></p>
			</div>
			<?php

					}

				}, 10 );
			
			} elseif ($type == 'notfound') {

				add_action( 'admin_notices', function() {

			?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'The <strong>' . $this->plugin_name . '</strong> plugin is active but MemberPress was not found.', 'spfixmeprtz' ); ?></p>
			</div>
			<?php

				}, 10 );

			}
		}

		/**
		 * Fix the timestamp when saving coupons
		 */

		function fix_membpress_coupon_timezone() {

			add_action('mepr-coupon-save-meta', function( $coupon ) {
			
				// get/set the timezone setting from wordpress
				$tz = wp_timezone_string();
				date_default_timezone_set($tz);

				// create datetime object 
				$dt = new DateTime();
				
				// check for start date
				if ($coupon->rec->should_start) {
					// original start date in UTC
					$utc_start = $coupon->rec->starts_on;
					$dt->setTimestamp($utc_start);
					
					// create new start date using correct timezone 
					$new_start = mktime($this->start_time_h, $this->start_time_m, $this->start_time_s, $dt->format('m'), $dt->format('d'), $dt->format('Y'));
					$coupon->rec->starts_on = $new_start;
				}
				
				// check for expiry date
				if ($coupon->rec->should_expire) {
					// original expiry in UTC
					$utc_ends = $coupon->rec->expires_on;
					$dt->setTimestamp($utc_ends);
					
					// create new expiry date using correct timezone
					$new_end = mktime($this->expire_time_h, $this->expire_time_m, $this->expire_time_s, $dt->format('m'), $dt->format('d'), $dt->format('Y'));
					$coupon->rec->expires_on = $new_end;
				}
				
				// save changes
				$coupon->store_meta();

			}, 20, 1);
		}
		

		/**
		 * Fix the date input fields when editing coupons
		 */

		function fix_membpress_coupon_javascript() {

			add_action( 'admin_footer', function() {

				if ( get_post_type() == 'memberpresscoupon' ) { 

					// Get the coupon
					$c = new MeprCoupon(get_the_ID());

					// check for a start date
					$start = $c->rec->starts_on;

					// check for an expiry
					$expiry = $c->rec->expires_on;

					if ($start || $expiry) {

						// get/set the timezone setting from wordpress
						$tz = wp_timezone_string();
						date_default_timezone_set($tz);

						// create datetime object 
						$dt = new DateTime();

						if ($start) {
							$dt->setTimestamp($start);
							$dt->setTimezone(new DateTimeZone($tz));
							$st_dy = $dt->format('j');
							$st_mn = $dt->format('n');
							$st_yr = $dt->format('Y');
						} 

						if ($expiry) {
							$dt->setTimestamp($expiry);
							$dt->setTimezone(new DateTimeZone($tz));
							$ex_dy = $dt->format('j');
							$ex_mn = $dt->format('n');
							$ex_yr = $dt->format('Y');
						}
					}
				}

				?>

				<script type="text/javascript">
					
					document.addEventListener("DOMContentLoaded", function() {

						// Adding or Editing a MemberPress Coupon
						if ( /[?&]action=edit/.test(location.search) || /[?&]post_type=memberpresscoupon/.test(location.search) ) {

							var sp_lstInput = document.querySelectorAll('#mepr_start_coupon_box td input');
								sp_lstInput = sp_lstInput[sp_lstInput.length - 1];

							var sp_newNode 	= document.createElement('span');
								sp_newNode.innerHTML = ' Coupon Valid from <strong>00:00:01 AM (<?php echo $this->timezone_string; ?>)</strong>';
							
							// Add a timezone note near the start inputs
							sp_lstInput.parentNode.insertBefore(sp_newNode, sp_lstInput.nextSibling);

							// Update the timezone note near the expiry inputs
							document.querySelector('#mepr_expire_coupon_box td strong').textContent = 'Midnight (<?php echo $this->timezone_string; ?>)';
						}
						
						/* Editing a MemberPress coupon */
						if (/[?&]action=edit/.test(location.search)) {

						<?php 	if ($start) { ?>

							document.getElementsByName('mepr_coupons_start_day')[0].value = <?php echo $st_dy; ?>;
							document.getElementsByName('mepr_coupons_start_month')[0].value = <?php echo $st_mn; ?>;
							document.getElementsByName('mepr_coupons_start_year')[0].value = <?php echo $st_yr; ?>;

						<?php 	} if ($expiry) { ?>

							document.getElementsByName('mepr_coupons_ex_day')[0].value = <?php echo $ex_dy; ?>;
							document.getElementsByName('mepr_coupons_ex_month')[0].value = <?php echo $ex_mn; ?>;
							document.getElementsByName('mepr_coupons_ex_year')[0].value = <?php echo $ex_yr; ?>;
							
						<?php 	} ?>

						}
					});
				</script>
			<?php
			}); // end of add_action
		}
		

		/**
		 * Fix the date in the columns on the Coupon list page
		 */

		function fix_mepr_columns_list_page() {

			add_action('manage_posts_custom_column', array( $this, 'fix_membpress_columns_open' ), 9, 2);
			add_action('manage_posts_custom_column', array( $this, 'fix_membpress_columns_close'), 11, 2);
		}

		function fix_membpress_columns_open($column, $coupon_id) {

		    $coupon = new MeprCoupon($coupon_id);
			$tz = $this->timezone_string;

		    if ($coupon->ID !== null) {
		      switch($column) {
		        case 'coupon-starts':
		          if ($coupon->post_status != 'trash') {
		            if ($coupon->should_start) {
		            	// create datetime object 
						$dt = new DateTime();
						$dt->setTimestamp($coupon->starts_on);
						$dt->setTimezone(new DateTimeZone($tz));
						echo $dt->format('M d, Y');
						echo '<br>';
						echo $dt->format('H:i:s');
						echo '<br>';
						echo $this->timezone_string;
						echo '<span style="display:none;">';
		            }
		          }
		          break;
		        case 'coupon-expires':
		          if ($coupon->post_status != 'trash') {
		            if ($coupon->should_expire) {
						// create datetime object 
						$dt = new DateTime();
						$dt->setTimestamp($coupon->expires_on);
						$dt->setTimezone(new DateTimeZone($tz));
						echo $dt->format('M d, Y');
						echo '<br>';
						echo $dt->format('H:i:s');
						echo '<br>';
						echo $this->timezone_string;
						echo '<span style="display:none;">';
		            }
		          }
		          break;
		      }
		    }
		}

		function fix_membpress_columns_close( $column, $coupon_id ) {
		    $mepr_options = MeprOptions::fetch();
		    $coupon = new MeprCoupon($coupon_id);

		    if($coupon->ID !== null) {
		      switch($column) {
		        case 'coupon-starts':
		        case 'coupon-expires':			  
		          if($coupon->post_status != 'trash') {
		            if($coupon->should_start) {
						echo '</span>';
		            }
		          }
		          break;
		      }
		    }
		}
	}

	// Initialize the plugin!
	$fix_mepr_tz = new SPFixMeprTZ( wp_timezone_string() );

}