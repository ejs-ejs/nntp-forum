<?php

define('ROOT_DIR', '../../..');
require(ROOT_DIR . '/include/header.php');

if( !isset($_GET['newsgroup']) )
	exit_with_not_found_error();
if( !isset($_GET['number']) )
	exit_with_not_found_error();

$group = sanitize_newsgroup_name($_GET['newsgroup']);
$topic_number = intval($_GET['number']);

// Connect to the newsgroup and get the (possibly cached) message tree and information.
$nntp = nntp_connect_and_authenticate($CONFIG);
list($message_tree, $message_infos) = get_message_tree($nntp, $group);

// If the newsgroup does not exists show the "not found" page.
if ( $message_tree == null )
	exit_with_not_found_error();

// Now look up the message id for the topic number (if there is no root level message with
// a matching number show a "not found" page).
$topic_id = null;
foreach(array_keys($message_tree) as $message_id){
	if ($message_infos[$message_id]['number'] == $topic_number){
		$topic_id = $message_id;
		break;
	}
}

if ($topic_id == null)
	exit_with_not_found_error();

// Extract the subtree for this topic
$thread_tree = array( $topic_id => $message_tree[$topic_id] );

// Load existing unread tracking information and update it in case the user jumped here with
// a direct link the tracker was not updated by the topic indes before. Otherwise messages added
// since the last update (newer than the tracked watermark) will be marked as unread on the
// next update, even if the user alread viewed the message now.
if ( $CONFIG['unread_tracker']['file'] ) {
	$tracker = new UnreadTracker($CONFIG['unread_tracker']['file']);
	$tracker->update($group, $message_tree, $message_infos, $CONFIG['unread_tracker']['topic_limit']);
} else {
	$tracker = null;
}

// Fetch the users subscriptions so we can display proper subscribe/unsubscribe links for each message.
list($subscribed_messages, $subscribed_messages_file) = load_subscriptions();

// See if the current user is allowed to post in this newsgroup
$nntp->command('list active ' . $group, 215);
$group_info = $nntp->get_text_response();
list($name, $last_article_number, $first_article_number, $post_flag) = explode(' ', $group_info);
$posting_allowed = $CONFIG['nntp']['can_post'] && ($post_flag != 'n');
//$posting_allowed = 0;

// Select the specified newsgroup for later content retrieval. We know it does exist (otherwise
// get_message_tree() would have failed).
$nntp->command('group ' . $group, 211);

// Setup layout variables
$title = $message_infos[$topic_id]['subject'];
$breadcrumbs[$group] = '/' . $group;
$breadcrumbs[$title] = '/' . $group . '/' . $topic_number;
$scripts[] = 'messages.js';
$body_class = 'messages';

//echo('<h2>' . h($title) . '</h2>');

if ( $CONFIG['google_search_id'] ) {
	echo('<table width="100%" class="hdr"><tr><td width="61%"><h2>' . h($title) . '</h2></td><td>');

	echo('<script async src="https://cse.google.com/cse.js?cx=' .$CONFIG['google_search_id'] . '"></script><div class="gcse-search"><script>$(".gsc-search-button").click(function() {$("#searchresults").show();}</script></div></td></tr></table>');
} else{
	echo('<h2>' . h($title) .'</h2>');
}



