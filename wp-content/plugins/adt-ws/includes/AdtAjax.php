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
        die('entra mal');
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

            if ( isset( $this->data['kind'] ) && ( !empty($this->data['kind']) )) {
                $new_property['property-type'] = $this->findorCreateTerm($property_id, $this->data['kind'], 'property-type');
            }

            if ( isset( $this->data['town'] ) && ( !empty($this->data['town']) )) {
                $new_property['property-city'] = $this->findorCreateTerm($property_id, $this->data['town'], 'property-city');
            }

            if ( isset( $this->data['status'] ) && ( !empty($this->data['status']) )) {
                $new_property['property-status'] = $this->findorCreateTerm($property_id, $this->data['status'], 'property-status');
            }

            if ( isset( $this->data['tags'] ) && ( !empty($this->data['tags']) )) {
                $property_features = array();
                foreach ($this->data['tags'] as $tag) {
                    $termid = term_exists($tag);
                    if (!$termid) {
                        $termid = wp_create_term($tag, 'property-feature');
                    }
                    $property_features[] = $termid;
                }
                wp_set_object_terms( $property_id, $property_features, 'property-feature' );
            }

            /* Attach Price Post Meta */
            if ( isset( $this->data['selling_cost'] ) && ( $this->data['selling_cost']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_price', $this->data['selling_cost']);
            } elseif ( isset( $this->data['renting_cost'] ) && ( $this->data['renting_cost']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_price', $this->data['renting_cost']);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_price');
            }

            /* Attach Size Post Meta */
            if ( isset( $this->data['area'] ) && ( $this->data['area']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_size', $this->data['area']);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_size');
            }

            /* Attach Bedrooms Post Meta */
            if ( isset( $this->data['bedrooms'] ) && ( $this->data['bedrooms']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_bedrooms', $this->data['bedrooms']);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_bedrooms');
            }

            /* Attach Bathrooms Post Meta */
            if ( isset( $this->data['bathrooms'] ) && ( $this->data['bathrooms']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_bathrooms', $this->data['bathrooms']);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_bathrooms');
            }

            /* Attach Year Post Meta */
            if ( isset( $this->data['year_built'] ) && ( $this->data['year_built']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_year_built', $this->data['year_built']);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_year_built');
            }

            /* Attach Address Post Meta */
            if ( isset( $this->data['street'] ) && ( $this->data['street']) ) {
                $address = $this->data['street'] . ' '. $this->data['street_number'] . ' ' . $this->data['town'] .
                    $this->data['zip_code'] . ' ' . $this->data['province'];
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_address', $address);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_address');
            }

            /* Attach Address Post Meta */
            if (( isset( $this->data['geo_lat'] ) && ( $this->data['geo_lat']) ) &&
                    ( isset( $this->data['geo_lng'] ) && ( $this->data['geo_lng']) ) ){
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_location',
                    $this->data['geo_lat'] . ',' . $this->data['geo_lng'] );
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_location');
            }

            /** TODO comprobar como insertar los agentes */
            /* Agent Display Option
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
            }*/

            /* Attach Property ID Post Meta */
            $this->updateorDeleteMeta($property_id, 'REAL_HOMES_property_id', $this->data['identifier']);
            $this->updateorDeleteMeta($property_id, 'witei_id', $this->data['identifier']);

            /* Attach Virtual Tour Video URL Post Meta */
            if ( isset( $this->data['video_url'] ) && ( $this->data['video_url']) ) {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_tour_video_url', $this->data['video_url']);
            } else {
                $this->updateorDeleteMeta($property_id, 'REAL_HOMES_tour_video_url');
            }

            /** TODO ver como añadir los datos extra */
            /* Attach additional details with property
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
            }  */

            /* Attach gallery images with newly created property */
            $imageids = $this->setImages();

            if ( ! empty( $imageids )  ) {
                foreach ( $imageids as $gallery_image_id ) {
                    $gallery_image_ids[] = intval( $gallery_image_id );
                    add_post_meta( $property_id, 'REAL_HOMES_property_images', $gallery_image_id );
                }
                update_post_meta( $property_id, '_thumbnail_id', $gallery_image_ids[0] );
            }

            /* Attach Propietario Post Meta */
            if ( isset( $this->data['owner']['email'] ) && ! empty( $this->data['owner']['email'] ) ) {
                $email = $this->data['owner']['email'];
                $myquery = new WP_Query( "post_type=contact&meta_key=recrm_contact_email&meta_value=". strtolower(trim($email))."&order=ASC&limit=1" );
                $post = array();
                $post = $myquery->get_posts();
                if (count($post) == 0) {
                    $data = array (
                        'ID' => 0,
                        'post_title' => $this->data['owner']['name'] . ' ' . $this->data['owner']['email'] . ' ' . $this->data['owner']['phone'],
                        'post_status' => 'publish',
                        'post_type' => 'contact',
                        'meta_input' => array (
                            'recrm_contact_status' => ' Vendor',
                            'recrm_contact_first_name' => $this->data['owner']['name'],
                            'recrm_contact_last_name' => '',
                            'recrm_contact_email' => $this->data['owner']['email'],
                            'recrm_contact_mobile' => $this->data['owner']['phone']
                        )
                    );
                    $postid = wp_insert_post($data);
                    update_post_meta( $property_id, 'propietario_id', $postid );
                } else {
                    update_post_meta( $property_id, 'propietario_id', $post[0]->ID );
                }
            }

            do_action( 'inspiry_after_property_submit', $property_id );

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

        delete_post_meta( $property_id, 'REAL_HOMES_property_images' );
        delete_post_meta( $property_id, '_thumbnail_id' );

        do_action( 'inspiry_after_property_update', $property_id );

    }

    private function findorCreateTerm($id, $term, $taxname = 'post_tag') {

        $termid = term_exists($term);

        if (!$termid) {
            $termid = wp_insert_term($term, $taxname);
            $termid = $termid['term_id'];
        }

        wp_set_object_terms( $id, $termid, $taxname );

    }

    private function updateorDeleteMeta($id, $metakey, $value = false) {

        if ($value) {
            update_post_meta( $id, $metakey, $value );
        } else {
            delete_post_meta( $id, $metakey );
        }

    }

    private function setImages() {

        // WordPress environment
        require(dirname(__FILE__) . '/../../../../wp-load.php');
        $upload_ids = array();

        foreach ($this->data['pictures'] as $urlimage) {


            $wordpress_upload_dir = wp_upload_dir();

            $filename = time() .'jpg';

            $new_file_path = $wordpress_upload_dir['path'] . '/' . $filename;

            file_put_contents($new_file_path, file_get_contents($urlimage));

// looks like everything is OK
            if (file_exists($new_file_path)) {


                $upload_ids[] = wp_insert_attachment(array(
                    'guid' => $new_file_path,
                    'post_mime_type' => mime_content_type($filename),
                    'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                    'post_content' => '',
                    'post_status' => 'inherit'
                ), $new_file_path);

                // wp_generate_attachment_metadata() won't work if you do not include this file
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                // Generate and save the attachment metas into the database
                wp_update_attachment_metadata($upload_id, wp_generate_attachment_metadata($upload_id, $new_file_path));

                // Show the uploaded file in browser
                wp_redirect($wordpress_upload_dir['url'] . '/' . basename($new_file_path));

            }
        }

        return $upload_ids;
    }


}