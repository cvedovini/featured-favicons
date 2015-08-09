<?php
/*
Plugin Name: Featured Favicons
Plugin URI: http://vedovini.net/plugins/?utm_source=wordpress&utm_medium=plugin&utm_campaign=featured-favicons
Description: A plugin that uses the featured image in your posts as a favicon for that post.
Author: Claude Vedovini
Author URI: http://vedovini.net/?utm_source=wordpress&utm_medium=plugin&utm_campaign=featured-favicons
Version: 1.0.1
Text Domain: featured-favicons

# The code in this plugin is free software; you can redistribute the code aspects of
# the plugin and/or modify the code under the terms of the GNU Lesser General
# Public License as published by the Free Software Foundation; either
# version 3 of the License, or (at your option) any later version.

# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
# EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
# NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
# LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
# OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
# WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
#
# See the GNU lesser General Public License for more details.
*/


function ff_load() {
	add_action('init', 'ff_init');
	add_action('admin_menu', 'ff_admin_menu');

	// Make plugin available for translation
	// Translations can be filed in the /languages/ directory
	add_filter('load_textdomain_mofile', 'ff_smarter_load_textdomain', 10, 2);
	load_plugin_textdomain('featured-favicons', false, dirname(plugin_basename(__FILE__)) . '/languages/' );
}
add_action('plugins_loaded', 'ff_load');


function ff_init() {
	add_image_size('favicon', 64, 64, true);
	add_action('wp_head', 'ff_head');

	if (get_option('ff_use_as_default_image')) {
		add_filter('get_post_metadata', 'ff_get_post_metadata', 10, 4);
	}
}


function ff_smarter_load_textdomain($mofile, $domain) {
	if ($domain == 'featured-favicons' && !is_readable($mofile)) {
		extract(pathinfo($mofile));
		$pos = strrpos($filename, '_');

		if ($pos !== false) {
			# cut off the locale part, leaving the language part only
			$filename = substr($filename, 0, $pos);
			$mofile = $dirname . '/' . $filename . '.' . $extension;
		}
	}

	return $mofile;
}


function ff_head() {
	if (has_post_thumbnail()) {
		$thumb_id = get_post_thumbnail_id();
	} else {
		$thumb_id = get_option('ff_default_favicon_id');
	}

	if (!empty($thumb_id)) {
		$thumb_src = wp_get_attachment_image_src($thumb_id, 'favicon');
		$thumb_url = $thumb_src[0];
		$thumb_mime = get_post_mime_type($thumb_id);

		if (!empty($thumb_url)) {
			echo "<link rel=\"shortcut icon\" href=\"$thumb_url\" type=\"$thumb_mime\" />";
		}
	}
}


function ff_get_post_metadata($null, $object_id, $meta_key, $single) {
	if (is_admin()) return;

	// only affect thumbnails
	if ('_thumbnail_id' != $meta_key)
		return;

	//@see /wp-includes/meta.php get_metadata()
	$meta_type = 'post';
	$meta_cache = wp_cache_get($object_id, $meta_type . '_meta');

	if (!$meta_cache) {
		$meta_cache = update_meta_cache($meta_type, array($object_id));
		$meta_cache = $meta_cache[$object_id];
	}

	if (!$meta_key) return $meta_cache;

	if (isset($meta_cache[$meta_key])) {
		if ($single)
			return maybe_unserialize($meta_cache[$meta_key][0]);
		else
			return array_map('maybe_unserialize', $meta_cache[$meta_key]);
	}

	if ($single)
		return get_option('ff_default_favicon_id', ''); // set the default featured img ID
	else
		return array();
}


function ff_admin_menu() {
	add_filter('plugin_action_links_featured-favicons/featured-favicons.php', 'ff_add_settings_link');
	add_options_page(__('Favicons', 'featured-favicons'), __('Favicons', 'featured-favicons'),
			'manage_options', 'featured-favicons', 'ff_options_page');
	add_settings_section('default', '', false, 'featured-favicons');

	register_setting('featured-favicons', 'ff_default_favicon_id');
	add_settings_field('ff_default_favicon_id', __('Default favicon', 'featured-favicons'),
			'ff_default_favicon_field', 'featured-favicons', 'default');

	register_setting('featured-favicons', 'ff_use_as_default_image');
	add_settings_field('ff_use_as_default_image', __('Use as default featured image', 'featured-favicons'),
			'ff_use_as_default_image_field', 'featured-favicons', 'default');
}


function ff_add_settings_link($links) {
	$url = site_url('/wp-admin/options-general.php?page=featured-favicons');
	$links[] = '<a href="' . $url . '">' . __('Settings') . '</a>';
	return $links;
}


function ff_default_favicon_field() {
	$thumb_id = get_option('ff_default_favicon_id');
	$thumb_img = '';

	if (!empty($thumb_id)) {
		$thumb_img = wp_get_attachment_image($thumb_id, thumbnail);
	} ?>

	<input type="hidden" name="ff_default_favicon_id" id="ff_default_favicon_id"
		value="<?php echo get_option('ff_default_favicon_id'); ?>" />
	<input class="button" type="button" id="favicon_select"
		value="<?php _e('Select a favicon', 'featured-favicons'); ?>"/><br/>
	<div id="favicon_demo" style="margin-top:20px"><?php echo $thumb_img; ?></div>
	<script language="javascript">
	jQuery(document).ready(function($){

	    var custom_uploader;

	    $('#favicon_select').click(function(e) {
	        e.preventDefault();

	        //If the uploader object has already been created, reopen the dialog
	        if (custom_uploader) {
	            custom_uploader.open();
	            return;
	        }

	        //Extend the wp.media object
	        custom_uploader = wp.media.frames.file_frame = wp.media({
	            title: 'Select a default favicon',
	            button: {
	                text: 'Select a default favicon'
	            },
	            multiple: false
	        });

	        //When a file is selected, grab the URL and set it as the text field's value
	        custom_uploader.on('select', function() {
	            attachment = custom_uploader.state().get('selection').first().toJSON();
	            $('#ff_default_favicon_id').val(attachment.id);
	            var thumb = attachment.sizes.thumbnail;
	            $('#favicon_demo').html('<img src="' + thumb.url + '" width="' +
	    	            thumb.width + '" height="' + thumb.height + '" />');
	        });

	        //Open the uploader dialog
	        custom_uploader.open();

	    });
	});
	</script><?php
}

function ff_use_as_default_image_field() { ?>
	<label><input type="checkbox" name="ff_use_as_default_image"
		value="1" <?php checked(get_option('ff_use_as_default_image')); ?> />&nbsp;
		<?php _e('Check this option if you also want to use that image as the default featured image.', 'featured-favicons') ?></label><?php
}


function ff_options_page() {
	wp_enqueue_media(); ?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h1><?php _e('Favicons Options', 'featured-favicons'); ?></h1>
		<form method="POST" action="options.php"><?php
			settings_fields('featured-favicons');
			do_settings_sections('featured-favicons');
			submit_button(); ?>
		</form>
	</div><?php
}
