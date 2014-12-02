<?php

/**
 * @file
 * Contains \Drupal\yp\Controller\YpController
 * @todo: add comments, inline documentation
 */
namespace Drupal\yp\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Controller routines for yp routes
 */
class YpController {
	var $stream_data = array ();
	var $map = array ();
	
	/**
	 * Main cgi callback function
	 */
	public function yp_cgi() {
		$request = Request::createFromGlobals ();
		$action = (null !== $request->request->get ( 'action' )) ? trim ( $request->request->get ( 'action' ) ) : '';
		$this->yp_cgi_action ( $request, $action );
	}
	
	/**
	 * Main Action function
	 * @param REQUEST $request
	 * @param varchar $action
	 */
	private function yp_cgi_action(REQUEST $request, $action) {
		$this->stream_data ['field_yp_stream_listing_ip'] = $request->server->get ( 'SERVER_ADDR' );
		$this->stream_data ['type'] = 'yp_stream';
		//set map and variables
		$this->set_map($request, $action);
		
		
		$response = new Response ( 'Content', 200, array (
				'content-type' => 'text/html' 
		) );
		switch ($action) {
			case 'add':
			$yp_cgi_response = $this->yg_cgi_add($request, $response);
			break;
			case 'touch':
			$yp_cgi_response = $this->yg_cgi_touch($request, $response);
			break;
			case 'remove':
			$yp_cgi_response = $this->yg_cgi_remove($request, $response);
			break;
			default:
				;
			break;
		}
		
		foreach ($yp_cgi_response as $key => $value) {
			$response->headers->set ($key, $value);
		}
		
		$response->prepare ( $request );
		$response->send ();
	}
	
	/**
	 * Set the variable maps
	 * @param unknown $request
	 * @param unknown $action
	 */
	private function set_map(REQUEST $request, $action) {
		switch ($action) {
			case 'add' :
				$this->map = array(
						'title' => array (
								'sn' 
						),
						'field_yp_stream_server_type' => array (
								'type' 
						),
						'field_yp_stream_genre' => array (
								'genre' 
						),
						'field_yp_stream_bitrate' => array (
								'audio_bitrate',
								'b',
								'bitrate',
								'ice-bitrate' 
						),
						'field_yp_stream_sample_rate' => array (
								'audio_samplerate',
								'samplerate',
								'ice-samplerate' 
						),
						'field_yp_stream_channels' => array('audio_channels', 'channels', 'ice-channels'),
					    'field_yp_stream_listen_url' => array('listenurl'),
					    'field_yp_stream_description' => array('desc'),
					    'field_yp_stream_url' => array('url'),
					    'field_yp_stream_cluster_password' => array('cpswd'),
				  );
				break;
			case 'touch' :
				$this->map = array(
				    'sid' => array('sid'),
				    'field_yp_stream_listeners' => array('listeners'),
				    'field_yp_stream_max_listeners' => array('max_listeners'),
				    'field_yp_stream_server_subtype' => array('stype'),
				    'field_yp_stream_current_song' => array('st'),
				  );
				break;
			case 'remove' :
				$this->map = array(
				    'sid' => array('sid'),
				  );
				break;
		}
		
		foreach ( $this->map as $key => $variables ) {
			$this->stream_data [$key] = '';
			foreach ( $variables as $variable ) {
				if (null !== $request->request->get ( $variable )) {
					$this->stream_data[$key] = trim ( $request->request->get ( $variable ) );
				}
			}
		}
		
	}

	private function yg_cgi_add(REQUEST $request, RESPONSE $response) {
    	//new node of type yp stream
		$items = array();
		//Start get the vocab from content type setting
		$vocabularies = array('yp_stream_genre' => entity_load('taxonomy_vocabulary', 'yp_stream_genre'));
		$this->stream_data['field_yp_stream_genre'] = explode(',', $this->stream_data['field_yp_stream_genre']);
		foreach($this->stream_data['field_yp_stream_genre'] as $value) {
			// See if the term exists in the chosen vocabulary and return the tid;
			// otherwise, create a new term.
			if ($possibilities = entity_load_multiple_by_properties('taxonomy_term', array('name' => trim($value), 'vid' => array_keys($vocabularies)))) {
				$term = array_pop($possibilities);
				$item = array('target_id' => $term->id());
			}
			else {
				$vocabulary = reset($vocabularies);
				$term = entity_create('taxonomy_term', array(
						'vid' => $vocabulary->id(),
						'name' => $value,
				));
				$item = array('target_id' => NULL, 'entity' => $term);
			}
			$items[] = $item;
		}
		$this->stream_data['field_yp_stream_genre'] = $items;
		$this->stream_data['field_audio_url']['url'] = $this->stream_data['field_yp_stream_listen_url'];
		$this->stream_data['field_audio_url']['title'] = "Tune In";
		$this->stream_data['field_audio_url']['options'] = array();
		$this->stream_data['field_audio_player']['value'] = "<audio controls>
			<source src='" . $this->stream_data['field_yp_stream_listen_url'] . "'>
			No Support
			</audio>" ;
		$this->stream_data['field_audio_player']['format'] = 'full_html';
		
		//check if it is already there
		$nids = \Drupal::entityQuery('node')
		->condition('type', 'yp_stream')
		->condition('field_yp_stream_listen_url', $this->stream_data['field_yp_stream_listen_url'])
		->execute();
		//if yes, then update
		if (!empty($nids)) {
			foreach ($nids as $nid) {
				$stream = entity_load('node', $nid);
				foreach ($this->stream_data as $key => $value) {
					if ($key != 'sid') {
						$stream->{$key} = $value;
					}
				}
				$stream->save();
			}
			
		}
		//else then create a new node
		else {
			$stream = entity_create('node', $this->stream_data);
			$stream->save();	
		}	
	    $yp_cgi_response['YPResponse'] = $stream->id() ? 1 : 0;
	    $yp_cgi_response['YPMessage'] = $yp_cgi_response['YPResponse'] ? 'Added' : 'Error';

		$yp_cgi_response['SID'] = $stream->id();
		$yp_cgi_response['TouchFreq'] = 60 ;
		return $yp_cgi_response;
	}
	
	private function yg_cgi_touch(REQUEST $request, RESPONSE $response) {
	
		$stream = entity_load('node', $this->stream_data['sid']);
		foreach ($this->stream_data as $key => $value) {
			if ($key != 'sid') {
				$stream->{$key} = $value;
			}
		}
		$stream->save();
		
		$yp_cgi_response['YPResponse'] = $stream->id() ? 1 : 0;
	    $yp_cgi_response['YPMessage'] = $yp_cgi_response['YPResponse'] ? 'Touched' : 'Error';
	    return $yp_cgi_response;
	}
	
	private function yg_cgi_remove(REQUEST $request, RESPONSE $response) {
	
		$stream = entity_load('node', $this->map['sid']);
		$stream->delete();
	    $yp_cgi_response['YPResponse'] = 1;
	    $yp_cgi_response['YPMessage'] = 'Removed';
	}
}
