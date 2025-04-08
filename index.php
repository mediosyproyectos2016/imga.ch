<?php
// Función para obtener el hash SHA1 de una URL
function get_sha1_hash($url) {
    return sha1($url);
}

// Capturar la URL solicitada por el usuario desde la ruta del servidor
$requestUri = $_SERVER['REQUEST_URI'];
 
global $dominio;
global $timestamp ;
global $debug;
global $url_mapping;
 
$url_mapping = json_decode(file_get_contents(__DIR__ . '/url_mapping.json'), true);
if(!$url_mapping) $url_mapping = array();
$debug = false;
$dominio = "imga.ch";
$requestedUrl = $dominio .$requestUri;
$timestamp = "20250324024536";
 
include_once "post.php";

// Si la URL solicitada está vacía, redirigir a index.html
if (empty($requestedUrl)) {
    header("Location: /index.html");
    exit;
}

$savePath = __DIR__ . '/scrappe';
$dir = dirname($savePath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
// Generar el nombre del archivo basado en el hash SHA1 de la URL
$filename = 'scrappe/sha1' . get_sha1_hash($requestedUrl) . '.html';

// Verificar si el archivo ya existe
if (file_exists($filename)) {
    // Si el archivo existe, devolver su contenido
    $archivedHtml =  file_get_contents($filename);

    $archivedHtml = cleanHtml($archivedHtml );
    $archivedHtml = preg_replace('/<\/body>/i',"<!-- ". $filename . " -->\n</body>", $archivedHtml);
    echo $archivedHtml ;
    exit;
}
 
// URL de la API de Wayback Machine para verificar si la URL está archivada
if(array_key_exists($requestUri,$url_mapping)){
    $waybackUrl = $url_mapping[$requestUri] ;
    $response = file_get_contents($waybackUrl);
    if ($response !== FALSE) {
        
        // Guardar el HTML en el archivo generado con el hash de la URL
        file_put_contents($filename, $response);
        $response = cleanHtml($response );
        $response = preg_replace('/<\/body>/i',"<!-- ". $filename . " -->\n</body>", $response);
        // Mostrar el HTML archivado
        echo $response;
    } else {
        // Redirigir a index.html si no se puede obtener el HTML
        header("Location: /index.html");
        exit;
    }
    $html = file_get_contents($waybackUrl);
    if(!$html){
        echo $filename ;
    }
}else{
    $waybackUrl = "http://archive.org/wayback/available?url=" . urlencode($requestedUrl)  ;
    
    // Hacer la petición a la API de Wayback Machine
    $response = file_get_contents($waybackUrl);
    

    // Verificar si la respuesta se obtuvo correctamente
    if ($response === FALSE) {
        // URL de la API de Wayback Machine para verificar si la URL está archivada
        $waybackUrl = "http://archive.org/wayback/available?url=" . urlencode($requestedUrl)."&timestamp=".$timestamp ;
        
        // Hacer la petición a la API de Wayback Machine
        $response = file_get_contents($waybackUrl);

        
        // Verificar si la respuesta se obtuvo correctamente
        if ($response === FALSE) {
            
            // Redirigir a index.html si no se puede obtener la respuesta
            header("Location: /index.html");
            exit;
        }

    
    }

    // Decodificar la respuesta JSON
    $data = json_decode($response, true);

    // Verificar si hay una versión archivada disponible
    if (isset($data['archived_snapshots']['closest']['available']) && $data['archived_snapshots']['closest']['available']) {
        // Obtener la URL del snapshot archivado
        $archivedUrl = $data['archived_snapshots']['closest']['url'];

        // Obtener el contenido HTML de la URL archivada
        $archivedHtml = file_get_contents($archivedUrl);

        // Verificar si se obtuvo el HTML correctamente
        if ($archivedHtml !== FALSE) {
        
            // Guardar el HTML en el archivo generado con el hash de la URL
            file_put_contents($filename, $archivedHtml);
            $archivedHtml = cleanHtml($archivedHtml );
            $archivedHtml = preg_replace('/<\/body>/i',"<!-- ". $filename . " -->\n</body>", $archivedHtml);
            // Mostrar el HTML archivado
            echo $archivedHtml;
        } else {
            // Redirigir a index.html si no se puede obtener el HTML
            header("Location: /index.html");
            exit;
        }
    } else {
        // Redirigir a index.html si no se encuentra una versión archivada
        header("Location: /index.html");
        exit;
    }

}



// Función para limpiar el HTML y actualizar los enlaces
function cleanHtml($html) {
    global $url_mapping;
    // 1. Eliminar el contenido dentro del <head> hasta el <!-- End Wayback Rewrite JS Include -->
    $html = preg_replace('/<head>.*?<!-- End Wayback Rewrite JS Include -->/is', '<head>', $html);
    // 2. Expresión regular para encontrar y eliminar todo el contenido entre comentarios HTML <!-- ... -->
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    // 3. Eliminamos divs
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);   
    $doc->loadHTML($html);
    $idsToRemove = ['donato', 'wm-ipp-inside', 'wm-ipp-print','wm-ipp-base'];  
    $xpath = new DOMXPath($doc);
    foreach ($idsToRemove as $id) {
        $nodes = $xpath->query('//div[@id="' . $id . '"]');
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }
    $html = $doc->saveHTML();

     // 4. Eliminamos __wm
     $html = preg_replace_callback(
        '/<script\b[^>]*>(.*?)<\/script>/is',
        function ($matches) {
            // Si contiene "__wm" en el contenido del script, lo eliminamos
            return (strpos($matches[1], '__wm') !== false) ? '' : $matches[0];
        },
        $html
    );



    $html = convert($html);

  

    file_put_contents(__DIR__ . '/url_mapping.json', json_encode($url_mapping, JSON_PRETTY_PRINT));

    return $html;
}

