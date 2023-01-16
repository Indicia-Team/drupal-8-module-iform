<?php

namespace Drupal\iform_ajaxproxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;

class Iform_ajaxproxyController extends ControllerBase {

  public function ajaxCallback() {
    iform_load_helpers(['data_entry_helper']);
    $error = FALSE;
    if (!$_POST) {
      $error = $this->t("no POST data.");
    }
    else {
      $nid = $_GET['node'] ?? NULL;
      $index = $_GET['index'] ?? NULL;
      $config = \Drupal::config('iform.settings');
      // Sanity check.
      if (empty($index)) {
        $error = $this->t("invocation format problem - no data format indicator.");
      }
      else {
        if (empty($nid)) {
          $conn = [
            'website_id' => $config->get('website_id'),
            'password' => $config->get('password'),
          ];
        }
        else {
          $node = Node::load($nid);
          if (isset($node->params['base_url']) && $node->params['base_url'] !== $config->get('base_url') && $config->get('allow_connection_override')) {
            global $_iform_warehouse_override;
            $_iform_warehouse_override = [
              'base_url' => $node->params['base_url'],
              'website_id' => $node->params['website_id'],
              'password' => $node->params['password'],
            ];
            \data_entry_helper::$base_url = $node->params['base_url'];
          }
          $conn = iform_get_connection_details($node);
          if ($node->getType() != 'iform_page') {
            $error = $this->t('Drupal node is not an iform node.');
          }
        }
        // Form type is held in $node->iform, but not relevant at this point.
        $postargs = "website_id=$conn[website_id]";
        $response = \data_entry_helper::http_post(\data_entry_helper::$base_url . '/index.php/services/security/get_nonce',
            $postargs, FALSE);
        $nonce = $response['output'];
        if (!array_key_exists('website_id', $_POST)) {
          $error = $this->t("Indicia website_id not provided in POST.");
        }
        elseif ($_POST['website_id'] != $conn['website_id']) {
          $error = $this->t("Indicia website_id in POST does not match the stored website ID.");
        }
      }
    }
    if ($error) {
      return new Response("{error:\"iform_ajaxproxy Error: $error\"}", 400);
    }
    $writeTokens = ['nonce' => $nonce, 'auth_token' => sha1($nonce . ":" . $conn['password'])];
    switch ($index) {
      case 'single_verify':
        return $this->postVerification($writeTokens);

      case 'list_verify':
        return $this->postVerification($writeTokens, 'list_verify');

      case 'single_verify_sample':
        return $this->postVerification($writeTokens, 'single_verify_sample');

      case "sample":
        $s = \submission_builder::wrap_with_images($_POST, 'sample');
        break;

      case "location":
        $structure = [
          'model' => 'location',
        ];
        // Only include website if in post data.
        if (array_key_exists('locations_website:website_id', $_POST)) {
          $structure['subModels']['locations_website'] = ['fk' => 'location_id'];
        }
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "loc-sample":
        $structure = [
          'model' => 'location',
          'subModels' => [
            'sample' => ['fk' => 'location_id'],
          ],
        ];
        if (array_key_exists('locations_website:website_id', $_POST)) {
          $structure['subModels']['locations_website'] = ['fk' => 'location_id'];
        }
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "loc-smp-occ":
        $structure = [
          'model' => 'sample',
          'subModels' => [
            'occurrence' => ['fk' => 'sample_id'],
          ],
          'superModels' => [
            'location' => ['fk' => 'location_id'],
          ],
        ];
        $s = \submission_builder::build_submission($_POST, $structure);
        if (array_key_exists('locations_website:website_id', $_POST)) {
          if (isset($s['superModels'][0]['model']['subModels'])) {
            $s['superModels'][0]['model']['subModels'] = [];
          }
          $s['superModels'][0]['model']['subModels'][] = [
            'fkId' => 'location_id',
            'model' => [
              'id' => 'locations_website',
              'fields' => [
                'website_id' => [
                  'value' => $_POST['locations_website:website_id'],
                ],
              ],
            ],
          ];
        }
        foreach ($_POST as $key => $value) {
          if (substr($key, 0, 14) == 'determination:') {
            $s['subModels'][0]['model']['subModels'][] = [
              'fkId' => 'occurrence_id',
              'model' => \submission_builder::wrap($_POST, 'determination', 'determination'),
            ];
            break;
          }
        }
        break;

      case "smp-occ":
        $structure = [
          'model' => 'sample',
          'subModels' => [
            'occurrence' => ['fk' => 'sample_id'],
          ],
        ];
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "media":
        // Media handled differently. Submission is handled by the
        // handle_media function.
        // Hardcode the auth into the $_Post array.
        $_POST['auth_token'] = sha1("$nonce:$conn[password]");
        $_POST['nonce'] = $nonce;
        $media_id = 'upload_file';
        // At the moment this only needs to handle a single media file at a
        // time.
        if (array_key_exists($media_id, $_FILES)) {
          // There is a single upload field.
          if ($_FILES[$media_id]['name'] != '') {
            // That field has a file.
            $file = $_FILES[$media_id];
            $return = [];
            $uploadpath = \helper_base::$upload_path;
            $target_url = \helper_base::$base_url . "/index.php/services/data/handle_media";
            $name = $file['name'];
            $fname = $file['tmp_name'];
            $parts = explode(".", $name);
            $fext = array_pop($parts);
            // Generate a file id to store the image as.
            $destination = time() . str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT) . ".$fext";
            if (move_uploaded_file($fname, $uploadpath . $destination)) {
              // Successfully stored locally - send to the warehouse.
              // We've done the time etc thing, so server doesn't need to.
              $postargs = ['name_is_guid' => 'true'];
              if (array_key_exists('auth_token', $_POST)) {
                $postargs['auth_token'] = $_POST['auth_token'];
              }
              if (array_key_exists('nonce', $_POST)) {
                $postargs['nonce'] = $_POST['nonce'];
              }
              $file_to_upload = ['media_upload' => '@' . realpath($uploadpath . $destination)];
              $response = \data_entry_helper::http_post($target_url, $file_to_upload + $postargs);
              $output = json_decode($response['output'], TRUE);
              if (is_array($output)) {
                // An array signals an error - attach the errors to the
                // control that caused them.
                if (array_key_exists('error', $output)) {
                  $return['error'] = $output['error'];
                  if (array_key_exists('errors', $output)) {
                    $return['errors'][$media_id] = $output['errors']['media_upload'];
                  }
                }
              }
              else {
                // Filenames are returned without structure - the output of
                // json_decode may not be valid.
                $exif = exif_read_data($uploadpath . $destination, 0, TRUE);
                if (!is_array($exif) || !isset($exif['IFD0']) || !is_array($exif['IFD0'])) {
                  $exif = ['IFD0' => []];
                }
                if (!isset($exif['IFD0']['Make'])) {
                  $exif['IFD0']['Make'] = '';
                }
                if (!isset($exif['IFD0']['Model'])) {
                  $exif['IFD0']['Model'] = '';
                }
                if (!isset($exif['IFD0']['DateTime'])) {
                  $exif['IFD0']['DateTime'] = '';
                }
                $return['files'][] = [
                  'filename' => $response['output'],
                  'EXIF_Camera_Make' => $exif['IFD0']['Make'] . ' ' . $exif['IFD0']['Model'],
                  'EXIF_DateTime' => $exif['IFD0']['DateTime'],
                ];
              }
              // Remove local copy.
              unlink($uploadpath . $destination);
            }
            else {
              // Attach the errors to the control that caused them.
              $return['error'] = 'iform_ajaxproxy Error: Upload error';
              $return['errors'][$media_id] = 'Sorry, there was a problem uploading this file - move failed.';
            }
          }
          else {
            // Attach the errors to the control that caused them.
            $return['error'] = 'iform_ajaxproxy Error: Upload error';
            $return['errors'][$media_id] = 'Sorry, no file present for "' . $media_id . '".';
          }
        }
        else {
          $return['error'] = 'iform_ajaxproxy Error: Upload error';
          $return['errors'][$media_id] = 'Sorry, "' . $media_id . '" not present in _FILES.';
        }
        // If no errors in the response array, all went well.
        $return['success'] = !(array_key_exists('error', $return) || array_key_exists('errors', $return));
        return new Response(json_encode($return));

      case "occurrence":
        $structure = ['model' => 'occurrence'];
        // Only include determination or comment record if determination in
        // post.
        foreach ($_POST as $key => $value) {
          if (substr($key, 0, 14) == 'determination:') {
            $structure['subModels'] = ['determination' => ['fk' => 'occurrence_id']];
            break;
          }
          elseif (substr($key, 0, 19) == 'occurrence_comment:') {
            $structure['subModels'] = ['occurrence_comment' => ['fk' => 'occurrence_id']];
            break;
          }
        }
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "occ-comment":
        $s = \submission_builder::wrap($_POST, 'occurrence_comment');
        break;

      case "smp-comment":
        $s = \submission_builder::wrap($_POST, 'sample_comment');
        break;

      case "loc-comment":
        $s = \submission_builder::wrap($_POST, 'location_comment');
        break;

      case "determination":
        $s = \submission_builder::wrap($_POST, 'determination');
        break;

      case "notification":
        $s = \submission_builder::wrap($_POST, 'notification');
        break;

      case "user-trust":
        $structure = ['model' => 'user_trust'];
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "person_attribute_value":
        $s = \submission_builder::wrap($_POST, 'person_attribute_value');
        break;

      case "filter":
        $s = \submission_builder::wrap($_POST, 'filter');
        break;

      case "filter_and_user":
        $structure = [
          'model' => 'filter',
          'subModels' => [
            'filters_user' => ['fk' => 'filter_id'],
          ],
        ];
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "groups_location":
        $s = \submission_builder::wrap($_POST, 'groups_location');
        break;

      case "groups_user":
        $s = \submission_builder::wrap($_POST, 'groups_user');
        break;

      case "scratchpad_list":
        $s = \submission_builder::wrap($_POST, 'scratchpad_list');
        break;

      case "comment_quick_reply_page_auth":
        $s = \submission_builder::wrap($_POST, 'comment_quick_reply_page_auth');
        break;

      case "taxa_taxon_list":
        $structure = [
          'model' => 'taxa_taxon_list',
          'superModels' => [
            'taxon' => ['fk' => 'taxon_id'],
            'taxon_meaning' => ['fk' => 'taxon_meaning_id'],
          ],
        ];
        $s = \submission_builder::build_submission($_POST, $structure);
        break;

      case "taxa_taxon_list_attribute":
        $s = \submission_builder::wrap($_POST, 'taxa_taxon_list_attribute');
        break;

      case "taxa_taxon_list_attribute_value":
        $s = \submission_builder::wrap($_POST, 'taxa_taxon_list_attribute_value');
        break;

      case "occurrence_attribute_website":
        $s = \submission_builder::wrap($_POST, 'occurrence_attribute_website');
        break;

      case "taxon_lists_taxa_taxon_list_attribute":
        $s = \submission_builder::wrap($_POST, 'taxon_lists_taxa_taxon_list_attribute');
        break;

      case "attribute_set":
        $s = \submission_builder::wrap($_POST, 'attribute_set');
        break;

      case "attribute_sets_taxa_taxon_list_attribute":
        $s = \submission_builder::wrap($_POST, 'attribute_sets_taxa_taxon_list_attribute');
        break;

      case "occurrence_attributes_taxa_taxon_list_attribute":
        $s = \submission_builder::wrap($_POST, 'occurrence_attributes_taxa_taxon_list_attribute');
        break;

      case "attribute_sets_taxon_restriction":
        $s = \submission_builder::wrap($_POST, 'attribute_sets_taxon_restriction');
        break;

      case "attribute_sets_survey":
        $s = \submission_builder::wrap($_POST, 'attribute_sets_survey');
        break;

      case "verification_template":
        $s = \submission_builder::wrap($_POST, 'verification_template');
        break;

      case "orca_effort":
        $s = \submission_builder::wrap($_POST, 'orca_effort');
        break;

      case "orca_sighting":
        $s = \submission_builder::wrap($_POST, 'orca_sighting');
        break;

      case "orca_incidental":
        $s = \submission_builder::wrap($_POST, 'orca_incidental');
        break;

      default:
        return new Response("{error:\"iform_ajaxproxy Error: Current defined methods are: sample, location, loc-sample, loc-smp-occ, smp-occ, '.
            'media, occurrence, occ-comment, smp-comment, determination, notification, user-trust, person_attribute_value\"}");

      // @todo Invoke optional method in relevant iform prebuilt form to
      // handle non standard indexes.
      // @todo Echo a failure response: invalid index type.
    }
    // Pass through the user ID as this can then be used to set created_by
    // and updated_by_ids.
    if (isset($_REQUEST['user_id'])) {
      $writeTokens['user_id'] = $_REQUEST['user_id'];
    }
    if (isset($_REQUEST['sharing'])) {
      $writeTokens['sharing'] = $_REQUEST['sharing'];
    }
    $response = \data_entry_helper::forward_post_to('save', $s, $writeTokens);

    // Invoke submission hooks.
    if (!empty($_POST["$s[id]:id"])) {
      $op = 'U';
    }
    else {
      $op = 'C';
    }
    $msg = 'foo';
    \Drupal::moduleHandler()->invokeAll('iform_after_submit', [
      $s,
      $op,
      $response,
      &$msg,
    ]);
    return new Response(json_encode($response));
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
      $r = new Response($response['output']);
    }
    else {
      $r = new Response(print_r($response, TRUE));
    }
    $r->headers->set('Content-Type', 'text/plain');
    return $r;
  }

}
