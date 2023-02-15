<?php
/*
Plugin Name: 1000 landings
Description: Vamos a crear 1000 landings al activar el plugin
Versión: 1.0
Author: ablancodev
*/
// Usamos el hook de activación del plugin
register_activation_hook( __FILE__, 'ablancodev_duplicate_post_as_draft' );

function ablancodev_duplicate_post_as_draft(){
  global $wpdb;
 
  $post_id = 212078;  // aquí va el id de la página a duplicar

  $post = get_post( $post_id );
 
  $current_user = wp_get_current_user();
  $new_post_author = $current_user->ID;
 
  if (isset( $post ) && $post != null) {

  	// Llamamos a nuestra función que nos consigue los datos de ChatGPT
  	$datos = ablancodev_get_data_ai();
  	
  	foreach ( $datos as $dato ) {

  		$content = $post->post_content;
  		$content = str_replace('{{descripcion}}', $dato['description'], $content);
   		$content = str_replace('{{receta}}', $dato['recipe'], $content);
   
      /*
       * new post data array
       */
      $args = array(
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'post_author'    => $new_post_author,
        'post_content'   => $content,
        'post_excerpt'   => $post->post_excerpt,
        'post_name'      => $post->post_name,
        'post_parent'    => $post->post_parent,
        'post_password'  => $post->post_password,
        'post_status'    => 'draft',
        'post_title'     => $dato['title'],
        'post_type'      => $post->post_type,
        'to_ping'        => $post->to_ping,
        'menu_order'     => $post->menu_order
      );
   
      /*
       * insert the post by wp_insert_post() function
       */
      $new_post_id = wp_insert_post( $args );
    }
  }
}



function ablancodev_get_data_ai() {

  $results = array();

$nombres_productos = ablancodev_mercadona_api();

$cnt = 1;

foreach ( $nombres_productos as $nombre_producto ) {
  if ( $cnt < 5 ) { // Este es un límite que hemos puesto para que no consumas más de 2800 productos que tiene mercadona en dobles llamadas a la API de chatGPT

    $url = 'https://api.openai.com/v1/completions';
    $curl = curl_init();
    $fields = array(
        'model' => 'text-davinci-003',
        'prompt' => 'Dame un claim para vender el producto ' . $nombre_producto,
        'max_tokens' => 999,
        'temperature' => 0
    );
    $json_string = json_encode($fields);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json_string);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer TU_API_KEY_DE_OPENAI'));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
    $data = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($data);
    
    if ( isset($data->choices) ) {
      $descripcion = $data->choices[0]->text;

      // Sólo si tenemos descripción preguntamos receta
      $curl = curl_init();
      $fields = array(
          'model' => 'text-davinci-003',
          'prompt' => 'Dame una receta que tenga como ingrediente ' . $nombre_producto,
          'max_tokens' => 999,
          'temperature' => 0,
      );
      $json_string = json_encode($fields);
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_POST, TRUE);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $json_string);
      curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer TU_API_KEY_DE_OPENAI'));
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
      $data = curl_exec($curl);
      curl_close($curl);

      $data = json_decode($data);
      if ( isset($data->choices) ) {
        $receta = str_replace("\n", "<br>", $data->choices[0]->text);
      }
    }

    $results[] = array(
      'title' => $nombre_producto,
      'description' => $descripcion,
      'recipe' => $receta
    );
  
  }

  $cnt++;
 }
 return $results;

}

// API Mercadona

function ablancodev_mercadona_api() {
  $datos = array();

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://tienda.mercadona.es/api/categories/'); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
  curl_setopt($ch, CURLOPT_HEADER, 0); 
  $data = curl_exec($ch); 
  curl_close($ch); 

  if ( $data ) {
     $categorias = json_decode($data);
     
     if ( isset($categorias->results) ) {
        foreach ( $categorias->results as $category ) {
           // Llamamos a dicha categoría para ver si tiene más niveles
           ablancodev_get_category($category->id, $datos);
        }
     }
  }
  return $datos;
}

function ablancodev_get_category( $category_id, &$datos ) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://tienda.mercadona.es/api/categories/' . $category_id); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
  curl_setopt($ch, CURLOPT_HEADER, 0); 
  $data = curl_exec($ch); 
  curl_close($ch); 
  if ( $data ) {
     $category = json_decode($data);
     if ( isset($category->categories) ) {
        foreach ( $category->categories as $cat_info ) {
           if ( isset($cat_info->products) ) {
              foreach ( $cat_info->products as $product ) {
                 $datos[] = $product->display_name;
              }
           }

           // Llamamos a dicha categoría para ver si tiene más niveles
           ablancodev_get_category($cat_info->id, $datos);
        }
     }
  }
}
?>