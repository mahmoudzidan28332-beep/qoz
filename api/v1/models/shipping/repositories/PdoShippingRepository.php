<?php
declare(strict_types=1);

/**
 * mysqli repository for delivery_companies, delivery_company_translations,
 * and delivery_company_tokens tables.
 */
final class PdoShippingRepository
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // ================================
    // delivery_companies
    // ================================

    public function insertCompany(string $colsSql, string $placeholders, string $types, array $params): int
    {
        $sql = "INSERT INTO delivery_companies ({$colsSql}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Create failed: ' . $this->db->error);
        }
        if (!empty($types)) {
            $this->bindParams($stmt, $types, $params);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Create failed: ' . $err);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        return $newId;
    }

    public function updateCompanyLogo(int $id, string $url): void
    {
        $stmt = $this->db->prepare("UPDATE delivery_companies SET logo_url = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $this->bindParams($stmt, 'si', [$url, $id]);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function updateCompany(string $setsSql, string $types, array $params): bool
    {
        $sql = "UPDATE delivery_companies SET " . $setsSql . " WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Update failed: ' . $this->db->error);
        }
        $this->bindParams($stmt, $types, $params);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Update failed: ' . $err);
        }
        $stmt->close();
        return true;
    }

    public function findCompanyOwner(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT user_id FROM delivery_companies WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $this->bindParams($stmt, 'i', [$id]);
        $stmt->execute();
        $row = $this->fetchOneAssoc($stmt);
        $stmt->close();
        return $row;
    }

    public function deleteCompany(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM delivery_companies WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $this->bindParams($stmt, 'i', [$id]);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ================================
    // delivery_company_translations
    // ================================

    public function deleteTranslations(int $companyId): void
    {
        $stmt = $this->db->prepare("DELETE FROM delivery_company_translations WHERE company_id = ?");
        if ($stmt) {
            $this->bindParams($stmt, 'i', [$companyId]);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function insertTranslation(int $companyId, string $lang, ?string $desc, ?string $terms, ?string $metaTitle, ?string $metaDesc): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO delivery_company_translations (company_id, language_code, description, terms, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) return false;
        $this->bindParams($stmt, 'isssss', [$companyId, $lang, $desc, $terms, $metaTitle, $metaDesc]);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ================================
    // delivery_company_tokens
    // ================================

    public function insertToken(int $companyId, string $token, string $name, string $scopes, ?string $expiresAt): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO delivery_company_tokens (company_id, token, name, scopes, expires_at) VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('Token creation failed: ' . $this->db->error);
        }
        $this->bindParams($stmt, 'issss', [$companyId, $token, $name, $scopes, $expiresAt]);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Token creation failed: ' . $err);
        }
        $stmt->close();
        return true;
    }

    // ================================
    // helpers
    // ================================

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '') return;
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) $bind[] = &$params[$i];
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    private function fetchOneAssoc(mysqli_stmt $stmt): ?array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if (!$res) return null;
            $row = $res->fetch_assoc();
            $res->free();
            return $row ?: null;
        }
        $meta = $stmt->result_metadata();
        if (!$meta) return null;
        $row = [];
        $fields = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $fields[] = &$row[$f->name]; }
        $meta->free();
        call_user_func_array([$stmt, 'bind_result'], $fields);
        if ($stmt->fetch()) return $row;
        return null;
    }
}
