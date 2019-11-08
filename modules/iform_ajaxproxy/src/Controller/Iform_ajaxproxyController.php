<?php
/**
 * @file
 * Contains \Drupal\iform\Controller\IformController.
 */

namespace Drupal\iform_ajaxproxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class Iform_ajaxproxyController extends ControllerBase {

  public function ajaxCallback() {
    iform_load_helpers(array('data_entry_helper'));
    $error=false;
    if (!$_POST) {
      $error = t("no POST data.");
    } else {
      $nid = isset($_GET['node']) ? $_GET['node'] : NULL;
      $index = isset($_GET['index']) ? $_GET['index'] : NULL;
      $config = \Drupal::config('iform.settings');
      // Sanity check
      if (empty($index)){
        $error = t("invocation format problem - no data format indicator.");
      } else {
        if (empty($nid))
          $conn=array('website_id'=>$config->get('website_id'), 'password'=>$config->get('password'));
        else {
          $node = \Drupal\node\Entity\Node::load($nid);
          if (isset($node->params['base_url']) && $node->params['base_url']!==$config->get('base_url')) {
            global $_iform_warehouse_override;
            $_iform_warehouse_override = array(
              'base_url' => $node->params['base_url'],
              'website_id' => $node->params['website_id'],
              'password' => $node->params['password']
            );
            \data_entry_helper::$base_url = $node->params['base_url'];
          }
          $conn = iform_get_connection_details($node);
          if ($node->getType() != 'iform_page') {
            $error = t('Drupal node is not an iform node.');
          }
        }
        // form type is held in $node->iform, but not relevant at this point.
        //    require_once drupal_get_path('module', 'iform').'/client_helpers/prebuilt_forms/'.$node->iform.'.php';
        $postargs = "website_id=".$conn['website_id'];
        $response = \data_entry_helper::http_post(\data_entry_helper::$base_url.'/index.php/services/security/get_nonce',
            $postargs, false);
        $nonce = $response['output'];
        if (!array_key_exists('website_id', $_POST)) {
          $error = t("Indicia website_id not provided in POST.");
        }
        elseif ($_POST['website_id'] != $conn['website_id']) {
          $error = t("Indicia website_id in POST does not match the stored website ID.");
        }
      }
    }
    if ($error) {
      return new Response("{error:\"iform_ajaxproxy Error: ".$error."\"}", 400);
    }
    $writeTokens = array('nonce'=>$nonce, 'auth_token' => sha1($nonce.":".$conn['password']));
    if ($index === 'single_verify') {
      return $this->postVerification($writeTokens);
    }
    if ($index === 'list_verify') {
      return $this->postVerification($writeTokens, 'list_verify');
    }
    elseif ($index === 'single_verify_sample') {
      return $this->postVerification($writeTokens, 'single_verify_sample');
    }
    else {
      switch ($index) {
        case "sample":
          $Model = \data_entry_helper::wrap_with_attrs($_POST, 'sample');
          break;
        case "location":
          $structure = array(
            'model' => 'location'
          );
          // Only include website if in post data
          if (array_key_exists('locations_website:website_id', $_POST)){
            $structure['subModels']['locations_website'] = array('fk' => 'location_id');
          }
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          break;
        case "loc-sample":
          $structure = array(
            'model' => 'location',
            'subModels' => array(
              'sample' => array('fk' => 'location_id')
            )
          );
          if (array_key_exists('locations_website:website_id', $_POST)){
            $structure['subModels']['locations_website'] = array('fk' => 'location_id');
          }
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          break;
        case "loc-smp-occ":
          $structure = array(
            'model' => 'sample',
            'subModels' => array(
              'occurrence' => array('fk' => 'sample_id')
            ),
            'superModels' => array(
              'location' => array('fk' => 'location_id')
            )
          );
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          if (array_key_exists('locations_website:website_id', $_POST)){
            if (isset($Model['superModels'][0]['model']['subModels']))
              $Model['superModels'][0]['model']['subModels'] = array();
            $Model['superModels'][0]['model']['subModels'][] = array(
              'fkId' => 'location_id',
              'model' => array('id' => 'locations_website',
                'fields' => array('website_id' => array('value' => $_POST['locations_website:website_id']))));
          }
          foreach($_POST as $key=>$value){
            if (substr($key,0,14) == 'determination:'){
              $Model['subModels'][0]['model']['subModels'][] = array(
                'fkId' => 'occurrence_id',
                'model' => \data_entry_helper::wrap($_POST, 'determination', 'determination')
              );
              break;
            }
          }
          break;
        case "smp-occ":
          $structure = array(
            'model' => 'sample',
            'subModels' => array(
              'occurrence' => array('fk' => 'sample_id')
            )
          );
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          break;
        case "media":
          // media handled differently. Submission is handled by the handle_media function.
          // hardcode the auth into the $_Post array
          $_POST['auth_token'] = sha1($nonce.":".$conn['password']);
          $_POST['nonce'] = $nonce;
          $media_id = 'upload_file';
          // At the moment this only needs to handle a single media file at a time
          if (array_key_exists($media_id, $_FILES)) { //there is a single upload field
            if ($_FILES[$media_id]['name'] != '') { //that field has a file
              $file = $_FILES[$media_id];
              $return = [];
              $uploadpath = \helper_base::$upload_path;
              $target_url = \helper_base::$base_url . "/index.php/services/data/handle_media";
              $name = $file['name'];
              $fname = $file['tmp_name'];
              $parts = explode(".", $name);
              $fext = array_pop($parts);
              // Generate a file id to store the image as
              $destination = time() . str_pad((string)rand(0,999),3,'0',STR_PAD_LEFT).".".$fext;
              if (move_uploaded_file($fname, $uploadpath.$destination)) { //successfully stored locally - send to the warehouse
                $postargs = array('name_is_guid' => 'true'); // we've done the time etc thing, so server doesn't need to.
                if (array_key_exists('auth_token', $_POST)) $postargs['auth_token'] = $_POST['auth_token'];
                if (array_key_exists('nonce', $_POST)) $postargs['nonce'] = $_POST['nonce'];
                $file_to_upload = array('media_upload'=>'@'.realpath($uploadpath.$destination));
                $response = \data_entry_helper::http_post($target_url, $file_to_upload + $postargs);
                $output = json_decode($response['output'], true);
                if (is_array($output)) {
                  // An array signals an error - attach the errors to the
                  // control that caused them.
                  if (array_key_exists('error', $output)) {
                    $return['error'] = $output['error'];
                    if (array_key_exists('errors', $output)) $return['errors'][$media_id] = $output['errors']['media_upload'];
                  }
                } else { //filenames are returned without structure - the output of json_decode may not be valid.
                  $exif = exif_read_data($uploadpath . $destination, 0, true);
                  if (!is_array($exif) || !isset($exif["IFD0"]) || !is_array($exif["IFD0"]))
                    $exif = array("IFD0" => array());
                  if (!isset($exif["IFD0"]["Make"])) $exif["IFD0"]["Make"] = '';
                  if (!isset($exif["IFD0"]["Model"])) $exif["IFD0"]["Model"] = '';
                  if (!isset($exif["IFD0"]["DateTime"])) $exif["IFD0"]["DateTime"] = '';
                  $return['files'][] = array('filename' => $response['output'],
                    'EXIF_Camera_Make' => $exif["IFD0"]["Make"].' ' . $exif["IFD0"]["Model"],
                    'EXIF_DateTime' => $exif["IFD0"]["DateTime"]);
                }
                unlink($uploadpath.$destination); //remove local copy
              } else { //attach the errors to the control that caused them
                $return['error'] = 'iform_ajaxproxy Error: Upload error';
                $return['errors'][$media_id] = 'Sorry, there was a problem uploading this file - move failed.';
              }
            }
            else { //attach the errors to the control that caused them
              $return['error'] = 'iform_ajaxproxy Error: Upload error';
              $return['errors'][$media_id] = 'Sorry, no file present for "'.$media_id.'".';
            }
          }
          else {
            $return['error'] = 'iform_ajaxproxy Error: Upload error';
            $return['errors'][$media_id] = 'Sorry, "' . $media_id . '" not present in _FILES.';
          }
          //If no errors in the response array, all went well.
          $return['success'] = !(array_key_exists('error', $return) || array_key_exists('errors', $return));
          return new Response(json_encode($return));
        case "occurrence":
          $structure = array('model' => 'occurrence');
          // Only include determination or comment record if determination in post
          foreach ($_POST as $key=>$value){
            if (substr($key,0,14) == 'determination:'){
              $structure['subModels'] = array('determination' => array('fk' => 'occurrence_id'));
              break;
            } elseif (substr($key,0,19) == 'occurrence_comment:'){
              $structure['subModels'] = array('occurrence_comment' => array('fk' => 'occurrence_id'));
              break;
            }
          }
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          break;

        case "occ-comment":
          $Model = \data_entry_helper::wrap($_POST, 'occurrence_comment');
          break;

        case "smp-comment":
          $Model = \data_entry_helper::wrap($_POST, 'sample_comment');
          break;

        case "determination":
          $Model = \data_entry_helper::wrap($_POST, 'determination');
          break;

        case "notification":
          $Model = \data_entry_helper::wrap($_POST, 'notification');
          break;

        case "user-trust":
          $structure = array('model' => 'user_trust');
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          break;

        case "person_attribute_value":
          $Model = \data_entry_helper::wrap($_POST, 'person_attribute_value');
          break;

        case "filter":
          $Model = \data_entry_helper::wrap($_POST, 'filter');
          break;
        case "filter_and_user":
          $structure = array('model' => 'filter', 'subModels' => array('filters_user' => array('fk' => 'filter_id')));
          $Model = \data_entry_helper::build_submission($_POST, $structure);
          break;

        case "groups_location":
          $Model = \data_entry_helper::wrap($_POST, 'groups_location');
          break;

        case "groups_user":
          $Model = \data_entry_helper::wrap($_POST, 'groups_user');
          break;

        case "scratchpad_list":
          $Model = \data_entry_helper::wrap($_POST, 'scratchpad_list');
          break;

        case "comment_quick_reply_page_auth":
          $Model = \data_entry_helper::wrap($_POST, 'comment_quick_reply_page_auth');
          break;

        case "taxa_taxon_list_attribute":
          $Model = \data_entry_helper::wrap($_POST, 'taxa_taxon_list_attribute');
          break;

        case "taxa_taxon_list_attribute_value":
          $Model = \data_entry_helper::wrap($_POST, 'taxa_taxon_list_attribute_value');
          break;

        case "occurrence_attribute_website":
          $Model = \data_entry_helper::wrap($_POST, 'occurrence_attribute_website');
          break;

        case "taxon_lists_taxa_taxon_list_attribute":
          $Model = \data_entry_helper::wrap($_POST, 'taxon_lists_taxa_taxon_list_attribute');
          break;

        case "attribute_set":
          $Model = \data_entry_helper::wrap($_POST, 'attribute_set');
          break;

        case "attribute_sets_taxa_taxon_list_attribute":
          $Model = \data_entry_helper::wrap($_POST, 'attribute_sets_taxa_taxon_list_attribute');
          break;

        case "occurrence_attributes_taxa_taxon_list_attribute":
          $Model = \data_entry_helper::wrap($_POST, 'occurrence_attributes_taxa_taxon_list_attribute');
          break;

        case "attribute_sets_taxon_restriction":
          $Model = \data_entry_helper::wrap($_POST, 'attribute_sets_taxon_restriction');
          break;

        case "attribute_sets_survey":
          $Model = \data_entry_helper::wrap($_POST, 'attribute_sets_survey');
          break;

        default:
          return new Response("{error:\"iform_ajaxproxy Error: Current defined methods are: sample, location, loc-sample, loc-smp-occ, smp-occ, '.
              'media, occurrence, occ-comment, smp-comment, determination, notification, user-trust, person_attribute_value\"}");
          // TODO invoke optional method in relevant iform prebuilt form to handle non standard indexes
          // TODO? echo a failure response: invalid index type
      }
      // pass through the user ID as this can then be used to set created_by and updated_by_ids
      if (isset($_REQUEST['user_id'])) $writeTokens['user_id'] = $_REQUEST['user_id'];
      if (isset($_REQUEST['sharing'])) $writeTokens['sharing'] = $_REQUEST['sharing'];
      $response = \data_entry_helper::forward_post_to('save', $Model, $writeTokens);
      // if it is not json format, assume error text, and json encode that.
      //if (!json_decode($output, true))
      //    $response = "{error:\"".$output."\"}";
      // possible:
      return new Response(json_encode($response));
    }
  }


  /**
   * Verify method handler.
   *
   * Special case handler for the single_verify, list_verify and
   * single_sample_verify methods, since this goes to the data_utils service
   * for performance reasons.
   */
  private function postVerification($writeTokens, $method = 'single_verify') {
    $request = \data_entry_helper::$base_url . "index.php/services/data_utils/$method";
    $postargs = \data_entry_helper::array_to_query_string(array_merge($_POST, $writeTokens), TRUE);
    $response = \data_entry_helper::http_post($request, $postargs);
    // The response should be in JSON if it worked.
    $output = json_decode($response['output'], TRUE);
    // If this is not JSON, it is an error, so just return it as is.
    if (!$output) {
      return new Response($response['output']);
    }
    else {
      return new Response(print_r($response, TRUE));
    }
  }

}
