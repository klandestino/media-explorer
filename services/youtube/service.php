<?php

namespace EMM\Services\Youtube;

class Service extends \EMM\Service {

	const DEFAULT_MAX_RESULTS = 18;

	public function __construct() {
		require_once __DIR__ . '/template.php';

		# Go!
		$this->set_template( new Template );
	}

	public function load() {

		$emm = \Extended_Media_Manager::init();

		wp_enqueue_script(
			'emm-service-youtube-infinitescroll',
			$emm->plugin_url( 'services/youtube/js.js' ),
			array( 'jquery', 'emm' ),
			$emm->plugin_ver( 'services/youtube/js.js' )
		);

	}

	/**
	 * TODO: escape parameters, sanitize_Text_field, for example
	 */
	public function request( array $request ) {
		$youtube = $this->get_connection();
		$params = $request['params'];

		switch ( $params['tab'] ) 
		{
			case 'by_user':
				$request = array(
					'channel' => sanitize_text_field( $params['channel'] ),
					'type' => 'video',
				);

				// Make the request to the Youtube API
				$search_response = $youtube->get_videos_from_channel( $request );
			break;

			default:
			case 'all':
				$request = array(
					'q' => sanitize_text_field( $params['q'] ),
					'maxResults' => self::DEFAULT_MAX_RESULTS,
				);

				if ( isset( $params['type'] ) )
					$request['type'] = sanitize_text_field( $params['type'] );

				// Make the request to the Youtube API
				$search_response = $youtube->get_videos( $request );
			break;
			
		}

		// Create the response for the API
		$response = new \EMM\Response();

		if ( !isset( $search_response['items'] ) )
			return false;

		foreach ( $search_response['items'] as $index => $search_item ) {
			$item = new \EMM\Response_Item();
			if ( $request['type'] == 'video' && isset( $request['q'] ) ) { // For videos searched by query
				$item->set_url( esc_url( sprintf( "http://www.youtube.com/watch?v=%s", $search_item['id']['videoId'] ) ) );
			} elseif( $request['type'] == 'playlist' && isset( $request['q'] ) ) { // For playlists searched by query
				$item->set_url( esc_url( sprintf( "http://www.youtube.com/playlist?list=%s", $search_item['id']['playlistId'] ) ) );
			} else { // For videos searched by channel name
				$item->set_url( esc_url( sprintf( "http://www.youtube.com/watch?v=%s", $search_item['snippet']['resourceId']['videoId'] ) ) );
			}
			$item->add_meta( 'user', $search_item['snippet']['channelTitle'] );
			$item->add_meta( 'next_page', $search_response['nextPageToken'] );
			$item->set_id( $index );
			$item->set_content( $search_item['snippet']['title'] );
			$item->set_thumbnail( $search_item['snippet']['thumbnails']['medium']['url'] );
			$item->set_date( strtotime( $search_item['snippet']['publishedAt'] ) );
			$item->set_date_format( 'g:i A - j M y' );
			$response->add_item($item);
		}

		return $response;
	}

	public function tabs() {
		return array(
			'all' => array(
				'text'       => _x( 'All', 'Tab title', 'emm'),
				'defaultTab' => true
			),
			'by_user' => array(
				'text'       => _x( 'By User', 'Tab title', 'emm'),
			),
		);
	}

	private function get_connection() {
		// Add the Google API classes to the runtime
		require_once plugin_dir_path( __FILE__) . '/class.wp-youtube-client.php';

		$developer_key = (string) apply_filters( 'emm_youtube_developer_key', '' ) ;

		return new Youtube_Client( $developer_key );
	}

	public function labels() {
		return array(
			'title'     => sprintf( __( 'Insert from %s', 'emm' ), 'Youtube' ),
			'insert'    => __( 'Insert', 'emm' ),
			'noresults' => __( 'No videos matched your search query', 'emm' ),
		);
	}
}

add_filter( 'emm_services', 'action_youtube_service' );

function action_youtube_service( $services ) {
	$services['youtube'] = new Service;
	return $services;
}
