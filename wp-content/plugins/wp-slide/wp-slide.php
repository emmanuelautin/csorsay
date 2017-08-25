<?php
/*
Plugin Name: WP-Slide
Plugin URI: http://www.shrawasti.com/about/development-center/
Description: This plugin creates an image slideshow from the images you upload using the jQuery Slide plugin. You can upload/delete images via the administration panel, and display the images in your theme by using the <code>wp_slide();</code> template tag, which will generate all the necessary HTML for outputting the rotating images.
Version: 1.1
Author: Mohammad Atif
Author URI: http://www.shrawasti.com/atif/

This plugin inherits the GPL license from it's parent system, WordPress.
*/

/*
///////////////////////////////////////////////
This section defines the variables that
will be used throughout the plugin
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/
//	define our defaults (filterable)
$wp_slide_defaults = apply_filters('wp_slide_defaults', array(
	'rotate' => 1,
	'effect' => 'fade',
	'delay' => 3,
	'duration' => 1,
	'img_width' => 300,
	'img_height' => 200,
	'div' => 'slider'
));

//	pull the settings from the db
$wp_slide_settings = get_option('wp_slide_settings');
$wp_slide_images = get_option('wp_slide_images');

//	fallback
$wp_slide_settings = wp_parse_args($wp_slide_settings, $wp_slide_defaults);


/*
///////////////////////////////////////////////
This section hooks the proper functions
to the proper actions in WordPress
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/

//	this function registers our settings in the db
add_action('admin_init', 'wp_slide_register_settings');
function wp_slide_register_settings() {
	register_setting('wp_slide_images', 'wp_slide_images', 'wp_slide_images_validate');
	register_setting('wp_slide_settings', 'wp_slide_settings', 'wp_slide_settings_validate');
}
//	this function adds the settings page to the Appearance tab
add_action('admin_menu', 'add_wp_slide_menu');
function add_wp_slide_menu() {
	add_submenu_page('upload.php', 'WP-Slide Settings', 'WP-Slide', 'upload_files', 'wp-slide', 'wp_slide_admin_page');
}

//	add "Settings" link to plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__) , 'wp_slide_plugin_action_links');
function wp_slide_plugin_action_links($links) {
	$wp_slide_settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'upload.php?page=wp-slide' ), __('Settings') );
	array_unshift($links, $wp_slide_settings_link);
	return $links;
}


/*
///////////////////////////////////////////////
this function is the code that gets loaded when the
settings page gets loaded by the browser.  It calls 
functions that handle image uploads and image settings
changes, as well as producing the visible page output.
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/
function wp_slide_admin_page() {
	echo '<div class="wrap">';
	
		//	handle image upload, if necessary
		if($_REQUEST['action'] == 'wp_handle_upload')
			wp_slide_handle_upload();
		
		//	delete an image, if necessary
		if(isset($_REQUEST['delete']))
			wp_slide_delete_upload($_REQUEST['delete']);
		
		//	the image management form
		wp_slide_images_admin();
		
		//	the settings management form
		wp_slide_settings_admin();

	echo '</div>';
}


/*
///////////////////////////////////////////////
this section handles uploading images, adding
the image data to the database, deleting images,
and deleting image data from the database.
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/
//	this function handles the file upload,
//	resize/crop, and adds the image data to the db
function wp_slide_handle_upload() {
	global $wp_slide_settings, $wp_slide_images;
	
	//	upload the image
	$upload = wp_handle_upload($_FILES['wp_slide'], 0);
	
	//	extract the $upload array
	extract($upload);
	
	//	the URL of the directory the file was loaded in
	$upload_dir_url = str_replace(basename($file), '', $url);
	
	//	get the image dimensions
	list($width, $height) = getimagesize($file);
	
	//	if the uploaded file is NOT an image
	if(strpos($type, 'image') === FALSE) {
		unlink($file); // delete the file
		echo '<div class="error" id="message"><p>Sorry, but the file you uploaded does not seem to be a valid image. Please try again.</p></div>';
		return;
	}
	
	//	if the image doesn't meet the minimum width/height requirements ...
	if($width < $wp_slide_settings['img_width'] || $height < $wp_slide_settings['img_height']) {
		unlink($file); // delete the image
		echo '<div class="error" id="message"><p>Sorry, but this image does not meet the minimum width/height requirements. Please upload another image</p></div>';
		return;
	}
	
	//	if the image is larger than the width/height requirements, then scale it down.
	if($width > $wp_slide_settings['img_width'] || $height > $wp_slide_settings['img_height']) {
		//	resize the image
		$resized = image_resize($file, $wp_slide_settings['img_width'], $wp_slide_settings['img_height'], true, 'resized');
		$resized_url = $upload_dir_url . basename($resized);
		//	delete the original
		unlink($file);
		$file = $resized;
		$url = $resized_url;
	}
	
	//	make the thumbnail
	$thumb_height = round((100 * $wp_slide_settings['img_height']) / $wp_slide_settings['img_width']);
	if(isset($upload['file'])) {
		$thumbnail = image_resize($file, 100, $thumb_height, true, 'thumb');
		$thumbnail_url = $upload_dir_url . basename($thumbnail);
	}
	
	//	use the timestamp as the array key and id
	$time = date('YmdHis');
	
	//	add the image data to the array
	$wp_slide_images[$time] = array(
		'id' => $time,
		'file' => $file,
		'file_url' => $url,
		'thumbnail' => $thumbnail,
		'thumbnail_url' => $thumbnail_url,
		'image_links_to' => ''
	);
	
	//	add the image information to the database
	$wp_slide_images['update'] = 'Added';
	update_option('wp_slide_images', $wp_slide_images);
}

//	this function deletes the image,
//	and removes the image data from the db
function wp_slide_delete_upload($id) {
	global $wp_slide_images;
	
	//	if the ID passed to this function is invalid,
	//	halt the process, and don't try to delete.
	if(!isset($wp_slide_images[$id])) return;
	
	//	delete the image and thumbnail
	unlink($wp_slide_images[$id]['file']);
	unlink($wp_slide_images[$id]['thumbnail']);
	
	//	indicate that the image was deleted
	$wp_slide_images['update'] = 'Deleted';
	
	//	remove the image data from the db
	unset($wp_slide_images[$id]);
	update_option('wp_slide_images', $wp_slide_images);
}


/*
///////////////////////////////////////////////
these two functions check to see if an update
to the data just occurred. if it did, then they
will display a notice, and reset the update option.
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/
//	this function checks to see if we just updated the settings
//	if so, it displays the "updated" message.
function wp_slide_settings_update_check() {
	global $wp_slide_settings;
	if(isset($wp_slide_settings['update'])) {
		echo '<div class="updated fade" id="message"><p>WP-Slide Settings <strong>'.$wp_slide_settings['update'].'</strong></p></div>';
		unset($wp_slide_settings['update']);
		update_option('wp_slide_settings', $wp_slide_settings);
	}
}
//	this function checks to see if we just added a new image
//	if so, it displays the "updated" message.
function wp_slide_images_update_check() {
	global $wp_slide_images;
	if($wp_slide_images['update'] == 'Added' || $wp_slide_images['update'] == 'Deleted' || $wp_slide_images['update'] == 'Updated') {
		echo '<div class="updated fade" id="message"><p>Image(s) '.$wp_slide_images['update'].' Successfully</p></div>';
		unset($wp_slide_images['update']);
		update_option('wp_slide_images', $wp_slide_images);
	}
}


/********************************************
these two functions display the front-end code
on the admin page. it's mostly form markup.
********************************************/
//	display the images administration code
function wp_slide_images_admin() { ?>
	<?php global $wp_slide_images; ?>
	<?php wp_slide_images_update_check(); ?>
	<h2><?php _e('WP-Slide Images', 'wp_slide'); ?></h2>
	
	<table class="form-table">
		<tr valign="top"><th scope="row">Upload New Image</th>
			<td>
			<form method="post" action="?page=wp-slide" enctype="multipart/form-data">
				<input type="hidden" name="post_id" id="post_id" value="0" />
				<input type="hidden" name="action" id="action" value="wp_handle_upload" />
				
				<label for="wp_slide">Select a File: </label>
				<input type="file" name="wp_slide" id="wp_slide" />
				<input type="submit" class="button-primary" name="html-upload" value="Upload" />
			</form>
			</td>
		</tr>
	</table><br />
	
	<?php if(!empty($wp_slide_images)) : ?>
	<table class="widefat fixed" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" class="column-slug">Image</th>
				<th scope="col">Image Links To</th>
				<th scope="col" class="column-slug">Actions</th>
			</tr>
		</thead>
		
		<tfoot>
			<tr>
				<th scope="col" class="column-slug">Image</th>
				<th scope="col">Image Links To</th>
				<th scope="col" class="column-slug">Actions</th>
			</tr>
		</tfoot>
		
		<tbody>
		
		<form method="post" action="options.php">
		<?php settings_fields('wp_slide_images'); ?>
		<?php foreach((array)$wp_slide_images as $image => $data) : ?>
			<tr>
				<input type="hidden" name="wp_slide_images[<?php echo $image; ?>][id]" value="<?php echo $data['id']; ?>" />
				<input type="hidden" name="wp_slide_images[<?php echo $image; ?>][file]" value="<?php echo $data['file']; ?>" />
				<input type="hidden" name="wp_slide_images[<?php echo $image; ?>][file_url]" value="<?php echo $data['file_url']; ?>" />
				<input type="hidden" name="wp_slide_images[<?php echo $image; ?>][thumbnail]" value="<?php echo $data['thumbnail']; ?>" />
				<input type="hidden" name="wp_slide_images[<?php echo $image; ?>][thumbnail_url]" value="<?php echo $data['thumbnail_url']; ?>" />
				<th scope="row" class="column-slug"><img src="<?php echo $data['thumbnail_url']; ?>" /></th>
				<td><input type="text" name="wp_slide_images[<?php echo $image; ?>][image_links_to]" value="<?php echo $data['image_links_to']; ?>" size="35" /></td>
				<td class="column-slug"><input type="submit" class="button-primary" value="Update" /> <a href="?page=wp-slide&amp;delete=<?php echo $image; ?>" class="button">Delete</a></td>
			</tr>
		<?php endforeach; ?>
		<input type="hidden" name="wp_slide_images[update]" value="Updated" />
		</form>
		
		</tbody>
	</table>
	<?php endif; ?>

<?php
}

