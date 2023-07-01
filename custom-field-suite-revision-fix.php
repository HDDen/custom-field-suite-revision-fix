<?php
/*
Plugin Name: Custom field suite revision fix
Author: HDDen
Plugin URI: https://github.com/HDDen/custom-field-suite-revision-fix
Description: Fix revision and preview issue for Custom Field Suite Plugin
Version: 0.1.0
Author URI: https://github.com/HDDen/
*/


$cfs_fix = new Custom_Field_Suite_Fix();

class Custom_Field_Suite_Fix {

function __construct()
{
    add_action('plugins_loaded', array($this, 'plugins_loaded'));
}

public function plugins_loaded()
{
    add_action( 'save_post', array($this, 'save_post'), 10, 2 );
    add_action( 'wp_restore_post_revision', array($this, 'restore_post_revision'), 10, 2 );
    add_filter( '_wp_post_revision_fields', array($this, 'wp_post_revision_fields') );
    add_filter( '_wp_post_revision_field_my_meta', array($this, 'wp_post_revision_field_my_meta'), 10, 2 );
}

public function save_post($post_id, $post){
    $parent_id = wp_is_post_revision( $post_id );

	if ( $parent_id ) {

		$parent  = get_post( $parent_id );
		$my_meta = get_post_meta( $parent->ID, '_wp_page_template', true );

		if ( false !== $my_meta )
			add_metadata( 'post', $post_id, '_wp_page_template', $my_meta );

	}
}

public function wp_post_revision_fields($fields){
    $fields['_wp_page_template'] = 'Шаблон страницы';
	return $fields;
}

public function wp_post_revision_field_my_meta($value, $field){
    global $revision;
	return get_metadata( 'post', $revision->ID, $field, true );
}

public function restore_post_revision($post_ID, $revision_id){

    global $wpdb;

    $wpdb->query($wpdb->prepare(
        "DELETE FROM $wpdb->postmeta WHERE post_id = %d",
        $post_ID
    ));

    // восстановление шаблона
    $post     = get_post( $post_ID );
	$revision = get_post( $revision_id );
	$my_meta  = get_metadata( 'post', $revision->ID, '_wp_page_template', true );

	if ( false !== $my_meta )
		update_post_meta( $post_ID, '_wp_page_template', $my_meta );
	else
		delete_post_meta( $post_ID, '_wp_page_template' );


    // кастомные поля
    $src_fields = CFS()->find_fields(array(
        'post_id' => $revision_id,
    ));
    $post_meta = get_post_meta($revision_id);
    
    $result_fields = array();

    // делаем прогон, основываясь на post_meta
    foreach ($post_meta as $post_meta_name => $post_meta_values){

        // нужно понять, принадлежит поле к циклу или простому значению
        $post_meta_forLoop = false;
        $post_meta_forLoopName = '';

        foreach ($src_fields as $src_field_info) {
            if (isset($src_field_info['name']) && ($post_meta_name === $src_field_info['name'])){
                // нашли поле, проверяем, установлен ли родитель
                if (isset($src_field_info['parent_id']) && $src_field_info['parent_id']){
                    $post_meta_forLoop = $src_field_info['parent_id'];
                    break;
                }
            }
        }

        // проверку провели, нашли id родителя. по id нужно найти теперь его и его имя
        if ($post_meta_forLoop){
            foreach ($src_fields as $src_field_info) {
                if (isset($src_field_info['id']) && ($post_meta_forLoop === (int)$src_field_info['id'])){
                    // нашли поле, изымаем name
                    if (isset($src_field_info['name'])){
                        $post_meta_forLoopName = $src_field_info['name'];
                        break;
                    }
                }
            }
        }

        // имя получили, теперь нужно добавить его элемент в результирующий массив
        if ($post_meta_forLoopName){
            
            $temp_array = array();

            // разбиваем значения по отдельным массивам
            foreach ($post_meta_values as $post_meta_value){
                $temp_array[] = array(
                    $post_meta_name => $post_meta_value,
                );
            }

            // либо переопределяем, либо объединяем
            if (!isset($result_fields[$post_meta_forLoopName])){
                $result_fields[$post_meta_forLoopName] = $temp_array;
            } else {
                $result_fields[$post_meta_forLoopName] = array_replace_recursive($result_fields[$post_meta_forLoopName], $temp_array);
            }
        } else {
            // простое поле
            $result_fields[$post_meta_name] = array_reverse($post_meta_values)[0];
        }
        
    }
    
    CFS()->save( $result_fields, array('ID' => $post_ID) );
}

}

// EOF
