<?php
declare(strict_types=1);

/**
 * mysqli repository for the independent_drivers table.
 */
final class PdoIndependentDriversRepository
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function list(string $types, array $params, string $extraSql): array
    {
        $sql = "SELECT * FROM independent_drivers" . $extraSql;
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        if ($types !== '') {
            $this->bindParams($stmt, $types, $params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    public function getFileUrls(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT license_photo_url, id_photo_url FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getOwnerId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT user_id FROM independent_drivers WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function insert(array $cols, string $types, array $values): ?int
    {
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colList = implode(',', array_map(function($c){ return "`$c`"; }, $cols));
        $sql = "INSERT INTO `independent_drivers` ({$colList}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;
        if ($values) {
            $this->bindParams($stmt, $types, $values);
        }
        $ok = $stmt->execute();
        if (!$ok) {
            $err = $stmt->error ?: $this->db->error;
            $stmt->close();
            throw new RuntimeException('Insert failed: ' . $err);
        }
        $id = (int)$this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function update(array $sets, string $types, array $values, int $id): bool
    {
        $sql = "UPDATE `independent_drivers` SET " . implode(', ', $sets) . " WHERE id = ? LIMIT 1";
        $types .= 'i';
        $values[] = $id;
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        $this->bindParams($stmt, $types, $values);
        $ok = $stmt->execute();
        if (!$ok) {
            $err = $stmt->error ?: $this->db->error;
            $stmt->close();
            throw new RuntimeException('Update failed: ' . $err);
        }
        $stmt->close();
        return true;
    }

    public function updateColumn(int $id, string $col, string $value): void
    {
        $stmt = $this->db->prepare("UPDATE `independent_drivers` SET `$col` = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('si', $value, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '') return;
        $bindParams = array_merge([$types], $params);
        $refs = [];
        foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}
