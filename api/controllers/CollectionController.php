<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';

/**
 * DUHN FRAGRANCES — Collection Controller (Public API)
 */
class CollectionController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** GET /api/collections */
    public function index(): void
    {
        $stmt = $this->db->query("
            SELECT c.*, COUNT(pc.product_id) AS product_count
            FROM collections c
            LEFT JOIN product_collections pc ON pc.collection_id = c.id
            GROUP BY c.id
            ORDER BY c.sort_order ASC
        ");
        ResponseHelper::success($stmt->fetchAll());
    }

    /** GET /api/collections/{slug} */
    public function show(string $slug): void
    {
        $stmt = $this->db->prepare("SELECT * FROM collections WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $collection = $stmt->fetch();

        if (!$collection) {
            ResponseHelper::notFound('Collection not found');
        }

        // Get products in this collection
        $prodStmt = $this->db->prepare("
            SELECT p.*, GROUP_CONCAT(DISTINCT c2.slug ORDER BY c2.sort_order SEPARATOR ',') AS collection_slugs
            FROM products p
            JOIN product_collections pc  ON pc.product_id = p.id
            JOIN collections c           ON c.id = pc.collection_id AND c.slug = :slug
            LEFT JOIN product_collections pc2 ON pc2.product_id = p.id
            LEFT JOIN collections c2     ON c2.id = pc2.collection_id
            GROUP BY p.id
            ORDER BY p.avg_rating DESC
        ");
        $prodStmt->execute([':slug' => $slug]);

        require_once __DIR__ . '/ProductController.php';
        $productCtrl = new ProductController();
        $collection['products'] = array_map(
            [$productCtrl, 'formatProduct'],
            $prodStmt->fetchAll()
        );

        ResponseHelper::success($collection);
    }
}
