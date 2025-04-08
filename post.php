<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtiene la URL completa (sin query strings, si no la tienes puedes usar $_SERVER['REQUEST_URI'])
    $url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    // Genera el SHA1 de la URL
    $sha1Url = sha1($url);
    
    // Carpeta donde guardaremos los POSTs
    $postDirectory = __DIR__ . '/post/' . $sha1Url;
    
    // Crea la carpeta si no existe
    if (!is_dir($postDirectory)) {
        mkdir($postDirectory, 0777, true);
    }
    
    // Obtener la fecha actual para el nombre del archivo JSON
    $date = date('Y-m-d_H-i-s');
    
    // El archivo JSON con los datos del POST
    $jsonFile = $postDirectory . '/' . $date ."_".uniqid(). '.json';
    
    // Datos del POST (puedes filtrar solo los datos que quieras guardar)
    $postData = [
        'form' => $_SERVER['REQUEST_URI'],
        'date' => $date,
        'post_data' => $_POST,  // Aquí puedes modificar si solo te interesa ciertos campos
    ];
    
    // Guarda el contenido del POST en el archivo JSON
    file_put_contents($jsonFile, json_encode($postData, JSON_PRETTY_PRINT));
    
    $refererUrl = $_SERVER['HTTP_REFERER'];
    if ($refererUrl) {
        header("Location: " . $refererUrl);
        exit;
    }else{
        echo "THANK YOU";
    }
}
?>
