<?php
/*
Plugin Name: EasyVideoPlayer 2.0
Plugin URI: http://www.seodenver.com/easy-video-player/
Description: A simple, attractive way to EasyVideoPlayer videos into your WordPress website.
Author: Katz Web Services, Inc.
Version: 1.4.2
Author URI: http://www.katzwebservices.com

Copyright 2012 Katz Web Services, Inc.  (email: info@katzwebservices.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

add_action('plugins_loaded', 'evp_plugins_loaded');

function evp_plugins_loaded() {
	add_action('admin_notices', 'evp_admin_notice');
	add_action('media_buttons_context', 'evp_add_form_button', 99999); // Add media buttons
	add_action('init', 'evp_tinymce_addbuttons'); // Add tinyMCE buttons
}

function evp_admin_notice() {

	if(isset($_REQUEST['evp_message'])) {
		$show = ($_REQUEST['evp_message'] == 'hide');
		update_option('evp_message', $show);
	}

	if(is_admin() && current_user_can('manage_options') && !get_option('evp_message')) {

?>
<div class="updated" style="background-color:#F7F8FA; border-color:#444;">
		<h2>The EVP Plugin for WordPress requires <a href="http://katz.si/evp" title="Buy Easy Video Player">Easy Video Player</a>.</h2>
		<h3 style="font-size:18px;"><img src="http://evp-site-assets.s3.amazonaws.com/main_images/icon-testimonial.png" width="40" height="38">Just Some Of The Top Marketers Who Use EasyVideoPlayer 2!</h3>
		<p><img src="http://evp-site-assets.s3.amazonaws.com/assets/marketers.jpg" alt="Mike Filsame, Chris Farrell, Ryan Deiss, Adam Horwitz, Jason Moffatt, Justin Brooke, Maria Andros" width="861" height="155" style="display:block;" /></p>
		<p style="font-size:16px;">EasyVideoPlayer 2 Comes With A Full NO Questions Asked 60 Day Money Back Guarantee For Full Peace Of Mind</p>
		<p style="margin:1em 0; border-top:1px solid #ccc; padding-top:1em;">
			<a href="http://katz.si/evp" target="_blank" style="white-space:nowrap; font-size:16px!important; font-weight:bold; margin-right:1em;" class="button button-primary">Buy Easy Video Player Now!</a>
			<a href="<?php echo add_query_arg('evp_message', 'hide'); ?>" class="button" style="font-style:normal;">I know, hide this message</a>
		</p>
		<div class="clear"></div>
	</div>
<?php
	}
}

function evp_tinymce_addbuttons() {
	// Don't bother doing this stuff if the current user lacks permissions
	if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
	return;

	// Add only in Rich Editor mode
	if ( get_user_option('rich_editing') == 'true') {
		add_filter("mce_external_plugins", "evp_add_custom_tinymce_plugin");
		add_filter('mce_buttons', 'evp_register_custom_button');
	}
}
function evp_register_custom_button($buttons) {
	array_push($buttons, "|", "wpevp");
	return $buttons;
}

function evp_add_custom_tinymce_plugin($plugin_array) {
	$plugin_array['wpevp'] = plugin_dir_url(__FILE__).'evp_editor_plugin.js';
	return $plugin_array;
}

function evp_add_form_button($context){
    $image_btn = plugin_dir_url(__FILE__).'images/button.gif';
    $out = '<a href="#TB_inline?width=450&height=700&inlineId=insert_evp" class="thickbox" title="' . __("Embed EVP", 'wpevp') . '"><img src="'.$image_btn.'" alt="' . __("Embed EVP Video", 'wpevp') . '" /></a>';
    return $context . $out;
}

function evp_replace_brackets($code = '') {
	$code = str_replace('%%LBRACKET%%', '[', $code);
	$code = str_replace('%%RBRACKET%%', ']', $code);
	$code = str_replace('%%SQUOTE%%', "'", $code);
	return $code;
}

function evp_get_embed_url_from_src($srcurl, $urlencode = true) {
	$configurl = str_replace('profile=default', 'profile=object', $srcurl);
	$configurl = str_replace('mode=default', '', $configurl);
	$configurl = str_replace('mode=object', '', $configurl);
	$configurl .= '&mode=object';
	if($urlencode) {
		$configurl = urlencode($configurl);
		$configurl = str_replace('%26amp%3B', '%26', $configurl);
	}
	return $configurl;
}

function wpevp_get_video_details($srcurl) {

	if(!apply_filters('evp_add_microformats', true)) { return false; }

	$cachekey = 'evp_'.sha1($srcurl);

	// Let's get the video data, if set.
	$cache = get_site_transient($cachekey);
	if(!empty($cache) && !isset($_REQUEST['cache'])) {
		return maybe_unserialize($cache);
	}

	$src = wp_remote_get(htmlspecialchars_decode($srcurl));
	if(is_wp_error($src) || !$src) { return false; }
	preg_match('/_evpStorage\[\'.*?\'\]\s=\s({.*}});/ism', $src['body'], $json);

	if(!isset($json[1])) { return false; }

	$json = stripslashes_deep($json[1]);
	$json = json_decode($json);

	// JSON isn't properly formatted.
	if(!is_object($json)) { return false; }

	//
	// We're going to make a bunch of settings based on http://schema.org/VideoObject
	//

	// URL of the video
	$video['url'] = $json->asset_location.'player/flowplayer-3.2.7/flowplayer.unlimited-3.2.7.swf';

	$configurl = evp_get_embed_url_from_src($srcurl);

	$video['embedUrl'] = add_query_arg(array('config' => $configurl), $video['url']);
	$video['playerType'] = 'Flash';

	// Add a thumbnail
	if(isset($json->splash) && !empty($json->splash)) {
		$video['thumbnailUrl'] = $video['splash_image'];
	}

	$video['encodingFormat'] = $json->extension;

	// Needs to be ISO 8601
	$seconds = $json->metainfo->duration;
	$hours = floor($seconds / 3600); //make hours
	$hours = !empty($hours) ? "{$hours}h" : '';
	$rem_seconds = $seconds % 3600; //get the remainder
	$minutes = floor($rem_seconds / 60);  //make minutes
	$rem_seconds = round($rem_seconds % 60, 0); //remainder is seconds

	$video['duration'] = "P{$hours}{$minutes}i{$rem_seconds}s";


	$video['height'] = $json->data->height;
	$video['width'] = $json->data->width;

	// Let's store this video data so we don't need to fetch it every time.
	$cache = set_site_transient($cachekey, $video);

	return $video;

	echo '<pre style="overflow:scroll;">';
	print_r($video);
	print_r($json);
	echo '</pre>';
	return $video;
	#die(print_r($src));

}

// This is where the actual generation of the video code happens.
add_shortcode('wpevp', 'wpevp_shortcode');

function wpevp_shortcode($atts, $content=NULL) {

	extract(shortcode_atts(array(
		'id' => '',
		'imgsrc' => '',
		'src' => '',
		'init' => '',
		'css'  => '',
		'class' => 'evp-video-wrap',
		'width' => '',
		'height' => '',
		'type' => 'Init',
		'title' => '',
		'description' => '',
		'microformats' => true,
	), $atts));

if(!empty($id) && !empty($src) && !empty($init)) {
	$src = esc_attr(evp_replace_brackets(html_entity_decode($src)));
	$init = evp_replace_brackets(html_entity_decode($init));

	$snippets_output = '';

	// Add microformats
	if(!empty($microformats)) {
		$snippets = wpevp_get_video_details($src);
		if(!empty($snippets) && is_array($snippets)) {
			foreach($snippets as $key => $value) {
				$snippets_output .= '<meta itemprop="'.$key.'" content="'.$value.'" />'."\n";
			}
		}
	}

	if(!empty($width) || !empty($height)) {
		$widthCSS = $heightCSS = '';
		$cssOut = "\n<style type=\"text/css\">\n\t";
			if(!empty($width)) {
				$widthCSS = "width: {$width}!important;\n\t\t";
			}
			if(!empty($width)) {
				$heightCSS = "height: {$height}!important;\n\t";
			}
		$cssOut .= "#{$id} {\n\t\t{$widthCSS}{$heightCSS}}\n\t#{$id} div, #$id object { width: 100%!important; height: 100%!important; }\n\t{$css}\n</style>";
		$css = $cssOut;
	} elseif(!empty($css)) {
		$css = "<style type=\"text/css\">\n\t{$css}\n</style>";
	}

	$init = htmlspecialchars_decode($init, ENT_QUOTES);

	// Lightbox code has an empty image.
	$img = !empty($imgsrc) ? '<img src="'.esc_attr($imgsrc).'" alt="" />' : '';

$code = <<<EOD
<!-- Begin Easy Video Player plugin Output -->
$css
<div itemprop="video" itemscope itemtype="http://schema.org/VideoObject">
	<meta itemprop="name" content="$title" />
	<meta itemprop="description" content="$description" />
	$snippets_output

<div id="$id" class="$class">$img</div>
<script type="text/javascript" src="$src"></script>
<script type="text/javascript"><!--
_evp{$type}('$init');
//--></script>
</div>
<!-- End Easy Video Player plugin Output -->
EOD;
} else {

	$message = __('Easy Video Player plugin was missing required information, so the video was not displayed.', 'wp-evp');

	if(current_user_can('administrator')) {
		$code = '<h3>Error:</h3>'.wpautop($message."\n\n(this message is visible only to administrators)");
	} else {
		$code = '<!-- '.$message.' -->';
	}
}
	return $code;
}

// Add the inline thickbox content
add_action('admin_init', 'evp_register_scripts');
function evp_register_scripts() {
	global $pagenow;
	if(in_array($pagenow, array('post.php', 'page.php', 'page-new.php', 'post-new.php'))){
		add_action('admin_footer',  'evp_add_mce_popup');
		add_filter('mce_css', 'evp_editor_css');
	}
}

// Enqueue the custom stylesheet to set the placeholder style
function evp_editor_css($mce_css) {
	if ( ! empty( $mce_css ) )
		$mce_css .= ',';

	$mce_css .= plugins_url( 'editor.css', __FILE__ );

	return $mce_css;
}

//Action target that displays the popup to insert a form to a post/page
function evp_add_mce_popup(){
    ?>
    <script type="text/javascript"><!--
    	jQuery('document').ready(function($) {
    		$('#evp_insert_button').click(function(e) {
    			InsertEVP();
    			return false;
    		});

			function cleanForEVP(b){
				if(!b) { return; }
				b = b.replace(/(')/gm, "%%SQUOTE%%");
				b = b.replace('"', "&quot;");
				b = tinymce.DOM.encode(b);
				b = b.replace('[', '%%LBRACKET%%');
				b = b.replace(']', '%%RBRACKET%%');
				return b;
			}

    		function InsertEVP(){
    			var win = window.dialogArguments || opener || parent || top;
				var code = $("#evp_code").val();
				var width = $("#evp_width").val();
				var height = $("#evp_height").val();
				var title = $("#evp_title").val();
				var description = $("#evp_description").val();

				// Strip out line breaks
				code = code.replace(/\s+/g, ' ');
				var imgCode = '';

				// [\s\S] is a dotall flag workaround. Would rather use ., but doesn't match whitespace in javascript.
				code.replace(/(?:[\s\S]*?)id=['"](.*?)['"](?:[\s\S]*?)class=['"](.*?)['"](?:(?:[\s\S]*?)<img\ src="(.*?)"(?:[\s\S]*?)\/>)?(?:[\s\S]*?)<script(?:[\s\S]*?)src=['"](.*?)['"](?:[\s\S]*?)_evp(Init|Lightbox)\(['"]([\s\S]*?(?:,\s['"][\s\S]*?)?)['"]\)(?:[\s\S]*)<\/script>/g, function(a,b,c,d,e,f,g){
					// console.log('a: '+a); console.log('b: '+b); console.log('c: '+c); console.log('d: '+d); console.log('e: '+e); console.log('f: '+f); console.log('g: '+g); // What gets captured?

					a = cleanForEVP(a); // whole match
					b = cleanForEVP(b); // id
					c = cleanForEVP(c); // class
					d = cleanForEVP(d); // img
					e = cleanForEVP(e); // src
					f = cleanForEVP(f); // evp type
					g = cleanForEVP(g); // init

					// Empty imgsrc
					if(d === undefined) { d = ''; }

					if(win.tinyMCE.hasVisual) {
						imgCode = '<img src="<?php echo plugin_dir_url(__FILE__).'images/placeholder.jpg'; ?>" class="mceItem wpevp-placeholder" data-settings="wpevp id=&quot;'+b+'&quot; class=&quot;'+c+'&quot; imgsrc=&quot;'+d+'&quot; src=&quot;'+e+'&quot; type=&quot;'+f+'&quot; init=&quot;'+g+'&quot; width=&quot;'+tinymce.DOM.encode(width)+'&quot; height=&quot;'+tinymce.DOM.encode(height)+'&quot; title=&quot;'+tinymce.DOM.encode(title)+'&quot; description=&quot;'+tinymce.DOM.encode(description)+'&quot; />';
					} else {
						if(width != '' && width != ' ') { width = ' width=&quot;'+tinymce.DOM.encode(width)+'&quot;';}
						if(height != '' && height != ' ') { height = ' height=&quot;'+tinymce.DOM.encode(height)+'&quot;';}
						if(description != '' && description != ' ') { description = ' description="'+tinymce.DOM.encode(description)+'"';}
						if(title != '' && title != ' ') { title = ' title="'+tinymce.DOM.encode(title)+'"';}
						imgCode = '[wpevp id="'+b+'" class="'+c+'" imgsrc="'+d+'" src="'+e+'" type="'+f+'" init="'+g+'"'+width+height+description+title+' /]';
					}
				});

                if(imgCode != '') {
                	$('#wpevp_error').remove();
                	$('#evp_no_error_instructions').show();
                	$('#insert_evp textarea, #insert_evp input').val('');
                	win.send_to_editor(imgCode);
	                win.tinyMCE.execCommand('mceCleanup');
	           	} else {
	           		$('#evp_no_error_instructions').hide();
	           		$('#evp_code').before('<div class="error wrap" id="wpevp_error"><p><?php
	           		$link = 'http://katz.si/evp?tid=EMBED';
	           		_e(sprintf("The code entered did not appear to be valid <a href=\"%s\" target=\"_blank\">EasyVideoPlayer</a> code.</p><p><strong>An <a href=\"%s\" target=\"_blank\">EasyVideoPlayer</a> account is required to use this plugin.</strong> <a href=\"%s\" target=\"_blank\">Learn more&rarr;</a>", $link, $link, $link), "wpevp");

	           		?></p></div>');
	           	}
            }

    	});
    -->
    </script>

    <div id="insert_evp" style="display:none;">
    	<div class="wrap">
            <div>
                <div class="wrap">
                    <h3 style="color:#5A5A5A!important; font-family:Georgia,Times New Roman,Times,serif!important; font-size:1.8em!important; font-weight:normal!important;"><?php _e("Embed an EVP video into this WordPress post", "wpevp"); ?></h3>
                    <h3 id="evp_no_error_instructions" class="howto">
                        <?php _e(sprintf("Please enter the code provided by <a href='%s'>EasyVideoPlayer</a> below. You can embed both video and audio files.", 'http://katz.si/evp?tid=EMBED'), "wpevp"); ?>
                    </h3>
                    <div class="inline-edit-row">
                    <fieldset>
                    <p style="margin:.5em 0"><textarea id="evp_code" class="widefat" rows="5"></textarea></p>
					<span class="howto">
					<?php _e("Once you click Insert Video below, your video code will be stored inside your post and it will show up as an image in the Visual editor, or as <code>[wpevp]</code> shortcode in the HTML editor.", "wpevp"); ?>
					</span>
					<p style="margin:.5em 0"><span><?php _e('Custom Video Width', 'wpevp'); ?></span><label for="evp_width"><input type="text" id="evp_width" value="" class="widefat" style="width:40%" /><span class="howto"><?php _e('Example: <code>100%</code> or <code>500px</code>. Leave empty for default width (the video&rsquo;s width).', 'wpevp'); ?></span></label></p>
					<p style="margin:.5em 0"><span><?php _e('Custom Video Height', 'wpevp'); ?></span><label for="evp_height"><input type="text" id="evp_height" value="" class="widefat" style="width:40%" /><span class="howto"><?php _e('Example: <code>350px</code>. Leave empty for default height (the video&rsquo;s height).', 'wpevp'); ?></span></label></p>
					</fieldset>
					<fieldset>
						<legend><h3><?php _e('SEO Settings', 'wpevp'); ?></h3></legend>
						<p class="description"><?php _e('These settings will help Google include the video in the search results previews.', 'wpevp'); ?></p>
						<p style="margin:.5em 0"><label for="evp_title"><span><?php _e('Video Title', 'wpevp'); ?></span><input type="text" id="evp_title" value="" class="widefat" /></label></p>
						<p style="margin:.5em 0"><label for="evp_description"><span><?php _e('Video Description', 'wpevp'); ?></span><textarea id="evp_description" class="widefat" /></textarea></label></p>
					</fieldset>
					</div>
                </div>

                <div class="submit" style="margin-top:0;">
                    <input type="button" class="button-primary" value="Insert Video" id="evp_insert_button" />&nbsp;&nbsp;&nbsp;
	                <a class="button" style="color:#bbb;" href="#" onclick="tb_remove(); return false;"><?php _e("Cancel", "wpevp"); ?></a>
                </div>
            </div>
        </div>
    </div>

    <?php
}