//	display the settings administration code
function wp_slide_settings_admin() { ?>

	<?php wp_slide_settings_update_check(); ?>
	<h2><?php _e('WP-Slide Settings', 'wp-slide'); ?></h2>
	<form method="post" action="options.php">
	<?php settings_fields('wp_slide_settings'); ?>
	<?php global $wp_slide_settings; $options = $wp_slide_settings; ?>
	<table class="form-table">

		<tr valign="top"><th scope="row">Transition Enabled</th>
		<td><input name="wp_slide_settings[rotate]" type="checkbox" value="1" <?php checked('1', $options['rotate']); ?> /> <label for="wp_slide_settings[rotate]">Check this box if you want to enable the transition effects</label></td>
		</tr>
		
		<tr><th scope="row">Transition Effect</th>
		<td>Please select the effect you would like to use when your images slide (if applicable):<br />
			<select name="wp_slide_settings[effect]">
				<option value="fade" <?php selected('fade', $options['effect']); ?>>fade</option>
				<option value="wipe" <?php selected('wipe', $options['effect']); ?>>wipe</option>
				<option value="scrollUp" <?php selected('scrollUp', $options['effect']); ?>>scrollUp</option>
				<option value="scrollDown" <?php selected('scrollDown', $options['effect']); ?>>scrollDown</option>
				<option value="scrollLeft" <?php selected('scrollLeft', $options['effect']); ?>>scrollLeft</option>
				<option value="scrollRight" <?php selected('scrollRight', $options['effect']); ?>>scrollRight</option>
				<option value="cover" <?php selected('cover', $options['effect']); ?>>cover</option>
				<option value="shuffle" <?php selected('shuffle', $options['effect']); ?>>shuffle</option>
			</select>
		</td></tr>
		
		<tr><th scope="row">Transition Delay</th>
		<td>Length of time (in seconds) you would like each image to be visible:<br />
			<input type="text" name="wp_slide_settings[delay]" value="<?php echo $options['delay'] ?>" size="4" />
			<label for="wp_slide_settings[delay]">second(s)</label>
		</td></tr>
		
		<tr><th scope="row">Transition Length</th>
		<td>Length of time (in seconds) you would like the transition length to be:<br />
			<input type="text" name="wp_slide_settings[duration]" value="<?php echo $options['duration'] ?>" size="4" />
			<label for="wp_slide_settings[duration]">second(s)</label>
		</td></tr>

		<tr><th scope="row">Image Dimensions</th>
		<td>Please input the width of the image slider:<br />
			<input type="text" name="wp_slide_settings[img_width]" value="<?php echo $options['img_width'] ?>" size="4" />
			<label for="wp_slide_settings[img_width]">px</label>
			<br /><br />
			Please input the height of the image slider:<br />
			<input type="text" name="wp_slide_settings[img_height]" value="<?php echo $options['img_height'] ?>" size="4" />
			<label for="wp_slide_settings[img_height]">px</label>
		</td></tr>
		
		<tr><th scope="row">Slider DIV ID</th>
		<td>Please indicate what you would like the slider DIV ID to be:<br />
			<input type="text" name="wp_slide_settings[div]" value="<?php echo $options['div'] ?>" />
		</td></tr>
		
		<input type="hidden" name="wp_slide_settings[update]" value="UPDATED" />
	
	</table>
	<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
	</form>
	
	<!-- The Reset Option -->
	<form method="post" action="options.php">
	<?php settings_fields('wp_slide_settings'); ?>
	<?php global $wp_slide_defaults; // use the defaults ?>
	<?php foreach((array)$wp_slide_defaults as $key => $value) : ?>
	<input type="hidden" name="wp_slide_settings[<?php echo $key; ?>]" value="<?php echo $value; ?>" />
	<?php endforeach; ?>
	<input type="hidden" name="wp_slide_settings[update]" value="RESET" />
	<input type="submit" class="button" value="<?php _e('Reset Settings') ?>" />
	</form>
	<!-- End Reset Option -->
	</p>

<?php
}


