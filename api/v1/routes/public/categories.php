<?php
declare(strict_types=1);
/**
 * Public API sub-route: categories
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 * 
 * ✅ Enhanced for Tree View with full frontend compatibility
 */

if ($first === 'categories') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    /* ── Single Category by ID ──────────────────────────── */
    if ($id) {
        $row = $pdoOne(
            "SELECT c.id, 
                    COALESCE(ct.name, c.name, c.slug) AS name, 
                    c.slug, 
                    COALESCE(ct.description, c.description) AS description,
                    (SELECT i.url FROM images i 
                       JOIN image_types it ON i.image_type_id = it.id
                       WHERE i.owner_id = c.id AND it.name = 'category'
                       ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS image_url,
                    c.is_featured, c.is_active, c.parent_id, c.sort_order, c.tenant_id,
                    (SELECT COUNT(*) FROM products p
                      INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id = c.id
                      WHERE p.is_active = 1) AS product_count
               FROM categories c
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
              WHERE c.id = ? AND c.is_active = 1 LIMIT 1",
            [$lang, (int)$id]
        );
        if ($row) ResponseFormatter::success(['ok' => true, 'category' => $row]);
        else      ResponseFormatter::notFound('Category not found');
        exit;
    }

    /* ════════════════════════════════════════════════════════
     * 🌳 TREE MODE: Return ALL categories as nested hierarchy
     * Supports: ?tree=1&search=...&featured=1
     * ════════════════════════════════════════════════════════ */
    if (!empty($_GET['tree'])) {
        
        // Base WHERE clause
        $treeWhere  = 'WHERE c.is_active = 1';
        $treeParams = [$lang]; // First param is always language
        
        if ($tenantId) { 
            $treeWhere .= ' AND c.tenant_id = ?'; 
            $treeParams[] = $tenantId; 
        }
        
        // ✅ Support Featured Filter in Tree Mode
        if (!empty($_GET['featured'])) { 
            $treeWhere .= ' AND c.is_featured = ?'; 
            $treeParams[] = 1; 
        }
        
        // ✅ Support Search Filter in Tree Mode
        $searchKeyword = '';
        if (!empty($_GET['search'])) {
            $kw = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($_GET['search'])) . '%';
            $treeWhere .= ' AND (c.slug LIKE ? OR c.name LIKE ? OR COALESCE(ct.name, \'\') LIKE ?)';
            $treeParams[] = $kw;
            $treeParams[] = $kw;
            $treeParams[] = $kw;
            $searchKeyword = trim($_GET['search']);
        }

        /* ── Optimized Query with JOIN for product_count ── */
        $allCats = $pdoList(
            "SELECT c.id, 
                    COALESCE(ct.name, c.name, c.slug) AS name, 
                    c.slug,
                    COALESCE(ct.description, c.description) AS description,
                    (SELECT i.url FROM images i 
                       JOIN image_types it ON i.image_type_id = it.id
                       WHERE i.owner_id = c.id AND it.name = 'category'
                       ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS image_url,
                    c.is_featured, 
                    c.parent_id, 
                    c.sort_order, 
                    c.tenant_id,
                    -- ✅ Optimized product count using LEFT JOIN + GROUP BY
                    COALESCE(pc.product_count, 0) AS product_count
               FROM categories c
               LEFT JOIN category_translations ct 
                      ON ct.category_id = c.id AND ct.language_code = ?
               -- ✅ Pre-calculate product counts in one query
               LEFT JOIN (
                   SELECT pc2.category_id, COUNT(DISTINCT p2.id) AS product_count
                   FROM products p2
                   INNER JOIN product_categories pc2 ON pc2.product_id = p2.id
                   WHERE p2.is_active = 1
                   GROUP BY pc2.category_id
               ) pc ON pc.category_id = c.id
               $treeWhere 
               ORDER BY c.parent_id ASC, c.sort_order ASC, c.id ASC",
            $treeParams
        );

        /* ── Build tree from flat list (with filtering support) ── */
        $map = [];
        foreach ($allCats as &$cat) {
            $cat['children'] = [];
            $cat['product_count'] = (int)$cat['product_count'];
            $map[(int)$cat['id']] =& $cat;
        }
        unset($cat); // Break reference
        
        $tree = [];
        $usedIds = []; // Track IDs used in tree (for filtered results)
        
        foreach ($map as $catId => &$catRef) {
            $pid = (int)($catRef['parent_id'] ?? 0);
            
            if ($pid && isset($map[$pid])) {
                // Add as child to parent
                $map[$pid]['children'][] =& $catRef;
                $usedIds[$catId] = true;
                $usedIds[$pid] = true; // Parent is also used
            } else {
                // Root level node
                $tree[] =& $catRef;
                $usedIds[$catId] = true;
            }
        }
        unset($catRef);
        
        /* ✅ NEW: If searching, include only matching branches */
        if ($searchKeyword && !empty($_GET['search'])) {
            $tree = filterTreeBySearch($tree, $searchKeyword);
        }

        /* ✅ NEW: If featured filter, remove non-featured branches */
        if (!empty($_GET['featured'])) {
            $tree = filterTreeByFeatured($tree);
        }

        /* ✅ NEW: If has_products filter, remove branches with no products anywhere */
        if (!empty($_GET['has_products'])) {
            $tree = filterTreeByProducts($tree);
        }

        /* ✅ Calculate total counts recursively */
        $totalCategories = countAllNodesRecursive($tree);
        $totalProducts = sumProductCountsRecursive($tree);

        ResponseFormatter::success([
            'ok'      => true,
            'data'    => $tree,
            'meta'    => [
                'total'           => $totalCategories,
                'total_products'  => $totalProducts,
                'root_count'      => count($tree),
                'max_depth'       => calculateMaxDepth($tree),
                'filters_applied' => [
                    'search'       => $searchKeyword ?: null,
                    'featured'     => !empty($_GET['featured']),
                    'has_products' => !empty($_GET['has_products']),
                    'tenant'       => $tenantId
                ]
            ],
        ]);
        exit;
    }

    /* ════════════════════════════════════════════════════════
     * 📄 PAGINATED LIST MODE (Original functionality)
     * For grid/card views with pagination
     * ════════════════════════════════════════════════════════ */
    
    // WHERE clause construction
    $where       = 'WHERE c.is_active = 1';
    $whereParams = [];
    
    if ($tenantId) { 
        $where .= ' AND c.tenant_id = ?'; 
        $whereParams[] = $tenantId; 
    }
    
    if (isset($_GET['parent_id'])) {
        if ($_GET['parent_id'] === '0' || $_GET['parent_id'] === '') {
            $where .= ' AND (c.parent_id IS NULL OR c.parent_id = 0)';
        } elseif (is_numeric($_GET['parent_id'])) {
            $where .= ' AND c.parent_id = ?'; 
            $whereParams[] = (int)$_GET['parent_id'];
        }
    }
    
    if (!empty($_GET['featured'])) { 
        $where .= ' AND c.is_featured = ?'; 
        $whereParams[] = 1; 
    }
    
    if (!empty($_GET['search'])) {
        $kw = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($_GET['search'])) . '%';
        $where .= ' AND (c.slug LIKE ? OR c.name LIKE ? OR EXISTS (SELECT 1 FROM category_translations ct2 WHERE ct2.category_id = c.id AND ct2.name LIKE ?))';
        $whereParams[] = $kw;
        $whereParams[] = $kw;
        $whereParams[] = $kw;
    }

    /* ✅ NEW: has_products filter for list mode */
    $having = '';
    if (!empty($_GET['has_products'])) {
        // Since product_count is a subquery in the SELECT, we can use HAVING or wrap it.
        // For simplicity in this specific schema, we can check if it matches the product join.
        $where .= " AND EXISTS (
            SELECT 1 FROM product_categories pc3 
            INNER JOIN products p3 ON p3.id = pc3.product_id 
            WHERE pc3.category_id = c.id AND p3.is_active = 1
        )";
    }

    // Count total
    $total = $pdoCount("SELECT COUNT(*) FROM categories c $where", $whereParams);
    
    // Fetch paginated data
    $rows  = $pdoList(
        "SELECT c.id, 
                COALESCE(ct.name, c.name, c.slug) AS name, 
                c.slug,
                COALESCE(ct.description, c.description) AS description,
                (SELECT i.url FROM images i 
                   JOIN image_types it ON i.image_type_id = it.id
                   WHERE i.owner_id = c.id AND it.name = 'category'
                   ORDER BY i.is_main DESC, i.sort_order ASC LIMIT 1) AS image_url,
                c.is_featured, c.is_active, c.parent_id, c.sort_order, c.tenant_id,
                (SELECT COUNT(*) FROM products p
                  INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id = c.id
                  WHERE p.is_active = 1) AS product_count
           FROM categories c
      LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
          $where ORDER BY c.is_featured DESC, c.sort_order ASC, c.id ASC LIMIT ? OFFSET ?",
        array_merge([$lang], $whereParams, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
        ]
    ]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════
 * 🔧 HELPER FUNCTIONS for Tree Operations
 * ═══════════════════════════════════════════════════════════════════ */

