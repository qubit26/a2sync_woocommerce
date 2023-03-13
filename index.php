<?php
date_default_timezone_set("America/Caracas");
require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

// header('Content-type: text/plain; charset=utf-8');

// Directorio del archivo .csv
$dir = "a2sync/";

// Directorios de los logs
$log_dir = "logs/";

// archivo origen
$origen = scandir($dir, 1);


// Conexión WooCommerce API destino
// ================================
$url_API_woo = 'https://ascanio.digital/beta/';
$ck_API_woo = 'ck_65fd38bfe7629136e7f1ef5115a990d7bfceb719';
$cs_API_woo = 'cs_bc5a109c073ee0bd40d02b109780f55970e547ee';

$woocommerce = new Client(
    $url_API_woo,
    $ck_API_woo,
    $cs_API_woo,
    ['version' => 'wc/v3']
);
// ================================

// Creación del archivo de los logs
$log_file = fopen($log_dir . date('Y-m-d') . ' ' . date('h.i.sa') . '.txt', "a");



fwrite($log_file, "SINCRONIZACIÓN ENTRE A2 y WOOCOMMERCE \n");
fwrite($log_file, "[ " . date('Y/m/d') . " " . date('h:i:sa') . " ] Iniciando Sincronización (timestamp de la hora actual de Venezuela) \n \n");
echo "SINCRONIZACIÓN ENTRE A2 y WOOCOMMERCE \n";
echo "[ " . date('Y/m/d') . " " . date('h:i:sa') . " ] Iniciando Sincronización (timestamp de la hora actual de Venezuela) \n \n";


// Obtenemos los datos del origen
$items_origin = '';

// Convertir csv en json
// ==================================
 
// abrimos csv
if (!($fp = fopen('a2sync/' . $origen[0], 'r'))) {
    fwrite($log_file,"No se pudo abrir el archivo...");
    die("No se pudo abrir el archivo...");
}

fwrite($log_file, "Leyendo el archivo " . $origen[0] . "\n \n");
echo "Leyendo el archivo " . $origen[0] . "\n \n";


//asignamos cabeceras csv
$key = fgetcsv($fp,"0",";");
     
// recorremos csv y guardamos en un array
$json = array();

while ($row = fgetcsv($fp,"0",";")) {
    if ($key == 'SKU') {
        $exp = '/[\W]*/';
        $new_row = preg_replace($exp, '', $row);
        $json[] = array_combine($key, $new_row);
    }
    $json[] = array_combine($key, $row);
}
     
// cerramos flujo abierto
fclose($fp);
     
// codificamos array en formato json
$items_origin = json_encode($json);

  


if ( ! $items_origin ) {
    fwrite($log_file, "Error en el archivo origen");
    exit('❗Error en el archivo origen');
}

// ===================


// Obtenemos datos de la API de origen
$items_origin = json_decode($items_origin, true);

// formamos el parámetro de lista de SKUs a actualizar
$param_sku ='';
foreach ($items_origin as $item){

    $temp_sku = $item['SKU'];
    $test_sku = explode(' ', $temp_sku);

    if(count($test_sku) > 1) {
        $item['SKU'] = str_ireplace(' ', '-', $item['SKU']);

        $param_sku .= $item['SKU'];
    } else {
        $param_sku .= $item['SKU'] . ',';
    }

}


// Obtenemos todos los productos
$products = $woocommerce->get('products?sku='. $param_sku);

// Construimos la data en base a los productos recuperados
$item_data = [];
foreach($products as $product){

    // Filtramos el array de origen por sku
    $sku = $product->sku;
    $search_item = array_filter($items_origin, function($item) use($sku) {
        return $item['SKU'] == $sku;
    });
    $search_item = reset($search_item);

    // Formamos el array a actualizar
    $item_data[] = [
        'id' => $product->id,
        'regular_price' => $search_item['precio_normal'],
        'stock_quantity' => $search_item['inventario'],
        'name' => $search_item['nombre']
    ];

    fwrite($log_file, "SKU " . $sku . " actualizando en Woocommerce... \n");
    echo "SKU " . $sku . " actualizando en Woocommerce... \n";

}

// Construimos información a actualizar en lotes
$data = [
    'update' => $item_data,
];


// Actualización en lotes
$result = $woocommerce->post('products/batch', $data);

if (! $result) {
    fwrite($log_file, "Error al actualizar productos \n");
    echo("❗Error al actualizar productos \n");
} else {
    fwrite($log_file, "\n✔ TOTAL: " . count($products) . " registros hallados y actualizados. \n");
    fwrite($log_file, date('Y/m/d') . " " .date('h:i:sa') . " Terminado el proceso.");
    echo "\n✔ TOTAL: " . count($products) . " registros hallados y actualizados. \n";
    echo date('Y/m/d') . " " .date('h:i:sa') . " Terminado el proceso.";
}

fwrite($log_file, "\n\n----------------------------------\n\n");
echo "<br><br>\n\n----------------------------------\n\n";
fclose($log_file);