// A recursive tree walker function. Unfortunately necessary because we start the recursion
// within the function (otherwise we could use an iterator).
function traverse_tree($tree_level){
	global $nntp, $message_infos, $group, $posting_allowed, $tracker, $topic_number, $CONFIG, $subscribed_messages, $subscribed_messages_file;

	// Default storage area for each message. This array is used to reset the storage area for the event
	// handlers after a message is parsed.
	$empty_message_data = array(
		'newsgroup' => null,
		'newsgroups' => null,
		'id' => null,
		'content' => null,
		'attachments' => array()
	);
	// Storage area for message parser event handlers
	$message_data = $empty_message_data;

	// Setup the message parser events to record the first text/plain part and record attachment
	// information if present.
	$message_parser = MessageParser::for_text_and_attachments($message_data);

	// The following scary bit of code extends the message parser to generate image previews while
	// the message is parsed. It is event driven code like the rest of the parser. We wrap new anonymous
	// functions around the events thar are already there.
	// This wrapper pattern (inspired by Lisp and JavaScript) should be moved to the `for_text_and_attachments()`
	// function as "extended events". But for now it works.
	if ( $CONFIG['thumbnails']['enabled'] and extension_loaded('gd') ){
		// Original event handlers. Remember them here to call them later on.
		$old_message_header = $message_parser->events['message-header'];
		$old_part_header = $message_parser->events['part-header'];
		$old_record_attachment_size = $message_parser->events['record-attachment-size'];
		$old_part_end = $message_parser->events['part-end'];
		$old_message_end = $message_parser->events['message-end'];

		// State variables used across our event handlers
		$message_id;
		$raw_data = null;

		// Only record the message ID from the message headers. We need it to build a unique hash.
		$message_parser->events['message-header'] = function($headers) use($old_message_header, &$message_id){
			$message_id = $headers['message-id'];

			return $old_message_header($headers);
		};

		// If we got an image and it's not already in the thumbnail cache set `$raw_data` so the other
		// events will take action.
		$message_parser->events['part-header'] = function($headers, $content_type, $content_type_params) use($old_part_header, &$raw_data, &$message_id, &$message_data){
			$content_event = $old_part_header($headers, $content_type, $content_type_params);

			// image parsing goes here
			if ( $content_event == 'record-attachment-size' and preg_match('#image/.*#', $content_type) ){
				$last_index = count($message_data['attachments']) - 1;
				$display_name = $message_data['attachments'][$last_index]['name'];
				$message_data['attachments'][$last_index]['type'] = 'IMAGE';
				if (preg_match('#image/gif#', $content_type)) {
					$cache_name = md5($message_id . $display_name).'.gif';
					$img_name = md5($message_id . $display_name).'.gif';
					$message_data['attachments'][$last_index]['image_format'] = 'GIF';
					$message_data['attachments'][$last_index]['preview'] = $img_name;
					$message_data['attachments'][$last_index]['img'] = $img_name;


				} else {
					$cache_name = md5($message_id . $display_name).'.jpg';
					$img_name = md5($message_id . $display_name).'.img.jpg';
					$message_data['attachments'][$last_index]['image_format'] = 'JPEG';
					$message_data['attachments'][$last_index]['preview'] = $cache_name;
					$message_data['attachments'][$last_index]['img'] = $img_name;

				}

				// If there is no cached version available kick of the data recording and preview generation
				if ( ! file_exists(ROOT_DIR . '/public/thumbnails/' . $cache_name) )
					$raw_data = array();

			}
			// video parsing goes here
			if ( $content_event == 'record-attachment-size' and preg_match('#video/.*#', $content_type) ){
				$last_index = count($message_data['attachments']) - 1;
				$display_name = $message_data['attachments'][$last_index]['name'];
				$message_data['attachments'][$last_index]['type'] = 'VIDEO';
				if (preg_match('#video/mp4#', $content_type)) {
					$cache_name = md5($message_id . $display_name).'.mp4';
					$img_name = md5($message_id . $display_name).'.mp4';
					$message_data['attachments'][$last_index]['image_format'] = 'MP4';
				} elseif (preg_match('#video/ogg#', $content_type)) {
					$cache_name = md5($message_id . $display_name).'.ogg';
					$img_name = md5($message_id . $display_name).'.ogg';
					$message_data['attachments'][$last_index]['image_format'] = 'OGG';
				} elseif (preg_match('#video/webm#', $content_type)) {
					$cache_name = md5($message_id . $display_name).'.webm';
					$img_name = md5($message_id . $display_name).'.webm';
					$message_data['attachments'][$last_index]['image_format'] = 'WEBM';
				}


					$message_data['attachments'][$last_index]['preview'] = $img_name;
					$message_data['attachments'][$last_index]['img'] = $img_name;

			}

			if ( $content_event == 'record-attachment-size' and preg_match('#application/pdf#', $content_type) ){
				$last_index = count($message_data['attachments']) - 1;
				$display_name = $message_data['attachments'][$last_index]['name'];
				$message_data['attachments'][$last_index]['type'] = 'PDF';

				$cache_name = md5($message_id . $display_name).'.pdf';
				$img_name = md5($message_id . $display_name).'.pdf';
				$message_data['attachments'][$last_index]['image_format'] = 'PDF';

				$message_data['attachments'][$last_index]['preview'] = $img_name;
				$message_data['attachments'][$last_index]['img'] = $img_name;

			}
			return $content_event;
		};

		// Record raw image data if requested. Append each data chunk to the `$raw_data` array to avoid
		// to many concatinations.
		$message_parser->events['record-attachment-size'] = function($line) use($old_record_attachment_size, &$raw_data){
			if ( $raw_data !== null )  // is_array() makes trouble in this spot, seems to hand in an endless loop
				$raw_data[] = $line;
			return $old_record_attachment_size($line);
		};

		// We're at the end of an MIME part. If we got raw data to process load the actual image from them.
		// Create a thumbnail version and put it into the cache.
		$message_parser->events['part-end'] = function() use($old_part_end, $CONFIG, &$raw_data, &$message_data){
			if ( $raw_data !== null ){
				$data = join('', $raw_data);

				if ($message_data['attachments'][$last_index]['type'] == 'VIDEO'){
					$video = base64_decode($data);
					$preview_created = true;
					$img_created = file_put_contents(ROOT_DIR . '/public/thumbnails/' . $img_name, $video);
				} else {
					$image = @imagecreatefromstring($data);
					$preview_created = false;
				}

				if ($image) {
// do not create the preview image. Makes no sense. Push the resized original image instead.
					 if ( $CONFIG['thumbnails']['create'] ) {
						$width = imagesx($image);
						$height = imagesy($image);

						if ($width > $height) {
							// Landscape format
							$preview_width = $CONFIG['thumbnails']['width'];
							$preview_height = $height / ($width / $CONFIG['thumbnails']['width']);
						} else {
							// Portrait format
							$preview_height = $CONFIG['thumbnails']['height'];
							$preview_width = $width / ($height / $CONFIG['thumbnails']['height']);
						}

						$preview_image = imagecreatetruecolor($preview_width, $preview_height);
						imagecopyresampled($preview_image, $image, 0, 0, 0, 0, $preview_width, $preview_height, $width, $height);
					}

					$last_index = count($message_data['attachments']) - 1;
					$cache_name = $message_data['attachments'][$last_index]['preview'];
					$img_name = $message_data['attachments'][$last_index]['img'];


					if ($message_data['attachments'][$last_index]['image_format'] == 'GIF') {
						$preview_created = 1;
						$img_created = @imagegif($image, ROOT_DIR . '/public/thumbnails/' . $img_name);
					} else {
						 if ( $CONFIG['thumbnails']['create'] ) {
							$preview_created = @imagejpeg($preview_image, ROOT_DIR . '/public/thumbnails/' . $cache_name, $CONFIG['thumbnails']['quality']);
							imagedestroy($preview_image);
						} else {
							$preview_created = 1; 
						}
						$img_created = @imagejpeg($image, ROOT_DIR . '/public/thumbnails/' . $img_name, $CONFIG['thumbnails']['quality']);

					}
					imagedestroy($image);

				} 

				if (!$preview_created) {
					// If we could not create the preview kill the preview name from the message data
					$last_index = count($message_data['attachments']) - 1;
					unset($message_data['attachments'][$last_index]['preview']);
				}

				$raw_data = null;
			}
			return $old_part_end();
		};
		
		// at the end of message parsing
		
		if ( $CONFIG['experimental']['uudecode'] ) {
			$message_parser->events['message-end'] = function() use($old_message_end, $CONFIG, &$message_data){
				
				// some NNTP clients will send a malformed message,
				// containing UUencoded data in the text/plain format
				//	print_r($message_data);
				
				
				if ( preg_match('#NewsTap/5.5*#', $message_data['user-agent']) ||  preg_match('#PiaoHong*#', $message_data['user-agent']) ) {
					
					echo('<p>DEBUG: GOT ONE: print message data: </p>');
					//print_r($message_data['content']);	
					
					//var_dump($message_data);
					$last_index = count($message_data['attachments']) - 1;
					if ( $last_index < 0 ) {
						$last_index = 0;
						$message_data['attachments'] = Array();
					}
					echo 'Number of attachments: ' . count($message_data['attachments']) . '['. $last_index .']';						
						
					// get the position of the trailing 'end' 
					// and the starting 'begin 644 ...'					
					$last_att =  strrpos($message_data['content'], 'end');
					$first_att = strrpos($message_data['content'], 'begin 644 ');
						
					if ($last_att && $first_att) {
						//	echo 'Found starting "begin 644"';
						$attachments = substr($message_data['content'], $first_att);
							
						$message_data['content'] = substr($message_data['content'], 0, $first_att);
						//echo 'BODY: '. $body;
						//	echo 'ATTS: '. $attachments;
						
						// separating ATT name ad body
						$name_ends = strpos($attachments, "\n");
						$att1_name=substr($attachments, 0, $name_ends);
						$att1_name = substr($att1_name,10);
						
						//$atts=substr($attachments, $name_ends + 1);
						//	echo bin2hex($atts);
						//echo '<p>START position '. $first_att .', END position: ' . strrpos($attachments, "end", 0) .' of '. strlen($attachments).' symbols</p>';
						
						//$atts=substr($atts, 0, strrpos($atts, "end", -5));
						$atts=substr($attachments, $name_ends + 1, strrpos($attachments, "end", 0));
								
							$message_data['attachments'][$last_index]['name'] = htmlspecialchars($att1_name);
							
							//$data = join('', $atts);
							//	$data = trim($atts, ' ');
							//	echo bin2hex($atts);
							//$data = $atts;
							//	echo $data;
						//	echo $atts;
							//$data = convert_uudecode(trim($atts, ' '));
							$data = convert_uudecode($atts);
							//$data = convert_uudecode($attachments);
								//echo $data;
								
								$imagepath = ROOT_DIR . '/public/thumbnails/'.md5($message_data['id']).'temp.file';
								$txt_created = file_put_contents($imagepath.'.txt', $attachments);
								$img_created = file_put_contents($imagepath, $data);
								$image = imagecreatefromstring(file_get_contents($imagepath)); 
								//unlink($imagepath);
							//echo $atts;
							//$image = @imagecreatefromstring($data);
							
							$message_data['attachments'][$last_index]['size'] = strlen($data);
								
							if ($image) {
								$width = imagesx($image);
								$height = imagesy($image);
								$size_info = getimagesizefromstring($atts);
								if ( $size_info) {
									//print_r($size_info);
									echo 'Image '. $width .' by ' .$height . 'pixels';
								}  else {
									echo 'Unable to determine size, something\'s wrong: '. $size_info;
								}
								} else {
							//		echo 'Unable to create an image, something\'s wrong: ' . $atts;		
									echo 'Unable to create an image, something\'s wrong';
								}

							$message_data['attachments'][$last_index]['type'] = 'IMAGE';
						//	echo 'DEBUG: setting type to IMAGE';
							
							if ($size_info['mime'] == 'image/jpeg' ) {
								// alter file name
								$att1_name = substr($att1_name, 0, strpos($att1_name, ".", -5));
								echo $att1_name;
								$att1_name = $att1_name . '.jpg';
								$message_data['attachments'][$last_index]['name'] = $att1_name;
								
								$message_data['attachments'][$last_index]['params']['image_format'] = 'JPEG';
							
								$cache_name = md5($message_data['id'] . $att1_name).'.img.jpg';
								$img_name = md5($message_data['id'] . $att1_name).'.img.jpg';
								
								$message_data['attachments'][$last_index]['preview'] = $cache_name;
								$message_data['attachments'][$last_index]['img'] = $img_name;
					
								//	if ($width > $height) {
								//	// Landscape format
								//		$preview_width = $CONFIG['thumbnails']['width'];
								//		$preview_height = $height / ($width / $CONFIG['thumbnails']['width']);
								//	} else {
								//	// Portrait format
								//		$preview_height = $CONFIG['thumbnails']['height'];
								//		$preview_width = $width / ($height / $CONFIG['thumbnails']['height']);
								//	}
									
									$preview_width = $width;
									$preview_height = $height;
									
								$preview_image = imagecreatetruecolor($preview_width, $preview_height);
								imagecopyresampled($preview_image, $image, 0, 0, 0, 0, $preview_width, $preview_height, $width, $height);
								
								$preview_created = @imagejpeg($preview_image, ROOT_DIR . '/public/thumbnails/' . $cache_name, $CONFIG['thumbnails']['quality']);
								$img_created = @imagejpeg($image, ROOT_DIR . '/public/thumbnails/' . $img_name, $CONFIG['thumbnails']['quality']);
								imagedestroy($preview_image);
								imagedestroy($image);
					
					
							} elseif ($size_info['mime'] == 'image/gif' ) {
								$message_data['attachments'][$last_index]['params']['image_format'] = 'GIF';
								$cache_name = md5($message_id . $message_data['attachments'][$last_index]['name']).'.gif';
								$img_name = $cache_name;
								$message_data['attachments'][$last_index]['preview'] = $img_name;
								$message_data['attachments'][$last_index]['img'] = $img_name;
								$preview_created = 1;
								$img_created = @imagegif($image, ROOT_DIR . '/public/thumbnails/' . $img_name);
							} elseif ($size_info['mime'] == 'image/png' ) {
								$message_data['attachments'][$last_index]['params']['image_format'] = 'PNG';
								$cache_name = md5($message_id . $message_data['attachments'][$last_index]['name']).'.png';
								$img_name = $cache_name;
								$message_data['attachments'][$last_index]['preview'] = $img_name;
								$message_data['attachments'][$last_index]['img'] = $img_name;
								$preview_created = 1;
								$img_created = @imagepng($image, ROOT_DIR . '/public/thumbnails/' . $img_name);
							//} else {
								//	$message_data['attachments'][$last_index]['image_format'] = '';
							}
							
							
						//		echo 'Name: '. $att1_name;
								//print_r($atts);
						} // there was UUencoded image(s) inside
						
					} // end checking for problematic clients
			return $old_message_end();
			};
		} // end of experimental feature
	}

	echo("<ul>\n");
	foreach($tree_level as $id => $replies){
		$overview = $message_infos[$id];

		list($status,) = $nntp->command('article ' . $id, array(220, 430));
		if ($status == 220){
			$nntp->get_text_response_per_line(array($message_parser, 'parse_line'));
			$message_parser->end_of_message();
			// All the stuff in `$message_data` is set by the event handlers of the parser
			$content = Markdown($message_data['content']);
		} else {
			$content = '<p class="empty">' . l('messages', 'deleted') . '</p>';
			$message_data['attachments'] = array();
		}

		echo("<li>\n");
		$unread_class = ( $tracker and $tracker->is_message_unread($group, $topic_number, $overview['number']) ) ? ' class="unread"' : '';
		printf('<article id="message-%d" data-number="%d" data-id="%s"%s>' . "\n", $overview['number'], $overview['number'], ha($message_data['id']), $unread_class);
		echo("	<header>\n");
		echo("		<p>");
		echo('			' . l('messages', 'message_header',
//			sprintf('<a href="mailto:%1$s" title="%1$s">%2$s</a>', ha($overview['author_mail']), h($overview['author_name'])),
			sprintf('<p>%2$s</a>', ha($overview['author_mail']), h($overview['author_name'])),
			timezone_aware_date($overview['date'], l('messages', 'message_header_date_format'))
		) . "\n");
		printf('			<a class="permalink" href="/%s/%d#message-%d">%s</a>' . "\n", urlencode($group), $topic_number, $overview['number'], l('messages', 'permalink'));
		echo("		</p>\n");
		echo("	</header>\n");
		echo('	' . $content . "\n");

		if ( ! empty($message_data['attachments']) ){
			echo('	<ul class="attachments">' . "\n");
			echo('		<li>' . lh('messages', 'attachments') . '</li>' . "</br>\n");
			foreach($message_data['attachments'] as $attachment){
			//	var_dump($attachment);
				if ( isset($attachment['preview']) ) {
					$img_loc = '/thumbnails/'.urlencode($attachment['preview']);

//					echo('		<li class="thumbnail" style="background-image: url(/thumbnails/' . $attachment['preview'] . ');">' . "\n");
					if ($attachment['type'] == 'PDF') {
						$img_loc = '/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']);
					    echo '<li class="thumbnail"><object type="application/pdf" width="100px" height="500px" frameBorder="0"
    scrolling="auto" ';
						echo 'data="'.$img_loc.'"><a href="' . $img_loc .'">'.urlencode($attachment['name']).'</a></object></li>';
					} elseif ($attachment['type'] == 'VIDEO') {
							$img_loc = '/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']);
					        echo '<li class="thumbnail"><a href="/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']) . '"><video width="'.$CONFIG['thumbnails']['width'].'" controls>';
							if ($attachment['image_format'] == 'MP4') {
							echo '<source src="'.$img_loc.'" type="video/mp4">';
							} elseif ($attachment['image_format'] == 'OGG') {
							echo '<source src="'.$img_loc.'" type="video/ogg">';
							} elseif ($attachment['image_format'] == 'WEBM') {
							echo '<source src="'.$img_loc.'" type="video/webm">';
							} else {
							echo l("Unsupported video format.").' </video></a></li>' . "\n";	
							}
							echo l("Your browser does not support the video tag.").' </video></a></li>' . "\n";
					} elseif ($attachment['type'] == 'IMAGE') { 
						if ($attachment['image_format'] == 'GIF') {
							$img_loc = '/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']);
							echo('<li class="thumbnail"><a href="' . $img_loc . '"><img src="'.$img_loc.'" width="'.$CONFIG['thumbnails']['width'].'"></a></li>' . "\n");
						} else {
							 if ( $CONFIG['thumbnails']['create'] ) {
								$img_loc = '/thumbnails/' . $attachment['preview'];
							        echo('<li class="thumbnail"><a href="/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']) . '"><img src="'.$img_loc.'" width="'.$CONFIG['thumbnails']['width'].'"></a></li>' . "\n");
							} else { 
								$img_loc = '/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']);
								echo('<li class="thumbnail"><a href="' . $img_loc . '"><img src="'.$img_loc.'" width="'.$CONFIG['thumbnails']['width'].'"></a></li>' . "\n");
							}
						}
					} else {
						echo('<li> You should not be here, bro.</li>');
					}
				} else {
					echo('		<li>' . "\n");
					echo('			<a href="/' . urlencode($group) . '/' . urlencode($overview['number']) . '/' . urlencode($attachment['name']) . '">' . h($attachment['name']) . '</a>' . "\n");
					echo('			(' . number_to_human_size($attachment['size']) . ')' . "\n");

				}
				echo('		</li>' . "\n");
			}
			echo("	</ul>\n");
		}

		echo('		<ul class="actions">' . "\n");
		if($posting_allowed)
			echo('			<li class="new message"><a href="#">' . l('messages', 'answer') . '</a></li>' . "\n");

		if($CONFIG['sender_is_self']($overview['author_mail'], $CONFIG['nntp']['user']))
			echo('			<li class="destroy message"><a href="#">' . l('messages', 'delete') . '</a></li>' . "\n");

		if ($subscribed_messages_file) {
			if (!in_array($message_data['id'], $subscribed_messages)) {
				echo('			<li class="new subscription"><a href="#">' . l('messages', 'subscribe') . '</a></li>' . "\n");
				echo('			<li class="destroy subscription disabled"><a href="#">' . l('messages', 'unsubscribe') . '</a></li>' . "\n");
			} else {
				echo('			<li class="new subscription disabled"><a href="#">' . l('messages', 'subscribe') . '</a></li>' . "\n");
				echo('			<li class="destroy subscription"><a href="#">' . l('messages', 'unsubscribe') . '</a></li>' . "\n");
			}
		}

		echo('		</ul>' . "\n");

		echo("</article>\n");

		// Reset message variables to make a clean start for the next message
		$message_parser->reset();
		$message_data = $empty_message_data;

		if ( count($replies) > 0 )
			traverse_tree($replies);

		echo("</li>\n");
	}

	echo("</ul>\n");
}