/**
 * Filter tree by search keyword (recursive)
 * Keeps branches that contain matching nodes anywhere in hierarchy
 * 
 * @param array $nodes Array of category nodes
 * @param string $keyword Search term
 * @return array Filtered tree
 */
function filterTreeBySearch(array $nodes, string $keyword): array {
    $result = [];
    $keywordLower = mb_strtolower($keyword, 'UTF-8');
    
    foreach ($nodes as &$node) {
        $nameMatch = false;
        $descMatch = false;
        $slugMatch = false;
        
        // Check current node
        if (isset($node['name'])) {
            $nameMatch = mb_strpos(mb_strtolower($node['name'], 'UTF-8'), $keywordLower) !== false;
        }
        if (isset($node['description']) && $node['description']) {
            $descMatch = mb_strpos(mb_strtolower($node['description'], 'UTF-8'), $keywordLower) !== false;
        }
        if (isset($node['slug'])) {
            $slugMatch = mb_strpos(mb_strtolower($node['slug'], 'UTF-8'), $keywordLower) !== false;
        }
        
        // Recursively filter children
        $hasMatchingChildren = false;
        if (!empty($node['children'])) {
            $node['children'] = filterTreeBySearch($node['children'], $keyword);
            $hasMatchingChildren = count($node['children']) > 0;
        }
        
        // Keep node if it matches OR has matching descendants
        if ($nameMatch || $descMatch || $slugMatch || $hasMatchingChildren) {
            $result[] = $node;
        }
    }
    unset($node);
    
    return $result;
}