/*
///////////////////////////////////////////////
these two functions sanitize the data before it
gets stored in the database via options.php
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/
//	this function sanitizes our settings data for storage
function wp_slide_settings_validate($input) {
	$input['rotate'] = ($input['rotate'] == 1 ? 1 : 0);
	$input['effect'] = wp_filter_nohtml_kses($input['effect']);
	$input['img_width'] = intval($input['img_width']);
	$input['img_height'] = intval($input['img_height']);
	$input['div'] = wp_filter_nohtml_kses($input['div']);
	
	return $input;
}
//	this function sanitizes our image data for storage
function wp_slide_images_validate($input) {
	foreach((array)$input as $key => $value) {
		if($key != 'update') {
			$input[$key]['file_url'] = clean_url($value['file_url']);
			$input[$key]['thumbnail_url'] = clean_url($value['thumbnail_url']);
			
			if($value['image_links_to'])
			$input[$key]['image_links_to'] = clean_url($value['image_links_to']);
		}
	}
	return $input;
}

/*
///////////////////////////////////////////////
this final section generates all the code that
is displayed on the front-end of the WP Theme
\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
*/
function wp_slide($args = array(), $content = null) {
	global $wp_slide_settings, $wp_slide_images;
	
	// possible future use
	$args = wp_parse_args($args, $wp_slide_settings);
	
	$newline = "\n"; // line break
	
	echo '<div id="'.$wp_slide_settings['div'].'">'.$newline;
	
	foreach((array)$wp_slide_images as $image => $data) {
		if($data['image_links_to'])
		echo '<a href="'.$data['image_links_to'].'">';
		
		echo '<img src="'.$data['file_url'].'" width="'.$wp_slide_settings['img_width'].'" height="'.$wp_slide_settings['img_height'].'" class="'.$data['id'].'" alt="" />';
		
		if($data['image_links_to'])
		echo '</a>';
		
		echo $newline;
	}
	
	echo '</div>'.$newline;
}

