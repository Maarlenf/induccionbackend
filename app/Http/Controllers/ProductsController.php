<?php

namespace App\Http\Controllers;

use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductsController extends Controller
{

    // función para traer la base de datos de jumpseller
    public function getDataJumpseller()
    {
        $url = 'https://api.jumpseller.com/v1/products.json';
        $login = 'aef289b9ba55306de168573aa4051850';
        $token = 'cef7c147a23884668554025eef4b4278';
        //se crea el archivo caché
        $file_path = 'data-jumpseller.json';

        // si no existe el archivo se hace la consulta y se guardan los datos
        if (!file_exists($file_path)) {
            $response = Http::withBasicAuth($login, $token)->get($url);
            $data = $response->json();
            file_put_contents($file_path, json_encode($data));
            return $data;
        }

        // si ya existen se leen 
        $file_data = json_decode(file_get_contents($file_path));
        $response = Http::withBasicAuth($login, $token)->get($url);
        $new_data = $response->json();

        // se comparan ambos, en caso de no ser iguales se borra y se vuelve a setear la data
        if ($file_data !== $new_data) {
            unlink($file_path);
            file_put_contents($file_path, json_encode($new_data));
            return $new_data;
        }

        return json_decode($file_data, true);
    }


    // functión para buscar los sku dentro de la base de datos
    function getSkuJumpseller($data, $skus)
    {
        // busco primero en todas las variantes
        $showSkus = Arr::pluck($data, 'product.variants');
        $getVariants = Arr::collapse($showSkus);
        $getAllVariantsSku = data_get($getVariants, '*.sku');
        // filtro solo el campo de sku
        $justSkuNumber = array_filter($getAllVariantsSku, function ($sku) {
            return $sku;
        });
        //corto el número para hacerlo coincidir con algún sku
        $justNumbers = array_map(function ($sku) {
            if ($sku != null && $sku != 'constructor_transparente' && $sku != 'borrable') {
                return Str::substr($sku, 0, -3);
            }
        }, $justSkuNumber);

        // entro a la llave directa sku comparando con el sku 
        $getJustSku = Arr::where($data, function ($sku) use ($skus) {
            if ($sku['product']['sku'] == $skus) {
                return $sku;
            };
        });

        // obtengo solo el número de sku
        $getJustThisSku = array_map(function ($sku) {
            return $sku['product']['sku'];
        }, $getJustSku);


        return in_array($skus, $justNumbers) || in_array($skus, $getJustThisSku) ? "Sku encontrado" : "no existe";
    }

    // función para realizar validación y ver si el sku existe o no en la data
    function existProductinJumpseller($sku)
    {
        $data = $this->getDataJumpseller();
        $validate = $this->getSkuJumpseller($data, $sku);
        return $validate;
    }

    // función para traer toda la información según el id
    function getId($skus)
    {
        $file_path = 'data-jumpseller.json';
        $pathData = file_get_contents($file_path);
        $data = json_decode($pathData, true);
        $getInfoProductForSku = Arr::where($data, function ($sku) use ($skus) {
            if ($sku['product']['sku'] == $skus) {
                return $sku;
            }
        });
        $getInfoProductForVariantSku = Arr::where($data, function ($variant) use ($skus) {
            $dataVariants = $variant['product']['variants'];
            foreach ($dataVariants as $value) {
                # code...
                $cutNumber = [];
                if ($value['sku'] != null && $value['sku'] != 'constructor_transparente' && $value['sku'] != 'borrable') {
                    $cutNumber = Str::substr($value['sku'], 0, -3);
                }
                if ($skus == $cutNumber) {
                    return $variant;
                }
            }
        });
        $count = count($getInfoProductForSku);
        $countTwo = count($getInfoProductForVariantSku);
        if ($count < $countTwo) {
            return $getInfoProductForVariantSku;
        } else {
            return $getInfoProductForSku;
        }
    }

    //función para traer toda la información nueva
    function getSkuFixlabs($data, $skus)
    {
        $getInfoProduct = array_filter($data, function ($sku) use ($skus) {
            if ($sku['sku'] === $skus) {
                return $sku;
            }
        });

        return $getInfoProduct;
    }

    //función para traer la data de fixlabs
    public function dataFixlabs()
    {
        $file_path = "data-fixlabs.json";
        // Comprueba si el archivo de almacenamiento en caché existe
        if (file_exists($file_path)) {
            // Lee los datos del archivo de almacenamiento en caché
            $data = file_get_contents($file_path);
            $data = json_decode($data, true);
            return $data;
        } else {
            // Realiza la consulta HTTP
            $response = Http::get("https://induccion.fixlabsdev.com/api/products");
            $data = $response->json();

            // Almacena los datos de la consulta HTTP en el archivo de almacenamiento en caché
            file_put_contents($file_path, json_encode($data));
            return json_decode($file_path, true);
        }
    }

    public function dataStockFixlabs()
    {
        $file_path = 'data-stock.json';
        $response = Http::get("https://induccion.fixlabsdev.com/api/products/stock");
        $data = $response->json();
        if (file_exists($file_path)) {
            // Lee los datos del archivo de almacenamiento en caché
            $data = file_get_contents($file_path);
            $data = json_decode($data, true);
            return $data;
        }
    }


    // función para traer el stock según sku y talla
    function getStock($data, $sku, $size)
    {
        $sk1_stock = [];
        # code...
        foreach ($data as $value) {
            if (array_key_exists($sku, $value)) {
                foreach ($value as $content) {
                    foreach ($content as $prod) {
                        $sk1_stock[] = Arr::where($prod['variants'], function ($value, $key) use ($sku, $size) {
                            return $value['sku'] === $sku . '-' . $size;
                        });
                    }
                }
            }
        }
        return Arr::flatten($sk1_stock, 1);
    }

    //función para sumar los stock por talla
    function sumStock($array)
    {
        $data = Arr::pluck($array, 'stock');
        $collection = collect($data);
        $total = $collection->reduce(function (?int $carry, int $item) {
            return $carry + $item;
        });
        return $total;
    }



    // función que me muestra el ordenado por talla y stock
    public function getStockForSize($sku)
    {
        $file_path = 'data-stock.json';
        $data = file_get_contents($file_path);
        $data = json_decode($data, true);
        $arraySizes = [34, 35, 36, 37];
        $newStock = [];

        $stock_sku1_34 = $this->getStock($data, $sku, '34');
        $stock_sku1_35 = $this->getStock($data, $sku, '35');
        $stock_sku1_36 = $this->getStock($data, $sku, '36');
        $stock_sku1_37 = $this->getStock($data, $sku, '37');
        $newStock = [
            34 => $this->sumStock($stock_sku1_34),
            35 => $this->sumStock($stock_sku1_35),
            36 => $this->sumStock($stock_sku1_36),
            37 => $this->sumStock($stock_sku1_37)
        ];
        return $newStock;
    }

    // función que me trae el stock total de todas las tallas según el sku
    function getStockTotal($array)
    {
        $stockTotal = array_reduce(array_values($array), function ($carry, $item) {
            $carry += $item;
            return $carry;
        });
        return $stockTotal;
    }


    // función para crear el formato según el stock total de todas las tallas en caso que sea 0 se crea como deshabilitado
    function formatToCreate($dataProduct)
    {
        $formatNewProduct = [
            'product' => json_decode(json_encode([
                'name' => $dataProduct['name'],
                'page_title' => $dataProduct['name'],
                'description' => $dataProduct['description'],
                'price' => $dataProduct['price'],
                'sku' => $dataProduct['sku'],
                'stock' => 0,
                'stock_unlimited' => false,
                'status' => 'disabled',
                'permalink' => $dataProduct['name'],
            ]))
        ];

        return $formatNewProduct;
    }



    // función para crear formato de nueva variante inicializada en 0
    public function createFormatOfVariants($sku, $size, $price)
    {
        $createVariants = [
            'variant' =>
            [
                'price' => $price,
                'sku' => $sku . '-' . $size,
                'stock' => 0,
                'stock_unlimited' => false,
                "options" => [
                    [
                        "product_option_id" => 0,
                        "product_option_value_id" => 0,
                        "name" => "talla",
                        "option_type" => "option",
                        "value" => "{$size}",
                        "custom" => null,

                    ]
                ]
            ],
        ];

        return $createVariants;
    }

    // función para crear el custom field del sku
    function createFormatCustomer($sku)
    //     "id": 58590,
    //     "label": "sku",
    {
        $customer = ['field' => [
            'id' => 58590,
            "value" => $sku,
        ]];

        return $customer;
    }

    // función que crea el producto
    public function createProduct($product)
    {
        $login = 'aef289b9ba55306de168573aa4051850';
        $token = 'cef7c147a23884668554025eef4b4278';
        $url = 'https://api.jumpseller.com/v1/products.json';
        $response = Http::withBasicAuth($login, $token)->post($url, $product);

        if ($response->getStatusCode() === 200) {
            $data = $response->json();
            return $data['product']['id'];
        } else {
            echo 'Error al crear el producto: ' . $response->getBody();
        }
    }



    // función para crear custom field
    function addCustomFields($id, $sku)
    {
        $login = 'aef289b9ba55306de168573aa4051850';
        $token = 'cef7c147a23884668554025eef4b4278';
        $customer = $this->createFormatCustomer($sku);
        $url = "https://api.jumpseller.com/v1/products/{$id}/fields.json";

        $response = Http::withBasicAuth($login, $token)->post($url, $customer);
        if ($response->getStatusCode() == 200) {
            echo '- ' . $sku . ': Producto creado exitosamente con sus variantes y custom field de sku. ';
        } else {
            echo 'Error al crear custom field: ' . $response->getBody();
        }
    }

    // función que llama el formato de la variante y la crea
    public function createVariants($id, $sku, $price)
    {
        $arraySizes = [34, 35, 36, 37];
        $login = 'aef289b9ba55306de168573aa4051850';
        $token = 'cef7c147a23884668554025eef4b4278';
        $url = "https://api.jumpseller.com/v1/products/{$id}/variants.json";
        $idVariants = [];
        foreach ($arraySizes as $size) {
            $variant = $this->createFormatOfVariants($sku, $size, $price);
            $response = Http::withBasicAuth($login, $token)->post($url, $variant);
            if ($response->getStatusCode() === 200) {
                $data = $response->json();
                $idVariants[] = [$sku . '-' . $size => ['id' => $data['variant']['id']]];
            } else {
                echo 'Error al crear variante: ' . $response->getBody();
            }
        };
        return $idVariants;
    }



    // función que llama las anteriores para poder crear de manera correcta el producto en caso de que este exista lanza mensaje que ya existe
    public function callCreateProduct($sku, $validate)
    {
        //  $validate = $this->existProductinJumpseller($sku);
        if ($validate != 'Sku encontrado') {
            $getAllDataNew = $this->dataFixlabs();
            $getInfoNew = $this->getSkuFixlabs($getAllDataNew, $sku);
            $dataAccesible = Arr::collapse($getInfoNew);
            $getInfoProduct = $this->formatToCreate($dataAccesible);
            $idProduct = $this->createProduct($getInfoProduct);
            $idsVariants = $this->createVariants($idProduct, $sku, $dataAccesible['price']);
            $this->addCustomFields($idProduct, $sku);
        } else {
            return [$sku => 'Este producto ha sido encontrado dentro de la data de jumpseller'];
        }
    }


    // función para traer el id de cada variante según el sku padre
    public function callIdOfVariant($data, $size)
    {
        $allVariant = Arr::pluck($data, 'product.variants');
        $joinArraysVariants = Arr::collapse($allVariant);
        $getIdsOfVariant = Arr::map($joinArraysVariants, function ($id) use ($size) {
            $getSkuAndSize = $id['sku'];
            $justSize = Str::substr($getSkuAndSize, -2);
            if ($justSize == $size) {
                return $id['id'];
            }
        });
        $getIdforSize = Arr::where($getIdsOfVariant, function ($id) {
            if ($id != null) {
                return $id;
            }
        });

        return Arr::first($getIdforSize);
    }

    // función que crea el formato para editar la variante  
    public function formatToEditVariant($sku, $size)
    {

        $file_path = 'data-stock.json';
        $data = file_get_contents($file_path);
        $dataStock = json_decode($data, true);
        $stock_sku = $this->getStock($dataStock, $sku, $size);
        $getAllDataNew = $this->dataFixlabs();
        $getInfoNew = $this->getSkuFixlabs($getAllDataNew, $sku);
        $dataAccesible = Arr::collapse($getInfoNew);

        $createVariants = [
            'variant' =>
            [
                'price' => $dataAccesible['price'],
                'sku' => $sku . '-' . $size,
                'stock' => $this->sumStock($stock_sku),
                'stock_unlimited' => false,
                'stock_threshold' => 0,
                "options" => [
                    [
                        "name" => "talla",
                        "option_type" => "option",
                        "value" => "{$size}",
                    ]
                ]
            ],
        ];
        return $createVariants;
    }

    // función que edita la variante
    public function editVariants($sku)
    {
        $arraySizes = [34, 35, 36, 37];
        $getInfoForId = $this->getId($sku);
        $cutId = Arr::pluck($getInfoForId, 'product.id');
        $getNumber = Arr::first($cutId);
        $login = 'aef289b9ba55306de168573aa4051850';
        $token = 'cef7c147a23884668554025eef4b4278';

        foreach ($arraySizes as $size) {
            # code...
            $idVariant = $this->callIdOfVariant($getInfoForId, $size);
            $variantModify = $this->formatToEditVariant($sku, $size);

            $url = "https://api.jumpseller.com/v1/products/{$getNumber}/variants/{$idVariant}.json";
            $response = Http::withBasicAuth($login, $token)->put($url, $variantModify);
            if ($response->getStatusCode() === 200) {
                $data = $response->json();
                echo '| ' . $sku . ': Variante ' . $data['variant']['id'] . ' editada correctamente |';
            } else {
                echo 'Error al crear editar la variante: ' . $response->getBody();
            }
        }
    }

    public function editStatusProduct($sku)
    {
        $getInfoForId = $this->getId($sku);
        $cutId = Arr::pluck($getInfoForId, 'product.id');
        $getNumber = Arr::first($cutId);
        $getStock = $this->getStockForSize($sku);
        $stockTotal = $this->getStockTotal($getStock);
        $urlProduct = "https://api.jumpseller.com/v1/products/{$getNumber}.json";
        $login = 'aef289b9ba55306de168573aa4051850';
        $token = 'cef7c147a23884668554025eef4b4278';
        $formatEditStatus = ['product' => ['status' => 'available']];
        if ($stockTotal > 0) {
            $response = Http::withBasicAuth($login, $token)->put($urlProduct, $formatEditStatus);
            if ($response->getStatusCode() == 200) {
                echo '| ' . $sku . ': producto habilitado |';
            }
        } else {
            echo '| ' . $sku . ': este producto tiene 0 stock |';
        }
    }


    // función que llama todo para la creación del producto
    function arraySkus()
    {
        $arraySkus = ["8734-768-23580-34576", "1234-567-89012-34567", "5678-826-23456-78901"];
        return $arraySkus;
    }
    public function createNewProduct()
    {
        $skus = $this->arraySkus();
        foreach ($skus as $sku) {
            $validate = $this->existProductinJumpseller($sku);
            if ($validate == 'Sku encontrado') {
                return '- ' . $sku . ': ' . $validate;
            } else {
                $this->callCreateProduct($sku, $validate);
            }
        }
    }

    // función que llama todo para editar la variante existente 
    public function modifyExistingVariant()
    {
        $skus = $this->arraySkus();
        foreach ($skus as $sku) {
            $this->editVariants($sku);
            $this->editStatusProduct($sku);
        }
    }
}
