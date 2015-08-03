<?php
/**
 * @file
 * Include file to define functions for signing up to recording groups.
 */

/**
 * Menu handler for the join/* path. Provide the name of the group in lowercase
 * with spaces converted to hyphens and all non-alphanumerics removed. Then the
 * group can be looked up. If the user is already signed in, then they will be
 * joined to the group. If the user is not already signed in, then they are
 * told to register and click on the link again.
 * @param string $title The group title from the URL path
 * @return string
 */
function iform_join_group($title='') {
  if (empty($title)) {
    drupal_set_message(t('The path you visited requires the name of the group to join in the path.'));
    drupal_goto('<front>');
  }
  // Perform same sanitisation of the group name as will be performed on db. Should be already done,
  // but this ensures it is safe.
  $title = trim(preg_replace('/[^a-z0-9\-]/', '', preg_replace('/[ ]/', '-', strtolower($title))), '-');
  $auth = data_entry_helper::get_read_write_auth(variable_get('indicia_website_id'), variable_get('indicia_password'));
  // look up the group.
  $groups = data_entry_helper::get_population_data(array(
    'table' => 'group',
    'extraParams' => $auth['read'] + array(
        'view' => 'detail',
        'url_safe_title' => $title
      )
  ));
  if (!count($groups)) {
    drupal_not_found();
    return;
  }
  global $user;
  if (!count($groups) > 1) {
    drupal_set_message(t('The group you are trying to join has a duplicate name with another group so cannot be joined in this way.'));
    drupal_goto('<front>');
  }
  $group = $groups[0];
  if ($user->uid) {
    // User is logged in
    $indiciaUserId = hostsite_get_user_field('indicia_user_id');
    if (!$indiciaUserId) {
      drupal_set_message(t("Before joining $group[title], please set your surname on your user account profile."));
      drupal_goto('<front>');
    }
    $showJoinMessage=true;
    $groupsUsers = data_entry_helper::get_population_data(array(
      'table' => 'groups_user',
      'extraParams' => $auth['read'] + array(
          'group_id' => $group['id'],
          'user_id' => $indiciaUserId
        ),
      'caching' => FALSE
    ));
    if (!count($groupsUsers)) {
      // User is not already a member. So, we can join them up
      if (!iform_join_public_group($group, $auth['write_tokens'], $indiciaUserId)) {
        drupal_goto('<front>');
        return;
      }
    }
    else {
      // User is already a member.
      if ($groupsUsers[0]['pending'] === 't') {
        // Membership exists but is pending. As this is now a public group we can overwrite to
        // remove the pending flag.
        if (!iform_join_public_group($group, $auth['write_tokens'], $indiciaUserId, $groupsUsers[0]['id'])) {
          drupal_goto('<front>');
          return;
        }
      }
      else {
        drupal_set_message(t("Welcome back to the @group.", array('@group' => iform_readable_group_title($group))));
        $showJoinMessage=false;
      }
    }
    return iform_show_group_join_success($group, $auth, $showJoinMessage);
  }
  else {
    // User is not logged in, so redirect to login page with parameters so we know which group
    drupal_goto('user', array('query' => array('group_id'=>$group['id'], 'destination' => $_GET['q'])));
  }
}

/**
 * Having successfully joined a group, show the success and a list of ongoing options.
 * @param $group
 * @param $auth
 * @param bool $showJoinMessage
 * @param string $group_home_path
 * @param string $groups_list_path
 * @return string
 */
function iform_show_group_join_success($group, $auth, $showJoinMessage=true, $group_home_path='', $groups_list_path='') {
  $pageData = data_entry_helper::get_population_data(array(
    'table'=>'group_page',
    'extraParams' => $auth['read'] + array(
        'group_id' => $group['id'],
        'query' => json_encode(array('in'=>array('administrator'=>array('', 'f')))),
        'orderby' => 'caption'
      )
  ));
  $welcome = t("Welcome to the @group!", array('@group' => iform_readable_group_title($group)));
  if (count($pageData)===1) {
    // Only one page so go straight to it
    if ($showJoinMessage)
      hostsite_show_message($welcome);
    drupal_goto($pageData[0]['path']);
  } else {
    // Multiple pages, let the user choose
    $intro = '';
    $r = '';

    if ($showJoinMessage) {
      hostsite_set_page_title($welcome);
      $intro = t("You've successfully joined the @group on @site.",
        array('@group' => iform_readable_group_title($group), '@site' => variable_get('site_name'))) . ' ';
    }
    $path = data_entry_helper::get_uploaded_image_folder();
    $r .= empty($group['logo_path']) ? '' : "<img style=\"width: 30%; float: left; padding: 0 5% 5%;\" alt=\"Logo\" src=\"$path$group[logo_path]\"/>";
    $r .= '<div style="float: left; width: 60%;"><p>' . $intro . t('You can access the following pages for the @group',
        array('@group' => iform_readable_group_title($group))) . ':</p>';
    $r .= '<ul>';
    if (!empty($group_home_path))
      $r .= '<li><a href="'.hostsite_get_url($group_home_path, array('group_id'=>$group['id'], 'implicit'=>$group['implicit_record_inclusion'])).'">'.lang::get("Visit the $group[title] home page").'<a></li>';
    foreach ($pageData as $page)
      $r .= '<li><a href="'.hostsite_get_url($page['path'], array('group_id'=>$group['id'], 'implicit'=>$group['implicit_record_inclusion'])).'">'.lang::get($page['caption']).'<a></li>';
    if (!empty($groups_list_path))
      $r .= '<li><a href="'.hostsite_get_url($groups_list_path).'">'.lang::get("Return to your recording groups list").'<a></li>';
    $r .= '</ul></div>';
    return $r;
  }
}

/**
 * Take account of the different way group titles are written to make it easier to create readable
 * sentences about a group. Basically adds " group" to the end of the group title if not already
 * there.
 * @param array $group Group record loaded from database
 */
function iform_readable_group_title($group) {
  $r = $group['title'];
  if (!preg_match('/ ' . t('group') . '$/', $r))
    $r .= ' ' . t('group');
  return $r;
}

/**
 * Joins a given user to a recording group. After joining, shows a list of options related to the group
 * or redirects to the group's page if there is only one.
 * @param array $group Group data loadede from the database.
 * @param array $writeAuth Write authorisation tokens
 * @param $indiciaUserId User's warehouse user ID
 * @param integer $groupsUserId Supply the ID of an existing groups_users record if overwriting one, e.g. to
 * remove the pending flag.
 * @return bool True if joining was successful.
 */
function iform_join_public_group($group, $writeAuth, $indiciaUserId, $groupsUserId = null) {
  global $user;
  $values = array(
    'website_id' => variable_get('indicia_website_id'),
    'groups_user:group_id' => $group['id'],
    'groups_user:user_id' => $indiciaUserId,
    'groups_user:username' => $user->name,
    'groups_user:pending' => 'f'
  );
  if ($groupsUserId) {
    // existing record to update?
    $values['groups_user:id'] = $groupsUserId;
  }
  $s = submission_builder::build_submission($values, array('model'=>'groups_user'));
  $r = data_entry_helper::forward_post_to('save', $s, $writeAuth);
  if (isset($r['success'])) {
    return true;
  }
  else {
    drupal_set_message(t("An error occurred whilst trying to join the @group.",
      array('@group'=>iform_readable_group_title($group))), 'warning');
    watchdog('groups', "An error occurred whilst trying to join $group[title] for {$user->name}.");
    watchdog('groups', var_export($r, true));
    return false;
  }
}