//	create the shortcode [wp_slide]
add_shortcode('wp_slide', 'wp_slide_shortcode');
function wp_slide_shortcode($atts) {
	
	// Temp solution, output buffer the echo function.
	ob_start();
	wp_slide();
	$output = ob_get_clean();
	
	return $output;
	
}

add_action('wp_print_scripts', 'wp_slide_scripts');
function wp_slide_scripts() {
	if(!is_admin())
	wp_enqueue_script('slide', WP_CONTENT_URL.'/plugins/wp-slide/jquery.slide.all.min.js', array('jquery'), '', true);
}

add_action('wp_footer', 'wp_slide_args', 15);
function wp_slide_args() {
	global $wp_slide_settings; ?>

<?php if($wp_slide_settings['rotate']) : ?>
<script type="text/javascript">
jQuery(document).ready(function($) {
	$("#<?php echo $wp_slide_settings['div']; ?>").slide({ 
	    fx: '<?php echo $wp_slide_settings['effect']; ?>',
	    timeout: <?php echo ($wp_slide_settings['delay'] * 1000); ?>,
	    speed: <?php echo ($wp_slide_settings['duration'] * 1000); ?>,
	    pause: 1,
	    fit: 1
	});
});
</script>
<?php endif; ?>

<?php }

add_action( 'wp_head', 'wp_slide_style' );
function wp_slide_style() { 
	global $wp_slide_settings;
?>
	
<style type="text/css" media="screen">
	#<?php echo $wp_slide_settings['div']; ?> {
		position: relative;
		width: <?php echo $wp_slide_settings['img_width']; ?>px;
		height: <?php echo $wp_slide_settings['img_height']?>px;
		margin: 0; padding: 0;
		overflow: hidden;
	}
</style>
	
<?php }