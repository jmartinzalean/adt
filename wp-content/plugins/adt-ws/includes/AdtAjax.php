<?php


class AdtAjax {

    /**
     * @var array $data
     */
    private $data = array();
    /**
     * @var WP_Post $propierty
     */
    private $propierty;

    public function __construct() {
        add_action( 'rest_api_init', array($this, 'registerRest'));
    }

    public function registerRest() {
        register_rest_route('adt/v2', '/postviviendas/', array(
                'methods' => 'POST',
                'callback' => array($this, 'postProperty')
            )
        );
    }

    public function postProperty(WP_REST_Request $request) {

        $this->data = $request->get_json_params();
        if (!(isset($this->data['agency']['name']) && isset($this->data['id']) && isset($this->data['identifier'])
            && $this->data['agency']['name'] == 'ADT Inmobiliaria')) {
            return new WP_REST_Response(array('error' => 'No llega el nombre de la agencia, id o identificador'), 200);
        }
        $this->proccesProperty();

    }

    private function proccesProperty() {

        $arg = array('post_type' => 'property','meta_key' => 'witei_id','meta_value' => $this->data['identifier']);
        $query = new WP_Query($arg);
        $result = $query->get_posts();
        if (count($result) == 0) {
            $this->createProperty();
        } else {
            $this->propierty = current($result);
            $this->updateProperty();
        }
        echo '<pre>';
        var_dump($result);
        echo '</pre>';
        die();
    }