traverse_tree($thread_tree);
$nntp->close();
if ($tracker)
	$tracker->mark_topic_read($group, $topic_number);

?>

<form action="/<?= urlencode($group) ?>/<?= urlencode($topic_number) ?>" method="post" enctype="multipart/form-data" class="message">

	<ul class="error">
		<li id="message_body_error"><?= lh('message_form', 'errors', 'missing_body') ?></li>
	</ul>

	<section class="help">
		<?= l('message_form', 'format_help') ?>
	</section>

	<section class="fields">
		<p>
			<textarea name="body" required id="message_body"></textarea>
		</p>
		<dl>
			<dt><?= lh('message_form', 'attachments_label') ?></dt>
				<dd><input name="attachments[]" type="file" /> <a href="#" class="destroy attachment"><?= l('message_form', 'delete_attachment') ?></a></dd>
		</dl>
		<p class="buttons">
			<button class="preview recommended"><?= lh('message_form', 'preview_button') ?></button>
			<?= lh('message_form', 'button_separator') ?>
			<button class="create"><?= lh('message_form', 'create_answer_button') ?></button>
			<?= lh('message_form', 'button_separator') ?>
			<button class="cancel"><?= lh('message_form', 'cancel_button') ?></button>
		</p>
	</section>

	<article id="post-preview">
		<header>
			<p><?= lh('message_form', 'preview_heading') ?></p>
		</header>

		<div></div>
	</article>
</form>

<? require(ROOT_DIR . '/include/footer.php') ?>
