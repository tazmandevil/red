<?php

require_once('include/security.php');
require_once('include/bbcode.php');
require_once('include/items.php');


function subthread_content(&$a) {

	if((! local_user()) && (! remote_user())) {
		return;
	}

	$activity = ACTIVITY_FOLLOW;

	$item_id = ((argc() > 1) ? notags(trim(argv(1))) : 0);

	$r = q("SELECT * FROM `item` WHERE `parent` = '%s' OR `parent_mid` = '%s' and parent = id LIMIT 1",
		dbesc($item_id),
		dbesc($item_id)
	);

	if(! $item_id || (! count($r))) {
		logger('subthread: no item ' . $item_id);
		return;
	}

	$item = $r[0];

	$owner_uid = $item['uid'];
	$observer = $a->get_observer();
	$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

	if(! perm_is_allowed($owner_uid,$ob_hash,'post_comments'))
		return;


	$remote_owner = null;

	if(! $item['wall']) {
		// The top level post may have been written by somebody on another system
		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($item['contact-id']),
			intval($item['uid'])
		);
		if(! count($r))
			return;
		if(! $r[0]['self'])
			$remote_owner = $r[0];
	}

	// this represents the post owner on this system. 

	$r = q("SELECT `contact`.*, `user`.`nickname` FROM `contact` LEFT JOIN `user` ON `contact`.`uid` = `user`.`uid`
		WHERE `contact`.`self` = 1 AND `contact`.`uid` = %d LIMIT 1",
		intval($owner_uid)
	);
	if(count($r))
		$owner = $r[0];

	if(! $owner) {
		logger('like: no owner');
		return;
	}

	if(! $remote_owner)
		$remote_owner = $owner;


	// This represents the person posting

	if((local_user()) && (local_user() == $owner_uid)) {
		$contact = $owner;
	}
	else {
		$r = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($_SESSION['visitor_id']),
			intval($owner_uid)
		);
		if(count($r))
			$contact = $r[0];
	}
	if(! $contact) {
		return;
	}

	$mid = item_message_id();

	$post_type = (($item['resource_id']) ? t('photo') : t('status'));
	$objtype = (($item['resource_id']) ? ACTIVITY_OBJ_PHOTO : ACTIVITY_OBJ_NOTE ); 
	$link = xmlify('<link rel="alternate" type="text/html" href="' . $a->get_baseurl() . '/display/' . $owner['nickname'] . '/' . $item['id'] . '" />' . "\n") ;
	$body = $item['body'];

	$obj = <<< EOT

	<object>
		<type>$objtype</type>
		<local>1</local>
		<id>{$item['mid']}</id>
		<link>$link</link>
		<title></title>
		<content>$body</content>
	</object>
EOT;
	$bodyverb = t('%1$s is following %2$s\'s %3$s');

	if(! isset($bodyverb))
			return; 

	$arr = array();

	$arr['mid'] = $mid;
	$arr['uid'] = $owner_uid;
	$arr['contact-id'] = $contact['id'];
	$arr['type'] = 'activity';
	$arr['wall'] = $item['wall'];
	$arr['origin'] = 1;
	$arr['gravity'] = GRAVITY_LIKE;
	$arr['parent'] = $item['id'];
	$arr['parent-mid'] = $item['mid'];
	$arr['thr_parent'] = $item['mid'];
	$arr['owner-name'] = $remote_owner['name'];
	$arr['owner-link'] = $remote_owner['url'];
	$arr['owner-avatar'] = $remote_owner['thumb'];
	$arr['author-name'] = $contact['name'];
	$arr['author-link'] = $contact['url'];
	$arr['author-avatar'] = $contact['thumb'];
	
	$ulink = '[zrl=' . $contact['url'] . ']' . $contact['name'] . '[/zrl]';
	$alink = '[zrl=' . $item['author-link'] . ']' . $item['author-name'] . '[/zrl]';
	$plink = '[zrl=' . $a->get_baseurl() . '/display/' . $owner['nickname'] . '/' . $item['id'] . ']' . $post_type . '[/zrl]';
	$arr['body'] =  sprintf( $bodyverb, $ulink, $alink, $plink );

	$arr['verb'] = $activity;
	$arr['object-type'] = $objtype;
	$arr['object'] = $obj;
	$arr['allow_cid'] = $item['allow_cid'];
	$arr['allow_gid'] = $item['allow_gid'];
	$arr['deny_cid'] = $item['deny_cid'];
	$arr['deny_gid'] = $item['deny_gid'];
	$arr['visible'] = 1;
	$arr['unseen'] = 1;
	$arr['last-child'] = 0;

	$post = item_store($arr);	
	$post_id = $post['item_id'];

	if(! $item['visible']) {
		$r = q("UPDATE `item` SET `visible` = 1 WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($item['id']),
			intval($owner_uid)
		);
	}			

	$arr['id'] = $post_id;

	call_hooks('post_local_end', $arr);

	killme();

}