    private function createProperty() {

        /* Start with basic array */
        $new_property = array(
            'post_type' => 'property',
        );

        /* Title */
        if ( isset( $this->data['title'] ) && ! empty( $this->data['title'] ) ) {
            $new_property['post_title'] = sanitize_text_field( $this->data['title'] );
        }

        /* Description */
        if ( isset( $this->data['description'] ) && ! empty( $this->data['description'] ) ) {
            $new_property['post_content'] = sanitize_text_field( $this->data['description'] );
        }

        /* API user */
        $new_property['post_author'] = 0;


        /* New id */
        $property_id = 0;

        /* Parent Property ID 0 by default */
        $new_property['post_parent'] = 0;

        /* Status post 'pending' by default */
        $new_property['post_status'] = 'pending';

        /*
         * This filter is used to filter the submission arguments of property before inserting it.
         */
        $new_property = apply_filters( 'inspiry_before_property_submit', $new_property );

        // Insert Property and get Property ID.
        $submitted_successfully = false;
        $property_id = wp_insert_post( $new_property );
        if ( $property_id > 0 ) {
            $submitted_successfully = true;
        }

        /**
         * If property is added or updated successfully then move ahead
         */
        if ( $property_id > 0 ) {

            /** Busca y/o creacion de tipo de propiedad */
            /** TODO revisar que devuelve, puede ser un array o un id, mismo en el resto de terminos */
            $new_property['property-type'] = term_exists($this->data['kind']);

            if (!$new_property['property-type']) {
                $new_property['property-type'] = wp_create_term($this->data['kind'], 'property-type');
            }

            /* Attach Property Type with Newly Created Property */
            if ( isset( $this->data['kind'] ) && ( !empty($this->data['kind']) )) {
                wp_set_object_terms( $property_id, intval( $this->data['kind'] ), 'property-type' );
            }

            /* Attach Property City with Newly Created Property */
            $location_select_names = inspiry_get_location_select_names();
            $locations_count       = count( $location_select_names );
            for ( $l = $locations_count - 1; $l >= 0; $l -- ) {
                if ( isset( $_POST[ $location_select_names[ $l ] ] ) ) {
                    $current_location = $_POST[ $location_select_names[ $l ] ];
                    if ( ( ! empty( $current_location ) ) && ( $current_location != inspiry_any_value() ) ) {
                        wp_set_object_terms( $property_id, $current_location, 'property-city' );
                        break;
                    }
                }
            }

            /* Attach Property Status with Newly Created Property */
            if ( isset( $_POST['status'] ) && ( '-1' != $_POST['status'] ) ) {
                wp_set_object_terms( $property_id, intval( $_POST['status'] ), 'property-status' );
            }

            /* Attach Property Features with Newly Created Property */
            if ( isset( $_POST['features'] ) ) {
                if ( ! empty( $_POST['features'] ) && is_array( $_POST['features'] ) ) {
                    $property_features = array();
                    foreach ( $_POST['features'] as $property_feature_id ) {
                        $property_features[] = intval( $property_feature_id );
                    }
                    wp_set_object_terms( $property_id, $property_features, 'property-feature' );
                }
            }

            /* Attach Price Post Meta */
            if ( isset( $_POST['price'] ) && ! empty( $_POST['price'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_price', sanitize_text_field( $_POST['price'] ) );

                if ( isset( $_POST['price-postfix'] ) && ! empty( $_POST['price-postfix'] ) ) {
                    update_post_meta( $property_id, 'REAL_HOMES_property_price_postfix', sanitize_text_field( $_POST['price-postfix'] ) );
                }
            } else {
                delete_post_meta( $property_id, 'REAL_HOMES_property_price' );
            }

            /* Attach Size Post Meta */
            if ( isset( $_POST['size'] ) && ! empty( $_POST['size'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_size', sanitize_text_field( $_POST['size'] ) );

                if ( isset( $_POST['area-postfix'] ) && ! empty( $_POST['area-postfix'] ) ) {
                    update_post_meta( $property_id, 'REAL_HOMES_property_size_postfix', sanitize_text_field( $_POST['area-postfix'] ) );
                }
            } else {
                delete_post_meta( $property_id, 'REAL_HOMES_property_size' );
            }

            /* Attach Bedrooms Post Meta */
            if ( isset( $_POST['bedrooms'] ) && ! empty( $_POST['bedrooms'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_bedrooms', floatval( $_POST['bedrooms'] ) );
            } else {
                delete_post_meta( $property_id, 'REAL_HOMES_property_bedrooms' );
            }

            /* Attach Estado Post Meta */
            if ( isset( $_POST['estado_reforma_id'] ) && ! empty( $_POST['estado_reforma_id'] ) ) {
                update_post_meta( $property_id, 'estado_reforma_id', $_POST['estado_reforma_id'] );
            } else {
                delete_post_meta( $property_id, 'estado_reforma_id' );
            }

            /* Attach Bathrooms Post Meta */
            if ( isset( $_POST['bathrooms'] ) && ! empty( $_POST['bathrooms'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_bathrooms', floatval( $_POST['bathrooms'] ) );
            } else {
                delete_post_meta( $property_id, 'REAL_HOMES_property_bathrooms' );
            }

            /* Attach Garages Post Meta */
            if ( isset( $_POST['garages'] ) && ! empty( $_POST['garages'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_garage', floatval( $_POST['garages'] ) );
            } else {
                delete_post_meta( $property_id, 'REAL_HOMES_property_garage' );
            }

            /* Attach Year Built Post Meta */
            if ( isset( $_POST['year-built'] ) && ! empty( $_POST['year-built'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_year_built', floatval( $_POST['year-built'] ) );
            }

            /* Attach Address Post Meta */
            if ( isset( $_POST['address'] ) && ! empty( $_POST['address'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_address', sanitize_text_field( $_POST['address'] ) );
            }

            /* Attach Address Post Meta */
            if ( isset( $_POST['coordinates'] ) && ! empty( $_POST['coordinates'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_location', $_POST['coordinates'] );
            }

            /* Agent Display Option */
            if ( isset( $_POST['agent_display_option'] ) && ! empty( $_POST['agent_display_option'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_agent_display_option', $_POST['agent_display_option'] );
                if ( ( $_POST['agent_display_option'] == 'agent_info' ) && isset( $_POST['agent_id'] ) ) {
                    delete_post_meta( $property_id, 'REAL_HOMES_agents' );
                    foreach ( $_POST['agent_id'] as $agent_id ) {
                        add_post_meta( $property_id, 'REAL_HOMES_agents', $agent_id );
                    }
                } else {
                    delete_post_meta( $property_id, 'REAL_HOMES_agents' );
                }
            }

            /* Attach Property ID Post Meta */
            if ( isset( $_POST['property-id'] ) && ! empty( $_POST['property-id'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_property_id', sanitize_text_field( $_POST['property-id'] ) );
            } else {
                $auto_property_id    = get_option( 'inspiry_auto_property_id_check' );
                $property_id_pattern = get_option( 'inspiry_auto_property_id_pattern' );
                if ( ! empty( $auto_property_id ) && ( 'true' === $auto_property_id ) && ! empty( $property_id_pattern ) ) {
                    $property_id_value = preg_replace( '/{ID}/', $property_id, $property_id_pattern );
                    update_post_meta( $property_id, 'REAL_HOMES_property_id', sanitize_text_field( $property_id_value ) );
                }
            }

            /* Attach Virtual Tour Video URL Post Meta */
            if ( isset( $_POST['video-url'] ) && ! empty( $_POST['video-url'] ) ) {
                update_post_meta( $property_id, 'REAL_HOMES_tour_video_url', esc_url_raw( $_POST['video-url'] ) );
            }

            /* Attach Message to Reviewer */
            if ( isset( $_POST['message_to_reviewer'] ) && ! empty( $_POST['message_to_reviewer'] ) ) {
                update_post_meta( $property_id, 'inspiry_message_to_reviewer', esc_textarea( $_POST['message_to_reviewer'] ) );
            }

            /* Attach additional details with property */
            if ( isset( $_POST['detail-titles'] ) && isset( $_POST['detail-values'] ) ) {

                $additional_details_titles = $_POST['detail-titles'];
                $additional_details_values = $_POST['detail-values'];

                $titles_count = count( $additional_details_titles );
                $values_count = count( $additional_details_values );

                // to skip empty values on submission
                if ( $titles_count == 1 && $values_count == 1 && empty( $additional_details_titles[0] ) && empty( $additional_details_values[0] ) ) {
                    // do nothing and let it go
                } else {

                    if ( ! empty( $additional_details_titles ) && ! empty( $additional_details_values ) ) {
                        $additional_details = array_combine( $additional_details_titles, $additional_details_values );

                        // remove empty values before adding to database
                        $additional_details = array_filter( $additional_details, 'strlen' );

                        update_post_meta( $property_id, 'REAL_HOMES_additional_details', $additional_details );
                    }
                }
            }

            /* Attach Property as Featured Post Meta */
            $featured = ( isset( $_POST['featured'] ) && ! empty( $_POST['featured'] ) ) ? 1 : 0;
            update_post_meta( $property_id, 'REAL_HOMES_featured', $featured );

            /* Property Submission Terms & Conditions */
            $terms = ( isset( $_POST['terms'] ) && ! empty( $_POST['terms'] ) ) ? 1 : 0;
            update_post_meta( $property_id, 'REAL_HOMES_terms_conditions', $terms );

            /* Tour video image - in case of update */
            $tour_video_image    = '';
            $tour_video_image_id = 0;
            if ( $action == 'update_property' ) {
                $tour_video_image_id = get_post_meta( $property_id, 'REAL_HOMES_tour_video_image', true );
                if ( ! empty( $tour_video_image_id ) ) {
                    $tour_video_image_src = wp_get_attachment_image_src( $tour_video_image_id, 'property-detail-video-image' );
                    $tour_video_image     = $tour_video_image_src[0];
                }
            }

            /* If property is being updated, clean up the old meta information related to images */
            if ( $action == 'update_property' ) {
                delete_post_meta( $property_id, 'REAL_HOMES_property_images' );
                delete_post_meta( $property_id, '_thumbnail_id' );
            }

            /* Attach gallery images with newly created property */
            if ( isset( $_POST['gallery_image_ids'] ) ) {
                if ( ! empty( $_POST['gallery_image_ids'] ) && is_array( $_POST['gallery_image_ids'] ) ) {
                    $gallery_image_ids = array();
                    foreach ( $_POST['gallery_image_ids'] as $gallery_image_id ) {
                        $gallery_image_ids[] = intval( $gallery_image_id );
                        add_post_meta( $property_id, 'REAL_HOMES_property_images', $gallery_image_id );
                    }
                    if ( isset( $_POST['featured_image_id'] ) ) {
                        $featured_image_id = intval( $_POST['featured_image_id'] );
                        if ( in_array( $featured_image_id, $gallery_image_ids ) ) {     // validate featured image id
                            update_post_meta( $property_id, '_thumbnail_id', $featured_image_id );

                            /* if video url is provided but there is no video image then use featured image as video image */
                            if ( empty( $tour_video_image ) && ! empty( $_POST['video-url'] ) ) {
                                update_post_meta( $property_id, 'REAL_HOMES_tour_video_image', $featured_image_id );
                            }
                        }
                    } elseif ( ! empty( $gallery_image_ids ) ) {
                        update_post_meta( $property_id, '_thumbnail_id', $gallery_image_ids[0] );

                        /* if video url is provided but there is no video image then use featured image as video image */
                        if ( empty( $tour_video_image ) && ! empty( $_POST['video-url'] ) ) {
                            update_post_meta( $property_id, 'REAL_HOMES_tour_video_image', $gallery_image_ids[0] );
                        }
                    }
                }
            }

            /* Attach Propietario Post Meta */
            if ( isset( $_POST['inspiry_property_email'] ) && ! empty( $_POST['inspiry_property_email'] ) ) {
                $email = $_POST['inspiry_property_email'];
                $myquery = new WP_Query( "post_type=contact&meta_key=recrm_contact_email&meta_value=". strtolower(trim($email))."&order=ASC&limit=1" );
                $post = array();
                $post = $myquery->get_posts();
                if (count($post) == 0) {
                    $data = array (
                        'ID' => 0,
                        'post_title' => $_POST['inspiry_property_first_name'] . ' ' . $_POST['inspiry_property_last_name'] . ' ' . $_POST['inspiry_property_email'] . ' ' . $_POST['inspiry_property_mobile'],
                        'post_status' => 'publish',
                        'post_type' => 'contact',
                        'meta_input' => array (
                            'recrm_contact_status' => ' Vendor',
                            'recrm_contact_first_name' => $_POST['inspiry_property_first_name'],
                            'recrm_contact_last_name' => $_POST['inspiry_property_last_name'],
                            'recrm_contact_email' => $_POST['inspiry_property_email'],
                            'recrm_contact_mobile' => $_POST['inspiry_property_mobile']
                        )
                    );
                    $postid = wp_insert_post($data);
                    update_post_meta( $property_id, 'propietario_id', $postid );
                } else {
                    update_post_meta( $property_id, 'propietario_id', $post[0]->ID );
                }
            }


            if ( 'add_property' == $_POST['action'] ) {
                /*
                 * inspiry_submit_notice function in property-submit-handler.php is hooked with this hook
                 */
                do_action( 'inspiry_after_property_submit', $property_id );

            } elseif ( 'update_property' == $_POST['action'] ) {
                /*
                 * no default theme function is hooked with this hook
                 */
                do_action( 'inspiry_after_property_update', $property_id );

            }

            /* Redirect to my properties page */
            $my_properties_url = inspiry_get_my_properties_url();
            if ( ! empty( $my_properties_url ) ) {
                $separator = ( parse_url( $my_properties_url, PHP_URL_QUERY ) == null ) ? '?' : '&';
                $parameter = ( $updated_successfully ) ? 'property-updated=true' : 'property-added=true';
                wp_redirect( $my_properties_url . $separator . $parameter );
            }
        }
    }

    private function updateProperty() {

        /* Continua con lo anterior */

        $new_property['ID'] = intval( $_POST['property_id'] );

        /*
         * This filter is used to filter the submission arguments of property before update
         */
        $new_property = apply_filters( 'inspiry_before_property_update', $new_property );

        // Update Property and get Property ID.
        $property_id = wp_update_post( $new_property );
        if ( $property_id > 0 ) {
            $updated_successfully = true;
        }

    }



}