/**
 * Filter tree to keep only featured categories and their ancestors
 * 
 * @param array $nodes Array of category nodes
 * @return array Filtered tree with only featured branches
 */
function filterTreeByFeatured(array $nodes): array {
    $result = [];
    
    foreach ($nodes as &$node) {
        $isFeatured = !empty($node['is_featured']);
        
        // Recursively check children
        $hasFeaturedChildren = false;
        if (!empty($node['children'])) {
            $node['children'] = filterTreeByFeatured($node['children']);
            $hasFeaturedChildren = count($node['children']) > 0;
        }
        
        // Keep if this is featured OR has featured descendants
        if ($isFeatured || $hasFeaturedChildren) {
            $result[] = $node;
        }
    }
    unset($node);
    
    return $result;
}

/**
 * Filter tree to keep only categories with products (directly or in descendants)
 * 
 * @param array $nodes Array of category nodes
 * @return array Filtered tree
 */
function filterTreeByProducts(array $nodes): array {
    $result = [];
    
    foreach ($nodes as &$node) {
        $hasDirectProducts = (int)($node['product_count'] ?? 0) > 0;
        
        // Recursively check children
        $hasProductsInChildren = false;
        if (!empty($node['children'])) {
            $node['children'] = filterTreeByProducts($node['children']);
            $hasProductsInChildren = count($node['children']) > 0;
        }
        
        // Keep if this has products OR has descendants with products
        if ($hasDirectProducts || $hasProductsInChildren) {
            $result[] = $node;
        }
    }
    unset($node);
    
    return $result;
}

/**
 * Count all nodes in tree recursively
 * 
 * @param array $nodes Tree nodes
 * @return int Total count
 */
function countAllNodesRecursive(array $nodes): int {
    $count = 0;
    foreach ($nodes as $node) {
        $count++;
        if (!empty($node['children'])) {
            $count += countAllNodesRecursive($node['children']);
        }
    }
    return $count;
}

/**
 * Sum all product counts recursively (includes children counts)
 * 
 * @param array $nodes Tree nodes
 * @return int Total products across all categories
 */
function sumProductCountsRecursive(array $nodes): int {
    $total = 0;
    foreach ($nodes as $node) {
        $total += (int)($node['product_count'] ?? 0);
        if (!empty($node['children'])) {
            $total += sumProductCountsRecursive($node['children']);
        }
    }
    return $total;
}

/**
 * Calculate maximum depth of tree
 * 
 * @param array $nodes Tree nodes
 * @param int $currentDepth Current depth level
 * @return int Maximum depth found
 */
function calculateMaxDepth(array $nodes, int $currentDepth = 1): int {
    $maxDepth = $currentDepth;
    foreach ($nodes as $node) {
        if (!empty($node['children'])) {
            $childDepth = calculateMaxDepth($node['children'], $currentDepth + 1);
            if ($childDepth > $maxDepth) {
                $maxDepth = $childDepth;
            }
        }
    }
    return $maxDepth;
}

/* -------------------------------------------------------
 * Route: Jobs (public listing — no auth required)
 * GET /api/public/jobs[/{id}]
 * ----------------------------------------------------- */