<?php
/**
 * API Handler Class
 * Centralizes all communication with external APIs like OpenAI and Google.
 *
 * @package CreatorAI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Creator_AI_API {

    /**
     * Send a request to the OpenAI API.
     *
     * @param array  $data    The request payload for the chat completion.
     * @param int    $timeout Request timeout in seconds.
     * @return array|WP_Error The decoded JSON response from the API or a WP_Error on failure.
     */
    public static function openai_request( $data, $timeout = 180 ) {
        $api_key = get_option( 'cai_openai_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'api_key_missing', __( 'OpenAI API key is not set.', 'creator-ai' ) );
        }

        $url = "https://api.openai.com/v1/chat/completions";

        $args = array(
            'body'    => wp_json_encode( $data ),
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => $timeout,
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $result      = json_decode( $body, true );

        if ( $status_code >= 400 ) {
            $error_message = isset( $result['error']['message'] ) ? $result['error']['message'] : __( 'Unknown OpenAI API error.', 'creator-ai' );
            return new WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
        }

        return $result;
    }

    /**
     * Get the Google API access token, automatically refreshing it if it's expired.
     *
     * @return string|false The access token on success, or false on failure.
     */
    public static function get_google_access_token() {
        $access_token  = get_option( 'cai_google_access_token' );
        $expires       = get_option( 'cai_google_expires', 0 );
        $refresh_token = get_option( 'cai_google_refresh_token' );

        // If token is expired (or will expire in the next 60 seconds), refresh it.
        if ( ! $access_token || time() > ( $expires - 60 ) ) {
            if ( empty( $refresh_token ) ) {
                return false; // Cannot refresh without a refresh token.
            }

            $client_id     = get_option( 'cai_google_client_id' );
            $client_secret = get_option( 'cai_google_client_secret' );
            $token_url     = "https://oauth2.googleapis.com/token";

            $args = array(
                'body' => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ),
                'timeout' => 30,
            );

            $response = wp_remote_post( $token_url, $args );

            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                // Failed to refresh token.
                delete_option( 'cai_google_access_token' ); // Clear the expired token.
                return false;
            }

            $token_data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( empty( $token_data['access_token'] ) ) {
                return false;
            }

            $access_token = $token_data['access_token'];
            $expires_in   = isset( $token_data['expires_in'] ) ? intval( $token_data['expires_in'] ) : 3599;

            // Update the stored token and its new expiration time.
            update_option( 'cai_google_access_token', $access_token );
            update_option( 'cai_google_expires', time() + $expires_in );
        }

        return $access_token;
    }
}

