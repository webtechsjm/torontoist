<?php
/**
 * Plugin name: Glossies
 * Plugin URI: 
 * Description: Creates a custom, formatted feed for Flipboard (and eventually other magazine-style feed readers)
 * Version: 0.1
 * Author: Senning Luk
 * Author URI: http://puppydogtales.ca
 *
 */

/*
*		Hooks/function index
*/
add_action('post_submitbox_misc_actions', 'tomag_include2feed_option');
add_action('save_post','tomag_include2feed_save');
add_action('manage_post_posts_columns','tomag_posts_column');
add_action('manage_posts_custom_column','tomag_posts_build_column',10,2);
add_action('quick_edit_custom_box','tomag_add_quick_edit',10,2);
add_action('admin_init','tomag_admin_edit_js',11);
add_filter('plugin_action_links','tomag_settings_link',10,2);
add_action('admin_menu','tomag_settings');
add_action('init','tomag_add_feed');

/*
*		Add checkbox to the Posts editing screen for inclusion to magazine feed
*/
function tomag_include2feed_option(){
	global $post;
	if(get_post_type($post) == 'post'){
	
	//Should posts be included by default?
	$default_inclusion = get_option('default_inclusion');
	
	if(current_user_can('publish_posts')){
	?>
		<div class="misc-pub-section" style="border-top: 1px solid #eee;">
			<?php
					wp_nonce_field(plugin_basename(__FILE__),'tomag_include_nonce'); 
					
					//if the post is saved as added to the magazine feed, make sure it's shown as checked
					//$attr = get_post_meta($post->ID,'tomag_include',true) ? "checked='checked'" : "";
					$current = get_post_meta($post->ID,'tomag_include',true);
					if(
						$current == "true"
						|| ($current != "false" && $default_inclusion == "1")
					){$attr = 'checked="checked"';
					}else{$attr="";}
					?>
					<input type="checkbox" <?php echo $attr ?> name="tomag_include" id="tomag_include_check" value="include"/>
					<label for="tomag_include_check">Include in the magazine feed</label>
			</div>
	<?
		}
	}
}

function tomag_include2feed_save($post_id){
	//security
	if(
		!isset($_POST['post_type'])
		|| !wp_verify_nonce($_POST['tomag_include_nonce'], plugin_basename(__FILE__))
		|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		|| !current_user_can('edit_post',$post_id)
		) return $post_id;

	if(isset($_POST['tomag_include'])){
		update_post_meta($post_id,'tomag_include','true');
	}else{
		update_post_meta($post_id,'tomag_include','false');
	}
}

/*
*		Add the magazine listing status to the post list
*/

function tomag_posts_column($columns){
	$columns['tomag_status'] = 'Magazine';
	return $columns;
}

function tomag_posts_build_column($column_name,$id){
	switch($column_name){
		case 'tomag_status':
			//show whether included in magazine feed
			$mag_status = get_post_meta($id,'tomag_include', true);
			if($mag_status == 'true') echo 'Included';
	}
}

/*
*		Add the magazine listing status to the quick edit box
*/
function tomag_add_quick_edit($column_name,$post_type){
	if($column_name != 'tomag_status') return; 
	?>
	<fieldset class="inline-edit-col-left">
		<div class="inline-edit-col">
			<?php 
				wp_nonce_field(plugin_basename(__FILE__),'tomag_include_nonce'); 
				
				//if the post is saved as added to the magazine feed, make sure it's shown as checked
				$attr = get_post_meta($post_id,'tomag_include',true) ? "checked='checked'" : "";
				?>
				<label class="alignleft">
					<input type="checkbox" <?php echo $attr ?> name="tomag_include" id="tomag_include_check" value="include"/>
					<span class="checkbox-title">Include in magazine</span>
				</label>
		</div>
	</fieldset>
	<?php
}

function tomag_admin_edit_js(){
	$slug = 'post';
	if(!isset($_GET['page']) && !isset($_GET['post_type'])){
		//register the scripts
		wp_register_script('tomag-admin-script',plugin_dir_url(__FILE__).'magnify.js','jquery',false,true);
		
		wp_enqueue_script('tomag-admin-script');
		}
}

/*
*		Settings
*/
function tomag_settings(){
	add_settings_section(
		'tomag_general',
		'Torontoist magazine feed options',
		'tomag_settings_page',
		'reading'
	);
	
	add_settings_field(
		'default_inclusion',
		'Include in feed',
		'tomag_toggle_default_inclusion',
		'reading',
		'tomag_general'
	);
	
	register_setting('reading','default_inclusion');
}

function tomag_settings_page(){
}

function tomag_toggle_default_inclusion(){ ?>
	<input type="checkbox" id="default_inclusion" name="default_inclusion" value="1" <?php checked(1,get_option('default_inclusion')); ?> />
	<label for="default_inclusion"><?php _e('Include posts in the magazine feed by default'); ?></label>
<?php }

function tomag_settings_link($links, $file){
	if ( $file == plugin_basename(__FILE__)) {
		$links[] = '<a href="options-reading.php">'.__('Settings').'</a>';
	}
	return $links;
}


/*
*		Generate magazine-style feed
*/
function tomag_add_feed(){
	add_feed('magazine','tomag_make_feed');
}
function tomag_make_feed(){
	$args = array(
		'post_type'		=>	'post',
		'meta_query'	=>	array(
			array(
				'key'			=>	'tomag_include',
				'value'		=>	'true'
			)
		)
	);
	$tomag = new WP_Query($args);

	/*
	*		Generate the actual feed
	*		Code taken from Automattic's feed-rss2.php
	*/

header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	<?php do_action('rss2_ns'); ?>
>

<channel>
	<title><?php bloginfo_rss('name'); wp_title_rss(); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss('url') ?></link>
	<description><?php bloginfo_rss("description") ?></description>
	<lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
	<?php do_action('rss2_head'); ?>
	<?php while( $tomag->have_posts()) : $tomag->the_post(); ?>
	<item>
		<title><?php the_title_rss() ?></title>
		<link><?php the_permalink_rss() ?></link>
		<comments><?php comments_link_feed(); ?></comments>
		<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
		<dc:creator><?php the_author() ?></dc:creator>
		<?php the_category_rss('rss2') ?>

		<guid isPermaLink="false"><?php the_guid(); ?></guid>
<?php if (get_option('rss_use_excerpt')) : ?>
		<description><![CDATA[<?php the_excerpt_rss() ?>]]></description>
<?php else : ?>
		<description><![CDATA[<?php the_excerpt_rss() ?>]]></description>
	<?php if ( strlen( $post->post_content ) > 0 ) : ?>
		<content:encoded><![CDATA[<?php the_content_feed('rss2') ?>]]></content:encoded>
	<?php else : ?>
		<content:encoded><![CDATA[<?php the_excerpt_rss() ?>]]></content:encoded>
	<?php endif; ?>
<?php endif; ?>
		<wfw:commentRss><?php echo esc_url( get_post_comments_feed_link(null, 'rss2') ); ?></wfw:commentRss>
		<slash:comments><?php echo get_comments_number(); ?></slash:comments>
<?php rss_enclosure(); ?>
	<?php do_action('rss2_item'); ?>
	</item>
	<?php endwhile; ?>
</channel>
</rss>
	
<?php
	//restore the original query & post
	//probably unnecessary, but shouldn't hurt
	wp_reset_query();
	wp_reset_postdata();	

}


/*
*		Build Flipboard-formatted feed
*/

?>
