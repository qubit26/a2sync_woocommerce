<?php
date_default_timezone_set("America/Caracas");
require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

// Directorio del archivo .csv
$dir = "a2sync/";

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

echo "SINCRONIZACIÓN ENTRE A2 y WOOCOMMERCE \n<br>";
echo "[ " . date('Y/m/d') . " " . date('h:i:sa') . " ] Iniciando Sincronización (timestamp de la hora actual de Venezuela) \n<br> \n<br>";


// Obtenemos los datos del origen
$items_origin = '';

// Convertir csv en json
// ==================================
 
// abrimos csv
if (!($fp = fopen('a2sync/' . $origen[0], 'r'))) {
    die("No se pudo abrir el archivo...");
}


echo "Leyendo el archivo " . $origen[0] . "\n<br> \n<br>";


//asignamos cabeceras csv
$key = fgetcsv($fp,"0",";");
     
// recorremos csv y guardamos en un array
$json = array();

while ($row = fgetcsv($fp,"0",";")) {
    $json[] = array_combine($key, $row);
}
     
// cerramos flujo abierto
fclose($fp);

print_r($json);
     
// codificamos array en formato json
$items_origin = json_encode($json);

  


if ( ! $items_origin ) {
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
$products = $woocommerce->get('products/?sku='. $param_sku);

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

    echo "SKU " . $sku . " actualizando en Woocommerce... \n<br>";

}

// Construimos información a actualizar en lotes
$data = [
    'update' => $item_data,
];


// Actualización en lotes
$result = $woocommerce->post('products/batch', $data);

if (! $result) {
    echo("❗Error al actualizar productos \n");
} else {
    echo "\n✔ TOTAL: " . count($products) . " registros hallados y actualizados. \n";
    echo date('Y/m/d') . " " .date('h:i:sa') . " Terminado el proceso.";
}

echo "<br><br>\n\n----------------------------------\n\n<br><br>";
