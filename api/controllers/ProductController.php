<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';

/**
 * DUHN FRAGRANCES — Product Controller (Public API)
 */
class ProductController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** GET /api/products */
    public function index(): void
    {
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = min(50, (int)($_GET['per_page'] ?? 12));
        $offset     = ($page - 1) * $perPage;
        $collection = trim($_GET['collection'] ?? '');

        if ($collection) {
            $stmt = $this->db->prepare("
                SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
                FROM products p
                JOIN product_collections pc0 ON pc0.product_id = p.id
                JOIN collections col0 ON col0.id = pc0.collection_id AND col0.slug = :slug
                LEFT JOIN product_collections pc ON pc.product_id = p.id
                LEFT JOIN collections c ON c.id = pc.collection_id
                GROUP BY p.id
                ORDER BY p.avg_rating DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':slug',   $collection);
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();

            $countStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT p.id)
                FROM products p
                JOIN product_collections pc ON pc.product_id = p.id
                JOIN collections c ON c.id = pc.collection_id AND c.slug = :slug
            ");
            $countStmt->execute([':slug' => $collection]);
        } else {
            $stmt = $this->db->prepare("
                SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
                FROM products p
                LEFT JOIN product_collections pc ON pc.product_id = p.id
                LEFT JOIN collections c ON c.id = pc.collection_id
                GROUP BY p.id
                ORDER BY p.name ASC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
            $stmt->execute();

            $countStmt = $this->db->query("SELECT COUNT(*) FROM products");
        }

        $products = array_map([$this, 'formatProduct'], $stmt->fetchAll());
        $total    = (int)$countStmt->fetchColumn();

        ResponseHelper::success($products, '', 200, [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / $perPage),
        ]);
    }

    /** GET /api/products/featured */
    public function featured(): void
    {
        $stmt = $this->db->query("
            SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
            FROM products p
            LEFT JOIN product_collections pc ON pc.product_id = p.id
            LEFT JOIN collections c ON c.id = pc.collection_id
            WHERE p.is_featured = 1
            GROUP BY p.id
            ORDER BY p.avg_rating DESC, p.review_count DESC
            LIMIT 8
        ");
        ResponseHelper::success(array_map([$this, 'formatProduct'], $stmt->fetchAll()));
    }

    /** GET /api/products/new-drops */
    public function newDrops(): void
    {
        $stmt = $this->db->query("
            SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
            FROM products p
            LEFT JOIN product_collections pc ON pc.product_id = p.id
            LEFT JOIN collections c ON c.id = pc.collection_id
            WHERE p.is_new_drop = 1
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT 12
        ");
        ResponseHelper::success(array_map([$this, 'formatProduct'], $stmt->fetchAll()));
    }

    /** GET /api/products/bestsellers */
    public function bestsellers(): void
    {
        $stmt = $this->db->query("
            SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
            FROM products p
            LEFT JOIN product_collections pc ON pc.product_id = p.id
            LEFT JOIN collections c ON c.id = pc.collection_id
            WHERE p.avg_rating >= 4.5 AND p.review_count > 5
            GROUP BY p.id
            ORDER BY p.review_count DESC, p.avg_rating DESC
            LIMIT 12
        ");
        ResponseHelper::success(array_map([$this, 'formatProduct'], $stmt->fetchAll()));
    }

    /** GET /api/products/search?q= */
    public function search(): void
    {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            ResponseHelper::success([]);
        }
        $like = '%' . $q . '%';
        $stmt = $this->db->prepare("
            SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
            FROM products p
            LEFT JOIN product_collections pc ON pc.product_id = p.id
            LEFT JOIN collections c ON c.id = pc.collection_id
            WHERE p.name LIKE :q OR p.inspired_by LIKE :q
               OR p.description LIKE :q
               OR p.top_notes LIKE :q OR p.heart_notes LIKE :q OR p.base_notes LIKE :q
            GROUP BY p.id
            ORDER BY p.avg_rating DESC
            LIMIT 20
        ");
        $stmt->execute([':q' => $like]);
        ResponseHelper::success(array_map([$this, 'formatProduct'], $stmt->fetchAll()));
    }

    /** GET /api/products/{slug} */
    public function show(string $slug): void
    {
        $stmt = $this->db->prepare("
            SELECT p.*, GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') AS collection_slugs
            FROM products p
            LEFT JOIN product_collections pc ON pc.product_id = p.id
            LEFT JOIN collections c ON c.id = pc.collection_id
            WHERE p.slug = :slug
            GROUP BY p.id
        ");
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch();

        if (!$product) {
            ResponseHelper::notFound('Product not found');
        }

        ResponseHelper::success($this->formatProduct($product));
    }

    /** Format a product row for API output */
    public function formatProduct(array $row): array
    {
        $imgStmt = $this->db->prepare(
            "SELECT image_url FROM product_images WHERE product_id = :id ORDER BY sort_order ASC"
        );
        $imgStmt->execute([':id' => $row['id']]);
        $images = array_column($imgStmt->fetchAll(), 'image_url');

        return [
            'id'           => (int)$row['id'],
            'slug'         => $row['slug'],
            'name'         => $row['name'],
            'inspired_by'  => $row['inspired_by'] ?? '',
            'description'  => $row['description'] ?? '',
            'top_notes'    => json_decode($row['top_notes'] ?? '[]', true) ?? [],
            'heart_notes'  => json_decode($row['heart_notes'] ?? '[]', true) ?? [],
            'base_notes'   => json_decode($row['base_notes'] ?? '[]', true) ?? [],
            'size_ml'      => (int)$row['size_ml'],
            'price'        => number_format((float)$row['price'], 2, '.', ''),
            'currency'     => $row['currency'],
            'stock_qty'    => (int)$row['stock_qty'],
            'sku'          => $row['sku'] ?? '',
            'is_featured'  => (bool)$row['is_featured'],
            'is_new_drop'  => (bool)$row['is_new_drop'],
            'avg_rating'   => round((float)$row['avg_rating'], 2),
            'review_count' => (int)$row['review_count'],
            'images'       => $images,
            'collections'  => $row['collection_slugs']
                ? explode(',', $row['collection_slugs'])
                : [],
        ];
    }
}