function convert($html ) {
    global $dominio;
    $html =   downloadAndReplaceCloudfront($html);
    $html =   downloadAndReplaceAssets($html);    
    $html =   replaceThirdParties($html);
    $html =   downloadAndReplaceJavascript($html);
    return  $html ; 
}
function replaceThirdParties($html) {
    global $dominio;
    global $debug;
    if($debug) echo "replaceThirdParties".PHP_EOL;
    $pattern = '/https?:\/\/[^\s"\'<>]*archive[^\s"\'<>]*|\/\/[^\s"\'<>]*archive[^\s"\'<>]*/i';
    preg_match_all($pattern, $html, $matches);
    $matches = array_filter($matches[0], function($url) use ($dominio) {
        return strpos($url, $dominio) === false;
    });
     
    foreach ($matches as $url) {
        $pos = strpos($url, '/http');
        $relativeUrl = substr($url, $pos + 1  );
        $html = str_replace($url, $relativeUrl, $html);
    } 
    return  $html;
}
function downloadAndReplaceCloudfront($html) {
    global $dominio;
    global $debug;
    if($debug) echo "downloadAndReplaceCloudfront".PHP_EOL;

    $savePath = __DIR__ . '/cloudfront';
    $dir = dirname($savePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
      // Ahora también procesamos las URLs que provienen de CloudFront
    // Expresión regular para CloudFront con subdominio dinámico
    $pattern = "/http:\/\/web\.archive\.org\/[^\s\"']*cloudfront\.net[^\s)\"']*/i";
    preg_match_all($pattern, $html, $cloudfrontMatches);

    $urls = $cloudfrontMatches[0];
  if( $debug)  print_r( $urls );
    foreach ($urls as $url) {
        // Extraer la parte relativa después de .cloudfront.net/
        $pos = strpos($url, 'cloudfront.net/');
        $relativePath = substr($url, $pos + strlen('cloudfront.net/'));
        $localPathForHtml =  '/cloudfront/' . $relativePath;
        // Ruta absoluta de guardado
        $savePath = __DIR__ . $localPathForHtml;

        if (file_exists($savePath)) {
            if( $debug)     echo "🟡 Ya existe: $savePath, se omite.\n";
        
        }else{
    // Crear directorios si no existen
            $dir = dirname($savePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            // Descargar contenido y guardarlo
            $content = @file_get_contents($url);
            if ($content !== false) {
            
                if (strpos($content, 'archive.org') !== false ) {
                    $extensions = ['.pdf', '.docx', '.pptx', '.xlsx', '.zip']; 
                    $pattern = '/(' . implode('|', array_map(function($ext) { return preg_quote($ext, '/'); }, $extensions)) . ')$/i';
                    if (preg_match($pattern, $url)) {
                        if (preg_match('/<iframe[^>]*id="playback"[^>]*src="([^"]+)"/i', $content, $matches)) {
                            // Si se encuentra el iframe, extraer la URL del iframe
                            $iframeUrl = $matches[1];
                            
                            // Descargar el contenido de la URL del iframe
                            $iframeContent = file_get_contents($iframeUrl);
                            
                            // Guardar el contenido del iframe en el archivo
                            if ($iframeContent !== false) {
                                file_put_contents($savePath, $iframeContent);
                                if ($debug) {
                                    echo "✅ Guardado el contenido del iframe en: $savePath\n";
                                }
                            } else {
                                if ($debug) {
                                    echo "❌ No se pudo descargar el contenido del iframe: $iframeUrl\n";
                                }
                            }
                        } else {
                            if ($debug) {
                                echo "⛔ No se encontró el iframe con id='playback' en el contenido del PDF.\n";
                            }
                        }
                    }else{
                       if( $debug)       echo "⛔ Contiene 'archive.org'. No se guarda: $url\n";
                        file_put_contents(__DIR__ . '/review.log',$url.PHP_EOL, FILE_APPEND);
                        continue; 
                    }
                    
                } else{
                    file_put_contents($savePath, $content);
                    if( $debug)     echo "✅ Guardado: $savePath\n";
                }
                
            } else {
                if( $debug)    echo "❌ No se pudo descargar: $url\n";
                continue;
            }            
        }

     
        $html = str_replace($url, $localPathForHtml, $html);
    } 

  
    return $html;
}

 
function downloadAndReplaceAssets($html) {
    global $dominio;
    global $debug;
    global $url_mapping;
    if($debug) echo "downloadAndReplaceAssets".PHP_EOL;
    // Expresión regular para encontrar cualquier URL de Wayback Machine con el dominio
    $pattern = '/https?:\/\/[^\s"\'<>]*archive[^\s"\'<>]*|\/\/[^\s"\'<>]*archive[^\s"\'<>]*|\/web\/[^\s"\'<>]*/i';
    preg_match_all($pattern, $html, $matches);
 
    $matches = array_filter($matches[0], function($url) use ($dominio) {
        return strpos($url, $dominio) !== false;
    });
 
 
    if( $debug) print_r($matches);

    foreach ($matches as $index => $originalUrl) {
        if( $debug) print_r($index );
        $pos = strpos($originalUrl, '/http');
        $relativeUrl = substr($originalUrl, $pos + 1  );
        if( $debug) print_r($relativeUrl);
        $parsedUrl = parse_url($relativeUrl);
        $savePath = isset($parsedUrl['path']) ? $parsedUrl['path'] : '/'; 
        $localPath = __DIR__ .  $savePath; // Ruta absoluta del archivo 
        if ($savePath == "/" || substr($originalUrl, -1) === '/' || strpos($originalUrl, '#') !== false) {         
            if( $debug)    echo "La URL termina con /, procediendo con el siguiente paso.\n"; 
            $url_mapping[$savePath] = $originalUrl;
        }  else{
            if( $debug) print_r($parsedUrl);
            if( $debug) print_r( $savePath);
            if( $debug) print_r($localPath);  
            // Si el archivo ya existe, no hacer nada
            if (file_exists($localPath)) {
                if( $debug)     echo "🟡 Ya existe: $localPath, se omite.\n"; 
            }else{
                // Crear directorios si no existen
                $dirPath = dirname($localPath);
                if (!is_dir($dirPath)) {
                    mkdir($dirPath, 0777, true);
                } 
                $archiveUrl = $parsedUrl['path']; 
                if (strpos($archiveUrl, 'mailto:') !== 0) {  
                   
                    $savePath =  substr($archiveUrl, strpos($archiveUrl, 'mailto:')  );
                    if( $debug)       echo  "⛔ Contiene 'mailto:' ".$savePath ."\n";
                }else if (strpos($originalUrl, '/web/') === 0) {  
                    file_put_contents(__DIR__ . '/review.log','https://web.archive.org' . $originalUrl.PHP_EOL, FILE_APPEND); 
                }else{
                    $fileContent = file_get_contents($originalUrl);
                    if ($fileContent !== false) {
                        if (strpos($fileContent, '<html') !== false) {
                            if( $debug)       echo "⛔ Contiene 'html'. No se guarda  pero se reemplaza\n";
                             
                        }else{
                            $fileContent = convert($fileContent);
                            file_put_contents($localPath, $fileContent);
                        }   
                    } else {
                        if( $debug)    echo "❌ No se pudo descargar: $originalUrl\n";
                        continue;
                    }    
                }
               
               
              
            }
        }
        //if( $debug)    echo "Reemplazamos  $originalUrl por  $savePath\n";
        $html = str_replace($originalUrl, $savePath, $html);
        
      }
 



    return $html;
}
function downloadAndReplaceJavascript($html) {
    global $dominio;
    global $debug;
    global $url_mapping;
    if($debug) echo "downloadAndReplaceJavascript".PHP_EOL;
    // 1. Buscar URLs normales y escapadas
    $pattern = '/(https?:\\\\\/\\\\\/[^\s"\']*' . preg_quote($dominio, '/') . '[^\s"\']*|https?:\/\/[^\s"\']*' . preg_quote($dominio, '/') . '[^\s"\']*|\/web\/[^\s"\']*)/i';
    preg_match_all($pattern, $html, $matches);

    // Aplanar y limpiar
    $urls = array_filter(array_unique(array_merge(...$matches)));

    if ($debug) print_r($urls);

    foreach ($urls as $originalUrl) {
        $pos = strpos($originalUrl, '/http');
        $relativeUrl = substr($originalUrl, $pos + 1  );
        

        $html = str_replace($originalUrl, $relativeUrl, $html);
    }

    
      // 5. Eliminamos chameleon
      $injection = <<<JS
      <script>
      setTimeout(function() {
          // Si la paginación ya fue inicializada, eliminamos sus eventos
          if (window.chameleon && window.chameleon.pagination) {
              if (window.chameleon.pagination.pagination) {
              window.chameleon.pagination.pagination.off("click");
              }
  
              // Redefinimos la función init para que no haga nada
              window.chameleon.pagination.init = function() {
              console.log("🔇 Chameleon pagination init desactivado.");
              return this;
              };
  
              // Eliminamos también cualquier listener que pueda haber quedado
              window.onpopstate = null;
  
              console.log("✅ Chameleon pagination desactivada.");
          }
          }, 1000); // Esperamos a que el script original se haya ejecutado
      </script>
      JS;
      $html = preg_replace('/<\/body>/i', $injection . "\n</body>", $html);

    return $html;
}

 
?>
