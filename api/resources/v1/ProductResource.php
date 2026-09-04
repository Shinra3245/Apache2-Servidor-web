<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Product.php';

class ProductResource
{
    private $db;
    private $product;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->product = new Product($this->db);
    }

    // GET /api/v1/products
    public function index()
    {
        header("Content-Type: application/json");

        $stmt = $this->product->read();

        $products_arr = array();
        $products_arr["records"] = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $product_item = array(
                "id" => $row["id"],
                "sku" => $row["sku"],
                "name" => $row["name"],
                "description" => $row["description"],
                "price" => $row["price"],
                "stock" => $row["stock"],
                "created_at" => $row["created_at"],
                "updated_at" => $row["updated_at"]
            );

            array_push($products_arr["records"], $product_item);
        }

        http_response_code(200);
        echo json_encode($products_arr);
    }

    // GET /api/v1/products/{id}
    public function show($id)
    {
        header("Content-Type: application/json");

        $this->product->id = $id;

        if ($this->product->readOne()) {
            $product_arr = array(
                "id" => $this->product->id,
                "sku" => $this->product->sku,
                "name" => $this->product->name,
                "description" => $this->product->description,
                "price" => $this->product->price,
                "stock" => $this->product->stock,
                "created_at" => $this->product->created_at,
                "updated_at" => $this->product->updated_at
            );

            http_response_code(200);
            echo json_encode($product_arr);
        } else {
            http_response_code(404);
            echo json_encode(array(
                "message" => "Producto no encontrado"
            ));
        }
    }

    // POST /api/v1/products
    public function store()
    {
        header("Content-Type: application/json");

        $data = json_decode(file_get_contents("php://input"));

        if (
            !empty($data->sku) &&
            !empty($data->name) &&
            isset($data->price) &&
            isset($data->stock)
        ) {
            $this->product->sku = $data->sku;
            $this->product->name = $data->name;
            $this->product->description = $data->description ?? '';
            $this->product->price = $data->price;
            $this->product->stock = $data->stock;

            try {
                if ($this->product->create()) {
                    http_response_code(201);

                    echo json_encode(array(
                        "message" => "Producto creado exitosamente",
                        "id" => $this->product->id
                    ));
                } else {
                    http_response_code(503);

                    echo json_encode(array(
                        "message" => "No se pudo crear el producto"
                    ));
                }
            } catch (PDOException $e) {
                http_response_code(409);

                echo json_encode(array(
                    "message" => "No se pudo crear el producto. Verifica que el SKU no esté duplicado."
                ));
            }
        } else {
            http_response_code(400);

            echo json_encode(array(
                "message" => "Datos incompletos"
            ));
        }
    }

    // PUT /api/v1/products/{id}
    public function update($id)
    {
        header("Content-Type: application/json");

        $data = json_decode(file_get_contents("php://input"));

        if (
            !empty($data->sku) &&
            !empty($data->name) &&
            isset($data->price) &&
            isset($data->stock)
        ) {
            $this->product->id = $id;
            $this->product->sku = $data->sku;
            $this->product->name = $data->name;
            $this->product->description = $data->description ?? '';
            $this->product->price = $data->price;
            $this->product->stock = $data->stock;

            try {
                if ($this->product->update()) {
                    http_response_code(200);

                    echo json_encode(array(
                        "message" => "Producto actualizado exitosamente"
                    ));
                } else {
                    http_response_code(503);

                    echo json_encode(array(
                        "message" => "No se pudo actualizar el producto"
                    ));
                }
            } catch (PDOException $e) {
                http_response_code(409);

                echo json_encode(array(
                    "message" => "No se pudo actualizar el producto. Verifica que el SKU no esté duplicado."
                ));
            }
        } else {
            http_response_code(400);

            echo json_encode(array(
                "message" => "Datos incompletos"
            ));
        }
    }

    // DELETE /api/v1/products/{id}
    public function destroy($id)
    {
        header("Content-Type: application/json");

        $this->product->id = $id;

        if ($this->product->delete()) {
            http_response_code(200);

            echo json_encode(array(
                "message" => "Producto eliminado exitosamente"
            ));
        } else {
            http_response_code(503);

            echo json_encode(array(
                "message" => "No se pudo eliminar el producto"
            ));
        }
    }
}
