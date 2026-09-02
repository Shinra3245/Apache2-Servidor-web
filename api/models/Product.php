<?php

class Product
{
    private $conn;
    private $table_name = "productos";

    public $id;
    public $sku;
    public $name;
    public $description;
    public $price;
    public $stock;
    public $created_at;
    public $updated_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Crear producto
    public function create()
    {
        $query = "INSERT INTO " . $this->table_name . "
                  (sku, name, description, price, stock)
                  VALUES (:sku, :name, :description, :price, :stock)";

        $stmt = $this->conn->prepare($query);

        $this->sku = trim($this->sku);
        $this->name = trim($this->name);
        $this->description = trim($this->description ?? '');
        $this->price = (float) $this->price;
        $this->stock = (int) $this->stock;

        $stmt->bindParam(":sku", $this->sku);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":stock", $this->stock);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    // Obtener todos los productos
    public function read()
    {
        $query = "SELECT
                    id,
                    sku,
                    name,
                    description,
                    price,
                    stock,
                    created_at,
                    updated_at
                  FROM " . $this->table_name . "
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // Obtener un producto por ID
    public function readOne()
    {
        $query = "SELECT
                    id,
                    sku,
                    name,
                    description,
                    price,
                    stock,
                    created_at,
                    updated_at
                  FROM " . $this->table_name . "
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->sku = $row["sku"];
            $this->name = $row["name"];
            $this->description = $row["description"];
            $this->price = $row["price"];
            $this->stock = $row["stock"];
            $this->created_at = $row["created_at"];
            $this->updated_at = $row["updated_at"];

            return true;
        }

        return false;
    }

    // Actualizar producto
    public function update()
    {
        $query = "UPDATE " . $this->table_name . "
                  SET
                    sku = :sku,
                    name = :name,
                    description = :description,
                    price = :price,
                    stock = :stock
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->sku = trim($this->sku);
        $this->name = trim($this->name);
        $this->description = trim($this->description ?? '');
        $this->price = (float) $this->price;
        $this->stock = (int) $this->stock;
        $this->id = (int) $this->id;

        $stmt->bindParam(":sku", $this->sku);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":stock", $this->stock);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    // Eliminar producto
    public function delete()
    {
        $query = "DELETE FROM " . $this->table_name . "
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->id = (int) $this->id;

        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }
}